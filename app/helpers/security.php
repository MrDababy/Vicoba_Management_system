<?php
/**
 * Security Helper Class
 * 
 * Provides comprehensive security utilities including CSRF protection,
 * XSS prevention, input sanitization, and secure random generation.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class Security
{
    /**
     * Generate a CSRF token
     * 
     * @return string
     */
    public static function generateCsrfToken(): string
    {
        $token = self::generateRandomString(32);
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @return bool
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
            return false;
        }
        
        // Check token lifetime
        $maxAge = defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 3600;
        if (isset($_SESSION['csrf_token_time']) && 
            (time() - $_SESSION['csrf_token_time']) > $maxAge) {
            return false;
        }
        
        return true;
    }

    /**
     * Generate a random string
     * 
     * @param int $length Length of string
     * @return string
     */
    public static function generateRandomString(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }

    /**
     * Sanitize input to prevent XSS
     * 
     * @param string $input Input to sanitize
     * @return string
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize an array of inputs
     * 
     * @param array $data Data to sanitize
     * @return array
     */
    public static function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitize($value);
            }
        }
        return $sanitized;
    }

    /**
     * Hash a password
     * 
     * @param string $password Plain password
     * @return string
     */
    public static function hashPassword(string $password): string
    {
        $options = [
            'cost' => 12
        ];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    /**
     * Verify password against hash
     * 
     * @param string $password Plain password
     * @param string $hash Stored hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate a secure random token
     * 
     * @param int $length Token length in bytes
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Prevent SQL injection in column names (for dynamic queries)
     * 
     * @param string $column Column name
     * @return string
     */
    public static function sanitizeColumn(string $column): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    }

    /**
     * Check if value is safe HTML (basic check)
     * 
     * @param string $html HTML to check
     * @return bool
     */
    public static function isSafeHtml(string $html): bool
    {
        // Remove known dangerous tags
        $pattern = '/<(script|iframe|object|embed|form|input|button|meta)/i';
        return preg_match($pattern, $html) === 0;
    }
}