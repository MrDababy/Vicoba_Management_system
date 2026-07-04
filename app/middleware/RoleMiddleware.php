<?php
/**
 * Role Middleware
 * 
 * Ensures that users have the required role to access specific routes.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Middleware;

use App\Classes\Auth;
use App\Classes\Router;

class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @var array Required roles
     */
    private array $roles = [];

    /**
     * Constructor
     * 
     * @param array $roles Required roles
     */
    public function __construct(array $roles = [])
    {
        $this->roles = $roles;
    }

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
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }
        
        if (!empty($this->roles) && !$auth->hasRole($this->roles)) {
            // Log unauthorized access attempt
            \App\Helpers\ActivityLogger::log(
                'UNAUTHORIZED_ACCESS',
                'system',
                $auth->id(),
                'Unauthorized access attempt to: ' . $_SERVER['REQUEST_URI']
            );
            
            // Return 403 Forbidden
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            echo '<p>You do not have permission to access this page.</p>';
            exit;
        }
        
        return $next();
    }
}