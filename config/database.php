<?php
/**
 * Database Configuration
 * 
 * This file contains database connection settings for different environments.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

// Database Credentials (loaded from .env in production)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'vikoba_db');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// Database Options
define('DB_PERSISTENT', false);
define('DB_SSL_ENABLED', false);
define('DB_SSL_KEY', '');
define('DB_SSL_CERT', '');
define('DB_SSL_CA', '');

// PDO Configuration
define('PDO_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_PERSISTENT => DB_PERSISTENT
]);
?>