<?php
/**
 * Dividend Model
 * 
 * Handles dividend calculation, distribution, and tracking.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Helpers\Encryption;
use App\Helpers\DividendCalculator;

class Dividend extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'dividends';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'member_id',
        'year',
        'total_profit',
        'savings_amount',
        'savings_period',
        'interest_percentage',
        'interest_earned',
        'status',
        'payment_date',
        'receipt_no',
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
        'member_id' => 'required|integer',
        'year' => 'required|integer|min:2000|max:2100',
        'total_profit' => 'required|numeric|min:0',
        'savings_amount' => 'required|numeric|min:0',
        'savings_period' => 'required|integer|min:1|max:12',
        'interest_percentage' => 'required|numeric|min:0|max:100',
        'interest_earned' => 'required|numeric|min:0',
        'status' => 'in:Declared,Paid,Partially_Paid'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'member_id' => 'int',
        'year' => 'int',
        'total_profit' => 'float',
        'savings_amount' => 'float',
        'savings_period' => 'int',
        'interest_percentage' => 'float',
        'interest_earned' => 'float',
        'payment_date' => 'date'
    ];

    /**
     * @var DividendPayment Payment model
     */
    private DividendPayment $paymentModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->paymentModel = new DividendPayment();
    }

    /**
     * Calculate and distribute dividends for a year
     * 
     * @param int $year Financial year
     * @param float $totalProfit Total annual profit
     * @param float $interestPercentage Interest percentage to distribute
     * @param int $createdBy User ID
     * @return array
     * @throws \Exception
     */
    public function calculateAndDistribute(int $year, float $totalProfit, float $interestPercentage, int $createdBy): array
    {
        // Check if dividends already calculated for this year
        if ($this->isYearCalculated($year)) {
            throw new \Exception("Dividends for year {$year} have already been calculated.");
        }
        
        // Get all active members with their savings
        $members = $this->getMemberSavingsData($year);
        
        if (empty($members)) {
            throw new \Exception('No active members found with savings data.');
        }
        
        // Calculate total savings
        $totalSavings = array_sum(array_column($members, 'total_savings'));
        
        if ($totalSavings <= 0) {
            throw new \Exception('Total member savings is zero. Cannot distribute dividends.');
        }
        
        $results = [];
        $this->beginTransaction();
        
        try {
            foreach ($members as $member) {
                // Calculate individual share
                $share = DividendCalculator::calculateIndividualShare(
                    $totalProfit,
                    $totalSavings,
                    $member['total_savings'],
                    $interestPercentage
                );
                
                // Create dividend record
                $data = [
                    'member_id' => $member['id'],
                    'year' => $year,
                    'total_profit' => $totalProfit,
                    'savings_amount' => $member['total_savings'],
                    'savings_period' => 12, // Full year
                    'interest_percentage' => $interestPercentage,
                    'interest_earned' => $share,
                    'status' => 'Declared',
                    'created_by' => $createdBy
                ];
                
                $dividendId = $this->create($data);
                
                if ($dividendId) {
                    $results[] = [
                        'member_id' => $member['id'],
                        'member_name' => $member['full_name'],
                        'member_no' => $member['member_no'],
                        'savings' => $member['total_savings'],
                        'share' => $share,
                        'dividend_id' => $dividendId
                    ];
                }
            }
            
            $this->commit();
            
            // Log activity
            \App\Helpers\ActivityLogger::log(
                'CALCULATE',
                'dividends',
                null,
                "Dividends calculated for year {$year}. Total profit: {$totalProfit}, Members: " . count($results)
            );
            
            return $results;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get member savings data
     * 
     * @param int $year Financial year
     * @return array
     */
    private function getMemberSavingsData(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "SELECT 
                    m.id,
                    m.full_name,
                    m.member_no,
                    COALESCE(SUM(s.amount), 0) as total_savings
                FROM members m
                LEFT JOIN savings s ON m.id = s.member_id 
                    AND s.transaction_type = 'Deposit'
                    AND s.transaction_date BETWEEN ? AND ?
                WHERE m.status = 'Active'
                GROUP BY m.id
                HAVING total_savings > 0
                ORDER BY total_savings DESC";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate . ' 23:59:59']);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                $row['full_name'] = $encryptor->decrypt($row['full_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Check if dividends already calculated for a year
     * 
     * @param int $year Financial year
     * @return bool
     */
    public function isYearCalculated(int $year): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE year = ?";
        $stmt = $this->db->query($sql, [$year]);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Update dividend status based on payments
     * 
     * @param int $dividendId Dividend ID
     * @return bool
     */
    public function updateStatus(int $dividendId): bool
    {
        $dividend = $this->find($dividendId);
        if (!$dividend) {
            return false;
        }
        
        $totalPaid = $this->paymentModel->getTotalPaid($dividendId);
        
        if ($totalPaid >= $dividend['interest_earned']) {
            $status = 'Paid';
            $paymentDate = date('Y-m-d');
        } elseif ($totalPaid > 0) {
            $status = 'Partially_Paid';
            $paymentDate = null;
        } else {
            $status = 'Declared';
            $paymentDate = null;
        }
        
        $sql = "UPDATE {$this->table} 
                SET status = ?, payment_date = ? 
                WHERE id = ?";
        
        $stmt = $this->db->query($sql, [$status, $paymentDate, $dividendId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get dividends with member details
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Search filters
     * @return array
     */
    public function getDividendsWithDetails(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [];
        
        // Build WHERE clause
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "d.member_id = ?";
            $params[] = $filters['member_id'];
        }
        
        if (isset($filters['year']) && !empty($filters['year'])) {
            $where[] = "d.year = ?";
            $params[] = $filters['year'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "d.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(m.member_no LIKE ? OR m.full_name LIKE ?)";
            $params = array_merge($params, [$search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} d 
                     JOIN members m ON d.member_id = m.id 
                     {$whereClause}";
        $stmt = $this->db->query($countSql, $params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT d.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       (SELECT SUM(amount_paid) FROM dividend_payments WHERE dividend_id = d.id) as total_paid
                FROM {$this->table} d 
                JOIN members m ON d.member_id = m.id 
                {$whereClause} 
                ORDER BY d.year DESC, d.id DESC 
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
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['interest_earned'] - $row['total_paid'];
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
     * Get dividend with full details
     * 
     * @param int $dividendId Dividend ID
     * @return array|null
     */
    public function getDividendWithDetails(int $dividendId): ?array
    {
        $sql = "SELECT d.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       (SELECT SUM(amount_paid) FROM dividend_payments WHERE dividend_id = d.id) as total_paid
                FROM {$this->table} d 
                JOIN members m ON d.member_id = m.id 
                WHERE d.id = ?";
        
        $stmt = $this->db->query($sql, [$dividendId]);
        $data = $stmt->fetch();
        
        if ($data) {
            $encryptor = new Encryption();
            try {
                $data['member_name'] = $encryptor->decrypt($data['member_name']);
                if (!empty($data['remarks'])) {
                    $data['remarks'] = $encryptor->decrypt($data['remarks']);
                }
                $data['total_paid'] = (float)($data['total_paid'] ?? 0);
                $data['outstanding'] = $data['interest_earned'] - $data['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
            
            // Get payment history
            $data['payments'] = $this->paymentModel->getByDividend($dividendId);
        }
        
        return $data;
    }

    /**
     * Get dividend statistics
     * 
     * @param int|null $year Specific year
     * @return array
     */
    public function getStats(?int $year = null): array
    {
        $where = [];
        $params = [];
        
        if ($year) {
            $where[] = "year = ?";
            $params[] = $year;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT 
                    COUNT(*) as total_members,
                    SUM(interest_earned) as total_earned,
                    SUM(CASE WHEN status = 'Paid' THEN interest_earned ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status = 'Declared' THEN interest_earned ELSE 0 END) as declared_amount,
                    SUM(CASE WHEN status = 'Partially_Paid' THEN interest_earned ELSE 0 END) as partial_amount,
                    COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN status = 'Declared' THEN 1 END) as declared_count,
                    COUNT(CASE WHEN status = 'Partially_Paid' THEN 1 END) as partial_count
                FROM {$this->table} d
                {$whereClause}";
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        
        $declaredAmount = (float)($result['declared_amount'] ?? 0);
        $partialAmount = (float)($result['partial_amount'] ?? 0);
        $outstandingAmount = $declaredAmount + $partialAmount;
        
        return [
            'total_members' => (int)($result['total_members'] ?? 0),
            'total_earned' => (float)($result['total_earned'] ?? 0),
            'paid_amount' => (float)($result['paid_amount'] ?? 0),
            'declared_amount' => $declaredAmount,
            'partial_amount' => $partialAmount,
            'outstanding_amount' => $outstandingAmount,
            'paid_count' => (int)($result['paid_count'] ?? 0),
            'declared_count' => (int)($result['declared_count'] ?? 0),
            'partial_count' => (int)($result['partial_count'] ?? 0)
        ];
    }

    /**
     * Get member dividend summary
     * 
     * @param int $memberId Member ID
     * @return array
     */
    public function getMemberSummary(int $memberId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_years,
                    SUM(interest_earned) as total_earned,
                    SUM(CASE WHEN status = 'Paid' THEN interest_earned ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status IN ('Declared', 'Partially_Paid') THEN interest_earned ELSE 0 END) as outstanding_amount
                FROM {$this->table}
                WHERE member_id = ?";
        
        $stmt = $this->db->query($sql, [$memberId]);
        $result = $stmt->fetch();
        
        return [
            'total_years' => (int)($result['total_years'] ?? 0),
            'total_earned' => (float)($result['total_earned'] ?? 0),
            'paid_amount' => (float)($result['paid_amount'] ?? 0),
            'outstanding_amount' => (float)($result['outstanding_amount'] ?? 0)
        ];
    }

    /**
     * Get dividend report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getReportData(array $filters = []): array
    {
        $params = [];
        $where = [];
        
        if (isset($filters['year']) && !empty($filters['year'])) {
            $where[] = "d.year = ?";
            $params[] = $filters['year'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "d.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "d.member_id = ?";
            $params[] = $filters['member_id'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT d.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       (SELECT SUM(amount_paid) FROM dividend_payments WHERE dividend_id = d.id) as total_paid
                FROM {$this->table} d 
                JOIN members m ON d.member_id = m.id 
                {$whereClause} 
                ORDER BY d.year DESC, d.interest_earned DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        $encryptor = new Encryption();
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $encryptor->decrypt($row['member_name']);
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['interest_earned'] - $row['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get top dividend earners
     * 
     * @param int $year Financial year
     * @param int $limit Number of members
     * @return array
     */
    public function getTopEarners(int $year, int $limit = 10): array
    {
        $sql = "SELECT d.*, 
                       m.member_no, 
                       m.full_name as member_name
                FROM {$this->table} d 
                JOIN members m ON d.member_id = m.id 
                WHERE d.year = ?
                ORDER BY d.interest_earned DESC 
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$year, $limit]);
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
     * Get available years
     * 
     * @return array
     */
    public function getAvailableYears(): array
    {
        $sql = "SELECT DISTINCT year FROM {$this->table} ORDER BY year DESC";
        $stmt = $this->db->query($sql);
        $data = $stmt->fetchAll();
        return array_column($data, 'year');
    }

    /**
     * Get annual profit from loans for a year
     * 
     * @param int $year Financial year
     * @return float
     */
    public function getAnnualProfit(int $year): float
    {
        // Calculate profit from loan interest
        $sql = "SELECT SUM(interest_amount) as total_interest 
                FROM repayments 
                WHERE YEAR(payment_date) = ?";
        
        $stmt = $this->db->query($sql, [$year]);
        $result = $stmt->fetch();
        $loanInterest = (float)($result['total_interest'] ?? 0);
        
        // Add other income sources (can be expanded)
        $sql = "SELECT SUM(amount) as total_fines 
                FROM fine_payments 
                WHERE YEAR(payment_date) = ?";
        
        $stmt = $this->db->query($sql, [$year]);
        $result = $stmt->fetch();
        $fineIncome = (float)($result['total_fines'] ?? 0);
        
        return $loanInterest + $fineIncome;
    }
}