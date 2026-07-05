<?php
/**
 * Savings Controller
 * 
 * Handles all savings operations including deposits, withdrawals,
 * history viewing, summaries, and receipt generation.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\Savings;
use App\Models\Member;
use App\Helpers\ActivityLogger;
use App\Helpers\ReceiptGenerator;
use App\Exceptions\ValidationException;

class SavingsController extends BaseController
{
    /**
     * @var Savings Savings model instance
     */
    private Savings $savingsModel;

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
        $this->savingsModel = new Savings();
        $this->memberModel = new Member();
        
        // Require authentication
        $this->requireAuth();
        
        // Only admins and treasurers can manage savings
        $this->requireRole(['Admin', 'Treasurer']);
    }

    /**
     * Show savings list/dashboard
     * 
     * @param string $view View type (all, member, monthly)
     * @return void
     */
    public function index(string $view = 'all'): void
    {
        $page = (int)$this->input('page', 1);
        $page = max(1, $page);
        
        $perPage = (int)$this->input('per_page', 20);
        $perPage = min(100, max(5, $perPage));
        
        $memberId = (int)$this->input('member_id', 0);
        
        // Build filters
        $filters = [
            'member_id' => $memberId ?: null,
            'transaction_type' => $this->input('transaction_type'),
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
            'search' => $this->input('search')
        ];
        $filters = array_filter($filters);
        
        // Get transactions
        if ($memberId) {
            $result = $this->savingsModel->getMemberTransactions($memberId, $page, $perPage, $filters);
            $member = $this->memberModel->getMember($memberId);
        } else {
            $result = $this->savingsModel->getTransactionsWithMembers($page, $perPage, $filters);
            $member = null;
        }
        
        // Get summary
        $summary = $this->savingsModel->getTotalSummary();
        
        // Get monthly summary if member selected
        $monthlySummary = null;
        if ($memberId) {
            $yearMonth = $this->input('year_month', date('Y-m'));
            $monthlySummary = $this->savingsModel->getMonthlySummary($memberId, $yearMonth);
        }
        
        $data = [
            'title' => 'Savings Management - ' . APP_NAME,
            'transactions' => $result['data'],
            'pagination' => $result,
            'summary' => $summary,
            'filters' => $filters,
            'member' => $member,
            'monthly_summary' => $monthlySummary,
            'members' => $member ? null : $this->getMembersList(),
            'transaction_types' => ['Deposit', 'Withdrawal'],
            'transaction_modes' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'per_page' => $perPage,
            'csrf_token' => $this->csrfToken(),
            'is_ajax' => $this->isAjax()
        ];
        
        if ($this->isAjax()) {
            $this->jsonSuccess('Data retrieved', $data);
        } else {
            $this->render('savings.index', $data, 'main');
        }
    }

    /**
     * Show create savings form
     * 
     * @return void
     */
    public function create(): void
    {
        $memberId = (int)$this->input('member_id', 0);
        
        $data = [
            'title' => 'Record Savings - ' . APP_NAME,
            'member_id' => $memberId,
            'members' => $this->getMembersList(),
            'transaction_types' => ['Deposit', 'Withdrawal'],
            'transaction_modes' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('savings.create', $data, 'main');
    }

    /**
     * Store a new savings transaction
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
                'transaction_type' => $this->input('transaction_type'),
                'amount' => (float)$this->input('amount'),
                'transaction_date' => $this->input('transaction_date'),
                'description' => $this->input('description'),
                'transaction_mode' => $this->input('transaction_mode'),
                'reference_no' => $this->input('reference_no'),
                'created_by' => $this->getUserId()
            ];
            
            // Create transaction
            $transactionId = $this->savingsModel->create($data);
            
            if ($transactionId) {
                // Get transaction details for receipt
                $transaction = $this->savingsModel->getForReceipt($transactionId);
                
                // Log activity
                ActivityLogger::log(
                    'CREATE',
                    'savings',
                    $transactionId,
                    "Savings transaction recorded: {$data['transaction_type']} of {$data['amount']} for member ID: {$data['member_id']}",
                    null,
                    $data
                );
                
                // Check if AJAX request
                if ($this->isAjax()) {
                    $this->jsonSuccess('Savings recorded successfully!', [
                        'transaction_id' => $transactionId,
                        'transaction' => $transaction
                    ]);
                } else {
                    $this->session->flash('success', 'Savings recorded successfully!');
                    $this->redirect('/savings?member_id=' . $data['member_id']);
                }
            } else {
                throw new \Exception('Failed to record savings. Please try again.');
            }
            
        } catch (ValidationException $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage(), $e->getErrors());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/savings/create');
            }
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/savings/create');
            }
        }
    }

    /**
     * Show edit savings form
     * 
     * @param int $id Transaction ID
     * @return void
     */
    public function edit(int $id): void
    {
        $transaction = $this->savingsModel->getForReceipt($id);
        
        if (!$transaction) {
            $this->session->flash('error', 'Transaction not found.');
            $this->redirect('/savings');
            return;
        }
        
        $data = [
            'title' => 'Edit Savings - ' . APP_NAME,
            'transaction' => $transaction,
            'members' => $this->getMembersList(),
            'transaction_types' => ['Deposit', 'Withdrawal'],
            'transaction_modes' => ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque'],
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('savings.edit', $data, 'main');
    }

    /**
     * Update a savings transaction
     * 
     * @param int $id Transaction ID
     * @return void
     */
    public function update(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get current transaction for logging
            $currentTransaction = $this->savingsModel->getForReceipt($id);
            if (!$currentTransaction) {
                throw new \Exception('Transaction not found.');
            }
            
            // Get form data
            $data = [
                'member_id' => (int)$this->input('member_id'),
                'transaction_type' => $this->input('transaction_type'),
                'amount' => (float)$this->input('amount'),
                'transaction_date' => $this->input('transaction_date'),
                'description' => $this->input('description'),
                'transaction_mode' => $this->input('transaction_mode'),
                'reference_no' => $this->input('reference_no')
            ];
            
            // Update transaction
            if ($this->savingsModel->update($id, $data)) {
                // Get updated transaction
                $updatedTransaction = $this->savingsModel->getForReceipt($id);
                
                // Log activity
                ActivityLogger::log(
                    'UPDATE',
                    'savings',
                    $id,
                    "Savings transaction updated: {$data['transaction_type']} of {$data['amount']} for member ID: {$data['member_id']}",
                    $currentTransaction,
                    $updatedTransaction
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Savings updated successfully!', [
                        'transaction' => $updatedTransaction
                    ]);
                } else {
                    $this->session->flash('success', 'Savings updated successfully!');
                    $this->redirect('/savings?member_id=' . $data['member_id']);
                }
            } else {
                throw new \Exception('Failed to update savings. Please try again.');
            }
            
        } catch (ValidationException $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage(), $e->getErrors());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/savings/' . $id . '/edit');
            }
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/savings/' . $id . '/edit');
            }
        }
    }

    /**
     * Delete a savings transaction
     * 
     * @param int $id Transaction ID
     * @return void
     */
    public function delete(int $id): void
    {
        try {
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get transaction for logging
            $transaction = $this->savingsModel->getForReceipt($id);
            if (!$transaction) {
                throw new \Exception('Transaction not found.');
            }
            
            // Delete transaction
            if ($this->savingsModel->delete($id)) {
                // Log activity
                ActivityLogger::log(
                    'DELETE',
                    'savings',
                    $id,
                    "Savings transaction deleted: {$transaction['transaction_type']} of {$transaction['amount']}",
                    $transaction
                );
                
                if ($this->isAjax()) {
                    $this->jsonSuccess('Savings deleted successfully!');
                } else {
                    $this->session->flash('success', 'Savings deleted successfully!');
                    $this->redirect('/savings?member_id=' . $transaction['member_id']);
                }
            } else {
                throw new \Exception('Failed to delete savings. Please try again.');
            }
            
        } catch (\Exception $e) {
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage());
            } else {
                $this->session->flash('error', $e->getMessage());
                $this->redirect('/savings');
            }
        }
    }

    /**
     * View savings details
     * 
     * @param int $id Transaction ID
     * @return void
     */
    public function view(int $id): void
    {
        $transaction = $this->savingsModel->getForReceipt($id);
        
        if (!$transaction) {
            $this->session->flash('error', 'Transaction not found.');
            $this->redirect('/savings');
            return;
        }
        
        $data = [
            'title' => 'Savings Details - ' . APP_NAME,
            'transaction' => $transaction,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('savings.view', $data, 'main');
    }

    /**
     * Generate printable receipt
     * 
     * @param int $id Transaction ID
     * @return void
     */
    public function receipt(int $id): void
    {
        $transaction = $this->savingsModel->getForReceipt($id);
        
        if (!$transaction) {
            $this->session->flash('error', 'Transaction not found.');
            $this->redirect('/savings');
            return;
        }
        
        $data = [
            'transaction' => $transaction,
            'company_name' => APP_NAME,
            'company_address' => 'Dar es Salaam, Tanzania',
            'company_phone' => '+255 700 000 000',
            'company_email' => 'info@vicoba.com'
        ];
        
        $this->render('savings.receipt', $data, 'print');
    }

    /**
     * Get member balance via AJAX
     * 
     * @return void
     */
    public function getBalance(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $memberId = (int)$this->input('member_id');
            
            if ($memberId <= 0) {
                throw new \Exception('Invalid member ID');
            }
            
            $balance = $this->savingsModel->getCurrentBalance($memberId);
            $member = $this->memberModel->getMember($memberId);
            
            $this->jsonSuccess('Balance retrieved', [
                'balance' => $balance,
                'member_name' => $member['full_name'] ?? 'Unknown',
                'member_no' => $member['member_no'] ?? ''
            ]);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Get monthly summary via AJAX
     * 
     * @return void
     */
    public function getMonthlySummary(): void
    {
        try {
            if (!$this->isAjax()) {
                throw new \Exception('Invalid request');
            }
            
            $memberId = (int)$this->input('member_id');
            $yearMonth = $this->input('year_month', date('Y-m'));
            
            if ($memberId <= 0) {
                throw new \Exception('Invalid member ID');
            }
            
            $summary = $this->savingsModel->getMonthlySummary($memberId, $yearMonth);
            
            $this->jsonSuccess('Monthly summary retrieved', [
                'summary' => $summary,
                'year_month' => $yearMonth
            ]);
            
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Export savings data
     * 
     * @param string $format Export format
     * @return void
     */
    public function export(string $format = 'csv'): void
    {
        try {
            // Get filters
            $filters = [
                'member_id' => (int)$this->input('member_id', 0),
                'transaction_type' => $this->input('transaction_type'),
                'from_date' => $this->input('from_date'),
                'to_date' => $this->input('to_date')
            ];
            $filters = array_filter($filters);
            
            // Get all transactions (not paginated)
            if (isset($filters['member_id']) && $filters['member_id'] > 0) {
                $result = $this->savingsModel->getMemberTransactions(
                    $filters['member_id'],
                    1,
                    9999,
                    $filters
                );
            } else {
                $result = $this->savingsModel->getTransactionsWithMembers(1, 9999, $filters);
            }
            
            $transactions = $result['data'];
            
            if (empty($transactions)) {
                $this->session->flash('error', 'No data to export.');
                $this->redirect('/savings');
                return;
            }
            
            // Generate CSV
            if ($format === 'csv') {
                $this->exportCsv($transactions);
            } else {
                $this->exportExcel($transactions);
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/savings');
        }
    }

    /**
     * Export as CSV
     * 
     * @param array $transactions Transaction data
     * @return void
     */
    private function exportCsv(array $transactions): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="savings_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        
        // Headers
        fputcsv($output, [
            'Date',
            'Member',
            'Member No',
            'Type',
            'Amount',
            'Balance',
            'Mode',
            'Receipt No',
            'Description'
        ]);
        
        // Data
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                date('Y-m-d H:i', strtotime($transaction['transaction_date'])),
                $transaction['member_name'] ?? 'Unknown',
                $transaction['member_no'] ?? '',
                $transaction['transaction_type'],
                number_format($transaction['amount'], 2),
                number_format($transaction['balance_after'], 2),
                $transaction['transaction_mode'],
                $transaction['receipt_no'],
                $transaction['description'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export as Excel (HTML table)
     * 
     * @param array $transactions Transaction data
     * @return void
     */
    private function exportExcel(array $transactions): void
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="savings_' . date('Y-m-d') . '.xls"');
        
        echo '<html>';
        echo '<head><meta charset="UTF-8"></head>';
        echo '<body>';
        echo '<h2>Savings Report - ' . APP_NAME . '</h2>';
        echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2;">';
        echo '<th>Date</th><th>Member</th><th>Member No</th><th>Type</th>';
        echo '<th>Amount</th><th>Balance</th><th>Mode</th>';
        echo '<th>Receipt No</th><th>Description</th>';
        echo '</tr>';
        
        foreach ($transactions as $transaction) {
            echo '<tr>';
            echo '<td>' . date('Y-m-d H:i', strtotime($transaction['transaction_date'])) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['member_name'] ?? 'Unknown') . '</td>';
            echo '<td>' . htmlspecialchars($transaction['member_no'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($transaction['transaction_type']) . '</td>';
            echo '<td>' . number_format($transaction['amount'], 2) . '</td>';
            echo '<td>' . number_format($transaction['balance_after'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['transaction_mode']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['receipt_no']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['description'] ?? '') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body></html>';
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