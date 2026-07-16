<?php
/**
 * Repayment Controller
 * 
 * Handles all loan repayment operations including recording,
 * editing, viewing, and deleting repayments.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\Repayment;
use App\Models\Loan;
use App\Models\Member;
use App\Models\LoanInstallment;
use App\Helpers\ActivityLogger;
use App\Exceptions\ValidationException;

class RepaymentController extends BaseController
{
    /**
     * @var Repayment Repayment model instance
     */
    private Repayment $repaymentModel;

    /**
     * @var Loan Loan model instance
     */
    private Loan $loanModel;

    /**
     * @var Member Member model instance
     */
    private Member $memberModel;

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
        $this->repaymentModel = new Repayment();
        $this->loanModel = new Loan();
        $this->memberModel = new Member();
        $this->installmentModel = new LoanInstallment();

        // Require authentication
        $this->requireAuth();

        // Only admins and treasurers can manage repayments
        $this->requireRole(['Admin', 'Treasurer']);
    }

    /**
     * List all repayments
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
            'loan_id' => (int)$this->input('loan_id', 0),
            'payment_method' => $this->input('payment_method'),
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
            'search' => $this->input('search')
        ];
        $filters = array_filter($filters);

        // Get repayments
        $result = $this->repaymentModel->getRepaymentsWithDetails($page, $perPage, $filters);

        // Get statistics
        $stats = $this->repaymentModel->getStats('all');

        // Get members for filter
        $members = $this->memberModel->all(['status' => 'Active'], 'full_name', 'ASC');

        // Get active loans for filter
        $loans = $this->loanModel->all(['status' => ['Approved', 'Active']], 'loan_no', 'ASC');

        $data = [
            'title' => 'Repayment Management - ' . APP_NAME,
            'repayments' => $result['data'],
            'pagination' => $result,
            'stats' => $stats,
            'filters' => $filters,
            'members' => $members,
            'loans' => $loans,
            'per_page' => $perPage,
            'payment_methods' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];

        $this->render('repayments.index', $data, 'main');
    }

    /**
     * Show create repayment form
     * 
     * @return void
     */
    public function create(): void
    {
        $loanId = (int)$this->input('loan_id', 0);
        $memberId = (int)$this->input('member_id', 0);

        // Get loan if specified
        $loan = null;
        $outstandingBalance = 0;
        $member = null;

        if ($loanId > 0) {
            $loan = $this->loanModel->getLoanWithDetails($loanId);
            if ($loan) {
                $outstandingBalance = $this->repaymentModel->getOutstandingBalance($loanId);
                $member = $this->memberModel->getMember($loan['member_id']);
            }
        } elseif ($memberId > 0) {
            $member = $this->memberModel->getMember($memberId);
        }

        // Get active loans for dropdown
        $loans = $this->loanModel->all(['status' => ['Approved', 'Active']], 'loan_no', 'ASC');

        // Get members for dropdown (Admins only)
        $members = null;
        if ($this->auth->isAdmin()) {
            $members = $this->memberModel->all(['status' => 'Active'], 'full_name', 'ASC');
        }

        $data = [
            'title' => 'Record Repayment - ' . APP_NAME,
            'loan' => $loan,
            'member' => $member,
            'outstanding_balance' => $outstandingBalance,
            'loans' => $loans,
            'members' => $members,
            'loan_id' => $loanId,
            'member_id' => $memberId,
            'payment_methods' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];

        $this->render('repayments.create', $data, 'main');
    }

    /**
     * Store a new repayment
     * 
     * @return void
     */
    public function store(): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }

            $loanId = (int)$this->input('loan_id');
            $memberId = (int)$this->input('member_id');

            // If member not specified, get from loan
            if ($memberId <= 0 && $loanId > 0) {
                $loan = $this->loanModel->find($loanId);
                if ($loan) {
                    $memberId = $loan['member_id'];
                }
            }

            // Validate loan exists
            if ($loanId <= 0) {
                throw new \Exception('Please select a loan.');
            }

            // Get form data
            $data = [
                'loan_id' => $loanId,
                'member_id' => $memberId,
                'amount' => (float)$this->input('amount'),
                'payment_date' => $this->input('payment_date'),
                'payment_method' => $this->input('payment_method'),
                'reference_no' => $this->input('reference_no'),
                'remarks' => $this->input('remarks'),
                'created_by' => $this->getUserId()
            ];

            // Create repayment
            $repaymentId = $this->repaymentModel->create($data);

            if ($repaymentId) {
                // Get repayment details
                $repayment = $this->repaymentModel->getRepaymentWithDetails($repaymentId);

                // Log activity
                ActivityLogger::log(
                    'CREATE',
                    'repayments',
                    $repaymentId,
                    "Repayment recorded: {$data['amount']} for loan ID: {$loanId}",
                    null,
                    $data
                );

                if ($this->isAjax()) {
                    $this->jsonSuccess('Repayment recorded successfully!', [
                        'repayment_id' => $repaymentId,
                        'repayment' => $repayment
                    ]);
                } else {
                    $this->session->flash('success', 'Repayment recorded successfully!');
                    $this->redirect('/repayments/' . $repaymentId);
                }
            } else {
                throw new \Exception('Failed to record repayment. Please try again.');
            }

        } catch (ValidationException $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage(), $e->getErrors());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/repayments/create?loan_id=' . $this->input('loan_id'));
            }
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/repayments/create?loan_id=' . $this->input('loan_id'));
            }
        }
    }

    /**
     * View repayment details
     * 
     * @param int $id Repayment ID
     * @return void
     */
    public function view(int $id): void
    {
        $repayment = $this->repaymentModel->getRepaymentWithDetails($id);

        if (!$repayment) {
            $this->session->flash('error', 'Repayment not found.');
            $this->redirect('/repayments');
            return;
        }

        // Get loan details
        $loan = $this->loanModel->getLoanWithDetails($repayment['loan_id']);
        $outstandingBalance = $this->repaymentModel->getOutstandingBalance($repayment['loan_id']);

        $data = [
            'title' => 'Repayment Details - ' . APP_NAME,
            'repayment' => $repayment,
            'loan' => $loan,
            'outstanding_balance' => $outstandingBalance,
            'can_edit' => $this->auth->hasRole(['Admin', 'Treasurer']),
            'can_delete' => $this->auth->isAdmin(),
            'csrf_token' => $this->csrfToken()
        ];

        $this->render('repayments.show', $data, 'main');
    }

    /**
     * Show edit repayment form
     * 
     * @param int $id Repayment ID
     * @return void
     */
    public function edit(int $id): void
    {
        $repayment = $this->repaymentModel->getRepaymentWithDetails($id);

        if (!$repayment) {
            $this->session->flash('error', 'Repayment not found.');
            $this->redirect('/repayments');
            return;
        }

        // Get loan details
        $loan = $this->loanModel->getLoanWithDetails($repayment['loan_id']);
        $outstandingBalance = $this->repaymentModel->getOutstandingBalance($repayment['loan_id']);

        $data = [
            'title' => 'Edit Repayment - ' . APP_NAME,
            'repayment' => $repayment,
            'loan' => $loan,
            'outstanding_balance' => $outstandingBalance,
            'payment_methods' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];

        $this->render('repayments.edit', $data, 'main');
    }

    /**
     * Update a repayment
     * 
     * @param int $id Repayment ID
     * @return void
     */
    public function update(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }

            // Get current repayment
            $currentRepayment = $this->repaymentModel->getRepaymentWithDetails($id);
            if (!$currentRepayment) {
                throw new \Exception('Repayment not found.');
            }

            // Get form data
            $data = [
                'amount' => (float)$this->input('amount'),
                'payment_date' => $this->input('payment_date'),
                'payment_method' => $this->input('payment_method'),
                'reference_no' => $this->input('reference_no'),
                'remarks' => $this->input('remarks')
            ];

            // Update repayment
            if ($this->repaymentModel->update($id, $data)) {
                // Log activity
                ActivityLogger::log(
                    'UPDATE',
                    'repayments',
                    $id,
                    "Repayment updated: {$data['amount']} for loan ID: {$currentRepayment['loan_id']}",
                    $currentRepayment,
                    $data
                );

                $this->session->flash('success', 'Repayment updated successfully!');
                $this->redirect('/repayments/' . $id);
            } else {
                throw new \Exception('Failed to update repayment. Please try again.');
            }

        } catch (ValidationException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/repayments/' . $id . '/edit');
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/repayments/' . $id . '/edit');
        }
    }

    /**
     * Delete a repayment
     * 
     * @param int $id Repayment ID
     * @return void
     */
    public function delete(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }

            // Only admins can delete
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Only administrators can delete repayments.');
            }

            // Get repayment for logging
            $repayment = $this->repaymentModel->getRepaymentWithDetails($id);
            if (!$repayment) {
                throw new \Exception('Repayment not found.');
            }

            // Delete repayment
            if ($this->repaymentModel->delete($id)) {
                // Log activity
                ActivityLogger::log(
                    'DELETE',
                    'repayments',
                    $id,
                    "Repayment deleted: {$repayment['amount']} for loan: {$repayment['loan_no']}",
                    $repayment
                );

                $this->session->flash('success', 'Repayment deleted successfully!');
                $this->redirect('/repayments');
            } else {
                throw new \Exception('Failed to delete repayment.');
            }

        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/repayments');
        }
    }

    /**
     * Get outstanding balance via AJAX
     * 
     * @return void
     */
    public function getBalance(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }

            $loanId = (int)$this->input('loan_id');

            if ($loanId <= 0) {
                throw new \Exception('Invalid loan ID');
            }

            $loan = $this->loanModel->getLoanWithDetails($loanId);
            if (!$loan) {
                throw new \Exception('Loan not found');
            }

            $outstandingBalance = $this->repaymentModel->getOutstandingBalance($loanId);
            $totalPaid = $this->repaymentModel->getTotalPaid($loanId);

            $this->jsonSuccess('Balance retrieved', [
                'outstanding_balance' => $outstandingBalance,
                'total_paid' => $totalPaid,
                'loan_amount' => $loan['amount'],
                'total_repayable' => $loan['total_repayable'],
                'loan_no' => $loan['loan_no'],
                'member_name' => $loan['member_name'],
                'member_no' => $loan['member_no']
            ]);

        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get loan details via AJAX
     * 
     * @return void
     */
    public function getLoanDetails(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }

            $loanId = (int)$this->input('loan_id');

            if ($loanId <= 0) {
                throw new \Exception('Invalid loan ID');
            }

            $loan = $this->loanModel->getLoanWithDetails($loanId);
            if (!$loan) {
                throw new \Exception('Loan not found');
            }

            $outstandingBalance = $this->repaymentModel->getOutstandingBalance($loanId);

            $this->jsonSuccess('Loan details retrieved', [
                'loan' => $loan,
                'outstanding_balance' => $outstandingBalance
            ]);

        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Export repayment report
     * 
     * @param string $format Export format
     * @return void
     */
    public function export(string $format = 'csv'): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer'])) {
                throw new \Exception('You do not have permission to export repayment data.');
            }

            // Build filters
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'loan_id' => (int)$this->input('loan_id', 0),
                'payment_method' => $this->input('payment_method'),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date')
            ];
            $filters = array_filter($filters);

            // Get data
            $repayments = $this->repaymentModel->getReportData($filters);

            if (empty($repayments)) {
                $this->session->flash('error', 'No data to export.');
                $this->redirect('/repayments');
                return;
            }

            // Export
            if ($format === 'csv') {
                $this->exportCsv($repayments);
            } elseif ($format === 'excel') {
                $this->exportExcel($repayments);
            } elseif ($format === 'pdf') {
                $this->exportPdf($repayments);
            } else {
                throw new \Exception('Invalid export format.');
            }

        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/repayments');
        }
    }

    /**
     * Export as CSV
     * 
     * @param array $repayments Repayment data
     * @return void
     */
    private function exportCsv(array $repayments): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="repayments_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'Receipt No',
            'Loan No',
            'Member',
            'Member No',
            'Amount',
            'Principal',
            'Interest',
            'Balance After',
            'Payment Date',
            'Payment Method',
            'Reference No',
            'Recorded By'
        ]);

        foreach ($repayments as $repayment) {
            fputcsv($output, [
                $repayment['receipt_no'],
                $repayment['loan_no'],
                $repayment['member_name'],
                $repayment['member_no'],
                number_format($repayment['amount'], 2),
                number_format($repayment['principal_amount'], 2),
                number_format($repayment['interest_amount'], 2),
                number_format($repayment['balance_after'], 2),
                $repayment['payment_date'],
                $repayment['payment_method'],
                $repayment['reference_no'] ?? '',
                $repayment['recorded_by_name']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export as Excel
     * 
     * @param array $repayments Repayment data
     * @return void
     */
    private function exportExcel(array $repayments): void
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="repayments_' . date('Y-m-d') . '.xls"');

        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h2>Repayment Report - ' . APP_NAME . '</h2>';
        echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2;">';
        echo '<th>Receipt No</th><th>Loan No</th><th>Member</th>';
        echo '<th>Member No</th><th>Amount</th><th>Principal</th>';
        echo '<th>Interest</th><th>Balance After</th>';
        echo '<th>Payment Date</th><th>Payment Method</th>';
        echo '<th>Reference No</th><th>Recorded By</th>';
        echo '</tr>';

        foreach ($repayments as $repayment) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($repayment['receipt_no']) . '</td>';
            echo '<td>' . htmlspecialchars($repayment['loan_no']) . '</td>';
            echo '<td>' . htmlspecialchars($repayment['member_name']) . '</td>';
            echo '<td>' . htmlspecialchars($repayment['member_no']) . '</td>';
            echo '<td>' . number_format($repayment['amount'], 2) . '</td>';
            echo '<td>' . number_format($repayment['principal_amount'], 2) . '</td>';
            echo '<td>' . number_format($repayment['interest_amount'], 2) . '</td>';
            echo '<td>' . number_format($repayment['balance_after'], 2) . '</td>';
            echo '<td>' . $repayment['payment_date'] . '</td>';
            echo '<td>' . htmlspecialchars($repayment['payment_method']) . '</td>';
            echo '<td>' . htmlspecialchars($repayment['reference_no'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($repayment['recorded_by_name']) . '</td>';
            echo '</tr>';
        }

        echo '</table></body></html>';
        exit;
    }

    /**
     * Export as PDF
     * 
     * @param array $repayments Repayment data
     * @return void
     */
    private function exportPdf(array $repayments): void
    {
        $pdfGenerator = new \App\Helpers\PdfGenerator();
        
        $columns = [
            ['field' => 'receipt_no', 'label' => 'Receipt No', 'width' => 18],
            ['field' => 'loan_no', 'label' => 'Loan No', 'width' => 15],
            ['field' => 'member_name', 'label' => 'Member', 'width' => 25],
            ['field' => 'amount', 'label' => 'Amount', 'width' => 18, 'format' => 'currency'],
            ['field' => 'payment_date', 'label' => 'Date', 'width' => 15, 'format' => 'date'],
            ['field' => 'payment_method', 'label' => 'Method', 'width' => 18]
        ];

        $content = $pdfGenerator->generateGenericReport(
            $repayments, 
            'Repayment Report', 
            $columns
        );

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="repayments_' . date('Y-m-d') . '.pdf"');
        echo $content;
        exit;
    }
}