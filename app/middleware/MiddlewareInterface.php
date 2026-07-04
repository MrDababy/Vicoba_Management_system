<?php
/**
 * Middleware Interface
 * 
 * Defines the contract for all middleware classes.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Middleware;

use App\Classes\Router;

interface MiddlewareInterface
{
    /**
     * Handle the middleware
     * 
     * @param Router $router Router instance
     * @param callable $next Next middleware or route handler
     * @return mixed
     */
    public function handle(Router $router, callable $next);
}