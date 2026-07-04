<?php
/**
 * Dashboard Model
 * 
 * Handles all dashboard statistics and data aggregation.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;

class DashboardModel
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
     * Get dashboard statistics
     * 
     * @param string $period Date range (today, week, month, year, all)
     * @return array
     */
    public function getStats(string $period = 'all'): array
    {
        $dateCondition = $this->getDateCondition($period);
        
        return [
            'total_members' => $this->getTotalMembers($dateCondition),
            'total_savings' => $this->getTotalSavings($dateCondition),
            'total_loans' => $this->getTotalLoans($dateCondition),
            'pending_loans' => $this->getPendingLoans(),
            'total_repayments' => $this->getTotalRepayments($dateCondition),
            'outstanding_fines' => $this->getOutstandingFines(),
            'annual_profit' => $this->getAnnualProfit(),
            'dividends_distributed' => $this->getDividendsDistributed(),
            'recent_activities' => $this->getRecentActivities(),
            'member_growth' => $this->getMemberGrowth(),
            'monthly_savings' => $this->getMonthlySavings(),
            'monthly_loans' => $this->getMonthlyLoans(),
            'monthly_repayments' => $this->getMonthlyRepayments(),
            'loan_status_distribution' => $this->getLoanStatusDistribution()
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
                return "DATE(created_at) = CURDATE()";
            case 'week':
                return "YEARWEEK(created_at) = YEARWEEK(CURDATE())";
            case 'month':
                return "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
            case 'year':
                return "YEAR(created_at) = YEAR(CURDATE())";
            default:
                return "1=1";
        }
    }

    /**
     * Get total members
     * 
     * @param string $dateCondition Date condition
     * @return int
     */
    private function getTotalMembers(string $dateCondition): int
    {
        $sql = "SELECT COUNT(*) as total FROM members WHERE {$dateCondition}";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get active members count
     * 
     * @return int
     */
    public function getActiveMembers(): int
    {
        $sql = "SELECT COUNT(*) as total FROM members WHERE status = 'Active'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get total savings
     * 
     * @param string $dateCondition Date condition
     * @return float
     */
    private function getTotalSavings(string $dateCondition): float
    {
        $sql = "SELECT SUM(amount) as total FROM savings 
                WHERE transaction_type = 'Deposit' AND {$dateCondition}";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get total loans
     * 
     * @param string $dateCondition Date condition
     * @return float
     */
    private function getTotalLoans(string $dateCondition): float
    {
        $sql = "SELECT SUM(amount) as total FROM loans 
                WHERE status NOT IN ('Rejected', 'Defaulted') AND {$dateCondition}";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get pending loans count
     * 
     * @return int
     */
    private function getPendingLoans(): int
    {
        $sql = "SELECT COUNT(*) as total FROM loans WHERE status = 'Pending'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get active loans count
     * 
     * @return int
     */
    public function getActiveLoans(): int
    {
        $sql = "SELECT COUNT(*) as total FROM loans WHERE status = 'Active'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get total repayments
     * 
     * @param string $dateCondition Date condition
     * @return float
     */
    private function getTotalRepayments(string $dateCondition): float
    {
        $sql = "SELECT SUM(amount) as total FROM repayments WHERE {$dateCondition}";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get outstanding fines
     * 
     * @return float
     */
    private function getOutstandingFines(): float
    {
        $sql = "SELECT SUM(f.amount - COALESCE(SUM(fp.amount_paid), 0)) as total 
                FROM fines f 
                LEFT JOIN fine_payments fp ON f.id = fp.fine_id 
                WHERE f.status IN ('Pending', 'Partially_Paid')
                GROUP BY f.id";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $total = 0;
        foreach ($results as $row) {
            $total += (float)($row['total'] ?? 0);
        }
        return $total;
    }

    /**
     * Get annual profit
     * 
     * @return float
     */
    private function getAnnualProfit(): float
    {
        $sql = "SELECT SUM(interest_earned) as total FROM dividends WHERE YEAR(created_at) = YEAR(CURDATE())";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get dividends distributed
     * 
     * @return float
     */
    private function getDividendsDistributed(): float
    {
        $sql = "SELECT SUM(interest_earned) as total FROM dividends 
                WHERE status = 'Paid' AND YEAR(created_at) = YEAR(CURDATE())";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get recent activities
     * 
     * @param int $limit Number of activities
     * @return array
     */
    private function getRecentActivities(int $limit = 10): array
    {
        $sql = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->query($sql, [$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get member growth data (last 12 months)
     * 
     * @return array
     */
    private function getMemberGrowth(): array
    {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM members
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['month']] = (int)$row['count'];
        }
        
        // Fill in missing months
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = $data[$month] ?? 0;
        }
        
        return $months;
    }

    /**
     * Get monthly savings data
     * 
     * @return array
     */
    private function getMonthlySavings(): array
    {
        $sql = "SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') as month,
                    SUM(amount) as total
                FROM savings
                WHERE transaction_type = 'Deposit'
                    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                ORDER BY month ASC";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['month']] = (float)$row['total'];
        }
        
        // Fill in missing months
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = $data[$month] ?? 0;
        }
        
        return $months;
    }

    /**
     * Get monthly loans data
     * 
     * @return array
     */
    private function getMonthlyLoans(): array
    {
        $sql = "SELECT 
                    DATE_FORMAT(application_date, '%Y-%m') as month,
                    SUM(amount) as total
                FROM loans
                WHERE application_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    AND status != 'Rejected'
                GROUP BY DATE_FORMAT(application_date, '%Y-%m')
                ORDER BY month ASC";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['month']] = (float)$row['total'];
        }
        
        // Fill in missing months
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = $data[$month] ?? 0;
        }
        
        return $months;
    }

    /**
     * Get monthly repayments data
     * 
     * @return array
     */
    private function getMonthlyRepayments(): array
    {
        $sql = "SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    SUM(amount) as total
                FROM repayments
                WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month ASC";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['month']] = (float)$row['total'];
        }
        
        // Fill in missing months
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = $data[$month] ?? 0;
        }
        
        return $months;
    }

    /**
     * Get loan status distribution
     * 
     * @return array
     */
    private function getLoanStatusDistribution(): array
    {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM loans
                GROUP BY status";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $distribution = [];
        foreach ($results as $row) {
            $distribution[$row['status']] = (int)$row['count'];
        }
        
        return $distribution;
    }

    /**
     * Get total savings by member
     * 
     * @param int $limit Number of members
     * @return array
     */
    public function getTopSavers(int $limit = 10): array
    {
        $sql = "SELECT 
                    m.id,
                    m.full_name,
                    m.member_no,
                    SUM(s.amount) as total_savings
                FROM members m
                LEFT JOIN savings s ON m.id = s.member_id 
                    AND s.transaction_type = 'Deposit'
                WHERE m.status = 'Active'
                GROUP BY m.id
                ORDER BY total_savings DESC
                LIMIT ?";
        $stmt = $this->db->query($sql, [$limit]);
        $results = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($results as &$row) {
            try {
                $row['full_name'] = $this->encryptor->decrypt($row['full_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $results;
    }

    /**
     * Get recent loans
     * 
     * @param int $limit Number of loans
     * @return array
     */
    public function getRecentLoans(int $limit = 5): array
    {
        $sql = "SELECT 
                    l.*,
                    m.full_name as member_name,
                    m.member_no
                FROM loans l
                JOIN members m ON l.member_id = m.id
                ORDER BY l.created_at DESC
                LIMIT ?";
        $stmt = $this->db->query($sql, [$limit]);
        $results = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($results as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $results;
    }

    /**
     * Get recent savings
     * 
     * @param int $limit Number of transactions
     * @return array
     */
    public function getRecentSavings(int $limit = 5): array
    {
        $sql = "SELECT 
                    s.*,
                    m.full_name as member_name,
                    m.member_no
                FROM savings s
                JOIN members m ON s.member_id = m.id
                ORDER BY s.created_at DESC
                LIMIT ?";
        $stmt = $this->db->query($sql, [$limit]);
        $results = $stmt->fetchAll();
        
        // Decrypt member names
        foreach ($results as &$row) {
            try {
                $row['member_name'] = $this->encryptor->decrypt($row['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $results;
    }

    /**
     * Get financial summary
     * 
     * @return array
     */
    public function getFinancialSummary(): array
    {
        $sql = "SELECT 
                    (SELECT SUM(amount) FROM savings WHERE transaction_type = 'Deposit') as total_deposits,
                    (SELECT SUM(amount) FROM savings WHERE transaction_type = 'Withdrawal') as total_withdrawals,
                    (SELECT SUM(amount) FROM loans WHERE status IN ('Approved', 'Active', 'Completed')) as total_loans_disbursed,
                    (SELECT SUM(amount) FROM repayments) as total_repayments,
                    (SELECT SUM(interest_earned) FROM dividends WHERE status = 'Paid') as total_dividends_paid,
                    (SELECT SUM(amount) FROM fines WHERE status IN ('Pending', 'Partially_Paid')) as outstanding_fines_total";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return [
            'total_deposits' => (float)($result['total_deposits'] ?? 0),
            'total_withdrawals' => (float)($result['total_withdrawals'] ?? 0),
            'total_loans_disbursed' => (float)($result['total_loans_disbursed'] ?? 0),
            'total_repayments' => (float)($result['total_repayments'] ?? 0),
            'total_dividends_paid' => (float)($result['total_dividends_paid'] ?? 0),
            'outstanding_fines_total' => (float)($result['outstanding_fines_total'] ?? 0)
        ];
    }

    /**
     * Get monthly income (last 12 months)
     * 
     * @return array
     */
    public function getMonthlyIncome(): array
    {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(amount) as total
                FROM repayments
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $data = [];
        foreach ($results as $row) {
            $data[$row['month']] = (float)$row['total'];
        }
        
        // Fill in missing months
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = $data[$month] ?? 0;
        }
        
        return $months;
    }
}