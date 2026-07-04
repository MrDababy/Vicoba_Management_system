<?php
/**
 * Activity Logger Helper
 * 
 * Logs all user activities for audit trail and security monitoring.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

use App\Classes\Database;

class ActivityLogger
{
    /**
     * @var Database Database instance
     */
    private static ?Database $db = null;

    /**
     * Log an activity
     * 
     * @param string $action Action performed
     * @param string $table Table affected
     * @param int|null $recordId Record ID
     * @param string $description Description
     * @param array|null $beforeData Data before change
     * @param array|null $afterData Data after change
     * @return bool
     */
    public static function log(
        string $action,
        string $table,
        ?int $recordId = null,
        string $description = '',
        ?array $beforeData = null,
        ?array $afterData = null
    ): bool {
        // Check if logging is enabled
        if (!defined('LOG_ACTIVITIES') || !LOG_ACTIVITIES) {
            return false;
        }
        
        try {
            // Get database instance
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }
            
            // Get user ID
            $userId = self::getCurrentUserId();
            
            // Get IP address
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ipAddress === '::1' || $ipAddress === '127.0.0.1') {
                $ipAddress = 'localhost';
            }
            
            // Get user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
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
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Insert log
            $sql = "INSERT INTO activity_logs 
                    (user_id, action, table_name, record_id, description, 
                     ip_address, user_agent, before_data, after_data, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
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
                $data['created_at']
            ];
            
            self::$db->query($sql, $params);
            return true;
            
        } catch (\Exception $e) {
            // Log to error log if database logging fails
            error_log('Activity Logging Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current user ID from session
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
     * Get activity logs with pagination
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
            if (!empty($filters)) {
                if (isset($filters['action'])) {
                    $where[] = "action = ?";
                    $params[] = $filters['action'];
                }
                
                if (isset($filters['table_name'])) {
                    $where[] = "table_name = ?";
                    $params[] = $filters['table_name'];
                }
                
                if (isset($filters['user_id'])) {
                    $where[] = "user_id = ?";
                    $params[] = $filters['user_id'];
                }
                
                if (isset($filters['search'])) {
                    $search = '%' . $filters['search'] . '%';
                    $where[] = "(description LIKE ? OR table_name LIKE ? OR action LIKE ?)";
                    $params = array_merge($params, [$search, $search, $search]);
                }
                
                if (isset($filters['from_date']) && isset($filters['to_date'])) {
                    $where[] = "created_at BETWEEN ? AND ?";
                    $params[] = $filters['from_date'] . ' 00:00:00';
                    $params[] = $filters['to_date'] . ' 23:59:59';
                }
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
            }
            
            return [
                'data' => $data,
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (\Exception $e) {
            error_log('Failed to get activity logs: ' . $e->getMessage());
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
     * Get recent activities
     * 
     * @param int $limit Number of activities
     * @return array
     */
    public static function getRecent(int $limit = 10): array
    {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }
            
            $sql = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?";
            $stmt = self::$db->query($sql, [$limit]);
            return $stmt->fetchAll();
            
        } catch (\Exception $e) {
            error_log('Failed to get recent activities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean old activity logs
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
            error_log('Failed to clean activity logs: ' . $e->getMessage());
            return false;
        }
    }
}