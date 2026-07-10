<?php
/**
 * Encryption Helper
 * 
 * Provides AES-256-CBC encryption and decryption for sensitive data
 * with secure key management.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

use App\Exceptions\SecurityException;

class EncryptionHelper
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
    private int $options = OPENSSL_RAW_DATA;

    /**
     * Constructor
     * 
     * @throws SecurityException
     */
    public function __construct()
    {
        // Load encryption key from environment
        $this->key = $this->getKey();
        
        // Validate key
        if (empty($this->key)) {
            throw new SecurityException('Encryption key is not configured');
        }
        
        // Validate cipher
        if (!in_array($this->cipher, openssl_get_cipher_methods())) {
            throw new SecurityException('Unsupported encryption cipher');
        }
        
        // Set IV length
        $this->ivLength = openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Get encryption key from environment
     * 
     * @return string
     * @throws SecurityException
     */
    private function getKey(): string
    {
        // Check environment variable
        $key = getenv('ENCRYPTION_KEY');
        
        if ($key) {
            return base64_decode($key);
        }
        
        // Check constant
        if (defined('ENCRYPTION_KEY')) {
            return base64_decode(ENCRYPTION_KEY);
        }
        
        // In development, generate a key
        if (APP_ENV === 'development') {
            $key = base64_encode(random_bytes(32));
            // Store in .env file for future use
            $this->saveKeyToEnv($key);
            return base64_decode($key);
        }
        
        throw new SecurityException('Encryption key not found in environment');
    }

    /**
     * Save encryption key to .env file (development only)
     * 
     * @param string $key Base64 encoded key
     * @return void
     */
    private function saveKeyToEnv(string $key): void
    {
        if (APP_ENV !== 'development') {
            return;
        }
        
        $envFile = ROOT_PATH . '/.env';
        if (!file_exists($envFile)) {
            return;
        }
        
        $content = file_get_contents($envFile);
        if (strpos($content, 'ENCRYPTION_KEY') === false) {
            $content .= PHP_EOL . 'ENCRYPTION_KEY=' . $key . PHP_EOL;
            file_put_contents($envFile, $content);
        }
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
            $decoded = base64_decode($data, true);
            
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
     * Get encryption information
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

    /**
     * Rotate encryption key
     * 
     * @param string $newKey New encryption key (base64 encoded)
     * @return bool
     * @throws SecurityException
     */
    public function rotateKey(string $newKey): bool
    {
        $newKeyDecoded = base64_decode($newKey);
        
        if (strlen($newKeyDecoded) !== 32) {
            throw new SecurityException('Invalid key length. Must be 32 bytes.');
        }
        
        // Get all tables with encrypted fields
        $tables = [
            'members' => ['national_id', 'phone', 'email', 'address', 'full_name'],
            'users' => ['phone', 'email'],
            'loans' => ['remarks'],
            'fines' => ['description', 'waiver_reason']
        ];
        
        $this->beginTransaction();
        
        try {
            foreach ($tables as $table => $fields) {
                foreach ($fields as $field) {
                    // Get all records with non-empty field
                    $sql = "SELECT id, {$field} FROM {$table} WHERE {$field} IS NOT NULL AND {$field} != ''";
                    $stmt = Database::getInstance()->query($sql);
                    $records = $stmt->fetchAll();
                    
                    foreach ($records as $record) {
                        // Re-encrypt with new key
                        $reEncrypted = $this->reEncrypt($record[$field], $newKeyDecoded);
                        
                        // Update record
                        $updateSql = "UPDATE {$table} SET {$field} = ? WHERE id = ?";
                        Database::getInstance()->query($updateSql, [$reEncrypted, $record['id']]);
                    }
                }
            }
            
            // Update key in environment
            $this->saveKeyToEnv($newKey);
            
            $this->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw new SecurityException('Key rotation failed: ' . $e->getMessage());
        }
    }

    /**
     * Begin transaction
     */
    private function beginTransaction(): void
    {
        Database::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    private function commit(): void
    {
        Database::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    private function rollback(): void
    {
        Database::getInstance()->rollback();
    }
}