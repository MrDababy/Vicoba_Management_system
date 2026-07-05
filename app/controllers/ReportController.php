<?php
/**
 * Report Controller
 * 
 * Handles all report generation, filtering, and export.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\ReportModel;
use App\Models\Member;
use App\Models\LoanType;
use App\Models\FineType;
use App\Helpers\PdfGenerator;
use App\Helpers\ExcelGenerator;
use App\Helpers\ActivityLogger;

class ReportController extends BaseController
{
    /**
     * @var ReportModel Report model instance
     */
    private ReportModel $reportModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->reportModel = new ReportModel();
        
        // Require authentication
        $this->requireAuth();
        
        // Only admins and treasurers can access reports
        $this->requireRole(['Admin', 'Treasurer']);
    }

    /**
     * Report dashboard/index
     * 
     * @return void
     */
    public function index(): void
    {
        $data = [
            'title' => 'Reports - ' . APP_NAME,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('reports.index', $data, 'main');
    }

    /**
     * Member report
     * 
     * @param string $format Report format (html, pdf, excel)
     * @return void
     */
    public function members(string $format = 'html'): void
    {
        try {
            // Get filters
            $filters = [
                'status' => $this->input('status'),
                'gender' => $this->input('gender'),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date'),
                'search' => $this->input('search')
            ];
            $filters = array_filter($filters);
            
            // Get data
            $data = $this->reportModel->getMemberReport($filters);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Member Report', $filters);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Member Report', $filters);
            } else {
                // HTML view
                $viewData = [
                    'title' => 'Member Report - ' . APP_NAME,
                    'data' => $data,
                    'filters' => $filters,
                    'report_type' => 'members',
                    'columns' => [
                        ['field' => 'member_no', 'label' => 'Member No', 'width' => 15],
                        ['field' => 'full_name', 'label' => 'Full Name', 'width' => 25],
                        ['field' => 'gender', 'label' => 'Gender', 'width' => 10],
                        ['field' => 'phone', 'label' => 'Phone', 'width' => 15],
                        ['field' => 'email', 'label' => 'Email', 'width' => 20],
                        ['field' => 'status', 'label' => 'Status', 'width' => 10],
                        ['field' => 'total_deposits', 'label' => 'Savings', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'total_loans', 'label' => 'Loans', 'width' => 10],
                        ['field' => 'outstanding_fines', 'label' => 'Fines', 'width' => 15, 'format' => 'currency']
                    ],
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.members', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Savings report
     * 
     * @param string $format Report format
     * @return void
     */
    public function savings(string $format = 'html'): void
    {
        try {
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'transaction_type' => $this->input('transaction_type'),
                'transaction_mode' => $this->input('transaction_mode'),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date'),
                'search' => $this->input('search')
            ];
            $filters = array_filter($filters);
            
            $data = $this->reportModel->getSavingsReport($filters);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Savings Report', $filters);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Savings Report', $filters);
            } else {
                $viewData = [
                    'title' => 'Savings Report - ' . APP_NAME,
                    'data' => $data,
                    'filters' => $filters,
                    'report_type' => 'savings',
                    'columns' => [
                        ['field' => 'transaction_date', 'label' => 'Date', 'width' => 15, 'format' => 'date'],
                        ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                        ['field' => 'member_no', 'label' => 'Member No', 'width' => 12],
                        ['field' => 'transaction_type', 'label' => 'Type', 'width' => 10],
                        ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'balance_after', 'label' => 'Balance', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'transaction_mode', 'label' => 'Mode', 'width' => 12],
                        ['field' => 'receipt_no', 'label' => 'Receipt', 'width' => 12]
                    ],
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.savings', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Loan report
     * 
     * @param string $format Report format
     * @return void
     */
    public function loans(string $format = 'html'): void
    {
        try {
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'status' => $this->input('status'),
                'loan_type_id' => (int)$this->input('loan_type_id', 0),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date'),
                'search' => $this->input('search')
            ];
            $filters = array_filter($filters);
            
            $data = $this->reportModel->getLoanReport($filters);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Loan Report', $filters);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Loan Report', $filters);
            } else {
                $viewData = [
                    'title' => 'Loan Report - ' . APP_NAME,
                    'data' => $data,
                    'filters' => $filters,
                    'report_type' => 'loans',
                    'columns' => [
                        ['field' => 'loan_no', 'label' => 'Loan No', 'width' => 12],
                        ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                        ['field' => 'member_no', 'label' => 'Member No', 'width' => 12],
                        ['field' => 'loan_type_name', 'label' => 'Type', 'width' => 15],
                        ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'interest_rate', 'label' => 'Rate', 'width' => 8],
                        ['field' => 'duration_months', 'label' => 'Months', 'width' => 8],
                        ['field' => 'total_repayable', 'label' => 'Total', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'outstanding_balance', 'label' => 'Outstanding', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'status', 'label' => 'Status', 'width' => 10]
                    ],
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.loans', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Repayment report
     * 
     * @param string $format Report format
     * @return void
     */
    public function repayments(string $format = 'html'): void
    {
        try {
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'loan_id' => (int)$this->input('loan_id', 0),
                'payment_method' => $this->input('payment_method'),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date'),
                'search' => $this->input('search')
            ];
            $filters = array_filter($filters);
            
            $data = $this->reportModel->getRepaymentReport($filters);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Repayment Report', $filters);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Repayment Report', $filters);
            } else {
                $viewData = [
                    'title' => 'Repayment Report - ' . APP_NAME,
                    'data' => $data,
                    'filters' => $filters,
                    'report_type' => 'repayments',
                    'columns' => [
                        ['field' => 'payment_date', 'label' => 'Date', 'width' => 15, 'format' => 'date'],
                        ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                        ['field' => 'member_no', 'label' => 'Member No', 'width' => 12],
                        ['field' => 'loan_no', 'label' => 'Loan No', 'width' => 12],
                        ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'principal_amount', 'label' => 'Principal', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'interest_amount', 'label' => 'Interest', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'balance_after', 'label' => 'Balance', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'payment_method', 'label' => 'Method', 'width' => 12]
                    ],
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.repayments', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Fine report
     * 
     * @param string $format Report format
     * @return void
     */
    public function fines(string $format = 'html'): void
    {
        try {
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'status' => $this->input('status'),
                'fine_type_id' => (int)$this->input('fine_type_id', 0),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date'),
                'search' => $this->input('search')
            ];
            $filters = array_filter($filters);
            
            $data = $this->reportModel->getFineReport($filters);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Fine Report', $filters);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Fine Report', $filters);
            } else {
                $viewData = [
                    'title' => 'Fine Report - ' . APP_NAME,
                    'data' => $data,
                    'filters' => $filters,
                    'report_type' => 'fines',
                    'columns' => [
                        ['field' => 'fine_date', 'label' => 'Date', 'width' => 12, 'format' => 'date'],
                        ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                        ['field' => 'member_no', 'label' => 'Member No', 'width' => 12],
                        ['field' => 'fine_type_name', 'label' => 'Type', 'width' => 15],
                        ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'total_paid', 'label' => 'Paid', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'outstanding', 'label' => 'Outstanding', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'due_date', 'label' => 'Due', 'width' => 12, 'format' => 'date'],
                        ['field' => 'status', 'label' => 'Status', 'width' => 10]
                    ],
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.fines', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Dividend report
     * 
     * @param string $format Report format
     * @return void
     */
    public function dividends(string $format = 'html'): void
    {
        try {
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'year' => (int)$this->input('year', 0),
                'status' => $this->input('status'),
                'search' => $this->input('search')
            ];
            $filters = array_filter($filters);
            
            $data = $this->reportModel->getDividendReport($filters);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Dividend Report', $filters);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Dividend Report', $filters);
            } else {
                $viewData = [
                    'title' => 'Dividend Report - ' . APP_NAME,
                    'data' => $data,
                    'filters' => $filters,
                    'report_type' => 'dividends',
                    'columns' => [
                        ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                        ['field' => 'member_no', 'label' => 'Member No', 'width' => 12],
                        ['field' => 'year', 'label' => 'Year', 'width' => 8],
                        ['field' => 'savings_amount', 'label' => 'Savings', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'interest_earned', 'label' => 'Earned', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'total_paid', 'label' => 'Paid', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'outstanding', 'label' => 'Outstanding', 'width' => 15, 'format' => 'currency'],
                        ['field' => 'status', 'label' => 'Status', 'width' => 10]
                    ],
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.dividends', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Financial summary report
     * 
     * @param string $format Report format
     * @return void
     */
    public function financial(string $format = 'html'): void
    {
        try {
            $year = (int)$this->input('year', date('Y'));
            
            $summary = $this->reportModel->getFinancialSummary($year);
            
            if ($format === 'pdf') {
                $pdfGenerator = new PdfGenerator();
                $content = $pdfGenerator->generateFinancialSummary($summary, $year);
                $this->outputPdf($content, 'Financial_Summary_' . $year);
            } elseif ($format === 'excel') {
                $content = ExcelGenerator::generateFinancialSummary($summary, $year);
                $this->outputExcel($content, 'Financial_Summary_' . $year);
            } else {
                $viewData = [
                    'title' => 'Financial Summary - ' . APP_NAME,
                    'summary' => $summary,
                    'year' => $year,
                    'available_years' => range(date('Y'), 2020),
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.financial', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Cash flow report
     * 
     * @param string $format Report format
     * @return void
     */
    public function cashflow(string $format = 'html'): void
    {
        try {
            $year = (int)$this->input('year', date('Y'));
            
            $data = $this->reportModel->getCashFlow($year);
            
            if ($format === 'pdf') {
                $this->exportPdf($data, 'Cash Flow Report ' . $year, ['year' => $year]);
            } elseif ($format === 'excel') {
                $this->exportExcel($data, 'Cash Flow Report ' . $year, ['year' => $year]);
            } else {
                $viewData = [
                    'title' => 'Cash Flow Report - ' . APP_NAME,
                    'data' => $data,
                    'year' => $year,
                    'available_years' => range(date('Y'), 2020),
                    'csrf_token' => $this->csrfToken()
                ];
                
                $this->render('reports.cashflow', $viewData, 'main');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/reports');
        }
    }

    /**
     * Export to PDF
     * 
     * @param array $data Report data
     * @param string $title Report title
     * @param array $filters Report filters
     * @return void
     */
    private function exportPdf(array $data, string $title, array $filters): void
    {
        $pdfGenerator = new PdfGenerator();
        
        // Get columns from session or request
        $columns = json_decode($this->input('columns', '[]'), true);
        if (empty($columns)) {
            // Default columns based on report type
            $columns = $this->getDefaultColumns($this->input('report_type', 'members'));
        }
        
        $content = $pdfGenerator->generateGenericReport($data, $title, $columns);
        $this->outputPdf($content, str_replace(' ', '_', $title));
    }

    /**
     * Export to Excel
     * 
     * @param array $data Report data
     * @param string $title Report title
     * @param array $filters Report filters
     * @return void
     */
    private function exportExcel(array $data, string $title, array $filters): void
    {
        $columns = json_decode($this->input('columns', '[]'), true);
        if (empty($columns)) {
            $columns = $this->getDefaultColumns($this->input('report_type', 'members'));
        }
        
        $content = ExcelGenerator::generate($data, $title, $columns);
        $this->outputExcel($content, str_replace(' ', '_', $title));
    }

    /**
     * Get default columns for report type
     * 
     * @param string $reportType Report type
     * @return array
     */
    private function getDefaultColumns(string $reportType): array
    {
        $columns = [
            'members' => [
                ['field' => 'member_no', 'label' => 'Member No', 'width' => 15],
                ['field' => 'full_name', 'label' => 'Full Name', 'width' => 25],
                ['field' => 'gender', 'label' => 'Gender', 'width' => 10],
                ['field' => 'phone', 'label' => 'Phone', 'width' => 15],
                ['field' => 'status', 'label' => 'Status', 'width' => 10]
            ],
            'savings' => [
                ['field' => 'transaction_date', 'label' => 'Date', 'width' => 15, 'format' => 'date'],
                ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                ['field' => 'transaction_type', 'label' => 'Type', 'width' => 10],
                ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                ['field' => 'balance_after', 'label' => 'Balance', 'width' => 15, 'format' => 'currency']
            ],
            'loans' => [
                ['field' => 'loan_no', 'label' => 'Loan No', 'width' => 12],
                ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                ['field' => 'total_repayable', 'label' => 'Total', 'width' => 15, 'format' => 'currency'],
                ['field' => 'status', 'label' => 'Status', 'width' => 10]
            ],
            'repayments' => [
                ['field' => 'payment_date', 'label' => 'Date', 'width' => 15, 'format' => 'date'],
                ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                ['field' => 'payment_method', 'label' => 'Method', 'width' => 12]
            ],
            'fines' => [
                ['field' => 'fine_date', 'label' => 'Date', 'width' => 12, 'format' => 'date'],
                ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                ['field' => 'amount', 'label' => 'Amount', 'width' => 15, 'format' => 'currency'],
                ['field' => 'status', 'label' => 'Status', 'width' => 10]
            ],
            'dividends' => [
                ['field' => 'member_name', 'label' => 'Member', 'width' => 20],
                ['field' => 'year', 'label' => 'Year', 'width' => 8],
                ['field' => 'interest_earned', 'label' => 'Earned', 'width' => 15, 'format' => 'currency'],
                ['field' => 'status', 'label' => 'Status', 'width' => 10]
            ]
        ];
        
        return $columns[$reportType] ?? $columns['members'];
    }

    /**
     * Output PDF content
     * 
     * @param string $content PDF content
     * @param string $filename File name
     * @return void
     */
    private function outputPdf(string $content, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $content;
        exit;
    }

    /**
     * Output Excel content
     * 
     * @param string $content Excel content
     * @param string $filename File name
     * @return void
     */
    private function outputExcel(string $content, string $filename): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $content;
        exit;
    }
}