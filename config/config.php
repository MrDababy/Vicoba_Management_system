<?php
/**
 * Main Application Configuration
 * 
 * This file contains the core configuration settings for the VICOBA
 * Management System application.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

// Application Settings
define('APP_NAME', 'VICOBA Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/vicoba');
define('APP_ENV', 'development'); // development | production | testing


// Timezone Settings
date_default_timezone_set('Africa/Dar_es_Salaam');

// Session Configuration
define('SESSION_NAME', 'vicoba_session');
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_PATH', '/');
define('SESSION_DOMAIN', '');
define('SESSION_SECURE', false);
define('SESSION_HTTP_ONLY', true);

// Security Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_STRENGTH', 'medium'); // weak | medium | strong

// Encryption Settings
define('ENCRYPTION_CIPHER', 'AES-256-CBC');
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', ''); // Set in .env file
}

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Pagination Settings
define('ITEMS_PER_PAGE', 20);
define('MAX_PAGES', 100);

// Logging Settings
define('LOG_ERRORS', true);
define('LOG_ACTIVITIES', true);
define('LOG_SECURITY', true);
define('LOG_LEVEL', 'debug'); // debug | info | warning | error

// Display Settings
define('DISPLAY_ERRORS', true);
define('DISPLAY_DEBUG_INFO', true);

// Cache Settings
define('CACHE_ENABLED', false);
define('CACHE_LIFETIME', 3600); // 1 hour

// Internationalization
define('DEFAULT_LANGUAGE', 'en');
define('CURRENCY_SYMBOL', 'TSh');
define('CURRENCY_CODE', 'TZS');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// API Settings
define('API_ENABLED', true);
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 60); // Requests per minute
?>