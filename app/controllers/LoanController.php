<?php
/**
 * Loan Controller
 * 
 * Handles all loan operations including application, approval,
 * rejection, and viewing.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Member;
use App\Helpers\ActivityLogger;
use App\Exceptions\ValidationException;

class LoanController extends BaseController
{
    /**
     * @var Loan Loan model instance
     */
    private Loan $loanModel;

    /**
     * @var LoanType Loan type model instance
     */
    private LoanType $loanTypeModel;

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
        $this->loanModel = new Loan();
        $this->loanTypeModel = new LoanType();
        $this->memberModel = new Member();
        
        // Require authentication
        $this->requireAuth();
    }

    /**
     * List all loans
     * 
     * @return void
     */
    public function index(): void
    {
        // Check permissions
        if (!$this->auth->hasRole(['Admin', 'Treasurer']) && !$this->auth->isMember()) {
            $this->session->flash('error', 'You do not have permission to view loans.');
            $this->redirect('/dashboard');
            return;
        }
        
        $page = (int)$this->input('page', 1);
        $page = max(1, $page);
        
        $perPage = (int)$this->input('per_page', 20);
        $perPage = min(100, max(5, $perPage));
        
        // If member, only show their loans
        $memberId = null;
        if ($this->auth->isMember()) {
            $user = $this->auth->user();
            $member = $this->memberModel->findBy('user_id', $user['id']);
            if ($member) {
                $memberId = $member['id'];
            }
        } else {
            $memberId = (int)$this->input('member_id', 0);
        }
        
        // Build filters
        $filters = [
            'member_id' => $memberId ?: null,
            'status' => $this->input('status'),
            'loan_type_id' => (int)$this->input('loan_type_id', 0),
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
            'search' => $this->input('search')
        ];
        $filters = array_filter($filters);
        
        // Get loans
        $result = $this->loanModel->getLoansWithDetails($page, $perPage, $filters);
        
        // Get statistics
        if ($this->auth->isMember()) {
            $stats = $this->loanModel->getStatusCounts($memberId);
        } else {
            $stats = $this->loanModel->getStats();
        }
        
        // Get loan types for filter
        $loanTypes = $this->loanTypeModel->getActive();
        
        $data = [
            'title' => 'Loan Management - ' . APP_NAME,
            'loans' => $result['data'],
            'pagination' => $result,
            'stats' => $stats,
            'filters' => $filters,
            'loan_types' => $loanTypes,
            'per_page' => $perPage,
            'is_member' => $this->auth->isMember(),
            'is_admin' => $this->auth->isAdmin(),
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('loans.index', $data, 'main');
    }

    /**
     * Show loan application form
     * 
     * @return void
     */
    public function create(): void
    {
        // Only members can apply for loans
        if (!$this->auth->isMember() && !$this->auth->isAdmin()) {
            $this->session->flash('error', 'Only members can apply for loans.');
            $this->redirect('/loans');
            return;
        }
        
        // Get member ID for current user
        $memberId = null;
        if ($this->auth->isMember()) {
            $user = $this->auth->user();
            $member = $this->memberModel->findBy('user_id', $user['id']);
            if ($member) {
                $memberId = $member['id'];
            } else {
                $this->session->flash('error', 'Member profile not found.');
                $this->redirect('/loans');
                return;
            }
        } else {
            $memberId = (int)$this->input('member_id', 0);
        }
        
        $loanTypes = $this->loanTypeModel->getActive();
        
        $data = [
            'title' => 'Apply for Loan - ' . APP_NAME,
            'member_id' => $memberId,
            'members' => $this->auth->isAdmin() ? $this->getMembersList() : null,
            'loan_types' => $loanTypes,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('loans.create', $data, 'main');
    }

    /**
     * Store loan application
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
            
            // Check if member can apply
            if ($this->auth->isMember()) {
                $user = $this->auth->user();
                $member = $this->memberModel->findBy('user_id', $user['id']);
                if (!$member) {
                    throw new \Exception('Member profile not found.');
                }
                $memberId = $member['id'];
            } else {
                $memberId = (int)$this->input('member_id');
            }
            
            // Get loan type
            $loanTypeId = (int)$this->input('loan_type_id');
            $loanType = $this->loanTypeModel->find($loanTypeId);
            if (!$loanType) {
                throw new \Exception('Invalid loan type selected.');
            }
            
            // Get form data
            $data = [
                'member_id' => $memberId,
                'loan_type_id' => $loanTypeId,
                'amount' => (float)$this->input('amount'),
                'interest_rate' => $loanType['default_rate'],
                'duration_months' => (int)$this->input('duration_months', 12),
                'application_date' => date('Y-m-d'),
                'remarks' => $this->input('remarks'),
                'created_by' => $this->getUserId()
            ];
            
            // Create loan
            $loanId = $this->loanModel->create($data);
            
            if ($loanId) {
                // Log activity
                ActivityLogger::log(
                    'CREATE',
                    'loans',
                    $loanId,
                    "Loan application submitted: {$data['amount']} for member ID: {$memberId}",
                    null,
                    $data
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Loan application submitted successfully!', [
                        'loan_id' => $loanId
                    ]);
                } else {
                    $this->session->flash('success', 'Loan application submitted successfully!');
                    $this->redirect('/loans/' . $loanId);
                }
            } else {
                throw new \Exception('Failed to submit loan application. Please try again.');
            }
            
        } catch (ValidationException $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage(), $e->getErrors());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/loans/create');
            }
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/loans/create');
            }
        }
    }

    /**
     * View loan details
     * 
     * @param int $id Loan ID
     * @return void
     */
    public function view(int $id): void
    {
        $loan = $this->loanModel->getLoanWithDetails($id);
        
        if (!$loan) {
            $this->session->flash('error', 'Loan not found.');
            $this->redirect('/loans');
            return;
        }
        
        // Check permission
        if ($this->auth->isMember()) {
            $user = $this->auth->user();
            $member = $this->memberModel->findBy('user_id', $user['id']);
            if ($member && $loan['member_id'] != $member['id']) {
                $this->session->flash('error', 'You do not have permission to view this loan.');
                $this->redirect('/loans');
                return;
            }
        }
        
        $data = [
            'title' => 'Loan Details - ' . APP_NAME,
            'loan' => $loan,
            'can_approve' => $this->auth->isAdmin() && $loan['status'] === 'Pending',
            'can_edit' => $this->auth->isAdmin() && $loan['status'] === 'Pending',
            'can_delete' => $this->auth->isAdmin() && $loan['status'] === 'Pending',
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('loans.view', $data, 'main');
    }

    /**
     * Show loan approval form
     * 
     * @param int $id Loan ID
     * @return void
     */
    public function approve(int $id): void
    {
        // Only admins can approve
        if (!$this->auth->isAdmin()) {
            $this->session->flash('error', 'Only administrators can approve loans.');
            $this->redirect('/loans');
            return;
        }
        
        $loan = $this->loanModel->getLoanWithDetails($id);
        
        if (!$loan) {
            $this->session->flash('error', 'Loan not found.');
            $this->redirect('/loans');
            return;
        }
        
        if ($loan['status'] !== 'Pending') {
            $this->session->flash('error', 'Only pending loans can be approved.');
            $this->redirect('/loans/' . $id);
            return;
        }
        
        $data = [
            'title' => 'Approve Loan - ' . APP_NAME,
            'loan' => $loan,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('loans.approve', $data, 'main');
    }

    /**
     * Process loan approval
     * 
     * @param int $id Loan ID
     * @return void
     */
    public function processApprove(int $id): void
    {
        try {
            // Only admins can approve
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Only administrators can approve loans.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            $remarks = $this->input('remarks', 'Loan approved');
            $approvedBy = $this->getUserId();
            
            if ($this->loanModel->approve($id, $approvedBy, $remarks)) {
                // Log activity
                ActivityLogger::log(
                    'APPROVE',
                    'loans',
                    $id,
                    "Loan approved by: {$approvedBy}. Remarks: {$remarks}"
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Loan approved successfully!');
                } else {
                    $this->session->flash('success', 'Loan approved successfully!');
                    $this->redirect('/loans/' . $id);
                }
            } else {
                throw new \Exception('Failed to approve loan. Please try again.');
            }
            
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/loans/' . $id . '/approve');
            }
        }
    }

    /**
     * Process loan rejection
     * 
     * @param int $id Loan ID
     * @return void
     */
    public function reject(int $id): void
    {
        try {
            // Only admins can reject
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Only administrators can reject loans.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            $reason = $this->input('reason', 'Loan rejected');
            
            if ($this->loanModel->reject($id, $reason)) {
                // Log activity
                ActivityLogger::log(
                    'REJECT',
                    'loans',
                    $id,
                    "Loan rejected. Reason: {$reason}"
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Loan rejected successfully!');
                } else {
                    $this->session->flash('success', 'Loan rejected successfully!');
                    $this->redirect('/loans/' . $id);
                }
            } else {
                throw new \Exception('Failed to reject loan. Please try again.');
            }
            
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/loans/' . $id);
            }
        }
    }

    /**
     * View loan installments
     * 
     * @param int $id Loan ID
     * @return void
     */
    public function installments(int $id): void
    {
        $loan = $this->loanModel->getLoanWithDetails($id);
        
        if (!$loan) {
            $this->session->flash('error', 'Loan not found.');
            $this->redirect('/loans');
            return;
        }
        
        // Check permission
        if ($this->auth->isMember()) {
            $user = $this->auth->user();
            $member = $this->memberModel->findBy('user_id', $user['id']);
            if ($member && $loan['member_id'] != $member['id']) {
                $this->session->flash('error', 'You do not have permission to view this loan.');
                $this->redirect('/loans');
                return;
            }
        }
        
        $data = [
            'title' => 'Loan Installments - ' . APP_NAME,
            'loan' => $loan,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('loans.installments', $data, 'main');
    }

    /**
     * Export loan report
     * 
     * @param string $format Export format
     * @return void
     */
    public function export(string $format = 'csv'): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer'])) {
                throw new \Exception('You do not have permission to export loan data.');
            }
            
            // Build filters
            $filters = [
                'status' => $this->input('status'),
                'loan_type_id' => (int)$this->input('loan_type_id', 0),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date')
            ];
            $filters = array_filter($filters);
            
            // Get data
            $loans = $this->loanModel->getReportData($filters);
            
            if (empty($loans)) {
                $this->session->flash('error', 'No data to export.');
                $this->redirect('/loans');
                return;
            }
            
            // Export
            if ($format === 'csv') {
                $this->exportCsv($loans);
            } else {
                $this->exportExcel($loans);
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/loans');
        }
    }

    /**
     * Export as CSV
     * 
     * @param array $loans Loan data
     * @return void
     */
    private function exportCsv(array $loans): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="loans_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, [
            'Loan No',
            'Member',
            'Member No',
            'Type',
            'Amount',
            'Interest Rate',
            'Duration',
            'Total Repayable',
            'Status',
            'Application Date',
            'Approval Date'
        ]);
        
        foreach ($loans as $loan) {
            fputcsv($output, [
                $loan['loan_no'],
                $loan['member_name'],
                $loan['member_no'],
                $loan['loan_type_name'],
                number_format($loan['amount'], 2),
                $loan['interest_rate'] . '%',
                $loan['duration_months'] . ' months',
                number_format($loan['total_repayable'], 2),
                $loan['status'],
                $loan['application_date'],
                $loan['approval_date'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export as Excel
     * 
     * @param array $loans Loan data
     * @return void
     */
    private function exportExcel(array $loans): void
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="loans_' . date('Y-m-d') . '.xls"');
        
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h2>Loan Report - ' . APP_NAME . '</h2>';
        echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2;">';
        echo '<th>Loan No</th><th>Member</th><th>Member No</th><th>Type</th>';
        echo '<th>Amount</th><th>Rate</th><th>Duration</th>';
        echo '<th>Total</th><th>Status</th><th>Application Date</th><th>Approval Date</th>';
        echo '</tr>';
        
        foreach ($loans as $loan) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($loan['loan_no']) . '</td>';
            echo '<td>' . htmlspecialchars($loan['member_name']) . '</td>';
            echo '<td>' . htmlspecialchars($loan['member_no']) . '</td>';
            echo '<td>' . htmlspecialchars($loan['loan_type_name']) . '</td>';
            echo '<td>' . number_format($loan['amount'], 2) . '</td>';
            echo '<td>' . $loan['interest_rate'] . '%</td>';
            echo '<td>' . $loan['duration_months'] . ' months</td>';
            echo '<td>' . number_format($loan['total_repayable'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($loan['status']) . '</td>';
            echo '<td>' . $loan['application_date'] . '</td>';
            echo '<td>' . ($loan['approval_date'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit;
    }

    /**
     * Get list of members for dropdown
     * 
     * @return array
     */
    private function getMembersList(): array
    {
        $result = $this->memberModel->getPaginated(1, 9999, ['status' => 'Active']);
        return $result['data'];
    }
}