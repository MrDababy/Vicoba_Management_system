<?php
/**
 * Security Helper
 * 
 * Provides comprehensive security utilities including CSRF protection,
 * XSS prevention, input sanitization, and secure random generation.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class SecurityHelper
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
        try {
            $bytes = random_bytes($length);
            return bin2hex($bytes);
        } catch (\Exception $e) {
            // Fallback to less secure but still random
            return substr(md5(uniqid(mt_rand(), true)), 0, $length);
        }
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
        try {
            return bin2hex(random_bytes($length));
        } catch (\Exception $e) {
            return substr(md5(uniqid(mt_rand(), true)), 0, $length * 2);
        }
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
        $pattern = '/<(script|iframe|object|embed|form|input|button|meta|link|style)/i';
        return preg_match($pattern, $html) === 0;
    }

    /**
     * Validate and sanitize URL
     * 
     * @param string $url URL to validate
     * @return string|bool
     */
    public static function validateUrl(string $url)
    {
        $sanitized = filter_var($url, FILTER_SANITIZE_URL);
        if (filter_var($sanitized, FILTER_VALIDATE_URL)) {
            return $sanitized;
        }
        return false;
    }

    /**
     * Validate and sanitize email
     * 
     * @param string $email Email to validate
     * @return string|bool
     */
    public static function validateEmail(string $email)
    {
        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return $sanitized;
        }
        return false;
    }

    /**
     * Get client IP address with proxy support
     * 
     * @return string
     */
    public static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Check for proxy headers
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                break;
            }
        }
        
        return $ip;
    }

    /**
     * Generate secure session ID
     * 
     * @return string
     */
    public static function generateSessionId(): string
    {
        return self::generateToken(32);
    }

    /**
     * Validate file upload
     * 
     * @param array $file File array from $_FILES
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array
     */
    public static function validateFileUpload(array $file, array $allowedTypes, int $maxSize): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Upload failed: ' . $file['error']];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'File too large. Maximum size: ' . ($maxSize / 1024 / 1024) . 'MB'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
        }
        
        return ['valid' => true, 'message' => 'File validated successfully'];
    }
}