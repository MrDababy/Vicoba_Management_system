<?php
/**
 * Encryption Helper Class
 * 
 * Provides AES-256-CBC encryption and decryption for sensitive data.
 * Implements secure key management and IV handling.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

use App\Exceptions\SecurityException;

class Encryption
{
    /**
     * @var string Encryption algorithm
     */
    private string $cipher = 'AES-256-CBC';

    /**
     * @var string Encryption key
     */
    private string $key;

    /**
     * @var int IV length
     */
    private int $ivLength = 16;

    /**
     * @var int Options for openssl
     */
    private int $options = 0;

    /**
     * Constructor
     * 
     * @throws SecurityException
     */
    public function __construct()
    {
        // Load encryption key from configuration
        $this->key = $this->getKey();
        
        // Validate key
        if (empty($this->key)) {
            throw new SecurityException('Encryption key is not configured');
        }
        
        // Validate cipher
        if (!in_array($this->cipher, openssl_get_cipher_methods())) {
            throw new SecurityException('Unsupported encryption cipher');
        }
    }

    /**
     * Get encryption key
     * 
     * @return string
     */
    private function getKey(): string
    {
        // Use constant if defined
        if (defined('ENCRYPTION_KEY')) {
            return base64_decode(ENCRYPTION_KEY);
        }
        
        // Fallback to environment variable
        $key = getenv('ENCRYPTION_KEY');
        
        if ($key) {
            return base64_decode($key);
        }
        
        return '';
    }

    /**
     * Encrypt data
     * 
     * @param string $data Plain text data
     * @return string Encrypted data (base64 encoded with IV)
     * @throws SecurityException
     */
    public function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        try {
            // Generate random IV
            $iv = openssl_random_pseudo_bytes($this->ivLength);
            
            // Encrypt data
            $encrypted = openssl_encrypt(
                $data,
                $this->cipher,
                $this->key,
                $this->options,
                $iv
            );
            
            if ($encrypted === false) {
                throw new SecurityException('Encryption failed: ' . openssl_error_string());
            }
            
            // Combine IV and encrypted data
            $result = base64_encode($iv . $encrypted);
            
            return $result;
        } catch (\Exception $e) {
            throw new SecurityException('Encryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt data
     * 
     * @param string $data Encrypted data (base64 encoded with IV)
     * @return string Decrypted data
     * @throws SecurityException
     */
    public function decrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        try {
            // Decode base64
            $decoded = base64_decode($data);
            
            if ($decoded === false) {
                throw new SecurityException('Invalid encrypted data format');
            }
            
            // Extract IV and encrypted data
            $iv = substr($decoded, 0, $this->ivLength);
            $encrypted = substr($decoded, $this->ivLength);
            
            // Validate IV length
            if (strlen($iv) !== $this->ivLength) {
                throw new SecurityException('Invalid IV length');
            }
            
            // Decrypt data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher,
                $this->key,
                $this->options,
                $iv
            );
            
            if ($decrypted === false) {
                throw new SecurityException('Decryption failed: ' . openssl_error_string());
            }
            
            return $decrypted;
        } catch (\Exception $e) {
            throw new SecurityException('Decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if a value is encrypted
     * 
     * @param string $value Value to check
     * @return bool
     */
    public function isEncrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }
        
        // Check if it's base64 encoded
        if (!preg_match('/^[a-zA-Z0-9\/+=]+$/', $value)) {
            return false;
        }
        
        // Try to decode and check structure
        $decoded = base64_decode($value, true);
        
        if ($decoded === false || strlen($decoded) <= $this->ivLength) {
            return false;
        }
        
        // Check if IV is at expected position
        $iv = substr($decoded, 0, $this->ivLength);
        
        if (strlen($iv) !== $this->ivLength) {
            return false;
        }
        
        return true;
    }

    /**
     * Get encryption cipher information
     * 
     * @return array
     */
    public function getInfo(): array
    {
        return [
            'cipher' => $this->cipher,
            'iv_length' => $this->ivLength,
            'key_length' => strlen($this->key),
            'options' => $this->options
        ];
    }

    /**
     * Re-encrypt data with a new key
     * 
     * @param string $data Encrypted data
     * @param string $newKey New encryption key
     * @return string Re-encrypted data
     * @throws SecurityException
     */
    public function reEncrypt(string $data, string $newKey): string
    {
        $oldKey = $this->key;
        
        try {
            // Decrypt with old key
            $decrypted = $this->decrypt($data);
            
            // Temporarily change key
            $this->key = $newKey;
            
            // Encrypt with new key
            $result = $this->encrypt($decrypted);
            
            // Restore old key
            $this->key = $oldKey;
            
            return $result;
        } catch (\Exception $e) {
            // Restore old key
            $this->key = $oldKey;
            throw new SecurityException('Re-encryption failed: ' . $e->getMessage());
        }
    }
}
?>