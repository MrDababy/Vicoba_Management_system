<?php
/**
 * Report Model
 * 
 * Handles all report data aggregation and generation.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;

class ReportModel
{
    /**
     * @var Database Database instance
     */
    private Database $db;

    /**
     * @var Encryption Encryption instance
     */
    private Encryption $encryptor;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->encryptor = new Encryption();
    }

    /**
     * Get member report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getMemberReport(array $filters = []): array
    {
        $params = [];
        $where = [];
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['gender']) && !empty($filters['gender'])) {
            $where[] = "gender = ?";
            $params[] = $filters['gender'];
        }
        
        if (isset($filters['from_date']) && !empty($filters['from_date'])) {
            $where[] = "DATE(joining_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (isset($filters['to_date']) && !empty($filters['to_date'])) {
            $where[] = "DATE(joining_date) <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(member_no LIKE ? OR full_name LIKE ?)";
            $params = array_merge($params, [$search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT m.*,
                       (SELECT SUM(amount) FROM savings WHERE member_id = m.id AND transaction_type = 'Deposit') as total_deposits,
                       (SELECT SUM(amount) FROM savings WHERE member_id = m.id AND transaction_type = 'Withdrawal') as total_withdrawals,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id) as total_loans,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status = 'Active') as active_loans,
                       (SELECT SUM(amount) FROM fines WHERE member_id = m.id AND status IN ('Pending', 'Partially_Paid')) as outstanding_fines
                FROM members m
                {$whereClause}
                ORDER BY m.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt sensitive data
        foreach ($data as &$row) {
            try {
                $row['full_name'] = $this->encryptor->decrypt($row['full_name']);
                $row['national_id'] = $this->encryptor->decrypt($row['national_id']);
                $row['phone'] = $this->encryptor->decrypt($row['phone']);
                $row['email'] = $this->encryptor->decrypt($row['email']);
                if (!empty($row['address'])) {
                    $row['address'] = $this->encryptor->decrypt($row['address']);
                }
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get savings report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getSavingsReport(array $filters = []): array
    {
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
        
        if (isset($filters['transaction_mode']) && !empty($filters['transaction_mode'])) {
            $where[] = "s.transaction_mode = ?";
            $params[] = $filters['transaction_mode'];
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
            $search = '%' . $filters['search'] . '%';
            $where[] = "(m.member_no LIKE ? OR m.full_name LIKE ? OR s.receipt_no LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT s.*, 
                       m.member_no, 
                       m.full_name as member_name
                FROM savings s
                JOIN members m ON s.member_id = m.id
                {$whereClause}
                ORDER BY s.transaction_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get loan report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getLoanReport(array $filters = []): array
    {
        $params = [];
        $where = [];
        
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
            $where[] = "(m.member_no LIKE ? OR m.full_name LIKE ? OR l.loan_no LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT l.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       lt.name as loan_type_name,
                       (SELECT SUM(amount) FROM repayments WHERE loan_id = l.id) as total_repaid,
                       (SELECT SUM(interest_amount) FROM repayments WHERE loan_id = l.id) as total_interest_paid
                FROM loans l
                JOIN members m ON l.member_id = m.id
                JOIN loan_types lt ON l.loan_type_id = lt.id
                {$whereClause}
                ORDER BY l.application_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
                $row['total_repaid'] = (float)($row['total_repaid'] ?? 0);
                $row['total_interest_paid'] = (float)($row['total_interest_paid'] ?? 0);
                $row['outstanding_balance'] = $row['total_repayable'] - $row['total_repaid'];
            } catch (\Exception $e) {
                // Leave as is
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
    public function getRepaymentReport(array $filters = []): array
    {
        $params = [];
        $where = [];
        
        if (isset($filters['member_id']) && !empty($filters['member_id'])) {
            $where[] = "m.id = ?";
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
            $where[] = "(m.member_no LIKE ? OR m.full_name LIKE ? OR r.receipt_no LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT r.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       l.loan_no,
                       l.amount as loan_amount
                FROM repayments r
                JOIN loans l ON r.loan_id = l.id
                JOIN members m ON l.member_id = m.id
                {$whereClause}
                ORDER BY r.payment_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get fine report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getFineReport(array $filters = []): array
    {
        $params = [];
        $where = [];
        
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
            $where[] = "(m.member_no LIKE ? OR m.full_name LIKE ?)";
            $params = array_merge($params, [$search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT f.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       ft.name as fine_type_name,
                       (SELECT SUM(amount_paid) FROM fine_payments WHERE fine_id = f.id) as total_paid
                FROM fines f
                JOIN members m ON f.member_id = m.id
                JOIN fine_types ft ON f.fine_type_id = ft.id
                {$whereClause}
                ORDER BY f.fine_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['amount'] - $row['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get dividend report data
     * 
     * @param array $filters Report filters
     * @return array
     */
    public function getDividendReport(array $filters = []): array
    {
        $params = [];
        $where = [];
        
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
        
        $sql = "SELECT d.*, 
                       m.member_no, 
                       m.full_name as member_name,
                       (SELECT SUM(amount_paid) FROM dividend_payments WHERE dividend_id = d.id) as total_paid
                FROM dividends d
                JOIN members m ON d.member_id = m.id
                {$whereClause}
                ORDER BY d.year DESC, d.interest_earned DESC";
        
        $stmt = $this->db->query($sql, $params);
        $data = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($data as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
                $row['total_paid'] = (float)($row['total_paid'] ?? 0);
                $row['outstanding'] = $row['interest_earned'] - $row['total_paid'];
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }

    /**
     * Get financial summary
     * 
     * @param int $year Financial year
     * @return array
     */
    public function getFinancialSummary(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        // Income from savings
        $sql = "SELECT SUM(amount) as total FROM savings 
                WHERE transaction_type = 'Deposit' 
                AND transaction_date BETWEEN ? AND ?";
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $savingsIncome = (float)($stmt->fetch()['total'] ?? 0);
        
        // Income from loan interest
        $sql = "SELECT SUM(interest_amount) as total FROM repayments 
                WHERE payment_date BETWEEN ? AND ?";
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $loanInterest = (float)($stmt->fetch()['total'] ?? 0);
        
        // Income from fines
        $sql = "SELECT SUM(amount_paid) as total FROM fine_payments 
                WHERE payment_date BETWEEN ? AND ?";
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $fineIncome = (float)($stmt->fetch()['total'] ?? 0);
        
        // Total income
        $totalIncome = $savingsIncome + $loanInterest + $fineIncome;
        
        // Expenses (loans disbursed)
        $sql = "SELECT SUM(amount) as total FROM loans 
                WHERE status IN ('Active', 'Completed') 
                AND disbursement_date BETWEEN ? AND ?";
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $loansDisbursed = (float)($stmt->fetch()['total'] ?? 0);
        
        // Expenses (dividends paid)
        $sql = "SELECT SUM(interest_earned) as total FROM dividends 
                WHERE status = 'Paid' 
                AND payment_date BETWEEN ? AND ?";
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $dividendsPaid = (float)($stmt->fetch()['total'] ?? 0);
        
        // Total expenses
        $totalExpenses = $loansDisbursed + $dividendsPaid;
        
        // Net income
        $netIncome = $totalIncome - $totalExpenses;
        
        return [
            'year' => $year,
            'income' => [
                'savings' => $savingsIncome,
                'loan_interest' => $loanInterest,
                'fines' => $fineIncome,
                'total' => $totalIncome
            ],
            'expenses' => [
                'loans_disbursed' => $loansDisbursed,
                'dividends_paid' => $dividendsPaid,
                'total' => $totalExpenses
            ],
            'net_income' => $netIncome,
            'total_savings_balance' => $this->getTotalSavingsBalance(),
            'total_outstanding_loans' => $this->getTotalOutstandingLoans(),
            'total_outstanding_fines' => $this->getTotalOutstandingFines()
        ];
    }

    /**
     * Get cash flow data
     * 
     * @param int $year Financial year
     * @return array
     */
    public function getCashFlow(int $year): array
    {
        $months = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $month = str_pad($i, 2, '0', STR_PAD_LEFT);
            $startDate = "{$year}-{$month}-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            
            // Income
            $sql = "SELECT 
                        SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END) as savings,
                        (SELECT SUM(interest_amount) FROM repayments WHERE payment_date BETWEEN ? AND ?) as interest,
                        (SELECT SUM(amount_paid) FROM fine_payments WHERE payment_date BETWEEN ? AND ?) as fines
                    FROM savings 
                    WHERE transaction_date BETWEEN ? AND ?";
            
            $stmt = $this->db->query($sql, [
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate
            ]);
            $income = $stmt->fetch();
            
            // Expenses
            $sql = "SELECT 
                        (SELECT SUM(amount) FROM loans WHERE disbursement_date BETWEEN ? AND ?) as loans,
                        (SELECT SUM(interest_earned) FROM dividends WHERE payment_date BETWEEN ? AND ?) as dividends
                    FROM dual";
            
            $stmt = $this->db->query($sql, [
                $startDate, $endDate,
                $startDate, $endDate
            ]);
            $expenses = $stmt->fetch();
            
            $months[$month] = [
                'income' => [
                    'savings' => (float)($income['savings'] ?? 0),
                    'interest' => (float)($income['interest'] ?? 0),
                    'fines' => (float)($income['fines'] ?? 0),
                    'total' => (float)($income['savings'] ?? 0) + (float)($income['interest'] ?? 0) + (float)($income['fines'] ?? 0)
                ],
                'expenses' => [
                    'loans' => (float)($expenses['loans'] ?? 0),
                    'dividends' => (float)($expenses['dividends'] ?? 0),
                    'total' => (float)($expenses['loans'] ?? 0) + (float)($expenses['dividends'] ?? 0)
                ],
                'net' => ((float)($income['savings'] ?? 0) + (float)($income['interest'] ?? 0) + (float)($income['fines'] ?? 0)) - 
                         ((float)($expenses['loans'] ?? 0) + (float)($expenses['dividends'] ?? 0))
            ];
        }
        
        return $months;
    }

    /**
     * Get total savings balance
     * 
     * @return float
     */
    private function getTotalSavingsBalance(): float
    {
        $sql = "SELECT 
                    SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END) - 
                    SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END) as balance
                FROM savings";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['balance'] ?? 0);
    }

    /**
     * Get total outstanding loans
     * 
     * @return float
     */
    private function getTotalOutstandingLoans(): float
    {
        $sql = "SELECT SUM(total_repayable - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = loans.id)) as outstanding
                FROM loans
                WHERE status IN ('Active', 'Approved')";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['outstanding'] ?? 0);
    }

    /**
     * Get total outstanding fines
     * 
     * @return float
     */
    private function getTotalOutstandingFines(): float
    {
        $sql = "SELECT SUM(amount - (SELECT COALESCE(SUM(amount_paid), 0) FROM fine_payments WHERE fine_id = fines.id)) as outstanding
                FROM fines
                WHERE status IN ('Pending', 'Partially_Paid')";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['outstanding'] ?? 0);
    }
}