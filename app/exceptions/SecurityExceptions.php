<?php
/**
 * Security Exception
 * 
 * Custom exception for security-related errors.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Exceptions;

class SecurityException extends \Exception
{
    /**
     * @var int Severity level
     */
    private int $severity;

    /**
     * @var array Additional context data
     */
    private array $context;

    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param int $severity Severity level (1-5)
     * @param array $context Additional context
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        int $severity = 3,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->severity = $severity;
        $this->context = $context;
        
        // Log the exception
        $this->logException();
    }

    /**
     * Get severity level
     * 
     * @return int
     */
    public function getSeverity(): int
    {
        return $this->severity;
    }

    /**
     * Get context data
     * 
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Log the exception
     * 
     * @return void
     */
    private function logException(): void
    {
        $logData = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'severity' => $this->severity,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log to file
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $logFile = $rootPath . '/logs/security_' . date('Y-m-d') . '.log';
        $logEntry = json_encode($logData) . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log to activity log if database is available
        try {
            \App\Helpers\ActivityLogger::log(
                'SECURITY_EXCEPTION',
                'security',
                null,
                $this->getMessage(),
                null,
                $logData
            );
        } catch (\Exception $e) {
            // Ignore if activity logging fails
        }
    }
}