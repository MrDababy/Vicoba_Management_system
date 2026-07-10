<?php
/**
 * Security Middleware
 * 
 * Applies security headers and checks to all requests.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Middleware;

use App\Classes\Router;
use App\Helpers\SecurityHelper;

class SecurityMiddleware implements MiddlewareInterface
{
    /**
     * @var array Security headers
     */
    private array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'"
    ];

    /**
     * Handle the middleware
     * 
     * @param Router $router Router instance
     * @param callable $next Next middleware or route handler
     * @return mixed
     */
    public function handle(Router $router, callable $next)
    {
        // Apply security headers
        $this->applySecurityHeaders();

        // Check for secure connection (if in production)
        $this->checkSecureConnection();

        // Rate limiting
        $this->checkRateLimit();

        // SQL injection detection (basic)
        $this->checkForSqlInjection();

        // Continue to next middleware/handler
        return $next();
    }

    /**
     * Apply security headers
     * 
     * @return void
     */
    private function applySecurityHeaders(): void
    {
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // HSTS - only in production
        if (APP_ENV === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Check for secure connection
     * 
     * @return void
     */
    private function checkSecureConnection(): void
    {
        if (APP_ENV === 'production') {
            $isSecure = (
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ||
                isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
            );
            
            if (!$isSecure) {
                // Redirect to HTTPS
                $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
                header('Location: ' . $url);
                exit;
            }
        }
    }

    /**
     * Check rate limit
     * 
     * @return void
     */
    private function checkRateLimit(): void
    {
        $ip = SecurityHelper::getClientIp();
        $key = 'rate_limit_' . md5($ip . $_SERVER['REQUEST_URI']);
        
        // Check for rate limit in session
        if (isset($_SESSION[$key])) {
            $data = $_SESSION[$key];
            $attempts = $data['attempts'] ?? 0;
            $timestamp = $data['timestamp'] ?? time();
            
            // Reset if window expired (60 seconds)
            if (time() - $timestamp > 60) {
                $_SESSION[$key] = ['attempts' => 1, 'timestamp' => time()];
                return;
            }
            
            // Check limit (100 requests per minute)
            if ($attempts >= 100) {
                // Log rate limit violation
                \App\Helpers\ActivityLogger::log(
                    'RATE_LIMIT_EXCEEDED',
                    'security',
                    null,
                    "Rate limit exceeded for IP: {$ip}",
                    ['ip' => $ip, 'uri' => $_SERVER['REQUEST_URI']]
                );
                
                header('HTTP/1.1 429 Too Many Requests');
                echo json_encode(['error' => 'Too many requests. Please try again later.']);
                exit;
            }
            
            $_SESSION[$key]['attempts'] = $attempts + 1;
        } else {
            $_SESSION[$key] = ['attempts' => 1, 'timestamp' => time()];
        }
    }

    /**
     * Basic SQL injection detection
     * 
     * @return void
     */
    private function checkForSqlInjection(): void
    {
        $dangerousPatterns = [
            '/(\%27)|(\')|(\-\-)/',
            '/(\%23)|(#)/',
            '/\b(union|select|insert|update|delete|drop|truncate)\b/i',
            '/\b(or|and)\s+[0-9]+\s*=\s*[0-9]+/i'
        ];
        
        $input = array_merge($_GET, $_POST, $_COOKIE);
        
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        // Log potential SQL injection attempt
                        \App\Helpers\ActivityLogger::log(
                            'SQL_INJECTION_ATTEMPT',
                            'security',
                            null,
                            "Potential SQL injection detected",
                            [
                                'ip' => SecurityHelper::getClientIp(),
                                'uri' => $_SERVER['REQUEST_URI'],
                                'key' => $key,
                                'value' => $value
                            ]
                        );
                        
                        header('HTTP/1.1 400 Bad Request');
                        echo json_encode(['error' => 'Invalid request parameters.']);
                        exit;
                    }
                }
            }
        }
    }
}