<?php
/**
 * Session Management Class
 * 
 * Handles secure session management, including session start, regeneration,
 * timeout detection, and data storage.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Classes;

class Session
{
    /**
     * @var bool Is session started
     */
    private bool $started = false;

    /**
     * Constructor - Initialize session
     */
    public function __construct()
    {
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
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }

        if (defined('SESSION_LIFETIME')) {
            session_set_cookie_params(
                SESSION_LIFETIME,
                defined('SESSION_PATH') ? SESSION_PATH : '/',
                defined('SESSION_DOMAIN') ? SESSION_DOMAIN : '',
                defined('SESSION_SECURE') ? SESSION_SECURE : false,
                defined('SESSION_HTTP_ONLY') ? SESSION_HTTP_ONLY : true
            );
        }

        // Start session
        session_start();
        $this->started = true;

        
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
     * Update the stored session ID
     * 
     * @return void
     */
    private function updateSessionId(): void
    {
        $_SESSION['session_id'] = session_id();
        $_SESSION['session_created'] = time();
    }

    /**
     * Check if session is expired
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->has('session_created')) {
            return false;
        }

        $created = $this->get('session_created');
        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;

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
    if (session_status() === PHP_SESSION_ACTIVE) {

        // Clear all session data
        $_SESSION = [];

        // Delete the session cookie
        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session
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
        ini_set('session.gc_maxlifetime', $seconds);
    }

    /**
     * Bind session to IP and User Agent
     * 
     * @return void
     */
    public function bindToClient(): void
    {
        $this->set('_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        $this->set('_user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    /**
     * Validate session binding
     * 
     * @return bool
     */
    public function validateBinding(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return $this->get('_ip') === $ip &&
               $this->get('_user_agent') === $userAgent;
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
?>