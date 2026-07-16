<?php
/**
 * Repayment Model
 * 
 * Handles all loan repayment operations including recording payments,
 * calculating balances, and updating loan status.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;
use App\Exceptions\DatabaseException;

class Repayment extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'repayments';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'loan_id',
        'member_id',
        'receipt_no',
        'amount',
        'principal_amount',
        'interest_amount',
        'balance_after',
        'payment_date',
        'payment_method',
        'reference_no',
        'remarks',
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
        'loan_id' => 'required|integer',
        'member_id' => 'required|integer',
        'amount' => 'required|numeric|min:0.01',
        'payment_date' => 'required|date',
        'payment_method' => 'required|in:Cash,Bank Transfer,Mobile Money,Cheque',
        'reference_no' => 'max:100',
        'remarks' => 'max:500'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'loan_id' => 'int',
        'member_id' => 'int',
        'amount' => 'float',
        'principal_amount' => 'float',
        'interest_amount' => 'float',
        'balance_after' => 'float',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * @var Loan Loan model instance
     */
    private Loan $loanModel;

    /**
     * @var LoanInstallment Installment model instance
     */
    private LoanInstallment $installmentModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->loanModel = new Loan();
        $this->installmentModel = new LoanInstallment();
    }

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
     * Create a new repayment
     * 
     * @param array $data Repayment data
     * @return int|bool
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Generate receipt number
        if (!isset($data['receipt_no']) || empty($data['receipt_no'])) {
            $data['receipt_no'] = $this->generateReceiptNumber();
        }

        // Set default payment date
        if (!isset($data['payment_date'])) {
            $data['payment_date'] = date('Y-m-d');
        }

        // Validate loan exists and is active
        $loan = $this->loanModel->find($data['loan_id']);
        if (!$loan) {
            throw new \Exception('Loan not found.');
        }

        if (!in_array($loan['status'], ['Approved', 'Active'])) {
            throw new \Exception('Repayments can only be made on approved or active loans.');
        }

        // Get outstanding balance
        $outstandingBalance = $this->getOutstandingBalance($data['loan_id']);
        
        if ($data['amount'] > $outstandingBalance) {
            throw new \Exception('Repayment amount exceeds outstanding balance.');
        }

        // Calculate principal and interest portions
        $remainingPrincipal = $loan['amount'] - $this->getTotalPrincipalPaid($data['loan_id']);
        $remainingInterest = $loan['total_repayable'] - $loan['amount'] - $this->getTotalInterestPaid($data['loan_id']);
        
        // Determine payment allocation
        if ($remainingInterest > 0) {
            // Pay interest first
            $interestPayment = min($data['amount'], $remainingInterest);
            $principalPayment = $data['amount'] - $interestPayment;
        } else {
            $interestPayment = 0;
            $principalPayment = $data['amount'];
        }

        $data['principal_amount'] = round($principalPayment, 2);
        $data['interest_amount'] = round($interestPayment, 2);
        $data['balance_after'] = round($outstandingBalance - $data['amount'], 2);

        // Validate and create
        $validatedData = $this->validate($data);
        $encryptedData = $this->encryptData($validatedData);

        $this->beginTransaction();

        try {
            // Create repayment
            $repaymentId = parent::create($encryptedData);

            if ($repaymentId) {
                // Update loan status
                $this->updateLoanStatus($data['loan_id']);
                
                // Update installment statuses
                $this->updateInstallments($data['loan_id']);

                $this->commit();
                return $repaymentId;
            }

            $this->rollback();
            return false;

        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Update a repayment
     * 
     * @param int $id Repayment ID
     * @param array $data Repayment data
     * @return bool
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        $current = $this->find($id);
        if (!$current) {
            throw new \Exception('Repayment not found.');
        }

        // If amount changed, recalculate
        if (isset($data['amount']) && $data['amount'] != $current['amount']) {
            $loan = $this->loanModel->find($current['loan_id']);
            if (!$loan) {
                throw new \Exception('Loan not found.');
            }

            // Get total paid excluding current repayment
            $totalPaid = $this->getTotalPaid($current['loan_id']) - $current['amount'];
            $newTotalPaid = $totalPaid + $data['amount'];
            
            if ($newTotalPaid > $loan['total_repayable']) {
                throw new \Exception('Total payments would exceed loan total.');
            }

            // Recalculate balances
            $balanceAfter = $loan['total_repayable'] - $newTotalPaid;
            $data['balance_after'] = round($balanceAfter, 2);

            // Recalculate principal and interest
            $remainingPrincipal = $loan['amount'] - $this->getTotalPrincipalPaid($current['loan_id']) + $current['principal_amount'];
            $remainingInterest = $loan['total_repayable'] - $loan['amount'] - $this->getTotalInterestPaid($current['loan_id']) + $current['interest_amount'];
            
            if ($remainingInterest > 0) {
                $interestPayment = min($data['amount'], $remainingInterest);
                $principalPayment = $data['amount'] - $interestPayment;
            } else {
                $interestPayment = 0;
                $principalPayment = $data['amount'];
            }

            $data['principal_amount'] = round($principalPayment, 2);
            $data['interest_amount'] = round($interestPayment, 2);
        }

        // Validate and update
        $validatedData = $this->validate($data, true);
        $encryptedData = $this->encryptData($validatedData);

        $this->beginTransaction();

        try {
            $result = parent::update($id, $encryptedData);

            if ($result) {
                // Update loan status
                $this->updateLoanStatus($current['loan_id']);
                
                // Update installment statuses
                $this->updateInstallments($current['loan_id']);

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
     * Delete a repayment
     * 
     * @param int $id Repayment ID
     * @return bool
     * @throws \Exception
     */
    public function delete(int $id): bool
    {
        $repayment = $this->find($id);
        if (!$repayment) {
            throw new \Exception('Repayment not found.');
        }

        $this->beginTransaction();

        try {
            $result = parent::delete($id);

            if ($result) {
                // Update loan status
                $this->updateLoanStatus($repayment['loan_id']);
                
                // Update installment statuses
                $this->updateInstallments($repayment['loan_id']);

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
     * Get outstanding balance for a loan
     * 
     * @param int $loanId Loan ID
     * @return float
     */
    public function getOutstandingBalance(int $loanId): float
    {
        $loan = $this->loanModel->find($loanId);
        if (!$loan) {
            return 0;
        }

        $totalPaid = $this->getTotalPaid($loanId);
        return round($loan['total_repayable'] - $totalPaid, 2);
    }

    /**
     * Get total paid for a loan
     * 
     * @param int $loanId Loan ID
     * @return float
     */
    public function getTotalPaid(int $loanId): float
    {
        $sql = "SELECT SUM(amount) as total FROM {$this->table} WHERE loan_id = ?";
        $stmt = $this->db->query($sql, [$loanId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get total principal paid for a loan
     * 
     * @param int $loanId Loan ID
     * @return float
     */
    public function getTotalPrincipalPaid(int $loanId): float
    {
        $sql = "SELECT SUM(principal_amount) as total FROM {$this->table} WHERE loan_id = ?";
        $stmt = $this->db->query($sql, [$loanId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get total interest paid for a loan
     * 
     * @param int $loanId Loan ID
     * @return float
     */
    public function getTotalInterestPaid(int $loanId): float
    {
        $sql = "SELECT SUM(interest_amount) as total FROM {$this->table} WHERE loan_id = ?";
        $stmt = $this->db->query($sql, [$loanId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Update loan status based on repayments
     * 
     * @param int $loanId Loan ID
     * @return bool
     */
    private function updateLoanStatus(int $loanId): bool
    {
        $loan = $this->loanModel->find($loanId);
        if (!$loan) {
            return false;
        }

        $outstandingBalance = $this->getOutstandingBalance($loanId);
        
        if ($outstandingBalance <= 0) {
            $status = 'Completed';
        } elseif ($loan['status'] === 'Approved') {
            $status = 'Active';
        } else {
            $status = $loan['status'];
        }

        if ($status !== $loan['status']) {
            $sql = "UPDATE loans SET status = ? WHERE id = ?";
            $stmt = $this->db->query($sql, [$status, $loanId]);
            return $stmt->rowCount() > 0;
        }

        return true;
    }

    /**
     * Update installment statuses
     * 
     * @param int $loanId Loan ID
     * @return bool
     */
    private function updateInstallments(int $loanId): bool
    {
        $installments = $this->installmentModel->getByLoan($loanId);
        $totalPaid = $this->getTotalPaid($loanId);
        
        $cumulativePaid = 0;
        foreach ($installments as $installment) {
            // Calculate payment allocation for this installment
            $remainingForInstallment = $installment['total_amount'] - $installment['paid_principal'] - $installment['paid_interest'];
            $availablePayment = max(0, min($totalPaid - $cumulativePaid, $remainingForInstallment));
            
            if ($availablePayment > 0) {
                // Allocate to principal and interest
                $remainingPrincipal = $installment['principal_amount'] - $installment['paid_principal'];
                $remainingInterest = $installment['interest_amount'] - $installment['paid_interest'];
                
                if ($remainingInterest > 0) {
                    $interestPayment = min($availablePayment, $remainingInterest);
                    $principalPayment = $availablePayment - $interestPayment;
                } else {
                    $interestPayment = 0;
                    $principalPayment = min($availablePayment, $remainingPrincipal);
                }
                
                // Update installment
                $sql = "UPDATE loan_installments 
                        SET paid_principal = paid_principal + ?,
                            paid_interest = paid_interest + ?,
                            balance_amount = balance_amount - ?,
                            status = CASE 
                                WHEN balance_amount - ? <= 0 THEN 'Paid'
                                WHEN paid_principal > 0 OR paid_interest > 0 THEN 'Partially_Paid'
                                ELSE status
                            END,
                            paid_date = CASE 
                                WHEN balance_amount - ? <= 0 THEN NOW()
                                ELSE paid_date
                            END
                        WHERE id = ?";
                
                $this->db->query($sql, [
                    $principalPayment,
                    $interestPayment,
                    $availablePayment,
                    $availablePayment,
                    $availablePayment,
                    $installment['id']
                ]);
            }
            
            $cumulativePaid += $installment['total_amount'];
        }
        
        return true;
    }

    /**
     * Get repayments with loan and member details
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Search filters
     * @return array
     */
    public function getRepaymentsWithDetails(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [];

        // Build WHERE clause
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "r.member_id = ?";
            $params[] = $filters['member_id'];
        }

        if (isset($filters['loan_id']) && !empty($filters['loan_id'])) {
            $where[] = "r.loan_id = ?";
            $params[] = $filters['loan_id'];
        }

        if (isset($filters['payment_method']) && !empty($filters['payment_method'])) {
            $where[] = "r.payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(r.payment_date) >= ?";
            $params[] = $filters['from_date'];
        }

        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(r.payment_date) <= ?";
            $params[] = $filters['to_date'];
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(m.member_no LIKE ? OR m.full_name LIKE ? OR l.loan_no LIKE ? OR r.receipt_no LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} r 
                     JOIN loans l ON r.loan_id = l.id 
                     JOIN members m ON r.member_id = m.id 
                     {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;

        // Get paginated results
        $sql = "SELECT r.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       l.loan_no,
                       l.amount as loan_amount,
                       u.full_name as recorded_by_name
                FROM {$this->table} r 
                JOIN loans l ON r.loan_id = l.id 
                JOIN members m ON r.member_id = m.id 
                JOIN users u ON r.created_by = u.id 
                {$whereClause} 
                ORDER BY r.payment_date DESC, r.id DESC 
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
     * Get repayment with full details
     * 
     * @param int $id Repayment ID
     * @return array|null
     */
    public function getRepaymentWithDetails(int $id): ?array
    {
        $sql = "SELECT r.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       l.loan_no,
                       l.amount as loan_amount,
                       l.total_repayable,
                       u.full_name as recorded_by_name
                FROM {$this->table} r 
                JOIN loans l ON r.loan_id = l.id 
                JOIN members m ON r.member_id = m.id 
                JOIN users u ON r.created_by = u.id 
                WHERE r.id = ?";

        $stmt = $this->db->query($sql, [$id]);
        $data = $stmt->fetch();

        if ($data) {
            $encryptor = new Encryption();
            try {
                $data['member_name'] = $encryptor->decrypt($data['member_name']);
                if (!empty($data['remarks'])) {
                    $data['remarks'] = $encryptor->decrypt($data['remarks']);
                }
            } catch (\Exception $e) {
                // Leave as is
            }
        }

        return $data;
    }

    /**
     * Get repayments by member
     * 
     * @param int $memberId Member ID
     * @param int $limit Number of repayments
     * @return array
     */
    public function getByMember(int $memberId, int $limit = 0): array
    {
        $sql = "SELECT r.*, l.loan_no 
                FROM {$this->table} r 
                JOIN loans l ON r.loan_id = l.id 
                WHERE r.member_id = ? 
                ORDER BY r.payment_date DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $stmt = $this->db->query($sql, [$memberId, $limit]);
        } else {
            $stmt = $this->db->query($sql, [$memberId]);
        }
        
        return $stmt->fetchAll();
    }

    /**
     * Get repayment statistics
     * 
     * @param string $period Date period (today, month, year)
     * @return array
     */
    public function getStats(string $period = 'today'): array
    {
        $dateCondition = $this->getDateCondition($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END) as cash_amount,
                    SUM(CASE WHEN payment_method = 'Bank Transfer' THEN amount ELSE 0 END) as bank_amount,
                    SUM(CASE WHEN payment_method = 'Mobile Money' THEN amount ELSE 0 END) as mobile_amount,
                    SUM(CASE WHEN payment_method = 'Cheque' THEN amount ELSE 0 END) as cheque_amount
                FROM {$this->table}
                WHERE {$dateCondition}";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return [
            'total_count' => (int)($result['total_count'] ?? 0),
            'total_amount' => (float)($result['total_amount'] ?? 0),
            'cash_amount' => (float)($result['cash_amount'] ?? 0),
            'bank_amount' => (float)($result['bank_amount'] ?? 0),
            'mobile_amount' => (float)($result['mobile_amount'] ?? 0),
            'cheque_amount' => (float)($result['cheque_amount'] ?? 0)
        ];
    }

    /**
     * Get date condition for queries
     * 
     * @param string $period Period
     * @return string
     */
    private function getDateCondition(string $period): string
    {
        switch ($period) {
            case 'today':
                return "DATE(payment_date) = CURDATE()";
            case 'week':
                return "YEARWEEK(payment_date) = YEARWEEK(CURDATE())";
            case 'month':
                return "MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
            case 'year':
                return "YEAR(payment_date) = YEAR(CURDATE())";
            default:
                return "1=1";
        }
    }

    /**
     * Get recent repayments for dashboard
     * 
     * @param int $limit Number of repayments
     * @return array
     */

    public function getRecentRepayments(int $limit = 5): array
{
    $sql = "SELECT
                r.*,
                l.loan_no,
                m.member_no,
                m.full_name AS member_name
            FROM {$this->table} r
            JOIN loans l
                ON r.loan_id = l.id
            JOIN members m
                ON l.member_id = m.id
            ORDER BY r.payment_date DESC
            LIMIT ?";

    $stmt = $this->db->query($sql, [$limit]);
    $data = $stmt->fetchAll();

    $encryptor = new Encryption();

    foreach ($data as &$row) {
        try {
            $row['member_name'] = $encryptor->decrypt($row['member_name']);
        } catch (\Exception $e) {
            // Keep original
        }
    }

    return $data;
}

    /**
     * Get repayment report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getReportData(array $filters = []): array
    {
        $params = [];
        $where = [];

        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(r.payment_date) >= ?";
            $params[] = $filters['from_date'];
        }

        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(r.payment_date) <= ?";
            $params[] = $filters['to_date'];
        }

        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "r.member_id = ?";
            $params[] = $filters['member_id'];
        }

        if (isset($filters['loan_id']) && !empty($filters['loan_id'])) {
            $where[] = "r.loan_id = ?";
            $params[] = $filters['loan_id'];
        }

        if (isset($filters['payment_method']) && !empty($filters['payment_method'])) {
            $where[] = "r.payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT r.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       l.loan_no,
                       u.full_name as recorded_by_name
                FROM {$this->table} r 
                JOIN members m ON r.member_id = m.id 
                JOIN loans l ON r.loan_id = l.id 
                JOIN users u ON r.created_by = u.id 
                {$whereClause} 
                ORDER BY r.payment_date DESC";
        
        $stmt = $this->db->query($sql, $params);
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
}