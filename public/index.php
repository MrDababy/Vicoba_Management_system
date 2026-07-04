<?php
/**
 * Front Controller
 * 
 * Entry point for all requests. Initializes the application,
 * loads configuration, and handles routing.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Load configuration
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/encryption.php';

// Set error handler
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Load environment variables from .env file
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Autoloader
spl_autoload_register(function ($class) {
    // Remove namespace prefix
    $class = str_replace('App\\', '', $class);
    
    // Convert namespace separators to directory separators
    $classPath = str_replace('\\', '/', $class);
    
    // Check each directory
    $directories = [
        APP_PATH . '/classes/',
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
        APP_PATH . '/helpers/',
        APP_PATH . '/middleware/',
        APP_PATH . '/exceptions/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $classPath . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize router
$router = new App\Classes\Router();

// Load routes
require_once APP_PATH . '/routes.php';

// Dispatch request
try {
    $response = $router->dispatch();
    
    // If response is not already output, echo it
    if ($response !== null && !headers_sent()) {
        echo $response;
    }
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500);
    
    if (APP_ENV === 'development') {
        echo '<h1>Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' line ' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        // Log error and show generic message
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        echo '<h1>An error occurred</h1>';
        echo '<p>Please try again later.</p>';
    }
}

// Flush output buffer
ob_end_flush();