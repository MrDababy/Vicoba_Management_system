<?php
/**
 * Authentication Class - Updated
 * 
 * Enhanced with better security features, session binding,
 * and activity logging.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Classes;

use App\Models\User;
use App\Helpers\Security;
use App\Helpers\ActivityLogger;

class Auth
{
    /**
     * @var Session Session instance
     */
    private Session $session;

    /**
     * @var User|null Authenticated user data
     */
    private ?array $user = null;

    /**
     * @var int Maximum login attempts
     */
    private int $maxAttempts = 5;

    /**
     * @var int Lockout time in seconds
     */
    private int $lockoutTime = 900; // 15 minutes

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->session = new Session();
        $this->loadUserFromSession();
        $this->checkRememberMe();
        $this->checkSessionTimeout();
    }

    /**
     * Attempt to login a user
     * 
     * @param string $username Email or username
     * @param string $password Plain password
     * @param bool $remember Remember me
     * @return bool
     */
    public function login(string $username, string $password, bool $remember = false): bool
    {
        // Check if account is locked
        if ($this->isLocked($username)) {
            $this->session->flash('error', 'Account temporarily locked. Please try again later.');
            return false;
        }
        
        $userModel = new User();
        
        // Find user by username or email
        $user = $userModel->findByUsernameOrEmail($username);
        
        if (!$user || !$this->verifyPassword($password, $user['password_hash'])) {
            $this->recordFailedAttempt($username);
            return false;
        }
        
        // Check if user is active
        if ($user['status'] !== 'Active') {
            $this->recordFailedAttempt($username);
            return false;
        }
        
        // Clear failed attempts
        $this->clearFailedAttempts($username);
        
        // Store user data
        $this->user = $user;
        $this->authenticateSession($user, $remember);
        
        // Update last login
        $userModel->updateLastLogin($user['id']);
        
        // Log activity
        ActivityLogger::log('LOGIN', 'users', $user['id'], 'User logged in');
        
        return true;
    }

    /**
     * Verify password against hash
     * 
     * @param string $password Plain password
     * @param string $hash Stored hash
     * @return bool
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        if (empty($hash)) {
            return false;
        }

        if (password_verify($password, $hash)) {
            return true;
        }

        return hash_equals($hash, $password);
    }

    /**
     * Authenticate user session with security features
     * 
     * @param array $user User data
     * @param bool $remember Remember me
     * @return void
     */
    private function authenticateSession(array $user, bool $remember): void
    {
        // Regenerate session ID to prevent fixation
        $this->session->regenerate();
        
        // Store user data in session
        $this->session->set('user_id', $user['id']);
        $this->session->set('username', $user['username']);
        $this->session->set('role', $user['role']);
        $this->session->set('full_name', $user['full_name']);
        $this->session->set('authenticated', true);
        $this->session->set('login_time', time());
        $this->session->set('last_activity', time());
        
        // Bind session to client
        $this->session->bindToClient();
        
        // Set remember me cookie
        if ($remember) {
            $this->setRememberMe($user['id']);
        }
    }

    /**
     * Set remember me cookie
     * 
     * @param int $userId User ID
     * @return void
     */
    private function setRememberMe(int $userId): void
    {
        $token = Security::generateToken(64);
        $expires = time() + (86400 * 30); // 30 days
        
        // Store token in database
        $userModel = new User();
        $userModel->updateRememberToken($userId, $token);
        
        // Set secure cookie
        setcookie(
            'remember_me',
            json_encode(['user_id' => $userId, 'token' => $token]),
            $expires,
            '/',
            '',
            true, // Secure flag
            true  // HttpOnly flag
        );
    }

    /**
     * Check remember me cookie
     * 
     * @return void
     */
    private function checkRememberMe(): void
    {
        if ($this->isAuthenticated()) {
            return;
        }
        
        if (!isset($_COOKIE['remember_me'])) {
            return;
        }
        
        $data = json_decode($_COOKIE['remember_me'], true);
        
        if (!isset($data['user_id']) || !isset($data['token'])) {
            return;
        }
        
        // Validate token
        $userModel = new User();
        $user = $userModel->findById($data['user_id']);
        
        if ($user && $user['remember_token'] === $data['token']) {
            // Regenerate token for security
            $newToken = Security::generateToken(64);
            $userModel->updateRememberToken($user['id'], $newToken);
            
            // Update cookie with new token
            setcookie(
                'remember_me',
                json_encode(['user_id' => $user['id'], 'token' => $newToken]),
                time() + (86400 * 30),
                '/',
                '',
                true,
                true
            );
            
            $this->authenticateSession($user, true);
        }
    }

    /**
     * Logout current user
     * 
     * @return void
     */
    public function logout(): void
    {
        // Clear remember token
        if ($this->isAuthenticated()) {
            $userModel = new User();
            $userModel->clearRememberToken($this->id());
            
            // Log activity
            ActivityLogger::log('LOGOUT', 'users', $this->id(), 'User logged out');
        }
        
        // Clear session
        $this->session->destroy();
        
        // Clear remember me cookie
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        
        $this->user = null;
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->session->get('authenticated', false) === true &&
               !$this->session->isExpired() &&
               $this->session->validateBinding() &&
               $this->id() !== null;
    }

    /**
     * Check session timeout
     * 
     * @return void
     */
    private function checkSessionTimeout(): void
    {
        if (!$this->isAuthenticated()) {
            return;
        }
        
        $lastActivity = $this->session->get('last_activity', time());
        $timeout = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
        
        if ((time() - $lastActivity) > $timeout) {
            $this->logout();
            $this->session->flash('error', 'Your session has expired. Please login again.');
        } else {
            $this->session->set('last_activity', time());
        }
    }

    /**
     * Get authenticated user ID
     * 
     * @return int|null
     */
    public function id(): ?int
    {
        return $this->session->get('user_id');
    }

    /**
     * Get authenticated user role
     * 
     * @return string|null
     */
    public function role(): ?string
    {
        return $this->session->get('role');
    }

    /**
     * Get authenticated user data
     * 
     * @return array|null
     */
    public function user(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        if ($this->user === null && $this->id()) {
            $userModel = new User();
            $this->user = $userModel->findById($this->id());
        }
        
        return $this->user;
    }

    /**
     * Load user from session
     * 
     * @return void
     */
    private function loadUserFromSession(): void
    {
        if ($this->isAuthenticated() && $this->id()) {
            $userModel = new User();
            $this->user = $userModel->findById($this->id());
        }
    }

    /**
     * Check if user has specific role
     * 
     * @param string|array $roles Role(s) to check
     * @return bool
     */
    public function hasRole($roles): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userRole = $this->role();
        
        if (is_array($roles)) {
            return in_array($userRole, $roles);
        }
        
        return $userRole === $roles;
    }

    /**
     * Check if user has permission (alias for hasRole)
     * 
     * @param string|array $permissions Permission(s) to check
     * @return bool
     */
    public function hasPermission($permissions): bool
    {
        return $this->hasRole($permissions);
    }

    /**
     * Record failed login attempt
     * 
     * @param string $username Username attempted
     * @return void
     */
    private function recordFailedAttempt(string $username): void
    {
        $key = 'login_attempts_' . $username;
        $attempts = $this->session->get($key, []);
        $attempts[] = time();
        $this->session->set($key, $attempts);
        
        // Log failed attempt
        ActivityLogger::log(
            'LOGIN_FAILED',
            'users',
            null,
            'Failed login attempt for: ' . $username
        );
    }

    /**
     * Check if account is locked
     * 
     * @param string $username Username to check
     * @return bool
     */
    private function isLocked(string $username): bool
    {
        $key = 'login_attempts_' . $username;
        $attempts = $this->session->get($key, []);
        
        if (count($attempts) < $this->maxAttempts) {
            return false;
        }
        
        // Check if attempts are within lockout window
        $recentAttempts = array_filter($attempts, function($timestamp) {
            return (time() - $timestamp) < $this->lockoutTime;
        });
        
        if (count($recentAttempts) < $this->maxAttempts) {
            // Reset attempts if not enough within window
            $this->clearFailedAttempts($username);
            return false;
        }
        
        return true;
    }

    /**
     * Clear failed login attempts
     * 
     * @param string $username Username
     * @return void
     */
    private function clearFailedAttempts(string $username): void
    {
        $key = 'login_attempts_' . $username;
        $this->session->remove($key);
    }

    /**
     * Get session instance
     * 
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Check if user is admin
     * 
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    /**
     * Check if user is treasurer
     * 
     * @return bool
     */
    public function isTreasurer(): bool
    {
        return $this->hasRole('Treasurer');
    }

    /**
     * Check if user is secretary
     * 
     * @return bool
     */
    public function isSecretary(): bool
    {
        return $this->hasRole('Secretary');
    }

    /**
     * Check if user is member
     * 
     * @return bool
     */
    public function isMember(): bool
    {
        return $this->hasRole('Member');
    }
}