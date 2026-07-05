<?php
/**
 * Fine Controller
 * 
 * Handles all fine operations including imposing fines, payments,
 * waiving, and reporting.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\Fine;
use App\Models\FineType;
use App\Models\FinePayment;
use App\Models\Member;
use App\Helpers\ActivityLogger;
use App\Exceptions\ValidationException;

class FineController extends BaseController
{
    /**
     * @var Fine Fine model instance
     */
    private Fine $fineModel;

    /**
     * @var FineType Fine type model instance
     */
    private FineType $fineTypeModel;

    /**
     * @var FinePayment Fine payment model instance
     */
    private FinePayment $finePaymentModel;

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
        $this->fineModel = new Fine();
        $this->fineTypeModel = new FineType();
        $this->finePaymentModel = new FinePayment();
        $this->memberModel = new Member();
        
        // Require authentication
        $this->requireAuth();
        
        // Only admins and treasurers can manage fines
        $this->requireRole(['Admin', 'Treasurer']);
    }

    /**
     * List all fines
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
            'status' => $this->input('status'),
            'fine_type_id' => (int)$this->input('fine_type_id', 0),
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
            'search' => $this->input('search')
        ];
        $filters = array_filter($filters);
        
        // Get fines
        $result = $this->fineModel->getFinesWithDetails($page, $perPage, $filters);
        
        // Get statistics
        $stats = $this->fineModel->getStats();
        
        // Get fine types for filter
        $fineTypes = $this->fineTypeModel->getActive();
        
        // Get members for filter
        $members = $this->memberModel->all(['status' => 'Active'], 'full_name', 'ASC');
        
        $data = [
            'title' => 'Fine Management - ' . APP_NAME,
            'fines' => $result['data'],
            'pagination' => $result,
            'stats' => $stats,
            'filters' => $filters,
            'fine_types' => $fineTypes,
            'members' => $members,
            'per_page' => $perPage,
            'statuses' => ['Pending', 'Partially_Paid', 'Paid', 'Waived'],
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.index', $data, 'main');
    }

    /**
     * Show impose fine form
     * 
     * @return void
     */
    public function create(): void
    {
        $memberId = (int)$this->input('member_id', 0);
        
        $data = [
            'title' => 'Impose Fine - ' . APP_NAME,
            'member_id' => $memberId,
            'members' => $this->memberModel->all(['status' => 'Active'], 'full_name', 'ASC'),
            'fine_types' => $this->fineTypeModel->getActive(),
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.create', $data, 'main');
    }

    /**
     * Store a new fine
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
            
            // Get form data
            $data = [
                'member_id' => (int)$this->input('member_id'),
                'fine_type_id' => (int)$this->input('fine_type_id'),
                'amount' => (float)$this->input('amount', 0),
                'fine_date' => $this->input('fine_date'),
                'due_date' => $this->input('due_date'),
                'description' => $this->input('description'),
                'created_by' => $this->getUserId()
            ];
            
            // Create fine
            $fineId = $this->fineModel->create($data);
            
            if ($fineId) {
                // Log activity
                ActivityLogger::log(
                    'CREATE',
                    'fines',
                    $fineId,
                    "Fine imposed: {$data['amount']} for member ID: {$data['member_id']}",
                    null,
                    $data
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Fine imposed successfully!', ['fine_id' => $fineId]);
                } else {
                    $this->session->flash('success', 'Fine imposed successfully!');
                    $this->redirect('/fines/' . $fineId);
                }
            } else {
                throw new \Exception('Failed to impose fine. Please try again.');
            }
            
        } catch (ValidationException $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage(), $e->getErrors());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/fines/create');
            }
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/fines/create');
            }
        }
    }

    /**
     * View fine details
     * 
     * @param int $id Fine ID
     * @return void
     */
    public function view(int $id): void
    {
        $fine = $this->fineModel->getFineWithDetails($id);
        
        if (!$fine) {
            $this->session->flash('error', 'Fine not found.');
            $this->redirect('/fines');
            return;
        }
        
        $data = [
            'title' => 'Fine Details - ' . APP_NAME,
            'fine' => $fine,
            'can_edit' => $fine['status'] !== 'Paid' && $fine['status'] !== 'Waived',
            'can_waive' => $fine['status'] !== 'Paid' && $fine['status'] !== 'Waived',
            'can_pay' => $fine['status'] !== 'Paid' && $fine['status'] !== 'Waived',
            'payment_methods' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.view', $data, 'main');
    }

    /**
     * Show edit fine form
     * 
     * @param int $id Fine ID
     * @return void
     */
    public function edit(int $id): void
    {
        $fine = $this->fineModel->getFineWithDetails($id);
        
        if (!$fine) {
            $this->session->flash('error', 'Fine not found.');
            $this->redirect('/fines');
            return;
        }
        
        if ($fine['status'] === 'Paid' || $fine['status'] === 'Waived') {
            $this->session->flash('error', 'Cannot edit a paid or waived fine.');
            $this->redirect('/fines/' . $id);
            return;
        }
        
        $data = [
            'title' => 'Edit Fine - ' . APP_NAME,
            'fine' => $fine,
            'members' => $this->memberModel->all(['status' => 'Active'], 'full_name', 'ASC'),
            'fine_types' => $this->fineTypeModel->getActive(),
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.edit', $data, 'main');
    }

    /**
     * Update a fine
     * 
     * @param int $id Fine ID
     * @return void
     */
    public function update(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get current fine for logging
            $currentFine = $this->fineModel->getFineWithDetails($id);
            if (!$currentFine) {
                throw new \Exception('Fine not found.');
            }
            
            // Get form data
            $data = [
                'member_id' => (int)$this->input('member_id'),
                'fine_type_id' => (int)$this->input('fine_type_id'),
                'amount' => (float)$this->input('amount', 0),
                'fine_date' => $this->input('fine_date'),
                'due_date' => $this->input('due_date'),
                'description' => $this->input('description')
            ];
            
            // Update fine
            if ($this->fineModel->update($id, $data)) {
                // Log activity
                ActivityLogger::log(
                    'UPDATE',
                    'fines',
                    $id,
                    "Fine updated: {$data['amount']} for member ID: {$data['member_id']}",
                    $currentFine,
                    $data
                );
                
                $this->session->flash('success', 'Fine updated successfully!');
                $this->redirect('/fines/' . $id);
            } else {
                throw new \Exception('Failed to update fine. Please try again.');
            }
            
        } catch (ValidationException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines/' . $id . '/edit');
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines/' . $id . '/edit');
        }
    }

    /**
     * Delete a fine
     * 
     * @param int $id Fine ID
     * @return void
     */
    public function delete(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get fine for logging
            $fine = $this->fineModel->getFineWithDetails($id);
            if (!$fine) {
                throw new \Exception('Fine not found.');
            }
            
            if ($fine['status'] === 'Paid') {
                throw new \Exception('Cannot delete a paid fine.');
            }
            
            // Check if there are payments
            if (!empty($fine['payments'])) {
                throw new \Exception('Cannot delete a fine with payments. Consider waiving instead.');
            }
            
            // Delete fine
            if ($this->fineModel->delete($id)) {
                // Log activity
                ActivityLogger::log(
                    'DELETE',
                    'fines',
                    $id,
                    "Fine deleted: {$fine['amount']} for member: {$fine['member_name']}",
                    $fine
                );
                
                $this->session->flash('success', 'Fine deleted successfully!');
            } else {
                throw new \Exception('Failed to delete fine.');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
        }
        
        $this->redirect('/fines');
    }

    /**
     * Process fine payment
     * 
     * @param int $id Fine ID
     * @return void
     */
    public function pay(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get fine
            $fine = $this->fineModel->getFineWithDetails($id);
            if (!$fine) {
                throw new \Exception('Fine not found.');
            }
            
            if ($fine['status'] === 'Paid') {
                throw new \Exception('This fine is already fully paid.');
            }
            
            // Get payment data
            $data = [
                'fine_id' => $id,
                'amount_paid' => (float)$this->input('amount_paid'),
                'payment_date' => $this->input('payment_date', date('Y-m-d')),
                'payment_method' => $this->input('payment_method'),
                'reference_no' => $this->input('reference_no'),
                'remarks' => $this->input('remarks'),
                'created_by' => $this->getUserId()
            ];
            
            // Create payment
            $paymentId = $this->finePaymentModel->create($data);
            
            if ($paymentId) {
                // Get payment details
                $payment = $this->finePaymentModel->getWithDetails($paymentId);
                
                // Log activity
                ActivityLogger::log(
                    'PAYMENT',
                    'fine_payments',
                    $paymentId,
                    "Fine payment: {$data['amount_paid']} for fine ID: {$id}",
                    null,
                    $data
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Payment recorded successfully!', ['payment' => $payment]);
                } else {
                    $this->session->flash('success', 'Payment recorded successfully!');
                    $this->redirect('/fines/' . $id);
                }
            } else {
                throw new \Exception('Failed to record payment. Please try again.');
            }
            
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/fines/' . $id);
            }
        }
    }

    /**
     * Waive a fine
     * 
     * @param int $id Fine ID
     * @return void
     */
    public function waive(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get fine
            $fine = $this->fineModel->getFineWithDetails($id);
            if (!$fine) {
                throw new \Exception('Fine not found.');
            }
            
            if ($fine['status'] === 'Paid') {
                throw new \Exception('Cannot waive a paid fine.');
            }
            
            if ($fine['status'] === 'Waived') {
                throw new \Exception('Fine is already waived.');
            }
            
            $reason = $this->input('reason', 'Fine waived by administrator');
            
            // Waive fine
            if ($this->fineModel->waive($id, $this->getUserId(), $reason)) {
                // Log activity
                ActivityLogger::log(
                    'WAIVE',
                    'fines',
                    $id,
                    "Fine waived. Reason: {$reason}"
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Fine waived successfully!');
                } else {
                    $this->session->flash('success', 'Fine waived successfully!');
                    $this->redirect('/fines/' . $id);
                }
            } else {
                throw new \Exception('Failed to waive fine. Please try again.');
            }
            
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/fines/' . $id);
            }
        }
    }

    /**
     * Fine types management
     * 
     * @return void
     */
    public function types(): void
    {
        // Only admins can manage fine types
        if (!$this->auth->isAdmin()) {
            $this->session->flash('error', 'Only administrators can manage fine types.');
            $this->redirect('/fines');
            return;
        }
        
        $fineTypes = $this->fineTypeModel->all([], 'name', 'ASC');
        $stats = $this->fineTypeModel->getStats();
        
        $data = [
            'title' => 'Fine Types - ' . APP_NAME,
            'fine_types' => $fineTypes,
            'stats' => $stats,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.types', $data, 'main');
    }

    /**
     * Create fine type form
     * 
     * @return void
     */
    public function typeCreate(): void
    {
        if (!$this->auth->isAdmin()) {
            $this->session->flash('error', 'Unauthorized access.');
            $this->redirect('/fines/types');
            return;
        }
        
        $data = [
            'title' => 'Create Fine Type - ' . APP_NAME,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.type-create', $data, 'main');
    }

    /**
     * Store fine type
     * 
     * @return void
     */
    public function typeStore(): void
    {
        try {
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Unauthorized access.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            $data = [
                'name' => $this->input('name'),
                'description' => $this->input('description'),
                'default_amount' => (float)$this->input('default_amount', 0),
                'is_percentage' => (int)$this->input('is_percentage', 0),
                'status' => $this->input('status', 'Active')
            ];
            
            if ($this->fineTypeModel->create($data)) {
                // Log activity
                ActivityLogger::log(
                    'CREATE',
                    'fine_types',
                    null,
                    "Fine type created: {$data['name']}",
                    null,
                    $data
                );
                
                $this->session->flash('success', 'Fine type created successfully!');
                $this->redirect('/fines/types');
            } else {
                throw new \Exception('Failed to create fine type.');
            }
            
        } catch (ValidationException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines/types/create');
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines/types/create');
        }
    }

    /**
     * Edit fine type form
     * 
     * @param int $id Fine type ID
     * @return void
     */
    public function typeEdit(int $id): void
    {
        if (!$this->auth->isAdmin()) {
            $this->session->flash('error', 'Unauthorized access.');
            $this->redirect('/fines/types');
            return;
        }
        
        $fineType = $this->fineTypeModel->find($id);
        
        if (!$fineType) {
            $this->session->flash('error', 'Fine type not found.');
            $this->redirect('/fines/types');
            return;
        }
        
        $data = [
            'title' => 'Edit Fine Type - ' . APP_NAME,
            'fine_type' => $fineType,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('fines.type-edit', $data, 'main');
    }

    /**
     * Update fine type
     * 
     * @param int $id Fine type ID
     * @return void
     */
    public function typeUpdate(int $id): void
    {
        try {
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Unauthorized access.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            $data = [
                'name' => $this->input('name'),
                'description' => $this->input('description'),
                'default_amount' => (float)$this->input('default_amount', 0),
                'is_percentage' => (int)$this->input('is_percentage', 0),
                'status' => $this->input('status', 'Active')
            ];
            
            if ($this->fineTypeModel->update($id, $data)) {
                // Log activity
                ActivityLogger::log(
                    'UPDATE',
                    'fine_types',
                    $id,
                    "Fine type updated: {$data['name']}"
                );
                
                $this->session->flash('success', 'Fine type updated successfully!');
                $this->redirect('/fines/types');
            } else {
                throw new \Exception('Failed to update fine type.');
            }
            
        } catch (ValidationException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines/types/' . $id . '/edit');
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines/types/' . $id . '/edit');
        }
    }

    /**
     * Delete fine type
     * 
     * @param int $id Fine type ID
     * @return void
     */
    public function typeDelete(int $id): void
    {
        try {
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Unauthorized access.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Check if has active fines
            if ($this->fineTypeModel->hasActiveFines($id)) {
                throw new \Exception('Cannot delete fine type with active fines. Deactivate it instead.');
            }
            
            if ($this->fineTypeModel->delete($id)) {
                ActivityLogger::log('DELETE', 'fine_types', $id, "Fine type deleted");
                $this->session->flash('success', 'Fine type deleted successfully!');
            } else {
                throw new \Exception('Failed to delete fine type.');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
        }
        
        $this->redirect('/fines/types');
    }

    /**
     * Export fine report
     * 
     * @param string $format Export format
     * @return void
     */
    public function export(string $format = 'csv'): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer'])) {
                throw new \Exception('You do not have permission to export fine data.');
            }
            
            // Build filters
            $filters = [
                'status' => $this->input('status'),
                'fine_type_id' => (int)$this->input('fine_type_id', 0),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date'),
                'member_id' => (int)$this->input('member_id', 0)
            ];
            $filters = array_filter($filters);
            
            // Get data
            $fines = $this->fineModel->getReportData($filters);
            
            if (empty($fines)) {
                $this->session->flash('error', 'No data to export.');
                $this->redirect('/fines');
                return;
            }
            
            // Export
            if ($format === 'csv') {
                $this->exportCsv($fines);
            } else {
                $this->exportExcel($fines);
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/fines');
        }
    }

    /**
     * Export as CSV
     * 
     * @param array $fines Fine data
     * @return void
     */
    private function exportCsv(array $fines): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fines_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, [
            'Member',
            'Member No',
            'Fine Type',
            'Amount',
            'Paid',
            'Outstanding',
            'Status',
            'Fine Date',
            'Due Date'
        ]);
        
        foreach ($fines as $fine) {
            fputcsv($output, [
                $fine['member_name'],
                $fine['member_no'],
                $fine['fine_type_name'],
                number_format($fine['amount'], 2),
                number_format($fine['total_paid'] ?? 0, 2),
                number_format($fine['outstanding'] ?? $fine['amount'], 2),
                $fine['status'],
                $fine['fine_date'],
                $fine['due_date']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export as Excel
     * 
     * @param array $fines Fine data
     * @return void
     */
    private function exportExcel(array $fines): void
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="fines_' . date('Y-m-d') . '.xls"');
        
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h2>Fine Report - ' . APP_NAME . '</h2>';
        echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2;">';
        echo '<th>Member</th><th>Member No</th><th>Fine Type</th>';
        echo '<th>Amount</th><th>Paid</th><th>Outstanding</th>';
        echo '<th>Status</th><th>Fine Date</th><th>Due Date</th>';
        echo '</tr>';
        
        foreach ($fines as $fine) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($fine['member_name']) . '</td>';
            echo '<td>' . htmlspecialchars($fine['member_no']) . '</td>';
            echo '<td>' . htmlspecialchars($fine['fine_type_name']) . '</td>';
            echo '<td>' . number_format($fine['amount'], 2) . '</td>';
            echo '<td>' . number_format($fine['total_paid'] ?? 0, 2) . '</td>';
            echo '<td>' . number_format($fine['outstanding'] ?? $fine['amount'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($fine['status']) . '</td>';
            echo '<td>' . $fine['fine_date'] . '</td>';
            echo '<td>' . $fine['due_date'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit;
    }
}