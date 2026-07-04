<?php
/**
 * Authentication Middleware
 * 
 * Ensures that users are authenticated before accessing protected routes.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Middleware;

use App\Classes\Auth;
use App\Classes\Router;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Handle the middleware
     * 
     * @param Router $router Router instance
     * @param callable $next Next middleware or route handler
     * @return mixed
     */
    public function handle(Router $router, callable $next)
    {
        $auth = new Auth();
        
        if (!$auth->isAuthenticated()) {
            // Store intended URL for redirect after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            
            // Redirect to login
            header('Location: /login');
            exit;
        }
        
        return $next();
    }
}