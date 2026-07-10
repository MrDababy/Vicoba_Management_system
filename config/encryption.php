<?php
/**
 * Encryption Configuration
 * 
 * This file manages the encryption keys and settings for securing
 * sensitive data in the application.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

/**
 * Encryption Key Management
 * 
 * The encryption key should be stored in the .env file in production.
 * For development, it can be loaded from this file.
 * 
 * NEVER hardcode encryption keys in application logic.
 */
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: '');
}

// If no key is set, generate a warning
if (empty(ENCRYPTION_KEY) && APP_ENV === 'production') {
    throw new Exception('Encryption key is not set in production environment.');
}

// In development, generate a key if not set
if (empty(ENCRYPTION_KEY) && APP_ENV === 'development') {
    $generatedKey = base64_encode(openssl_random_pseudo_bytes(32));
    if (!defined('ENCRYPTION_KEY')) {
        define('ENCRYPTION_KEY', $generatedKey);
    }
}

// Encryption Algorithm Settings
define('ENCRYPTION_ALGORITHM', 'aes-256-cbc');
define('ENCRYPTION_IV_LENGTH', openssl_cipher_iv_length(ENCRYPTION_ALGORITHM));
define('ENCRYPTION_OPTIONS', 0);

// Encryption Key Rotation Settings
define('ENCRYPTION_KEY_ROTATION_ENABLED', false);
define('ENCRYPTION_KEY_ROTATION_INTERVAL', 90); // days

// Data to Encrypt
define('ENCRYPTED_FIELDS', [
    'members' => ['national_id', 'phone', 'email', 'address'],
    'users' => ['phone', 'email'],
    'loans' => ['remarks'],
    'fines' => ['description']
]);

// Decryption Access Rules
define('DECRYPTION_ROLES_ALLOWED', [
    'Admin',
    'Treasurer',
    'Secretary'
]);

// Audit Logging for Encrypted Data
define('LOG_ENCRYPTION_ACTIVITY', true);
define('LOG_DECRYPTION_ACTIVITY', true);



?>
