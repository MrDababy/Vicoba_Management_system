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
        $content = $viewPath;
        
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