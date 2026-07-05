<?php
/**
 * Dividend Payment Model
 * 
 * Handles dividend payment transactions and tracking.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

class DividendPayment extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'dividend_payments';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'dividend_id',
        'amount_paid',
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
        'dividend_id' => 'required|integer',
        'amount_paid' => 'required|numeric|min:0.01',
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
        'dividend_id' => 'int',
        'amount_paid' => 'float',
        'payment_date' => 'date'
    ];

    /**
     * Generate a unique receipt number
     * 
     * @return string
     */
    public function generateReceiptNumber(): string
    {
        $prefix = 'DVP';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        return $prefix . $date . $random;
    }

    /**
     * Create a dividend payment
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
        
        // Validate dividend exists
        $dividendModel = new Dividend();
        $dividend = $dividendModel->find($data['dividend_id']);
        if (!$dividend) {
            throw new \Exception('Dividend record not found.');
        }
        
        if ($dividend['status'] === 'Paid') {
            throw new \Exception('This dividend is already fully paid.');
        }
        
        // Check if amount exceeds balance
        $totalPaid = $this->getTotalPaid($data['dividend_id']);
        $balance = $dividend['interest_earned'] - $totalPaid;
        
        if ($data['amount_paid'] > $balance) {
            throw new \Exception('Payment amount exceeds remaining balance.');
        }
        
        // Validate and create
        $validatedData = $this->validate($data);
        
        $this->beginTransaction();
        
        try {
            // Create payment
            $paymentId = parent::create($validatedData);
            
            if ($paymentId) {
                // Update dividend status
                $dividendModel->updateStatus($data['dividend_id']);
                
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
     * Get total paid for a dividend
     * 
     * @param int $dividendId Dividend ID
     * @return float
     */
    public function getTotalPaid(int $dividendId): float
    {
        $sql = "SELECT SUM(amount_paid) as total FROM {$this->table} WHERE dividend_id = ?";
        $stmt = $this->db->query($sql, [$dividendId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get payments for a dividend
     * 
     * @param int $dividendId Dividend ID
     * @return array
     */
    public function getByDividend(int $dividendId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE dividend_id = ? ORDER BY payment_date DESC";
        $stmt = $this->db->query($sql, [$dividendId]);
        return $stmt->fetchAll();
    }

    /**
     * Get payment with dividend and member details
     * 
     * @param int $paymentId Payment ID
     * @return array|null
     */
    public function getWithDetails(int $paymentId): ?array
    {
        $sql = "SELECT dp.*, 
                       d.interest_earned as dividend_amount,
                       d.year as dividend_year,
                       d.total_profit,
                       m.full_name as member_name,
                       m.member_no
                FROM {$this->table} dp
                JOIN dividends d ON dp.dividend_id = d.id
                JOIN members m ON d.member_id = m.id
                WHERE dp.id = ?";
        
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