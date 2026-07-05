<?php
/**
 * Fine Payment Model
 * 
 * Handles fine payment transactions and balance tracking.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

class FinePayment extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'fine_payments';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'fine_id',
        'amount_paid',
        'balance_after',
        'payment_date',
        'payment_method',
        'receipt_no',
        'reference_no',
        'remarks',
        'created_by'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'fine_id' => 'required|integer',
        'amount_paid' => 'required|numeric|min:0.01',
        'balance_after' => 'required|numeric|min:0',
        'payment_date' => 'required|date',
        'payment_method' => 'required|in:Cash,Bank Transfer,Mobile Money,Cheque',
        'receipt_no' => 'required|max:50',
        'reference_no' => 'max:100',
        'remarks' => 'max:500'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'fine_id' => 'int',
        'amount_paid' => 'float',
        'balance_after' => 'float',
        'payment_date' => 'date'
    ];

    /**
     * Generate a unique receipt number
     * 
     * @return string
     */
    public function generateReceiptNumber(): string
    {
        $prefix = 'FNP';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        return $prefix . $date . $random;
    }

    /**
     * Create a fine payment
     * 
     * @param array $data Payment data
     * @return int|bool
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Generate receipt number
        if (!isset($data['receipt_no']) || empty($data['receipt_no'])) {
            $data['receipt_no'] = $this->generateReceiptNumber();
        }
        
        // Set default payment date
        if (!isset($data['payment_date'])) {
            $data['payment_date'] = date('Y-m-d');
        }
        
        // Validate fine exists and get current balance
        $fineModel = new Fine();
        $fine = $fineModel->find($data['fine_id']);
        if (!$fine) {
            throw new \Exception('Fine not found.');
        }
        
        if ($fine['status'] === 'Paid') {
            throw new \Exception('This fine is already fully paid.');
        }
        
        // Calculate balance after payment
        $currentBalance = $fine['amount'] - $this->getTotalPaid($data['fine_id']);
        if ($data['amount_paid'] > $currentBalance) {
            throw new \Exception('Payment amount exceeds outstanding balance.');
        }
        
        $data['balance_after'] = $currentBalance - $data['amount_paid'];
        
        // Validate and create
        $validatedData = $this->validate($data);
        
        $this->beginTransaction();
        
        try {
            // Create payment
            $paymentId = parent::create($validatedData);
            
            if ($paymentId) {
                // Update fine status
                $fineModel->updateStatus($data['fine_id']);
                
                $this->commit();
                return $paymentId;
            }
            
            $this->rollback();
            return false;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get total paid for a fine
     * 
     * @param int $fineId Fine ID
     * @return float
     */
    public function getTotalPaid(int $fineId): float
    {
        $sql = "SELECT SUM(amount_paid) as total FROM {$this->table} WHERE fine_id = ?";
        $stmt = $this->db->query($sql, [$fineId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get payments for a fine
     * 
     * @param int $fineId Fine ID
     * @return array
     */
    public function getByFine(int $fineId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE fine_id = ? ORDER BY payment_date DESC";
        $stmt = $this->db->query($sql, [$fineId]);
        return $stmt->fetchAll();
    }

    /**
     * Get payment with fine and member details
     * 
     * @param int $paymentId Payment ID
     * @return array|null
     */
    public function getWithDetails(int $paymentId): ?array
    {
        $sql = "SELECT fp.*, 
                       f.amount as fine_amount,
                       f.fine_date,
                       f.due_date,
                       f.description as fine_description,
                       m.full_name as member_name,
                       m.member_no
                FROM {$this->table} fp
                JOIN fines f ON fp.fine_id = f.id
                JOIN members m ON f.member_id = m.id
                WHERE fp.id = ?";
        
        $stmt = $this->db->query($sql, [$paymentId]);
        $data = $stmt->fetch();
        
        if ($data) {
            $encryptor = new \App\Helpers\Encryption();
            try {
                $data['member_name'] = $encryptor->decrypt($data['member_name']);
            } catch (\Exception $e) {
                // Leave as is
            }
        }
        
        return $data;
    }
}