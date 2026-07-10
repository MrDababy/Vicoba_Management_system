<?php
/**
 * Authentication Controller
 * 
 * Handles user authentication including registration, login, logout,
 * password reset, and session management.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\User;
use App\Classes\Auth;
use App\Helpers\Security;
use App\Helpers\ActivityLogger;
use App\Exceptions\ValidationException;
use App\Exceptions\AuthException;

class AuthController extends BaseController
{
    /**
     * @var User User model instance
     */
    protected User $userModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    /**
     * Show login page
     * 
     * @return void
     */
    public function showLogin(): void
    {
        // If already logged in, redirect to dashboard
        if ($this->auth->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }
        
        $data = [
            'title' => 'Login - ' . APP_NAME,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->render('auth.login', $data, 'auth');
    }

    /**
     * Process login
     * 
     * @return void
     */
    public function login(): void
    {
        try {
            // Validate CSRF token when present
            $csrfToken = $this->input('csrf_token');
            if ($csrfToken !== null && $csrfToken !== '' && !Security::verifyCsrfToken($csrfToken)) {
                throw new AuthException('Invalid security token. Please try again.');
            }
            
            // Get input
            $username = $this->input('username');
            $password = $this->input('password');
            $remember = (bool)$this->input('remember', false);
            
            // Validate input
            if (empty($username) || empty($password)) {
                throw new AuthException('Please enter your username/email and password.');
            }
            
            // Attempt login
            if ($this->auth->login($username, $password, $remember)) {
                // Log activity
                ActivityLogger::log(
                    'LOGIN',
                    'users',
                    $this->auth->id(),
                    'User logged in successfully'
                );
                
                // Set success message
                $this->session->flash('success', 'Welcome back, ' . $this->auth->user()['full_name'] . '!');
                
                // Redirect to dashboard
                $this->redirect('/dashboard');
            } else {
                throw new AuthException('Invalid username/email or password.');
            }
            
        } catch (AuthException $e) {
            $this->session->flash('error', $e->getMessage());
            
            // Log failed login attempt
            ActivityLogger::log(
                'LOGIN_FAILED',
                'users',
                null,
                'Failed login attempt for: ' . $this->input('username')
            );
            
            $this->redirect('/login');
        } catch (ValidationException $e) {
            $this->session->flash('error', 'Validation error: ' . $e->getMessage());
            $this->redirect('/login');
        }
    }

    /**
     * Show registration page
     * 
     * @return void
     */
    public function showRegister(): void
    {
        // If already logged in, redirect to dashboard
        if ($this->auth->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }
        
        $data = [
            'title' => 'Register - ' . APP_NAME,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->render('auth.register', $data, 'auth');
    }

    /**
     * Process registration
     * 
     * @return void
     */
    public function register(): void
    {
        try {
            // Validate CSRF token
            $csrfToken = $this->input('csrf_token');
            if (!Security::verifyCsrfToken($csrfToken)) {
                throw new AuthException('Invalid security token. Please try again.');
            }
            
            // Get input
            $data = [
                'username' => $this->input('username'),
                'email' => $this->input('email'),
                'full_name' => $this->input('full_name'),
                'phone' => $this->input('phone'),
                'password' => $this->input('password'),
                'password_confirmation' => $this->input('password_confirmation'),
                'role' => 'Member', // Default role for registration
                'status' => 'Active'
            ];
            
            // Validate password confirmation
            if ($data['password'] !== $data['password_confirmation']) {
                throw new AuthException('Passwords do not match.');
            }
            
            // Check if username exists
            if ($this->userModel->usernameExists($data['username'])) {
                throw new AuthException('Username already exists. Please choose another.');
            }
            
            // Check if email exists
            if ($this->userModel->emailExists($data['email'])) {
                throw new AuthException('Email already registered. Please use another email.');
            }
            
            // Create user
            $userId = $this->userModel->register($data);
            
            if ($userId) {
                // Log activity
                ActivityLogger::log(
                    'REGISTER',
                    'users',
                    $userId,
                    'New user registered: ' . $data['username']
                );
                
                // Set success message
                $this->session->flash('success', 'Registration successful! Please login.');
                
                // Redirect to login page
                $this->redirect('/login');
            } else {
                throw new AuthException('Registration failed. Please try again.');
            }
            
        } catch (AuthException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/register');
        } catch (ValidationException $e) {
            $this->session->flash('error', 'Validation error: ' . $e->getMessage());
            $this->redirect('/register');
        }
    }

    /**
     * Process logout
     * 
     * @return void
     */
    public function logout(): void
    {
        // Get user info before logout
        $userId = $this->auth->id();
        $username = $this->auth->user()['username'] ?? 'Unknown';
        
        // Log activity
        ActivityLogger::log(
            'LOGOUT',
            'users',
            $userId,
            'User logged out: ' . $username
        );
        
        // Perform logout
        $this->auth->logout();
        
        // Clear session
        $this->session->destroy();
        
        // Set success message
        $this->session->flash('success', 'You have been logged out successfully.');
        
        // Redirect to login
        $this->redirect('/login');
    }

    /**
     * Show forgot password page
     * 
     * @return void
     */
    public function showForgotPassword(): void
    {
        $data = [
            'title' => 'Forgot Password - ' . APP_NAME,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->render('auth.forgot-password', $data, 'auth');
    }

    /**
     * Process forgot password
     * 
     * @return void
     */
    public function forgotPassword(): void
    {
        try {
            // Validate CSRF token
            $csrfToken = $this->input('csrf_token');
            if (!Security::verifyCsrfToken($csrfToken)) {
                throw new AuthException('Invalid security token. Please try again.');
            }
            
            $email = $this->input('email');
            
            if (empty($email)) {
                throw new AuthException('Please enter your email address.');
            }
            
            // Generate reset token
            $token = $this->userModel->generateResetToken($email);
            
            if ($token) {
                // In production, send email with reset link
                // For simulation, just show the token
                $this->session->flash('success', 'Password reset link has been sent to your email.');
                $this->session->flash('reset_token', $token);
                
                // Log activity
                ActivityLogger::log(
                    'PASSWORD_RESET_REQUEST',
                    'users',
                    null,
                    'Password reset requested for: ' . $email
                );
            } else {
                // Don't reveal if email exists or not for security
                $this->session->flash('success', 'If your email is registered, you will receive a reset link.');
            }
            
            $this->redirect('/forgot-password');
            
        } catch (AuthException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/forgot-password');
        }
    }

    /**
     * Show reset password page
     * 
     * @param string $token Reset token
     * @return void
     */
    public function showResetPassword(string $token): void
    {
        // Verify token
        $user = $this->userModel->verifyResetToken($token);
        
        if (!$user) {
            $this->session->flash('error', 'Invalid or expired reset token.');
            $this->redirect('/forgot-password');
            return;
        }
        
        $data = [
            'title' => 'Reset Password - ' . APP_NAME,
            'token' => $token,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->render('auth.reset-password', $data, 'auth');
    }

    /**
     * Process password reset
     * 
     * @return void
     */
    public function resetPassword(): void
    {
        try {
            // Validate CSRF token
            $csrfToken = $this->input('csrf_token');
            if (!Security::verifyCsrfToken($csrfToken)) {
                throw new AuthException('Invalid security token. Please try again.');
            }
            
            $token = $this->input('token');
            $password = $this->input('password');
            $passwordConfirmation = $this->input('password_confirmation');
            
            if (empty($token)) {
                throw new AuthException('Invalid reset token.');
            }
            
            if (empty($password) || strlen($password) < 8) {
                throw new AuthException('Password must be at least 8 characters long.');
            }
            
            if ($password !== $passwordConfirmation) {
                throw new AuthException('Passwords do not match.');
            }
            
            // Verify token
            $user = $this->userModel->verifyResetToken($token);
            
            if (!$user) {
                throw new AuthException('Invalid or expired reset token.');
            }
            
            // Update password
            if ($this->userModel->updatePassword($user['id'], $password)) {
                // Clear reset token
                $this->userModel->clearResetToken($user['id']);
                
                // Log activity
                ActivityLogger::log(
                    'PASSWORD_RESET',
                    'users',
                    $user['id'],
                    'Password reset completed for: ' . $user['username']
                );
                
                $this->session->flash('success', 'Password reset successfully. Please login with your new password.');
                $this->redirect('/login');
            } else {
                throw new AuthException('Failed to reset password. Please try again.');
            }
            
        } catch (AuthException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reset-password/' . $this->input('token'));
        }
    }

    /**
     * Check if user is authenticated (AJAX)
     * 
     * @return void
     */
    public function checkAuth(): void
    {
        $isAuthenticated = $this->auth->isAuthenticated();
        $user = $isAuthenticated ? $this->auth->user() : null;
        
        $this->json([
            'authenticated' => $isAuthenticated,
            'user' => $user ? [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ] : null
        ]);
    }

    /**
     * Verify email availability (AJAX)
     * 
     * @return void
     */
    public function verifyEmail(): void
    {
        $email = $this->input('email');
        $exists = $this->userModel->emailExists($email);
        
        $this->json([
            'valid' => !$exists,
            'message' => $exists ? 'Email already registered' : 'Email available'
        ]);
    }

    /**
     * Verify username availability (AJAX)
     * 
     * @return void
     */
    public function verifyUsername(): void
    {
        $username = $this->input('username');
        $exists = $this->userModel->usernameExists($username);
        
        $this->json([
            'valid' => !$exists,
            'message' => $exists ? 'Username already taken' : 'Username available'
        ]);
    }
}