<?php
/**
 * Savings Model
 * 
 * Handles all savings-related database operations including deposits,
 * withdrawals, balance calculations, and history management.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;
use App\Exceptions\DatabaseException;

class Savings extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'savings';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'member_id',
        'transaction_type',
        'amount',
        'balance_after',
        'transaction_date',
        'description',
        'receipt_no',
        'transaction_mode',
        'reference_no',
        'created_by'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'member_id' => 'required|integer',
        'transaction_type' => 'required|in:Deposit,Withdrawal',
        'amount' => 'required|numeric|min:0.01',
        'transaction_date' => 'required|date',
        'description' => 'max:500',
        'receipt_no' => 'required|max:50',
        'transaction_mode' => 'required|in:Cash,Bank Transfer,Mobile Money,Cheque',
        'reference_no' => 'max:100'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'member_id' => 'int',
        'amount' => 'float',
        'balance_after' => 'float',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime'
    ];

    /**
     * Generate a unique receipt number
     * 
     * @return string
     */
    public function generateReceiptNumber(): string
    {
        $prefix = 'RCP';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        return $prefix . $date . $random;
    }

    /**
     * Create a new savings transaction
     * 
     * @param array $data Transaction data
     * @return int|bool
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Generate receipt number if not provided
        if (!isset($data['receipt_no']) || empty($data['receipt_no'])) {
            $data['receipt_no'] = $this->generateReceiptNumber();
        }
        
        // Set default transaction date
        if (!isset($data['transaction_date'])) {
            $data['transaction_date'] = date('Y-m-d H:i:s');
        }
        
        // Calculate running balance
        $memberId = $data['member_id'];
        $currentBalance = $this->getCurrentBalance($memberId);
        
        if ($data['transaction_type'] === 'Deposit') {
            $data['balance_after'] = $currentBalance + $data['amount'];
        } else {
            // Withdrawal
            if ($data['amount'] > $currentBalance) {
                throw new \Exception('Insufficient balance. Available: ' . number_format($currentBalance, 2));
            }
            $data['balance_after'] = $currentBalance - $data['amount'];
        }
        
        // Validate and create
        $validatedData = $this->validate($data);
        
        // Check if receipt number already exists
        if ($this->receiptExists($validatedData['receipt_no'])) {
            throw new \Exception('Receipt number already exists. Please try again.');
        }
        
        return parent::create($validatedData);
    }

    /**
     * Update a savings transaction
     * 
     * @param int $id Transaction ID
     * @param array $data Transaction data
     * @return bool
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        // Get current transaction
        $current = $this->find($id);
        if (!$current) {
            throw new \Exception('Transaction not found.');
        }
        
        // If amount or type changed, recalculate balance
        $amountChanged = isset($data['amount']) && $data['amount'] != $current['amount'];
        $typeChanged = isset($data['transaction_type']) && $data['transaction_type'] != $current['transaction_type'];
        
        if ($amountChanged || $typeChanged) {
            // Get member's current balance
            $memberId = $current['member_id'];
            $currentBalance = $this->getCurrentBalance($memberId);
            
            // Reverse the current transaction's effect
            if ($current['transaction_type'] === 'Deposit') {
                $currentBalance -= $current['amount'];
            } else {
                $currentBalance += $current['amount'];
            }
            
            // Apply new transaction
            $newType = $data['transaction_type'] ?? $current['transaction_type'];
            $newAmount = $data['amount'] ?? $current['amount'];
            
            if ($newType === 'Deposit') {
                $data['balance_after'] = $currentBalance + $newAmount;
            } else {
                if ($newAmount > $currentBalance) {
                    throw new \Exception('Insufficient balance. Available: ' . number_format($currentBalance, 2));
                }
                $data['balance_after'] = $currentBalance - $newAmount;
            }
        }
        
        // Validate and update
        $validatedData = $this->validate($data, true);
        
        return parent::update($id, $validatedData);
    }

    /**
     * Get current balance for a member
     * 
     * @param int $memberId Member ID
     * @return float
     */
    public function getCurrentBalance(int $memberId): float
    {
        $sql = "SELECT balance_after FROM {$this->table} 
                WHERE member_id = ? 
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1";
        
        $stmt = $this->db->query($sql, [$memberId]);
        $result = $stmt->fetch();
        
        return (float)($result['balance_after'] ?? 0);
    }

    /**
     * Get all transactions for a member with pagination
     * 
     * @param int $memberId Member ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Additional filters
     * @return array
     */
    public function getMemberTransactions(int $memberId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [$memberId];
        $where = ["member_id = ?"];
        
        // Add filters
        if (isset($filters['transaction_type']) && !empty($filters['transaction_type'])) {
            $where[] = "transaction_type = ?";
            $params[] = $filters['transaction_type'];
        }
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(transaction_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(transaction_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['transaction_mode']) && !empty($filters['transaction_mode'])) {
            $where[] = "transaction_mode = ?";
            $params[] = $filters['transaction_mode'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT * FROM {$this->table} 
                WHERE {$whereClause} 
                ORDER BY transaction_date DESC, id DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
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
     * Get monthly summary for a member
     * 
     * @param int $memberId Member ID
     * @param string $yearMonth Year-month (Y-m)
     * @return array
     */
    public function getMonthlySummary(int $memberId, string $yearMonth): array
    {
        $sql = "SELECT 
                    transaction_type,
                    SUM(amount) as total_amount,
                    COUNT(*) as transaction_count
                FROM {$this->table}
                WHERE member_id = ?
                    AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
                GROUP BY transaction_type";
        
        $stmt = $this->db->query($sql, [$memberId, $yearMonth]);
        $results = $stmt->fetchAll();
        
        $summary = [
            'total_deposits' => 0,
            'deposit_count' => 0,
            'total_withdrawals' => 0,
            'withdrawal_count' => 0,
            'net_change' => 0
        ];
        
        foreach ($results as $row) {
            if ($row['transaction_type'] === 'Deposit') {
                $summary['total_deposits'] = (float)$row['total_amount'];
                $summary['deposit_count'] = (int)$row['transaction_count'];
            } else {
                $summary['total_withdrawals'] = (float)$row['total_amount'];
                $summary['withdrawal_count'] = (int)$row['transaction_count'];
            }
        }
        
        $summary['net_change'] = $summary['total_deposits'] - $summary['total_withdrawals'];
        
        // Get starting balance for the month
        $sql = "SELECT balance_after FROM {$this->table} 
                WHERE member_id = ? 
                    AND transaction_date < ? 
                ORDER BY transaction_date DESC 
                LIMIT 1";
        
        $startDate = $yearMonth . '-01';
        $stmt = $this->db->query($sql, [$memberId, $startDate]);
        $result = $stmt->fetch();
        $summary['starting_balance'] = (float)($result['balance_after'] ?? 0);
        
        // Get ending balance
        $summary['ending_balance'] = $summary['starting_balance'] + $summary['net_change'];
        
        return $summary;
    }

    /**
     * Get all monthly summaries for a member
     * 
     * @param int $memberId Member ID
     * @param int $months Number of months
     * @return array
     */
    public function getMonthlySummaries(int $memberId, int $months = 12): array
    {
        $summaries = [];
        
        for ($i = 0; $i < $months; $i++) {
            $yearMonth = date('Y-m', strtotime("-$i months"));
            $summaries[$yearMonth] = $this->getMonthlySummary($memberId, $yearMonth);
        }
        
        return $summaries;
    }

    /**
     * Check if receipt number exists
     * 
     * @param string $receiptNo Receipt number
     * @param int|null $excludeId Transaction ID to exclude
     * @return bool
     */
    public function receiptExists(string $receiptNo, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE receipt_no = ?";
        $params = [$receiptNo];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get total savings for a member
     * 
     * @param int $memberId Member ID
     * @return float
     */
    public function getTotalSavings(int $memberId): float
    {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END), 0) - 
                    COALESCE(SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END), 0) 
                    as total 
                FROM {$this->table} 
                WHERE member_id = ?";
        
        $stmt = $this->db->query($sql, [$memberId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get all transactions with member info
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Filters
     * @return array
     */
    public function getTransactionsWithMembers(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [];
        
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "s.member_id = ?";
            $params[] = $filters['member_id'];
        }
        
        if (isset($filters['transaction_type']) && !empty($filters['transaction_type'])) {
            $where[] = "s.transaction_type = ?";
            $params[] = $filters['transaction_type'];
        }
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(s.transaction_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(s.transaction_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            // Since member name is encrypted, we need to handle this differently
            // We'll search by member number or transaction fields
            $search = '%' . $filters['search'] . '%';
            $where[] = "(m.member_no LIKE ? OR s.receipt_no LIKE ? OR s.reference_no LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} s 
                     JOIN members m ON s.member_id = m.id 
                     {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT s.*, m.member_no, m.full_name as member_name 
                FROM {$this->table} s 
                JOIN members m ON s.member_id = m.id 
                {$whereClause} 
                ORDER BY s.transaction_date DESC, s.id DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $encryptor->decrypt($row['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return [
            'data' => $data,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get total savings summary
     * 
     * @return array
     */
    public function getTotalSummary(): array
    {
        $sql = "SELECT 
                    SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END) as total_deposits,
                    SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
                    COUNT(*) as total_transactions
                FROM {$this->table}";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return [
            'total_deposits' => (float)($result['total_deposits'] ?? 0),
            'total_withdrawals' => (float)($result['total_withdrawals'] ?? 0),
            'total_transactions' => (int)($result['total_transactions'] ?? 0),
            'net_savings' => (float)($result['total_deposits'] ?? 0) - (float)($result['total_withdrawals'] ?? 0)
        ];
    }

    /**
     * Get daily savings summary
     * 
     * @param string $date Date (Y-m-d)
     * @return array
     */
    public function getDailySummary(string $date): array
    {
        $sql = "SELECT 
                    transaction_type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE DATE(transaction_date) = ?
                GROUP BY transaction_type";
        
        $stmt = $this->db->query($sql, [$date]);
        $results = $stmt->fetchAll();
        
        $summary = [
            'total_deposits' => 0,
            'deposit_count' => 0,
            'total_withdrawals' => 0,
            'withdrawal_count' => 0
        ];
        
        foreach ($results as $row) {
            if ($row['transaction_type'] === 'Deposit') {
                $summary['total_deposits'] = (float)$row['total_amount'];
                $summary['deposit_count'] = (int)$row['count'];
            } else {
                $summary['total_withdrawals'] = (float)$row['total_amount'];
                $summary['withdrawal_count'] = (int)$row['count'];
            }
        }
        
        return $summary;
    }

    /**
     * Get member's transaction for receipt
     * 
     * @param int $id Transaction ID
     * @return array|null
     */
    public function getForReceipt(int $id): ?array
    {
        $sql = "SELECT s.*, m.member_no, m.full_name as member_name 
                FROM {$this->table} s 
                JOIN members m ON s.member_id = m.id 
                WHERE s.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        $data = $stmt->fetch();
        
        if ($data) {
            $encryptor = new Encryption();
            try {
                $data['member_name'] = $encryptor->decrypt($data['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get recent savings transactions for dashboard
     * 
     * @param int $limit Number of transactions
     * @return array
     */
    public function getRecentSavings(int $limit = 5): array
    {
        $sql = "SELECT s.*, m.member_no, m.full_name as member_name 
                FROM {$this->table} s 
                JOIN members m ON s.member_id = m.id 
                ORDER BY s.transaction_date DESC, s.id DESC 
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$limit]);
        $data = $stmt->fetchAll();
        
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $encryptor->decrypt($row['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get monthly savings data for charts
     * 
     * @param int $months Number of months
     * @return array
     */
    public function getMonthlySavingsData(int $months = 12): array
    {
        $data = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $sql = "SELECT 
                        SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END) as deposits,
                        SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END) as withdrawals
                    FROM {$this->table}
                    WHERE transaction_date BETWEEN ? AND ?";
            
            $stmt = $this->db->query($sql, [$startDate, $endDate . ' 23:59:59']);
            $result = $stmt->fetch();
            
            $data[$month] = [
                'deposits' => (float)($result['deposits'] ?? 0),
                'withdrawals' => (float)($result['withdrawals'] ?? 0),
                'net' => (float)($result['deposits'] ?? 0) - (float)($result['withdrawals'] ?? 0)
            ];
        }
        
        return $data;
    }
}