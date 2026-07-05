<?php
/**
 * Application Routes
 * 
 * Defines all routes for the VICOBA Management System.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

use App\Controllers\AuthController;

// Get router instance
$router = $GLOBALS['router'] ?? null;

if (!$router) {
    throw new Exception('Router not initialized');
}

// ============================================
// Authentication Routes
// ============================================
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout']);

// Password Reset Routes
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// AJAX Verification Routes
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);
$router->get('/verify-username', [AuthController::class, 'verifyUsername']);
$router->get('/check-auth', [AuthController::class, 'checkAuth']);

// ============================================
// Protected Routes (Require Authentication)
// ============================================
// $router->group('/dashboard', function($router) {
//     $router->get('/dashboard', [DashboardController::class, 'index']);
// });

// ============================================
// Admin Routes
// ============================================
// $router->group('/admin', function($router) {
//     $router->addGlobalMiddleware(RoleMiddleware::class);
//     $router->get('/users', [UserController::class, 'index']);
//     $router->get('/users/create', [UserController::class, 'create']);
//     $router->post('/users', [UserController::class, 'store']);
//     $router->get('/users/{id}', [UserController::class, 'show']);
//     $router->get('/users/{id}/edit', [UserController::class, 'edit']);
//     $router->put('/users/{id}', [UserController::class, 'update']);
//     $router->delete('/users/{id}', [UserController::class, 'delete']);
// });

// ============================================
// 404 Handler
// ============================================
// Default 404 handling is in Router::dispatch()

// ============================================
// Dashboard Routes
// ============================================
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/{period}', [DashboardController::class, 'index']);
$router->get('/dashboard/data', [DashboardController::class, 'getData']);
$router->get('/dashboard/chart-data', [DashboardController::class, 'getChartData']);
$router->get('/dashboard/activities', [DashboardController::class, 'getActivities']);
$router->post('/dashboard/update-period', [DashboardController::class, 'updatePeriod']);
$router->post('/dashboard/toggle-dark-mode', [DashboardController::class, 'toggleDarkMode']);
$router->get('/dashboard/export/{format}', [DashboardController::class, 'export']);


// ============================================
// Member Management Routes
// ============================================
$router->get('/members', [MemberController::class, 'index']);
$router->get('/members/create', [MemberController::class, 'create']);
$router->post('/members', [MemberController::class, 'store']);
$router->get('/members/{id}', [MemberController::class, 'view']);
$router->get('/members/{id}/edit', [MemberController::class, 'edit']);
$router->put('/members/{id}', [MemberController::class, 'update']);
$router->delete('/members/{id}', [MemberController::class, 'delete']);
$router->post('/members/{id}/deactivate', [MemberController::class, 'deactivate']);
$router->get('/members/export', [MemberController::class, 'export']);


// ============================================
// Savings Routes
// ============================================
$router->get('/savings', [SavingsController::class, 'index']);
$router->get('/savings/create', [SavingsController::class, 'create']);
$router->post('/savings', [SavingsController::class, 'store']);
$router->get('/savings/{id}', [SavingsController::class, 'view']);
$router->get('/savings/{id}/edit', [SavingsController::class, 'edit']);
$router->put('/savings/{id}', [SavingsController::class, 'update']);
$router->delete('/savings/{id}', [SavingsController::class, 'delete']);
$router->get('/savings/receipt/{id}', [SavingsController::class, 'receipt']);
$router->get('/savings/export/{format}', [SavingsController::class, 'export']);
$router->get('/savings/balance', [SavingsController::class, 'getBalance']);
$router->get('/savings/monthly-summary', [SavingsController::class, 'getMonthlySummary']);