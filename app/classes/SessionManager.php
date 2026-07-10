<?php
/**
 * Session Manager
 * 
 * Provides secure session management with binding, timeout,
 * and regeneration features.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Classes;

use App\Helpers\SecurityHelper;

class SessionManager
{
    /**
     * @var bool Is session started
     */
    private bool $started = false;

    /**
     * @var array Session configuration
     */
    private array $config = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = [
            'name' => defined('SESSION_NAME') ? SESSION_NAME : 'vicoba_session',
            'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600,
            'path' => defined('SESSION_PATH') ? SESSION_PATH : '/',
            'domain' => defined('SESSION_DOMAIN') ? SESSION_DOMAIN : '',
            'secure' => defined('SESSION_SECURE') ? SESSION_SECURE : false,
            'httponly' => defined('SESSION_HTTP_ONLY') ? SESSION_HTTP_ONLY : true
        ];
        
        $this->start();
    }

    /**
     * Start the session
     * 
     * @return void
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Set session configuration
        session_name($this->config['name']);
        session_set_cookie_params(
            $this->config['lifetime'],
            $this->config['path'],
            $this->config['domain'],
            $this->config['secure'],
            $this->config['httponly']
        );

        // Start session
        session_start();
        $this->started = true;

        // Regenerate session ID for security
        $this->regenerate();

        // Bind session to client
        $this->bindToClient();

        // Check session timeout
        $this->checkTimeout();
    }

    /**
     * Set a session value
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key
     * 
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Flash a message for one request
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get and delete a flash message
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Regenerate session ID to prevent fixation
     * 
     * @param bool $deleteOld Delete old session data
     * @return void
     */
    public function regenerate(bool $deleteOld = true): void
    {
        if ($this->started) {
            session_regenerate_id($deleteOld);
            $this->updateSessionId();
        }
    }

    /**
     * Update stored session ID
     * 
     * @return void
     */
    private function updateSessionId(): void
    {
        $_SESSION['_session_id'] = session_id();
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Bind session to client IP and User Agent
     * 
     * @return void
     */
    private function bindToClient(): void
    {
        $_SESSION['_ip'] = SecurityHelper::getClientIp();
        $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['_fingerprint'] = $this->generateFingerprint();
    }

    /**
     * Generate session fingerprint
     * 
     * @return string
     */
    private function generateFingerprint(): string
    {
        $data = ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
                ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') . 
                ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        return hash('sha256', $data);
    }

    /**
     * Validate session binding
     * 
     * @return bool
     */
    public function validateBinding(): bool
    {
        $fingerprint = $this->generateFingerprint();
        
        return $this->get('_ip') === SecurityHelper::getClientIp() &&
               $this->get('_user_agent') === ($_SERVER['HTTP_USER_AGENT'] ?? '') &&
               $this->get('_fingerprint') === $fingerprint;
    }

    /**
     * Check session timeout
     * 
     * @return void
     */
    private function checkTimeout(): void
    {
        if (!$this->has('_last_activity')) {
            return;
        }
        
        $lastActivity = $this->get('_last_activity', time());
        $timeout = $this->config['lifetime'];
        
        if ((time() - $lastActivity) > $timeout) {
            $this->destroy();
            
            // Set flag for timeout
            $_SESSION['_session_timeout'] = true;
            
            // Log timeout
            \App\Helpers\ActivityLogger::log(
                'SESSION_TIMEOUT',
                'session',
                null,
                'Session expired due to inactivity'
            );
        } else {
            $this->set('_last_activity', time());
        }
    }

    /**
     * Check if session is expired
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->has('_created_at')) {
            return false;
        }

        $created = $this->get('_created_at');
        $lifetime = $this->config['lifetime'];

        return (time() - $created) > $lifetime;
    }

    /**
     * Get all session data
     * 
     * @return array
     */
    public function getAll(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Destroy the session
     * 
     * @return void
     */
    public function destroy(): void
    {
        if ($this->started) {
            $_SESSION = [];
            session_destroy();
            $this->started = false;
        }
    }

    /**
     * Set session timeout
     * 
     * @param int $seconds Timeout in seconds
     * @return void
     */
    public function setTimeout(int $seconds): void
    {
        $this->config['lifetime'] = $seconds;
        ini_set('session.gc_maxlifetime', $seconds);
        session_set_cookie_params($seconds);
    }

    /**
     * Get session ID
     * 
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Check if session is started
     * 
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Destructor - clear flash messages
     */
    public function __destruct()
    {
        // Clear flash messages
        if (isset($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }
    }
}