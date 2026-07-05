<?php
/**
 * Fine Model
 * 
 * Handles all fine-related operations including imposing fines,
 * tracking payments, and managing fine status.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Helpers\Encryption;

class Fine extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'fines';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'member_id',
        'fine_type_id',
        'amount',
        'fine_date',
        'due_date',
        'status',
        'description',
        'waiver_reason',
        'waived_by',
        'created_by'
    ];

    /**
     * @var array Encrypted fields
     */
    protected array $encrypted = [
        'description',
        'waiver_reason'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'member_id' => 'required|integer',
        'fine_type_id' => 'required|integer',
        'amount' => 'required|numeric|min:0.01',
        'fine_date' => 'required|date',
        'due_date' => 'required|date|after_or_equal:fine_date',
        'status' => 'in:Pending,Paid,Partially_Paid,Waived',
        'description' => 'max:500'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'member_id' => 'int',
        'fine_type_id' => 'int',
        'amount' => 'float',
        'fine_date' => 'date',
        'due_date' => 'date'
    ];

    /**
     * @var FinePayment Payment model
     */
    private FinePayment $paymentModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->paymentModel = new FinePayment();
    }

    /**
     * Create a new fine
     * 
     * @param array $data Fine data
     * @return int|bool
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Set default status
        if (!isset($data['status'])) {
            $data['status'] = 'Pending';
        }
        
        // Set due date if not provided (default 14 days from fine date)
        if (!isset($data['due_date']) && isset($data['fine_date'])) {
            $data['due_date'] = date('Y-m-d', strtotime($data['fine_date'] . ' +14 days'));
        }
        
        // Validate member exists
        $memberModel = new Member();
        $member = $memberModel->getMember($data['member_id']);
        if (!$member) {
            throw new \Exception('Member not found.');
        }
        
        // Validate fine type exists
        $fineTypeModel = new FineType();
        $fineType = $fineTypeModel->find($data['fine_type_id']);
        if (!$fineType) {
            throw new \Exception('Fine type not found.');
        }
        
        // If amount is not set, use default from fine type
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            $data['amount'] = $fineType['default_amount'];
        }
        
        // Validate and create
        $validatedData = $this->validate($data);
        $encryptedData = $this->encryptData($validatedData);
        
        return parent::create($encryptedData);
    }

    /**
     * Update a fine
     * 
     * @param int $id Fine ID
     * @param array $data Fine data
     * @return bool
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        $fine = $this->find($id);
        if (!$fine) {
            throw new \Exception('Fine not found.');
        }
        
        // If fine is paid or waived, prevent changes
        if ($fine['status'] === 'Paid' || $fine['status'] === 'Waived') {
            throw new \Exception('Cannot edit a paid or waived fine.');
        }
        
        // Validate and update
        $validatedData = $this->validate($data, true);
        $encryptedData = $this->encryptData($validatedData);
        
        return parent::update($id, $encryptedData);
    }

    /**
     * Waive a fine
     * 
     * @param int $fineId Fine ID
     * @param int $waivedBy User ID
     * @param string $reason Waiver reason
     * @return bool
     * @throws \Exception
     */
    public function waive(int $fineId, int $waivedBy, string $reason): bool
    {
        $fine = $this->find($fineId);
        if (!$fine) {
            throw new \Exception('Fine not found.');
        }
        
        if ($fine['status'] === 'Paid') {
            throw new \Exception('Cannot waive a paid fine.');
        }
        
        if ($fine['status'] === 'Waived') {
            throw new \Exception('Fine is already waived.');
        }
        
        $encryptedReason = $this->encryptor->encrypt($reason);
        
        $sql = "UPDATE {$this->table} 
                SET status = 'Waived',
                    waiver_reason = ?,
                    waived_by = ?
                WHERE id = ?";
        
        $stmt = $this->db->query($sql, [$encryptedReason, $waivedBy, $fineId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update fine status based on payments
     * 
     * @param int $fineId Fine ID
     * @return bool
     */
    public function updateStatus(int $fineId): bool
    {
        $fine = $this->find($fineId);
        if (!$fine) {
            return false;
        }
        
        $totalPaid = $this->paymentModel->getTotalPaid($fineId);
        
        if ($totalPaid >= $fine['amount']) {
            $status = 'Paid';
        } elseif ($totalPaid > 0) {
            $status = 'Partially_Paid';
        } else {
            $status = 'Pending';
        }
        
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->db->query($sql, [$status, $fineId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get fines with member and type details
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Search filters
     * @return array
     */
    public function getFinesWithDetails(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [];
        
        // Build WHERE clause
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "f.member_id = ?";
            $params[] = $filters['member_id'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['fine_type_id']) && !empty($filters['fine_type_id'])) {
            $where[] = "f.fine_type_id = ?";
            $params[] = $filters['fine_type_id'];
        }
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(f.fine_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(f.fine_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(m.member_no LIKE ? OR ft.name LIKE ?)";
            $params = array_merge($params, [$search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} f 
                     JOIN members m ON f.member_id = m.id 
                     JOIN fine_types ft ON f.fine_type_id = ft.id 
                     {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT f.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       ft.name as fine_type_name,
                       (SELECT SUM(amount_paid) FROM fine_payments WHERE fine_id = f.id) as total_paid
                FROM {$this->table} f 
                JOIN members m ON f.member_id = m.id 
                JOIN fine_types ft ON f.fine_type_id = ft.id 
                {$whereClause} 
                ORDER BY f.fine_date DESC, f.id DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $encryptor->decrypt($row['member_name']);
                if (!empty($row['description'])) {
                    $row['description'] = $encryptor->decrypt($row['description']);
                }
                if (!empty($row['waiver_reason'])) {
                    $row['waiver_reason'] = $encryptor->decrypt($row['waiver_reason']);
                }
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['amount'] - $row['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
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
     * Get fine with full details
     * 
     * @param int $fineId Fine ID
     * @return array|null
     */
    public function getFineWithDetails(int $fineId): ?array
    {
        $sql = "SELECT f.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       ft.name as fine_type_name,
                       ft.default_amount,
                       ft.is_percentage,
                       (SELECT SUM(amount_paid) FROM fine_payments WHERE fine_id = f.id) as total_paid
                FROM {$this->table} f 
                JOIN members m ON f.member_id = m.id 
                JOIN fine_types ft ON f.fine_type_id = ft.id 
                WHERE f.id = ?";
        
        $stmt = $this->db->query($sql, [$fineId]);
        $data = $stmt->fetch();
        
        if ($data) {
            $encryptor = new Encryption();
            try {
                $data['member_name'] = $encryptor->decrypt($data['member_name']);
                if (!empty($data['description'])) {
                    $data['description'] = $encryptor->decrypt($data['description']);
                }
                if (!empty($data['waiver_reason'])) {
                    $data['waiver_reason'] = $encryptor->decrypt($data['waiver_reason']);
                }
                $data['total_paid'] = (float)($data['total_paid'] ?? 0);
                $data['outstanding'] = $data['amount'] - $data['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
            
            // Get payment history
            $data['payments'] = $this->paymentModel->getByFine($fineId);
        }
        
        return $data;
    }

    /**
     * Get fines for a member
     * 
     * @param int $memberId Member ID
     * @param string $status Filter by status
     * @return array
     */
    public function getByMember(int $memberId, string $status = null): array
    {
        $sql = "SELECT f.*, 
                       ft.name as fine_type_name,
                       (SELECT SUM(amount_paid) FROM fine_payments WHERE fine_id = f.id) as total_paid
                FROM {$this->table} f 
                JOIN fine_types ft ON f.fine_type_id = ft.id 
                WHERE f.member_id = ?";
        
        $params = [$memberId];
        
        if ($status) {
            $sql .= " AND f.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY f.fine_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                if (!empty($row['description'])) {
                    $row['description'] = $encryptor->decrypt($row['description']);
                }
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['amount'] - $row['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get fine statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_fines,
                    SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN status = 'Partially_Paid' THEN amount ELSE 0 END) as partial_amount,
                    SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status = 'Waived' THEN amount ELSE 0 END) as waived_amount,
                    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = 'Partially_Paid' THEN 1 END) as partial_count,
                    COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN status = 'Waived' THEN 1 END) as waived_count
                FROM {$this->table}";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        $pendingAmount = (float)($result['pending_amount'] ?? 0);
        $partialAmount = (float)($result['partial_amount'] ?? 0);
        $outstandingAmount = $pendingAmount + $partialAmount;
        
        return [
            'total_fines' => (int)($result['total_fines'] ?? 0),
            'pending_amount' => $pendingAmount,
            'partial_amount' => $partialAmount,
            'paid_amount' => (float)($result['paid_amount'] ?? 0),
            'waived_amount' => (float)($result['waived_amount'] ?? 0),
            'outstanding_amount' => $outstandingAmount,
            'pending_count' => (int)($result['pending_count'] ?? 0),
            'partial_count' => (int)($result['partial_count'] ?? 0),
            'paid_count' => (int)($result['paid_count'] ?? 0),
            'waived_count' => (int)($result['waived_count'] ?? 0)
        ];
    }

    /**
     * Get member fine summary
     * 
     * @param int $memberId Member ID
     * @return array
     */
    public function getMemberSummary(int $memberId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('Pending', 'Partially_Paid') THEN amount ELSE 0 END) as outstanding_amount,
                    SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status = 'Waived' THEN amount ELSE 0 END) as waived_amount,
                    COUNT(CASE WHEN status IN ('Pending', 'Partially_Paid') THEN 1 END) as outstanding_count,
                    COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_count
                FROM {$this->table}
                WHERE member_id = ?";
        
        $stmt = $this->db->query($sql, [$memberId]);
        $result = $stmt->fetch();
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'outstanding_amount' => (float)($result['outstanding_amount'] ?? 0),
            'paid_amount' => (float)($result['paid_amount'] ?? 0),
            'waived_amount' => (float)($result['waived_amount'] ?? 0),
            'outstanding_count' => (int)($result['outstanding_count'] ?? 0),
            'paid_count' => (int)($result['paid_count'] ?? 0)
        ];
    }

    /**
     * Get recent fines for dashboard
     * 
     * @param int $limit Number of fines
     * @return array
     */
    public function getRecentFines(int $limit = 5): array
    {
        $sql = "SELECT f.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       ft.name as fine_type_name
                FROM {$this->table} f 
                JOIN members m ON f.member_id = m.id 
                JOIN fine_types ft ON f.fine_type_id = ft.id 
                ORDER BY f.fine_date DESC, f.id DESC 
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
     * Get overdue fines
     * 
     * @param int $days Number of days overdue
     * @return array
     */
    public function getOverdueFines(int $days = 0): array
    {
        $sql = "SELECT f.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       ft.name as fine_type_name
                FROM {$this->table} f 
                JOIN members m ON f.member_id = m.id 
                JOIN fine_types ft ON f.fine_type_id = ft.id 
                WHERE f.status IN ('Pending', 'Partially_Paid')
                    AND f.due_date < CURDATE()";
        
        if ($days > 0) {
            $sql .= " AND DATEDIFF(CURDATE(), f.due_date) >= ?";
            $params = [$days];
        }
        
        $sql .= " ORDER BY f.due_date ASC";
        
        $stmt = $this->db->query($sql, $params ?? []);
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
     * Get monthly fine statistics
     * 
     * @param int $months Number of months
     * @return array
     */
    public function getMonthlyStats(int $months = 12): array
    {
        $data = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $sql = "SELECT 
                        COUNT(*) as count,
                        SUM(amount) as total_amount,
                        SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END) as paid_amount
                    FROM {$this->table}
                    WHERE fine_date BETWEEN ? AND ?";
            
            $stmt = $this->db->query($sql, [$startDate, $endDate . ' 23:59:59']);
            $result = $stmt->fetch();
            
            $data[$month] = [
                'count' => (int)($result['count'] ?? 0),
                'total_amount' => (float)($result['total_amount'] ?? 0),
                'paid_amount' => (float)($result['paid_amount'] ?? 0)
            ];
        }
        
        return $data;
    }

    /**
     * Get fine report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getReportData(array $filters = []): array
    {
        $params = [];
        $where = [];
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(f.fine_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(f.fine_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['fine_type_id']) && !empty($filters['fine_type_id'])) {
            $where[] = "f.fine_type_id = ?";
            $params[] = $filters['fine_type_id'];
        }
        
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "f.member_id = ?";
            $params[] = $filters['member_id'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT f.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       ft.name as fine_type_name,
                       (SELECT SUM(amount_paid) FROM fine_payments WHERE fine_id = f.id) as total_paid
                FROM {$this->table} f 
                JOIN members m ON f.member_id = m.id 
                JOIN fine_types ft ON f.fine_type_id = ft.id 
                {$whereClause} 
                ORDER BY f.fine_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $encryptor->decrypt($row['member_name']);
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['amount'] - $row['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }
}