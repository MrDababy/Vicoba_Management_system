<?php
/**
 * Dashboard Controller
 * 
 * Handles dashboard display and data retrieval.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\DashboardModel;
use App\Models\Member;
use App\Models\Loan;
use App\Models\Savings;
use App\Helpers\ActivityLogger;

class DashboardController extends BaseController
{
    /**
     * @var DashboardModel Dashboard model
     */
    private DashboardModel $dashboardModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->dashboardModel = new DashboardModel();
        
        // Require authentication for dashboard
        $this->requireAuth();
    }

    /**
     * Show dashboard
     * 
     * @param string $period Date period filter
     * @return void
     */
    public function index(string $period = 'all'): void
    {
        // Get dashboard statistics
        $stats = $this->dashboardModel->getStats($period);
        
        // Get additional data
        $financialSummary = $this->dashboardModel->getFinancialSummary();
        $topSavers = $this->dashboardModel->getTopSavers(5);
        $recentLoans = $this->dashboardModel->getRecentLoans(5);
        $recentSavings = $this->dashboardModel->getRecentSavings(5);
        
        // Get user data
        $user = $this->getUser();
        $userRole = $user['role'] ?? 'Member';
        
        // Prepare data for view
        $data = [
            'title' => 'Dashboard - ' . APP_NAME,
            'dashboardModel' => $this->dashboardModel,
            'stats' => $stats,
            'financial_summary' => $financialSummary,
            'top_savers' => $topSavers,
            'recent_loans' => $recentLoans,
            'recent_savings' => $recentSavings,
            'user' => $user,
            'user_role' => $userRole,
            'period' => $period,
            'is_admin' => $this->auth->isAdmin(),
            'is_treasurer' => $this->auth->isTreasurer(),
            'is_secretary' => $this->auth->isSecretary(),
            'is_member' => $this->auth->isMember(),
            'csrf_token' => $this->csrfToken()
        ];
        
        // Log dashboard view
        ActivityLogger::log(
            'VIEW_DASHBOARD',
            'dashboard',
            null,
            'User viewed dashboard with period: ' . $period
        );

        // Add to DashboardController's index method
        $savingsModel = new Savings();
        $stats['monthly_savings'] = $savingsModel->getMonthlySavingsData();
        $stats['recent_savings'] = $savingsModel->getRecentSavings(5);

        // Add to DashboardController's index method
        $fineModel = new \App\Models\Fine();
        $stats['outstanding_fines'] = $fineModel->getStats()['outstanding_amount'];
        $stats['recent_fines'] = $fineModel->getRecentFines(5);

        // Add to DashboardController's index method
        $dividendModel = new \App\Models\Dividend();
        $stats['dividends_distributed'] = $dividendModel->getStats(date('Y'))['paid_amount'];
        
        $this->render('dashboard.index', $data, 'main');
    }

    /**
     * Get dashboard data via AJAX
     * 
     * @return void
     */
    public function getData(): void
    {
        try {
            // Check if AJAX request
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $period = $this->input('period', 'all');
            $stats = $this->dashboardModel->getStats($period);
            
            $this->jsonSuccess('Dashboard data retrieved successfully', [
                'stats' => $stats,
                'period' => $period
            ]);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get chart data via AJAX
     * 
     * @return void
     */
    public function getChartData(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $chartType = $this->input('chart_type', 'savings');
            
            switch ($chartType) {
                case 'savings':
                    $data = $this->dashboardModel->getMonthlySavings();
                    $label = 'Monthly Savings';
                    break;
                case 'loans':
                    $data = $this->dashboardModel->getMonthlyLoans();
                    $label = 'Monthly Loans';
                    break;
                case 'repayments':
                    $data = $this->dashboardModel->getMonthlyRepayments();
                    $label = 'Monthly Repayments';
                    break;
                case 'income':
                    $data = $this->dashboardModel->getMonthlyIncome();
                    $label = 'Monthly Income';
                    break;
                case 'growth':
                    $data = $this->dashboardModel->getMemberGrowth();
                    $label = 'Member Growth';
                    break;
                case 'loan_status':
                    $data = $this->dashboardModel->getLoanStatusDistribution();
                    $label = 'Loan Status Distribution';
                    break;
                default:
                    throw new \Exception('Invalid chart type');
            }
            
            // Prepare chart data
            $chartData = [
                'labels' => array_keys($data),
                'values' => array_values($data),
                'label' => $label
            ];
            
            $this->jsonSuccess('Chart data retrieved successfully', $chartData);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get recent activities via AJAX
     * 
     * @return void
     */
    public function getActivities(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $limit = (int)$this->input('limit', 10);
            $activities = $this->dashboardModel->getRecentActivities($limit);
            
            $this->jsonSuccess('Activities retrieved successfully', $activities);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Update dashboard period
     * 
     * @return void
     */
    public function updatePeriod(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $period = $this->input('period', 'all');
            
            // Validate period
            $validPeriods = ['today', 'week', 'month', 'year', 'all'];
            if (!in_array($period, $validPeriods)) {
                throw new \Exception('Invalid period');
            }
            
            // Store preference in session
            $this->session->set('dashboard_period', $period);
            
            $this->jsonSuccess('Period updated successfully', ['period' => $period]);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Toggle dark mode
     * 
     * @return void
     */
    public function toggleDarkMode(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $darkMode = $this->input('dark_mode', false);
            
            // Store preference in session
            $this->session->set('dark_mode', $darkMode);
            
            $this->jsonSuccess('Theme updated successfully', ['dark_mode' => $darkMode]);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Export dashboard data
     * 
     * @param string $format Export format
     * @return void
     */
    public function export(string $format = 'pdf'): void
    {
        try {
            // Only admins and treasurers can export
            if (!$this->auth->hasRole(['Admin', 'Treasurer'])) {
                throw new \Exception('Unauthorized access');
            }
            
            $period = $this->input('period', 'all');
            $stats = $this->dashboardModel->getStats($period);
            
            // In a real implementation, generate PDF or Excel
            // For now, just return JSON
            if ($format === 'json') {
                $this->jsonSuccess('Export data', [
                    'stats' => $stats,
                    'generated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Redirect to report generation page
                $this->redirect('/reports/dashboard?period=' . $period . '&format=' . $format);
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/dashboard');
        }
    }
}