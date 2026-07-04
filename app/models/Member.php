<?php
/**
 * Member Model
 * 
 * Handles all member-related database operations with encryption
 * for sensitive data. Implements soft delete and comprehensive
 * search functionality.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;
use App\Helpers\Security;
use App\Exceptions\DatabaseException;

class Member extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'members';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'user_id',
        'member_no',
        'full_name',
        'gender',
        'date_of_birth',
        'national_id',
        'phone',
        'email',
        'address',
        'occupation',
        'joining_date',
        'status',
        'profile_pic',
        'created_by'
    ];

    /**
     * @var array Encrypted fields
     */
    protected array $encrypted = [
        'full_name',
        'national_id',
        'phone',
        'email',
        'address'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'member_no' => 'required|max:20',
        'full_name' => 'required|max:100',
        'gender' => 'required|in:Male,Female,Other',
        'date_of_birth' => 'required|date',
        'national_id' => 'required|national_id',
        'phone' => 'required|phone',
        'email' => 'required|email|max:100',
        'address' => 'max:500',
        'occupation' => 'max:100',
        'joining_date' => 'required|date',
        'status' => 'in:Active,Inactive,Suspended,Defaulted'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int',
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Generate a unique member number
     * 
     * @return string
     */
    public function generateMemberNumber(): string
    {
        $year = date('Y');
        $prefix = 'VMB';
        
        // Get the last member number
        $sql = "SELECT member_no FROM {$this->table} 
                WHERE member_no LIKE ? 
                ORDER BY id DESC LIMIT 1";
        
        $pattern = $prefix . $year . '%';
        $stmt = $this->db->query($sql, [$pattern]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Extract the sequence number
            $lastNumber = (int)substr($result['member_no'], -4);
            $sequence = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }
        
        return $prefix . $year . $sequence;
    }

    /**
     * Create a new member with encryption
     * 
     * @param array $data Member data
     * @return int|bool
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Generate member number if not provided
        if (!isset($data['member_no']) || empty($data['member_no'])) {
            $data['member_no'] = $this->generateMemberNumber();
        }
        
        // Set default status
        if (!isset($data['status'])) {
            $data['status'] = 'Active';
        }
        
        // Validate unique fields
        if ($this->memberNumberExists($data['member_no'])) {
            throw new \Exception('Member number already exists.');
        }
        
        if ($this->emailExists($data['email'])) {
            throw new \Exception('Email already registered to another member.');
        }
        
        if ($this->nationalIdExists($data['national_id'])) {
            throw new \Exception('National ID already registered to another member.');
        }
        
        // Validate and encrypt data
        $validatedData = $this->validate($data);
        $encryptedData = $this->encryptData($validatedData);
        
        // Handle profile picture upload
        if (isset($data['profile_pic']) && !empty($data['profile_pic'])) {
            $encryptedData['profile_pic'] = $data['profile_pic'];
        }
        
        // Create the member
        return parent::create($encryptedData);
    }

    /**
     * Update a member
     * 
     * @param int $id Member ID
     * @param array $data Member data
     * @return bool
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        // Get current member data
        $current = $this->find($id);
        if (!$current) {
            throw new \Exception('Member not found.');
        }
        
        // Validate unique fields (excluding current record)
        if (isset($data['member_no']) && $data['member_no'] !== $current['member_no']) {
            if ($this->memberNumberExists($data['member_no'], $id)) {
                throw new \Exception('Member number already exists.');
            }
        }
        
        if (isset($data['email']) && $data['email'] !== $current['email']) {
            if ($this->emailExists($data['email'], $id)) {
                throw new \Exception('Email already registered to another member.');
            }
        }
        
        if (isset($data['national_id']) && $data['national_id'] !== $current['national_id']) {
            if ($this->nationalIdExists($data['national_id'], $id)) {
                throw new \Exception('National ID already registered to another member.');
            }
        }
        
        // Validate and encrypt data
        $validatedData = $this->validate($data, true);
        $encryptedData = $this->encryptData($validatedData);
        
        // Handle profile picture
        if (isset($data['profile_pic'])) {
            // Delete old profile picture if exists
            if (!empty($current['profile_pic']) && 
                file_exists(UPLOAD_PATH . '/profiles/' . $current['profile_pic'])) {
                unlink(UPLOAD_PATH . '/profiles/' . $current['profile_pic']);
            }
            $encryptedData['profile_pic'] = $data['profile_pic'];
        }
        
        return parent::update($id, $encryptedData);
    }

    /**
     * Delete a member (soft delete)
     * 
     * @param int $id Member ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        // Check if member has related records
        if ($this->hasRelatedRecords($id)) {
            throw new \Exception('Cannot delete member with existing records. Consider deactivating instead.');
        }
        
        // Get member data for logging
        $member = $this->find($id);
        if (!$member) {
            throw new \Exception('Member not found.');
        }
        
        // Delete profile picture
        if (!empty($member['profile_pic']) && 
            file_exists(UPLOAD_PATH . '/profiles/' . $member['profile_pic'])) {
            unlink(UPLOAD_PATH . '/profiles/' . $member['profile_pic']);
        }
        
        return parent::delete($id);
    }

    /**
     * Soft deactivate a member instead of delete
     * 
     * @param int $id Member ID
     * @param string $reason Reason for deactivation
     * @return bool
     */
    public function deactivate(int $id, string $reason = ''): bool
    {
        $sql = "UPDATE {$this->table} SET status = 'Inactive' WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        
        if ($stmt->rowCount() > 0) {
            // Log deactivation
            \App\Helpers\ActivityLogger::log(
                'DEACTIVATE',
                $this->table,
                $id,
                "Member deactivated. Reason: {$reason}"
            );
            return true;
        }
        
        return false;
    }

    /**
     * Check if member has related records
     * 
     * @param int $id Member ID
     * @return bool
     */
    private function hasRelatedRecords(int $id): bool
    {
        $tables = ['savings', 'loans', 'repayments', 'fines', 'dividends', 'attendances'];
        
        foreach ($tables as $table) {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE member_id = ?";
            $stmt = $this->db->query($sql, [$id]);
            $result = $stmt->fetch();
            if (($result['count'] ?? 0) > 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if member number exists
     * 
     * @param string $memberNo Member number
     * @param int|null $excludeId Member ID to exclude
     * @return bool
     */
    public function memberNumberExists(string $memberNo, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE member_no = ?";
        $params = [$memberNo];
        
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
     * @param string $email Email address
     * @param int|null $excludeId Member ID to exclude
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        // Email is encrypted, so we need to search by comparing encrypted values
        $encryptedEmail = $this->encryptor->encrypt($email);
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = ?";
        $params = [$encryptedEmail];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Check if national ID exists
     * 
     * @param string $nationalId National ID
     * @param int|null $excludeId Member ID to exclude
     * @return bool
     */
    public function nationalIdExists(string $nationalId, ?int $excludeId = null): bool
    {
        $encryptedId = $this->encryptor->encrypt($nationalId);
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE national_id = ?";
        $params = [$encryptedId];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get members with pagination and search
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
            // Search by name (encrypted)
            if (isset($filters['search']) && !empty($filters['search'])) {
                // Since name is encrypted, we need to search differently
                // We'll get all members and filter in PHP
                // This is a limitation of encrypted search
                // For better performance, consider using full-text search with decryption
                $where[] = "1=1"; // We'll filter in PHP
            }
            
            if (isset($filters['status']) && !empty($filters['status'])) {
                $where[] = "status = ?";
                $params[] = $filters['status'];
            }
            
            if (isset($filters['gender']) && !empty($filters['gender'])) {
                $where[] = "gender = ?";
                $params[] = $filters['gender'];
            }
            
            if (isset($filters['joining_date_from']) && !empty($filters['joining_date_from'])) {
                $where[] = "joining_date >= ?";
                $params[] = $filters['joining_date_from'];
            }
            
            if (isset($filters['joining_date_to']) && !empty($filters['joining_date_to'])) {
                $where[] = "joining_date <= ?";
                $params[] = $filters['joining_date_to'];
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT * FROM {$this->table} 
                {$whereClause} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
        $data = array_map([$this, 'decryptData'], $data);
        
        // Apply search filter in PHP (since data is encrypted)
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $data = array_filter($data, function($member) use ($searchTerm) {
                $searchable = strtolower(
                    ($member['full_name'] ?? '') . ' ' .
                    ($member['member_no'] ?? '') . ' ' .
                    ($member['email'] ?? '') . ' ' .
                    ($member['phone'] ?? '') . ' ' .
                    ($member['occupation'] ?? '')
                );
                return strpos($searchable, $searchTerm) !== false;
            });
            
            // Re-index and update total
            $data = array_values($data);
            $total = count($data);
        }
        
        return [
            'data' => $data,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Get member with decrypted data
     * 
     * @param int $id Member ID
     * @return array|null
     */
    public function getMember(int $id): ?array
    {
        $data = $this->find($id);
        if ($data) {
            return $this->decryptData($data);
        }
        return null;
    }

    /**
     * Get active members count
     * 
     * @return int
     */
    public function getActiveCount(): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE status = 'Active'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get members by status
     * 
     * @param string $status Member status
     * @return array
     */
    public function getByStatus(string $status): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = ?";
        $stmt = $this->db->query($sql, [$status]);
        $data = $stmt->fetchAll();
        return array_map([$this, 'decryptData'], $data);
    }

    /**
     * Get recent members
     * 
     * @param int $limit Number of members
     * @return array
     */
    public function getRecent(int $limit = 5): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->query($sql, [$limit]);
        $data = $stmt->fetchAll();
        return array_map([$this, 'decryptData'], $data);
    }

    /**
     * Get gender statistics
     * 
     * @return array
     */
    public function getGenderStats(): array
    {
        $sql = "SELECT gender, COUNT(*) as count FROM {$this->table} GROUP BY gender";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $stats = ['Male' => 0, 'Female' => 0, 'Other' => 0];
        foreach ($results as $row) {
            $stats[$row['gender']] = (int)$row['count'];
        }
        
        return $stats;
    }

    /**
     * Get members with savings summary
     * 
     * @param int $limit Number of members
     * @return array
     */
    public function getMembersWithSavings(int $limit = 10): array
    {
        $sql = "SELECT 
                    m.*,
                    COALESCE(SUM(s.amount), 0) as total_savings
                FROM {$this->table} m
                LEFT JOIN savings s ON m.id = s.member_id AND s.transaction_type = 'Deposit'
                WHERE m.status = 'Active'
                GROUP BY m.id
                ORDER BY total_savings DESC
                LIMIT ?";
        $stmt = $this->db->query($sql, [$limit]);
        $data = $stmt->fetchAll();
        return array_map([$this, 'decryptData'], $data);
    }

    /**
     * Search members by name (decrypted)
     * 
     * @param string $query Search query
     * @param int $limit Number of results
     * @return array
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        // Since name is encrypted, we need to get all active members
        // and filter in PHP
        $sql = "SELECT * FROM {$this->table} WHERE status = 'Active' ORDER BY created_at DESC";
        $stmt = $this->db->query($sql);
        $data = $stmt->fetchAll();
        
        // Decrypt and filter
        $results = [];
        $searchTerm = strtolower($query);
        
        foreach ($data as $row) {
            $decrypted = $this->decryptData($row);
            $fullName = strtolower($decrypted['full_name'] ?? '');
            
            if (strpos($fullName, $searchTerm) !== false) {
                $results[] = $decrypted;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        return $results;
    }
}