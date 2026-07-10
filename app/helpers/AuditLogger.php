<?php
/**
 * Audit Logger
 * 
 * Provides comprehensive audit logging for all system actions
 * with support for before/after data tracking.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

use App\Classes\Database;

class AuditLogger
{
    /**
     * @var Database Database instance
     */
    private static ?Database $db = null;

    /**
     * @var array Logged actions
     */
    private static array $actions = [];

    /**
     * @var bool Is logging enabled
     */
    private static bool $enabled = true;

    /**
     * Log an audit entry
     * 
     * @param string $action Action performed (CREATE, UPDATE, DELETE, etc.)
     * @param string $table Table affected
     * @param int|null $recordId Record ID
     * @param string $description Description of the action
     * @param array|null $beforeData Data before change
     * @param array|null $afterData Data after change
     * @param array $additionalData Additional context data
     * @return bool
     */
    public static function log(
        string $action,
        string $table,
        ?int $recordId = null,
        string $description = '',
        ?array $beforeData = null,
        ?array $afterData = null,
        array $additionalData = []
    ): bool {
        if (!self::$enabled) {
            return false;
        }

        try {
            // Get database instance
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }

            // Get user information
            $userId = self::getCurrentUserId();
            $ipAddress = SecurityHelper::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // Sanitize sensitive data before storing
            if ($beforeData !== null) {
                $beforeData = self::sanitizeForLog($beforeData);
            }
            if ($afterData !== null) {
                $afterData = self::sanitizeForLog($afterData);
            }

            // Prepare data
            $data = [
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'description' => $description,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'before_data' => $beforeData ? json_encode($beforeData) : null,
                'after_data' => $afterData ? json_encode($afterData) : null,
                'additional_data' => json_encode($additionalData),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Insert log
            $sql = "INSERT INTO activity_logs 
                    (user_id, action, table_name, record_id, description, 
                     ip_address, user_agent, before_data, after_data, additional_data, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['user_id'],
                $data['action'],
                $data['table_name'],
                $data['record_id'],
                $data['description'],
                $data['ip_address'],
                $data['user_agent'],
                $data['before_data'],
                $data['after_data'],
                $data['additional_data'],
                $data['created_at']
            ];

            self::$db->query($sql, $params);

            // Store in memory for batch processing if needed
            self::$actions[] = $data;

            return true;

        } catch (\Exception $e) {
            // Log to error log if database logging fails
            error_log('Audit Logging Failed: ' . $e->getMessage());
            self::logToFile($e->getMessage(), $data ?? []);
            return false;
        }
    }

    /**
     * Get current user ID
     * 
     * @return int|null
     */
    private static function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Sanitize data for logging
     * 
     * @param array $data Data to sanitize
     * @return array
     */
    private static function sanitizeForLog(array $data): array
    {
        $sensitiveFields = [
            'password', 'password_hash', 'remember_token', 
            'reset_token', 'csrf_token', 'api_key'
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000) . '... [TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Log to file as fallback
     * 
     * @param string $message Error message
     * @param array $data Log data
     * @return void
     */
    private static function logToFile(string $message, array $data): void
    {
        $logFile = ROOT_PATH . '/logs/audit_fallback.log';
        $entry = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($data) . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get audit logs with pagination and filters
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Search filters
     * @return array
     */
    public static function getLogs(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }

            $offset = ($page - 1) * $perPage;
            $params = [];
            $where = [];

            // Build WHERE clause
            if (isset($filters['action']) && !empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            if (isset($filters['table_name']) && !empty($filters['table_name'])) {
                $where[] = "table_name = ?";
                $params[] = $filters['table_name'];
            }

            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (isset($filters['search']) && !empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $where[] = "(description LIKE ? OR action LIKE ? OR table_name LIKE ?)";
                $params = array_merge($params, [$search, $search, $search]);
            }

            if (isset($filters['from_date']) && !empty($filters['from_date'])) {
                $where[] = "DATE(created_at) >= ?";
                $params[] = $filters['from_date'];
            }

            if (isset($filters['to_date']) && !empty($filters['to_date'])) {
                $where[] = "DATE(created_at) <= ?";
                $params[] = $filters['to_date'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM activity_logs {$whereClause}";
            $stmt = self::$db->query($countSql, $params);
            $total = $stmt->fetch()['total'] ?? 0;

            // Get paginated results
            $sql = "SELECT * FROM activity_logs {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;

            $stmt = self::$db->query($sql, $params);
            $data = $stmt->fetchAll();

            // Decode JSON data
            foreach ($data as &$row) {
                if ($row['before_data']) {
                    $row['before_data'] = json_decode($row['before_data'], true);
                }
                if ($row['after_data']) {
                    $row['after_data'] = json_decode($row['after_data'], true);
                }
                if ($row['additional_data']) {
                    $row['additional_data'] = json_decode($row['additional_data'], true);
                }
            }

            return [
                'data' => $data,
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];

        } catch (\Exception $e) {
            error_log('Failed to get audit logs: ' . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }

    /**
     * Get recent audit logs
     * 
     * @param int $limit Number of logs
     * @param array $filters Additional filters
     * @return array
     */
    public static function getRecent(int $limit = 10, array $filters = []): array
    {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }

            $params = [];
            $where = [];

            if (isset($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            if (isset($filters['table_name'])) {
                $where[] = "table_name = ?";
                $params[] = $filters['table_name'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT * FROM activity_logs {$whereClause} ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = self::$db->query($sql, $params);
            return $stmt->fetchAll();

        } catch (\Exception $e) {
            error_log('Failed to get recent audit logs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enable/disable audit logging
     * 
     * @param bool $enabled
     * @return void
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Clean old audit logs
     * 
     * @param int $days Number of days to keep
     * @return bool
     */
    public static function cleanOldLogs(int $days = 90): bool
    {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }

            $cutoffDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            $sql = "DELETE FROM activity_logs WHERE created_at < ?";
            self::$db->query($sql, [$cutoffDate]);
            return true;

        } catch (\Exception $e) {
            error_log('Failed to clean audit logs: ' . $e->getMessage());
            return false;
        }
    }
}