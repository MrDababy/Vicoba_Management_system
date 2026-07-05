<?php
/**
 * Loan Model
 * 
 * Handles all loan-related database operations including application,
 * approval, rejection, and status management.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;
use App\Helpers\LoanCalculator;

class Loan extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'loans';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'member_id',
        'loan_type_id',
        'loan_no',
        'amount',
        'interest_rate',
        'duration_months',
        'total_repayable',
        'application_date',
        'approval_date',
        'disbursement_date',
        'status',
        'approved_by',
        'remarks',
        'rejection_reason',
        'created_by'
    ];

    /**
     * @var array Encrypted fields
     */
    protected array $encrypted = [
        'remarks'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'member_id' => 'required|integer',
        'loan_type_id' => 'required|integer',
        'amount' => 'required|numeric|min:0.01',
        'interest_rate' => 'required|numeric|min:0|max:100',
        'duration_months' => 'required|integer|min:1|max:60',
        'application_date' => 'required|date',
        'status' => 'in:Pending,Approved,Rejected,Active,Completed,Defaulted'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'member_id' => 'int',
        'loan_type_id' => 'int',
        'amount' => 'float',
        'interest_rate' => 'float',
        'duration_months' => 'int',
        'total_repayable' => 'float',
        'application_date' => 'date',
        'approval_date' => 'date',
        'disbursement_date' => 'date'
    ];

    /**
     * @var LoanInstallment Installment model
     */
    private LoanInstallment $installmentModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->installmentModel = new LoanInstallment();
    }

    /**
     * Generate a unique loan number
     * 
     * @return string
     */
    public function generateLoanNumber(): string
    {
        $year = date('Y');
        $prefix = 'LN';
        
        $sql = "SELECT loan_no FROM {$this->table} 
                WHERE loan_no LIKE ? 
                ORDER BY id DESC LIMIT 1";
        
        $pattern = $prefix . $year . '%';
        $stmt = $this->db->query($sql, [$pattern]);
        $result = $stmt->fetch();
        
        if ($result) {
            $lastNumber = (int)substr($result['loan_no'], -4);
            $sequence = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }
        
        return $prefix . $year . $sequence;
    }

    /**
     * Create a new loan application
     * 
     * @param array $data Loan data
     * @return int|bool
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Generate loan number
        if (!isset($data['loan_no']) || empty($data['loan_no'])) {
            $data['loan_no'] = $this->generateLoanNumber();
        }
        
        // Calculate total repayable
        if (!isset($data['total_repayable']) || $data['total_repayable'] == 0) {
            $data['total_repayable'] = $this->calculateTotalRepayable(
                $data['amount'],
                $data['interest_rate'],
                $data['duration_months']
            );
        }
        
        // Set default status
        if (!isset($data['status'])) {
            $data['status'] = 'Pending';
        }
        
        // Validate member eligibility
        $this->validateMemberEligibility($data['member_id']);
        
        // Validate loan limits
        $this->validateLoanLimits($data['loan_type_id'], $data['amount']);
        
        // Validate and create
        $validatedData = $this->validate($data);
        $encryptedData = $this->encryptData($validatedData);
        
        $loanId = parent::create($encryptedData);
        
        if ($loanId) {
            // Generate installments
            $this->installmentModel->generateInstallments(
                $loanId,
                $data['amount'],
                $data['interest_rate'],
                $data['duration_months']
            );
        }
        
        return $loanId;
    }

    /**
     * Validate member eligibility for loan
     * 
     * @param int $memberId Member ID
     * @throws \Exception
     */
    private function validateMemberEligibility(int $memberId): void
    {
        // Check if member is active
        $memberModel = new Member();
        $member = $memberModel->getMember($memberId);
        
        if (!$member) {
            throw new \Exception('Member not found.');
        }
        
        if ($member['status'] !== 'Active') {
            throw new \Exception('Only active members can apply for loans.');
        }
        
        // Check if member has defaulted loans
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE member_id = ? AND status = 'Defaulted'";
        $stmt = $this->db->query($sql, [$memberId]);
        $result = $stmt->fetch();
        
        if (($result['count'] ?? 0) > 0) {
            throw new \Exception('Member has defaulted loans and is not eligible for new loans.');
        }
        
        // Check if member has pending loans
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE member_id = ? AND status IN ('Pending', 'Active')";
        $stmt = $this->db->query($sql, [$memberId]);
        $result = $stmt->fetch();
        
        if (($result['count'] ?? 0) >= 3) {
            throw new \Exception('Member has too many active/pending loans.');
        }
    }

    /**
     * Validate loan limits
     * 
     * @param int $loanTypeId Loan type ID
     * @param float $amount Loan amount
     * @throws \Exception
     */
    private function validateLoanLimits(int $loanTypeId, float $amount): void
    {
        $loanTypeModel = new LoanType();
        $loanType = $loanTypeModel->find($loanTypeId);
        
        if (!$loanType) {
            throw new \Exception('Invalid loan type.');
        }
        
        if ($amount < $loanType['min_amount']) {
            throw new \Exception("Minimum loan amount for this type is " . number_format($loanType['min_amount'], 2));
        }
        
        if ($amount > $loanType['max_amount']) {
            throw new \Exception("Maximum loan amount for this type is " . number_format($loanType['max_amount'], 2));
        }
    }

    /**
     * Calculate total repayable amount
     * 
     * @param float $principal Principal amount
     * @param float $interestRate Annual interest rate
     * @param int $durationMonths Duration in months
     * @return float
     */
    private function calculateTotalRepayable(float $principal, float $interestRate, int $durationMonths): float
    {
        $monthlyRate = ($interestRate / 100) / 12;
        $months = $durationMonths;
        
        if ($monthlyRate == 0) {
            return $principal;
        }
        
        $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        $totalRepayable = $monthlyPayment * $months;
        
        return round($totalRepayable, 2);
    }

    /**
     * Approve a loan
     * 
     * @param int $loanId Loan ID
     * @param int $approvedBy User ID of approver
     * @param string $remarks Approval remarks
     * @return bool
     * @throws \Exception
     */
    public function approve(int $loanId, int $approvedBy, string $remarks = ''): bool
    {
        $loan = $this->find($loanId);
        if (!$loan) {
            throw new \Exception('Loan not found.');
        }
        
        if ($loan['status'] !== 'Pending') {
            throw new \Exception('Only pending loans can be approved.');
        }
        
        $this->beginTransaction();
        
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = 'Approved',
                        approved_by = ?,
                        approval_date = NOW(),
                        remarks = ?,
                        disbursement_date = NOW()
                    WHERE id = ?";
            
            $encryptedRemarks = $this->encryptor->encrypt($remarks);
            $stmt = $this->db->query($sql, [$approvedBy, $encryptedRemarks, $loanId]);
            
            if ($stmt->rowCount() > 0) {
                // Update loan status to Active (since it's now disbursed)
                $sql = "UPDATE {$this->table} SET status = 'Active' WHERE id = ?";
                $this->db->query($sql, [$loanId]);
                
                $this->commit();
                return true;
            }
            
            $this->rollback();
            return false;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Reject a loan
     * 
     * @param int $loanId Loan ID
     * @param string $reason Rejection reason
     * @return bool
     * @throws \Exception
     */
    public function reject(int $loanId, string $reason): bool
    {
        $loan = $this->find($loanId);
        if (!$loan) {
            throw new \Exception('Loan not found.');
        }
        
        if ($loan['status'] !== 'Pending') {
            throw new \Exception('Only pending loans can be rejected.');
        }
        
        $sql = "UPDATE {$this->table} 
                SET status = 'Rejected',
                    rejection_reason = ?,
                    approval_date = NOW()
                WHERE id = ?";
        
        $encryptedReason = $this->encryptor->encrypt($reason);
        $stmt = $this->db->query($sql, [$encryptedReason, $loanId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark loan as defaulted
     * 
     * @param int $loanId Loan ID
     * @return bool
     */
    public function markDefaulted(int $loanId): bool
    {
        $sql = "UPDATE {$this->table} SET status = 'Defaulted' WHERE id = ?";
        $stmt = $this->db->query($sql, [$loanId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get loans with member and type details
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Search filters
     * @return array
     */
    public function getLoansWithDetails(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [];
        
        // Build WHERE clause
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "l.member_id = ?";
            $params[] = $filters['member_id'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['loan_type_id']) && !empty($filters['loan_type_id'])) {
            $where[] = "l.loan_type_id = ?";
            $params[] = $filters['loan_type_id'];
        }
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(l.application_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(l.application_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(l.loan_no LIKE ? OR m.member_no LIKE ?)";
            $params = array_merge($params, [$search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} l 
                     JOIN members m ON l.member_id = m.id 
                     {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT l.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       lt.name as loan_type_name
                FROM {$this->table} l 
                JOIN members m ON l.member_id = m.id 
                JOIN loan_types lt ON l.loan_type_id = lt.id 
                {$whereClause} 
                ORDER BY l.created_at DESC 
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
                if (!empty($row['remarks'])) {
                    $row['remarks'] = $encryptor->decrypt($row['remarks']);
                }
                if (!empty($row['rejection_reason'])) {
                    $row['rejection_reason'] = $encryptor->decrypt($row['rejection_reason']);
                }
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
     * Get loan with full details including installments
     * 
     * @param int $loanId Loan ID
     * @return array|null
     */
    public function getLoanWithDetails(int $loanId): ?array
    {
        $sql = "SELECT l.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       lt.name as loan_type_name,
                       u.full_name as approved_by_name
                FROM {$this->table} l 
                JOIN members m ON l.member_id = m.id 
                JOIN loan_types lt ON l.loan_type_id = lt.id 
                LEFT JOIN users u ON l.approved_by = u.id 
                WHERE l.id = ?";
        
        $stmt = $this->db->query($sql, [$loanId]);
        $data = $stmt->fetch();
        
        if ($data) {
            // Decrypt sensitive data
            $encryptor = new Encryption();
            try {
                $data['member_name'] = $encryptor->decrypt($data['member_name']);
                if (!empty($data['remarks'])) {
                    $data['remarks'] = $encryptor->decrypt($data['remarks']);
                }
                if (!empty($data['rejection_reason'])) {
                    $data['rejection_reason'] = $encryptor->decrypt($data['rejection_reason']);
                }
            } catch (\Exception $e) {
                // Leave as is
            }
            
            // Get installments
            $data['installments'] = $this->installmentModel->getByLoan($loanId);
            $data['installment_summary'] = $this->installmentModel->getLoanSummary($loanId);
        }
        
        return $data;
    }

    /**
     * Get loan statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_loans,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'Defaulted' THEN 1 ELSE 0 END) as defaulted,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN status IN ('Approved', 'Active', 'Completed') THEN amount ELSE 0 END) as disbursed_amount
                FROM {$this->table}";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return [
            'total_loans' => (int)($result['total_loans'] ?? 0),
            'pending' => (int)($result['pending'] ?? 0),
            'approved' => (int)($result['approved'] ?? 0),
            'rejected' => (int)($result['rejected'] ?? 0),
            'active' => (int)($result['active'] ?? 0),
            'completed' => (int)($result['completed'] ?? 0),
            'defaulted' => (int)($result['defaulted'] ?? 0),
            'total_amount' => (float)($result['total_amount'] ?? 0),
            'disbursed_amount' => (float)($result['disbursed_amount'] ?? 0)
        ];
    }

    /**
     * Get loans by member
     * 
     * @param int $memberId Member ID
     * @return array
     */
    public function getByMember(int $memberId): array
    {
        $sql = "SELECT l.*, lt.name as loan_type_name 
                FROM {$this->table} l 
                JOIN loan_types lt ON l.loan_type_id = lt.id 
                WHERE l.member_id = ? 
                ORDER BY l.created_at DESC";
        
        $stmt = $this->db->query($sql, [$memberId]);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                if (!empty($row['remarks'])) {
                    $row['remarks'] = $encryptor->decrypt($row['remarks']);
                }
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get loan status counts by member
     * 
     * @param int $memberId Member ID
     * @return array
     */
    public function getStatusCounts(int $memberId): array
    {
        $sql = "SELECT status, COUNT(*) as count 
                FROM {$this->table} 
                WHERE member_id = ? 
                GROUP BY status";
        
        $stmt = $this->db->query($sql, [$memberId]);
        $results = $stmt->fetchAll();
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }
        
        return $counts;
    }

    /**
     * Get loan report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getReportData(array $filters = []): array
    {
        $params = [];
        $where = [];
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(l.application_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(l.application_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['loan_type_id']) && !empty($filters['loan_type_id'])) {
            $where[] = "l.loan_type_id = ?";
            $params[] = $filters['loan_type_id'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT l.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       lt.name as loan_type_name
                FROM {$this->table} l 
                JOIN members m ON l.member_id = m.id 
                JOIN loan_types lt ON l.loan_type_id = lt.id 
                {$whereClause} 
                ORDER BY l.application_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
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
}