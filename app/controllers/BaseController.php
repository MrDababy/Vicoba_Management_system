<?php
/**
 * Base Controller Class
 * 
 * Provides common functionality for all controllers including view rendering,
 * input handling, validation, and response methods.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Classes\Session;
use App\Classes\Auth;
use App\Helpers\Validation;
use App\Helpers\Security;
use App\Helpers\ActivityLogger;
use App\Exceptions\ValidationException;

abstract class BaseController
{
    /**
     * @var Session Session instance
     */
    protected Session $session;

    /**
     * @var Auth Auth instance
     */
    protected Auth $auth;

    /**
     * @var Validation Validation instance
     */
    protected Validation $validation;

    /**
     * @var array Request data
     */
    protected array $request;

    /**
     * @var array Route parameters
     */
    protected array $params = [];

    /**
     * @var string Current action
     */
    protected string $action;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->session = new Session();
        $this->auth = new Auth();
        $this->validation = new Validation();
        $this->request = $this->getRequestData();
    }

    /**
     * Get request data (POST/GET)
     * 
     * @return array
     */
    protected function getRequestData(): array
    {
        $data = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $_GET;
        }
        
        // Also include JSON input
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $jsonData = json_decode($input, true);
            if (is_array($jsonData)) {
                $data = array_merge($data, $jsonData);
            }
        }
        
        return $data;
    }

    /**
     * Render a view
     * 
     * @param string $view View path
     * @param array $data Data to pass to view
     * @param string $layout Layout to use
     * @return void
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewPath = $this->getViewPath($view);
        
        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: {$viewPath}");
        }
        
        // Extract data for view
        extract($data);
        
        // Add common data
        $session = $this->session;
        $auth = $this->auth;
        $title = $data['title'] ?? APP_NAME;
        
        ob_start();
        require $viewPath;
        $content = ob_get_clean();
        
        // Include layout
        $layoutPath = __DIR__ . '/../views/layouts/' . $layout . '.php';
        
        if (!file_exists($layoutPath)) {
            throw new \Exception("Layout not found: {$layoutPath}");
        }
        
        require $layoutPath;
    }

    /**
     * Get full view path
     * 
     * @param string $view View name
     * @return string
     */
    protected function getViewPath(string $view): string
    {
        return __DIR__ . '/../views/' . str_replace('.', '/', $view) . '.php';
    }

    /**
     * Return JSON response
     * 
     * @param mixed $data Data to encode
     * @param int $status HTTP status code
     * @return void
     */
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Return success JSON response
     * 
     * @param string $message Success message
     * @param mixed $data Additional data
     * @param int $status HTTP status code
     * @return void
     */
    protected function jsonSuccess(string $message, $data = null, int $status = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
        $this->json($response, $status);
    }

    /**
     * Return error JSON response
     * 
     * @param string $message Error message
     * @param array $errors Validation errors
     * @param int $status HTTP status code
     * @return void
     */
    protected function jsonError(string $message, array $errors = [], int $status = 400): void
    {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ];
        $this->json($response, $status);
    }

    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @return void
     */
    protected function redirect(string $url): void
    {
        // If it's not already an absolute URL, prepend BASE_URL
        if (!preg_match('#^https?://#', $url)) {
        $url = BASE_URL . '/' . ltrim($url, '/');
        }
        
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirect back to previous page
     * 
     * @return void
     */
    protected function redirectBack(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Set route parameters for the current request
     *
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get a route parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Read input from request data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(string $key, $default = null)
    {
        return $this->request[$key] ?? $default;
    }

    /**
     * Ensure the user is authenticated
     *
     * @return void
     */
    protected function requireAuth(): void
    {
        if (!$this->auth->isAuthenticated()) {
            $this->redirect('/login');
        }
    }

    /**
     * Ensure the user has one of the required roles
     *
     * @param string|array $roles
     * @return void
     */
    protected function requireRole($roles): void
    {
        $this->requireAuth();

        if (!$this->auth->hasRole($roles)) {
            http_response_code(403);
            $this->redirect('/dashboard');
        }
    }

    /**
     * Generate or retrieve a CSRF token
     *
     * @return string
     */
    protected function csrfToken(): string
    {
        return Security::generateCsrfToken();
    }

    /**
     * Verify a CSRF token
     *
     * @param mixed $token
     * @return bool
     */
    protected function verifyCsrfToken($token): bool
    {
        return Security::verifyCsrfToken((string) $token);
    }

    /**
     * Check whether the current request is an AJAX request
     *
     * @return bool
     */
    protected function isAjax(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($requestedWith) === 'xmlhttprequest') {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * Get the authenticated user
     *
     * @return array|null
     */
    protected function getUser()
    {
        return $this->auth->user();
    }

    /**
     * Get the authenticated user ID
     *
     * @return int|null
     */
    protected function getUserId()
    {
        return $this->auth->id();
    }

    /**
     * Check whether the current user has a role or set of roles
     *
     * @param string|array $roles
     * @return bool
     */
    protected function hasRole($roles): bool
    {
        return $this->auth->hasRole($roles);
    }

    /**
     * Alias for hasRole
     *
     * @param string|array $permissions
     * @return bool
     */
    protected function hasPermission($permissions): bool
    {
        return $this->auth->hasPermission($permissions);
    }

    /**
     * Store a flash message
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setFlash(string $key, $value): void
    {
        $this->session->flash($key, $value);
    }

    /**
     * Read and consume a flash message
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getFlash(string $key, $default = null)
    {
        return $this->session->getFlash($key, $default);
    }

    /**
     * Sanitize a value or array of values
     *
     * @param mixed $value
     * @return mixed
     */
    protected function sanitize($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }

        return Security::sanitize((string) $value);
    }
}
