<?php
/**
 * Authentication Class
 * 
 * Handles user authentication, authorization, and session management.
 * Provides secure login, logout, and role-based access control.
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
     * @var User|null Authenticated user
     */
    private ?User $user = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->session = new Session();
        $this->loadUserFromSession();
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
        $userModel = new User();
        
        // Find user by username or email
        $user = $userModel->findByUsernameOrEmail($username);
        
        if (!$user || !$this->verifyPassword($password, $user['password_hash'])) {
            return false;
        }
        
        // Check if user is active
        if ($user['status'] !== 'Active') {
            return false;
        }
        
        // Update user
        $this->user = $user;
        $this->authenticateSession($user, $remember);
        
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
        return password_verify($password, $hash);
    }

    /**
     * Authenticate user session
     * 
     * @param array $user User data
     * @param bool $remember Remember me
     * @return void
     */
    private function authenticateSession(array $user, bool $remember): void
    {
        // Regenerate session ID
        $this->session->regenerate();
        
        // Store user data in session
        $this->session->set('user_id', $user['id']);
        $this->session->set('username', $user['username']);
        $this->session->set('role', $user['role']);
        $this->session->set('full_name', $user['full_name']);
        $this->session->set('authenticated', true);
        $this->session->set('login_time', time());
        
        // Bind session to client
        $this->session->bindToClient();
        
        // Set remember me cookie
        if ($remember) {
            $this->setRememberMe($user['id']);
        }
        
        // Update last login
        $userModel = new User();
        $userModel->updateLastLogin($user['id']);
    }

    /**
     * Set remember me cookie
     * 
     * @param int $userId
     * @return void
     */
    private function setRememberMe(int $userId): void
    {
        $token = Security::generateRandomString(64);
        $expires = time() + (86400 * 30); // 30 days
        
        // Store token in database
        $userModel = new User();
        $userModel->updateRememberToken($userId, $token);
        
        // Set cookie
        setcookie(
            'remember_me',
            json_encode(['user_id' => $userId, 'token' => $token]),
            $expires,
            '/',
            '',
            true,
            true
        );
    }

    /**
     * Check remember me cookie
     * 
     * @return void
     */
    public function checkRememberMe(): void
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
        // Log activity
        if ($this->isAuthenticated()) {
            ActivityLogger::log('LOGOUT', 'users', $this->id(), 'User logged out');
        }
        
        // Clear session
        $this->session->destroy();
        
        // Clear remember me cookie
        setcookie('remember_me', '', time() - 3600, '/');
        
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
               $this->session->validateBinding();
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
        
        if ($this->user === null) {
            $userModel = new User();
            $this->user = $userModel->findById($this->id());
        }
        
        return $this->user;
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
}
?>