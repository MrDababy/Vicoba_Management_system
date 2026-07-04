<?php
/**
 * User Model
 * 
 * Handles all user-related database operations including authentication,
 * CRUD operations, and role management.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Security;
use App\Helpers\Encryption;
use App\Exceptions\DatabaseException;

class User extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'users';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'username',
        'email',
        'password_hash',
        'full_name',
        'phone',
        'role',
        'status',
        'remember_token',
        'last_login'
    ];

    /**
     * @var array Encrypted fields
     */
    protected array $encrypted = [
        'phone',
        'email'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'username' => 'required|alpha_dash|min:3|max:50',
        'email' => 'required|email|max:100',
        'password' => 'required|min:8',
        'full_name' => 'required|max:100',
        'phone' => 'required|phone',
        'role' => 'required|in:Admin,Treasurer,Secretary,Member',
        'status' => 'in:Active,Inactive,Suspended'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Create a new user with hashed password
     * 
     * @param array $data User data
     * @return int|bool
     * @throws \Exception
     */
    public function register(array $data)
    {
        // Validate data
        $validated = $this->validate($data);
        
        // Hash password
        if (isset($validated['password'])) {
            $validated['password_hash'] = Security::hashPassword($validated['password']);
            unset($validated['password']);
        }
        
        // Set default role if not provided
        if (!isset($validated['role'])) {
            $validated['role'] = 'Member';
        }
        
        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'Active';
        }
        
        // Create user
        return $this->create($validated);
    }

    /**
     * Find user by username or email
     * 
     * @param string $username Username or email
     * @return array|null
     */
    public function findByUsernameOrEmail(string $username): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE username = ? OR email = ? LIMIT 1";
        $stmt = $this->db->query($sql, [$username, $username]);
        $data = $stmt->fetch();
        
        if ($data) {
            $this->data = $data;
            $this->exists = true;
            return $this->decryptData($data);
        }
        
        return null;
    }

    /**
     * Find user by email
     * 
     * @param string $email Email address
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /**
     * Find user by username
     * 
     * @param string $username Username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findBy('username', $username);
    }

    /**
     * Find user by remember token
     * 
     * @param string $token Remember token
     * @return array|null
     */
    public function findByRememberToken(string $token): ?array
    {
        return $this->findBy('remember_token', $token);
    }

    /**
     * Update user's last login timestamp
     * 
     * @param int $userId User ID
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = ?";
        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update user's remember token
     * 
     * @param int $userId User ID
     * @param string $token Remember token
     * @return bool
     */
    public function updateRememberToken(int $userId, string $token): bool
    {
        $sql = "UPDATE {$this->table} SET remember_token = ? WHERE id = ?";
        $stmt = $this->db->query($sql, [$token, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Clear user's remember token
     * 
     * @param int $userId User ID
     * @return bool
     */
    public function clearRememberToken(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET remember_token = NULL WHERE id = ?";
        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hashedPassword = Security::hashPassword($newPassword);
        $sql = "UPDATE {$this->table} SET password_hash = ? WHERE id = ?";
        $stmt = $this->db->query($sql, [$hashedPassword, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate password reset token
     * 
     * @param string $email User email
     * @return string|bool
     */
    public function generateResetToken(string $email)
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            return false;
        }
        
        $token = Security::generateToken(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "UPDATE {$this->table} SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
        $stmt = $this->db->query($sql, [$token, $expires, $user['id']]);
        
        if ($stmt->rowCount() > 0) {
            return $token;
        }
        
        return false;
    }

    /**
     * Verify password reset token
     * 
     * @param string $token Reset token
     * @return array|bool
     */
    public function verifyResetToken(string $token)
    {
        $sql = "SELECT * FROM {$this->table} WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1";
        $stmt = $this->db->query($sql, [$token]);
        $data = $stmt->fetch();
        
        if ($data) {
            return $this->decryptData($data);
        }
        
        return false;
    }

    /**
     * Clear reset token after password change
     * 
     * @param int $userId User ID
     * @return bool
     */
    public function clearResetToken(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET reset_token = NULL, reset_token_expires = NULL WHERE id = ?";
        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all users with pagination
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Search filters
     * @return array
     */
    public function getPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [];
        
        // Build WHERE clause
        if (!empty($filters)) {
            if (isset($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $where[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
                $params = array_merge($params, [$search, $search, $search]);
            }
            
            if (isset($filters['role'])) {
                $where[] = "role = ?";
                $params[] = $filters['role'];
            }
            
            if (isset($filters['status'])) {
                $where[] = "status = ?";
                $params[] = $filters['status'];
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
        $data = array_map([$this, 'decryptData'], $data);
        
        return [
            'data' => $data,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Check if username exists
     * 
     * @param string $username Username to check
     * @param int|null $excludeId User ID to exclude
     * @return bool
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE username = ?";
        $params = [$username];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Check if email exists
     * 
     * @param string $email Email to check
     * @param int|null $excludeId User ID to exclude
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = ?";
        $params = [$email];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get users by role
     * 
     * @param string $role Role name
     * @return array
     */
    public function getByRole(string $role): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE role = ? AND status = 'Active'";
        $stmt = $this->db->query($sql, [$role]);
        $data = $stmt->fetchAll();
        return array_map([$this, 'decryptData'], $data);
    }

    /**
     * Get active users count
     * 
     * @return int
     */
    public function getActiveCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'Active'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get users by role count
     * 
     * @return array
     */
    public function getRoleCounts(): array
    {
        $sql = "SELECT role, COUNT(*) as count FROM {$this->table} GROUP BY role";
        $stmt = $this->db->query($sql);
        $data = $stmt->fetchAll();
        
        $counts = [];
        foreach ($data as $row) {
            $counts[$row['role']] = (int)$row['count'];
        }
        
        return $counts;
    }
}