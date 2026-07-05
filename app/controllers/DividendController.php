<?php
/**
 * Dividend Controller
 * 
 * Handles dividend calculation, distribution, and reporting.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\Dividend;
use App\Models\DividendPayment;
use App\Models\Member;
use App\Helpers\ActivityLogger;
use App\Helpers\DividendCalculator;
use App\Exceptions\ValidationException;

class DividendController extends BaseController
{
    /**
     * @var Dividend Dividend model instance
     */
    private Dividend $dividendModel;

    /**
     * @var DividendPayment Dividend payment model instance
     */
    private DividendPayment $dividendPaymentModel;

    /**
     * @var Member Member model instance
     */
    private Member $memberModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->dividendModel = new Dividend();
        $this->dividendPaymentModel = new DividendPayment();
        $this->memberModel = new Member();
        
        // Require authentication        $this->requireAuth();
        
        // Only admins and treasurers can manage dividends
        $this->requireRole(['Admin', 'Treasurer']);
    }

    /**
     * List all dividends
     * 
     * @return void
     */
    public function index(): void
    {
        $page = (int)$this->input('page', 1);
        $page = max(1, $page);
        
        $perPage = (int)$this->input('per_page', 20);
        $perPage = min(100, max(5, $perPage));
        
        // Build filters
        $filters = [
            'member_id' => (int)$this->input('member_id', 0),
            'year' => (int)$this->input('year', 0),
            'status' => $this->input('status'),
            'search' => $this->input('search')
        ];
        $filters = array_filter($filters);
        
        // Get dividends
        $result = $this->dividendModel->getDividendsWithDetails($page, $perPage, $filters);
        
        // Get statistics
        $year = (int)($filters['year'] ?? date('Y'));
        $stats = $this->dividendModel->getStats($year ?: null);
        
        // Get available years
        $years = $this->dividendModel->getAvailableYears();
        
        // Get members for filter
        $members = $this->memberModel->all(['status' => 'Active'], 'full_name', 'ASC');
        
        $data = [
            'title' => 'Dividend Management - ' . APP_NAME,
            'dividends' => $result['data'],
            'pagination' => $result,
            'stats' => $stats,
            'filters' => $filters,
            'members' => $members,
            'years' => $years,
            'per_page' => $perPage,
            'statuses' => ['Declared', 'Partially_Paid', 'Paid'],
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('dividends.index', $data, 'main');
    }

    /**
     * Show dividend calculation form
     * 
     * @return void
     */
    public function calculate(): void
    {
        $year = (int)$this->input('year', date('Y'));
        
        // Check if already calculated
        $isCalculated = $this->dividendModel->isYearCalculated($year);
        
        // Get annual profit
        $annualProfit = $this->dividendModel->getAnnualProfit($year);
        
        // Get active members count
        $memberCount = $this->memberModel->getActiveCount();
        
        $data = [
            'title' => 'Calculate Dividends - ' . APP_NAME,
            'year' => $year,
            'annual_profit' => $annualProfit,
            'member_count' => $memberCount,
            'is_calculated' => $isCalculated,
            'scenarios' => DividendCalculator::calculateScenarios($annualProfit, 1000000, [50, 60, 70, 80, 90]),
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('dividends.calculate', $data, 'main');
    }

    /**
     * Process dividend calculation
     * 
     * @return void
     */
    public function processCalculate(): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            $year = (int)$this->input('year');
            $totalProfit = (float)$this->input('total_profit');
            $interestPercentage = (float)$this->input('interest_percentage');
            
            if ($year <= 0) {
                throw new \Exception('Invalid year selected.');
            }
            
            if ($totalProfit <= 0) {
                throw new \Exception('Total profit must be greater than zero.');
            }
            
            if ($interestPercentage <= 0 || $interestPercentage > 100) {
                throw new \Exception('Interest percentage must be between 1 and 100.');
            }
            
            // Calculate and distribute
            $results = $this->dividendModel->calculateAndDistribute(
                $year,
                $totalProfit,
                $interestPercentage,
                $this->getUserId()
            );
            
            if (!empty($results)) {
                $this->session->flash('success', "Dividends for {$year} calculated successfully! Distributed to " . count($results) . " members.");
                $this->redirect('/dividends?year=' . $year);
            } else {
                throw new \Exception('No dividends were calculated. Please check member data.');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/dividends/calculate?year=' . $this->input('year'));
        }
    }

    /**
     * View dividend details
     * 
     * @param int $id Dividend ID
     * @return void
     */
    public function view(int $id): void
    {
        $dividend = $this->dividendModel->getDividendWithDetails($id);
        
        if (!$dividend) {
            $this->session->flash('error', 'Dividend record not found.');
            $this->redirect('/dividends');
            return;
        }
        
        $data = [
            'title' => 'Dividend Details - ' . APP_NAME,
            'dividend' => $dividend,
            'can_pay' => $dividend['status'] !== 'Paid',
            'payment_methods' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('dividends.view', $data, 'main');
    }

    /**
     * Process dividend payment
     * 
     * @param int $id Dividend ID
     * @return void
     */
    public function pay(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get dividend
            $dividend = $this->dividendModel->getDividendWithDetails($id);
            if (!$dividend) {
                throw new \Exception('Dividend record not found.');
            }
            
            if ($dividend['status'] === 'Paid') {
                throw new \Exception('This dividend is already fully paid.');
            }
            
            // Get payment data
            $data = [
                'dividend_id' => $id,
                'amount_paid' => (float)$this->input('amount_paid'),
                'payment_date' => $this->input('payment_date', date('Y-m-d')),
                'payment_method' => $this->input('payment_method'),
                'reference_no' => $this->input('reference_no'),
                'remarks' => $this->input('remarks'),
                'created_by' => $this->getUserId()
            ];
            
            // Create payment
            $paymentId = $this->dividendPaymentModel->create($data);
            
            if ($paymentId) {
                // Get payment details
                $payment = $this->dividendPaymentModel->getWithDetails($paymentId);
                
                // Log activity
                ActivityLogger::log(
                    'PAYMENT',
                    'dividend_payments',
                    $paymentId,
                    "Dividend payment: {$data['amount_paid']} for dividend ID: {$id}",
                    null,
                    $data
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Payment recorded successfully!', ['payment' => $payment]);
                } else {
                    $this->session->flash('success', 'Payment recorded successfully!');
                    $this->redirect('/dividends/' . $id);
                }
            } else {
                throw new \Exception('Failed to record payment. Please try again.');
            }
            
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/dividends/' . $id);
            }
        }
    }

    /**
     * Generate dividend report
     * 
     * @param string $format Report format
     * @return void
     */
    public function report(string $format = 'html'): void
    {
        try {
            // Get filters
            $filters = [
                'year' => (int)$this->input('year', date('Y')),
                'status' => $this->input('status'),
                'member_id' => (int)$this->input('member_id', 0)
            ];
            $filters = array_filter($filters);
            
            // Get data
            $dividends = $this->dividendModel->getReportData($filters);
            
            if (empty($dividends)) {
                $this->session->flash('error', 'No dividend data available for the selected criteria.');
                $this->redirect('/dividends');
                return;
            }
            
            // Get summary
            $summary = $this->dividendModel->getStats($filters['year'] ?? null);
            
            if ($format === 'csv') {
                $this->exportCsv($dividends, $summary);
            } elseif ($format === 'excel') {
                $this->exportExcel($dividends, $summary);
            } else {
                // HTML view
                $data = [
                    'title' => 'Dividend Report - ' . APP_NAME,
                    'dividends' => $dividends,
                    'summary' => $summary,
                    'filters' => $filters,
                    'generated_at' => date('Y-m-d H:i:s'),
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('dividends.report', $data, 'print');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/dividends');
        }
    }

    /**
     * Export as CSV
     * 
     * @param array $dividends Dividend data
     * @param array $summary Summary data
     * @return void
     */
    private function exportCsv(array $dividends, array $summary): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="dividend_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Summary section
        fputcsv($output, ['DIVIDEND REPORT SUMMARY']);
        fputcsv($output, ['Total Members', $summary['total_members'] ?? 0]);
        fputcsv($output, ['Total Earned', number_format($summary['total_earned'] ?? 0, 2)]);
        fputcsv($output, ['Paid Amount', number_format($summary['paid_amount'] ?? 0, 2)]);
        fputcsv($output, ['Outstanding Amount', number_format($summary['outstanding_amount'] ?? 0, 2)]);
        fputcsv($output, []);
        
        // Data section
        fputcsv($output, ['Member', 'Member No', 'Year', 'Savings', 'Interest Earned', 'Paid', 'Outstanding', 'Status']);
        
        foreach ($dividends as $dividend) {
            fputcsv($output, [
                $dividend['member_name'],
                $dividend['member_no'],
                $dividend['year'],
                number_format($dividend['savings_amount'], 2),
                number_format($dividend['interest_earned'], 2),
                number_format($dividend['total_paid'] ?? 0, 2),
                number_format($dividend['outstanding'] ?? $dividend['interest_earned'], 2),
                $dividend['status']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export as Excel
     * 
     * @param array $dividends Dividend data
     * @param array $summary Summary data
     * @return void
     */
    private function exportExcel(array $dividends, array $summary): void
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="dividend_report_' . date('Y-m-d') . '.xls"');
        
        echo '<html><head><meta charset="UTF-8"></head><body>';
        
        // Header
        echo '<h2>Dividend Report - ' . APP_NAME . '</h2>';
        echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<hr>';
        
        // Summary
        echo '<h3>Summary</h3>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr><td><strong>Total Members</strong></td><td>' . ($summary['total_members'] ?? 0) . '</td></tr>';
        echo '<tr><td><strong>Total Earned</strong></td><td>' . number_format($summary['total_earned'] ?? 0, 2) . '</td></tr>';
        echo '<tr><td><strong>Paid Amount</strong></td><td>' . number_format($summary['paid_amount'] ?? 0, 2) . '</td></tr>';
        echo '<tr><td><strong>Outstanding Amount</strong></td><td>' . number_format($summary['outstanding_amount'] ?? 0, 2) . '</td></tr>';
        echo '</table>';
        echo '<br>';
        
        // Data
        echo '<h3>Member Dividends</h3>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2;">';
        echo '<th>Member</th><th>Member No</th><th>Year</th>';
        echo '<th>Savings</th><th>Interest Earned</th>';
        echo '<th>Paid</th><th>Outstanding</th><th>Status</th>';
        echo '</tr>';
        
        foreach ($dividends as $dividend) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($dividend['member_name']) . '</td>';
            echo '<td>' . htmlspecialchars($dividend['member_no']) . '</td>';
            echo '<td>' . $dividend['year'] . '</td>';
            echo '<td>' . number_format($dividend['savings_amount'], 2) . '</td>';
            echo '<td>' . number_format($dividend['interest_earned'], 2) . '</td>';
            echo '<td>' . number_format($dividend['total_paid'] ?? 0, 2) . '</td>';
            echo '<td>' . number_format($dividend['outstanding'] ?? $dividend['interest_earned'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($dividend['status']) . '</td>';
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit;
    }
}