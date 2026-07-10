<?php
/**
 * Router Class
 * 
 * Handles URL routing and dispatches requests to appropriate controllers.
 * Supports RESTful routing patterns and middleware.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Classes;

use App\Exceptions\RouteNotFoundException;

class Router
{
    /**
     * @var array Registered routes
     */
    private array $routes = [];

    /**
     * @var array Route middleware
     */
    private array $middleware = [];

    /**
     * @var array Global middleware
     */
    private array $globalMiddleware = [];

    /**
     * @var string Current request method
     */
    private string $method;

    /**
     * @var string Current request URI
     */
    private string $uri;

    /**
     * @var string Current route prefix
     */
    private string $prefix = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Remove base path if any
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && strpos($this->uri, $basePath) === 0) {
            $this->uri = substr($this->uri, strlen($basePath));
        }
        
        // Remove trailing slash except for root
        if ($this->uri !== '/') {
            $this->uri = rtrim($this->uri, '/');
        }
    }

    /**
     * Register a GET route
     * 
     * @param string $path Route path
     * @param string|callable $handler Handler
     * @param array $middleware Middleware to apply
     * @return void
     */
    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route
     * 
     * @param string $path Route path
     * @param string|callable $handler Handler
     * @param array $middleware Middleware to apply
     * @return void
     */
    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route
     * 
     * @param string $path Route path
     * @param string|callable $handler Handler
     * @param array $middleware Middleware to apply
     * @return void
     */
    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route
     * 
     * @param string $path Route path
     * @param string|callable $handler Handler
     * @param array $middleware Middleware to apply
     * @return void
     */
    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a route for any HTTP method
     * 
     * @param array $methods HTTP methods
     * @param string $path Route path
     * @param string|callable $handler Handler
     * @param array $middleware Middleware to apply
     * @return void
     */
    public function any(array $methods, string $path, $handler, array $middleware = []): void
    {
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
    }

    /**
     * Add a route to the router
     * 
     * @param string $method HTTP method
     * @param string $path Route path
     * @param string|callable $handler Handler
     * @param array $middleware Middleware to apply
     * @return void
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        $path = $this->prefix . '/' . trim($path, '/');
        $path = $path === '' ? '/' : $path;
        
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middleware' => array_merge($this->globalMiddleware, $middleware)
        ];
    }

    /**
     * Group routes with a prefix
     * 
     * @param string $prefix URL prefix
     * @param callable $callback Group callback
     * @return void
     */
    public function group(string $prefix, callable $callback): void
    {
        $oldPrefix = $this->prefix;
        $this->prefix = $oldPrefix . '/' . trim($prefix, '/');
        $callback($this);
        $this->prefix = $oldPrefix;
    }

    /**
     * Add global middleware
     * 
     * @param string $middleware Middleware class
     * @return void
     */
    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Dispatch the current request
     * 
     * @return mixed
     * @throws RouteNotFoundException
     */
    public function dispatch()
    {
       
        
        $route = $this->findRoute();

        
        if ($route === null) {
            throw new RouteNotFoundException('Route not found: ' . $this->method . ' ' . $this->uri);
        }
        
        // Process middleware
        $middlewareStack = $route['middleware'];
        $handler = $route['handler'];
        
        foreach (array_reverse($middlewareStack) as $middlewareClass) {
            $handler = function() use ($middlewareClass, $handler) {
                $middleware = new $middlewareClass();
                return $middleware->handle($this, $handler);
            };
        }
        
        // Extract route parameters
        $params = $this->extractParameters($route['path'], $this->uri);
        
        if (is_callable($handler)) {
            return $handler(...$params);
        }
        
        return $this->callController($handler, $params);
    }

    /**
     * Find matching route
     * 
     * @return array|null
     */
    private function findRoute(): ?array
    {
        // Check exact match first
        if (isset($this->routes[$this->method][$this->uri])) {
            return array_merge($this->routes[$this->method][$this->uri], ['path' => $this->uri]);
        }
        
        // Check parameterized routes
        foreach ($this->routes[$this->method] ?? [] as $routePath => $routeData) {
            $pattern = $this->convertRouteToRegex($routePath);
            
            if (preg_match($pattern, $this->uri)) {
                return array_merge($routeData, ['path' => $routePath]);
            }
        }
        
        return null;
    }

    /**
     * Convert route path to regex pattern
     * 
     * @param string $routePath
     * @return string
     */
    private function convertRouteToRegex(string $routePath): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $routePath);
        return '/^' . str_replace('/', '\/', $pattern) . '$/';
    }

    /**
     * Extract parameters from URI
     * 
     * @param string $routePath
     * @param string $uri
     * @return array
     */
    private function extractParameters(string $routePath, string $uri): array
    {
        $pattern = $this->convertRouteToRegex($routePath);
        preg_match($pattern, $uri, $matches);
        
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        
        return $params;
    }

    /**
     * Call a controller action
     * 
     * @param string $handler
     * @param array $params
     * @return mixed
     * @throws RouteNotFoundException
     */
    private function callController($handler, array $params)
    {
        if (is_array($handler) && count($handler) === 2) {
            [$controllerOrClass, $method] = $handler;

            if (is_object($controllerOrClass)) {
                $controller = $controllerOrClass;
                $controllerClass = get_class($controller);
            } else {
                $controllerClass = $controllerOrClass;
                if (!class_exists($controllerClass)) {
                    throw new RouteNotFoundException("Controller '$controllerClass' not found");
                }

                $controller = new $controllerClass();
            }

            if (!method_exists($controller, $method)) {
                throw new RouteNotFoundException("Method '$method' not found in '$controllerClass'");
            }

            return $controller->$method(...$params);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controllerClass, $method] = explode('@', $handler, 2);

            if (!class_exists($controllerClass)) {
                throw new RouteNotFoundException("Controller '$controllerClass' not found");
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $method)) {
                throw new RouteNotFoundException("Method '$method' not found in '$controllerClass'");
            }

            return $controller->$method(...$params);
        }

        throw new RouteNotFoundException('Invalid controller handler');
    }

    /**
     * Get all registered routes (for debugging)
     * 
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
?>
