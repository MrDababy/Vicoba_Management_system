<?php
/**
 * Loan Installment Model
 * 
 * Manages loan repayment schedules and installment tracking.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

class LoanInstallment extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'loan_installments';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'loan_id',
        'installment_no',
        'due_date',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'paid_principal',
        'paid_interest',
        'balance_amount',
        'status',
        'paid_date'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'loan_id' => 'required|integer',
        'installment_no' => 'required|integer',
        'due_date' => 'required|date',
        'principal_amount' => 'required|numeric|min:0',
        'interest_amount' => 'required|numeric|min:0',
        'total_amount' => 'required|numeric|min:0',
        'balance_amount' => 'required|numeric|min:0',
        'status' => 'in:Pending,Paid,Partially_Paid,Overdue',
        'paid_date' => 'nullable|date'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'loan_id' => 'int',
        'installment_no' => 'int',
        'principal_amount' => 'float',
        'interest_amount' => 'float',
        'total_amount' => 'float',
        'paid_principal' => 'float',
        'paid_interest' => 'float',
        'balance_amount' => 'float',
        'due_date' => 'date',
        'paid_date' => 'date'
    ];

    /**
     * Generate installments for a loan
     * 
     * @param int $loanId Loan ID
     * @param float $principal Principal amount
     * @param float $interestRate Annual interest rate
     * @param int $durationMonths Loan duration in months
     * @return bool
     */
    public function generateInstallments(int $loanId, float $principal, float $interestRate, int $durationMonths): bool
    {
        // Calculate monthly payment using amortization formula
        $monthlyRate = ($interestRate / 100) / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($principal, $monthlyRate, $durationMonths);
        
        $balance = $principal;
        $installmentNo = 1;
        
        $this->beginTransaction();
        
        try {
            for ($i = 1; $i <= $durationMonths; $i++) {
                // Calculate interest for this period
                $interestAmount = $balance * $monthlyRate;
                
                // Calculate principal portion
                $principalAmount = $monthlyPayment - $interestAmount;
                
                // If this is the last installment, adjust to clear the balance
                if ($i === $durationMonths) {
                    $principalAmount = $balance;
                    $monthlyPayment = $balance + $interestAmount;
                }
                
                // Create installment
                $data = [
                    'loan_id' => $loanId,
                    'installment_no' => $installmentNo,
                    'due_date' => date('Y-m-d', strtotime("+$i months")),
                    'principal_amount' => round($principalAmount, 2),
                    'interest_amount' => round($interestAmount, 2),
                    'total_amount' => round($monthlyPayment, 2),
                    'paid_principal' => 0,
                    'paid_interest' => 0,
                    'balance_amount' => round($monthlyPayment, 2),
                    'status' => 'Pending'
                ];
                
                $this->create($data);
                
                // Update remaining balance
                $balance -= $principalAmount;
                $installmentNo++;
            }
            
            $this->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Calculate monthly payment using amortization formula
     * 
     * @param float $principal Principal amount
     * @param float $monthlyRate Monthly interest rate (decimal)
     * @param int $months Number of months
     * @return float
     */
    private function calculateMonthlyPayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate == 0) {
            return $principal / $months;
        }
        
        $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        return round($payment, 2);
    }

    /**
     * Get installments for a loan
     * 
     * @param int $loanId Loan ID
     * @return array
     */
    public function getByLoan(int $loanId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE loan_id = ? ORDER BY installment_no ASC";
        $stmt = $this->db->query($sql, [$loanId]);
        return $stmt->fetchAll();
    }

    /**
     * Get overdue installments
     * 
     * @param int $loanId Loan ID
     * @return array
     */
    public function getOverdue(int $loanId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE loan_id = ? 
                    AND status IN ('Pending', 'Partially_Paid') 
                    AND due_date < CURDATE() 
                ORDER BY due_date ASC";
        $stmt = $this->db->query($sql, [$loanId]);
        return $stmt->fetchAll();
    }

    /**
     * Update installment status based on payments
     * 
     * @param int $installmentId Installment ID
     * @return bool
     */
    public function updateStatus(int $installmentId): bool
    {
        $installment = $this->find($installmentId);
        if (!$installment) {
            return false;
        }
        
        $status = 'Pending';
        
        if ($installment['balance_amount'] <= 0) {
            $status = 'Paid';
        } elseif ($installment['paid_principal'] > 0 || $installment['paid_interest'] > 0) {
            $status = 'Partially_Paid';
        } elseif ($installment['due_date'] < date('Y-m-d')) {
            $status = 'Overdue';
        }
        
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->db->query($sql, [$status, $installmentId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Apply payment to an installment
     * 
     * @param int $installmentId Installment ID
     * @param float $principalAmount Principal payment
     * @param float $interestAmount Interest payment
     * @param string $paymentDate Payment date
     * @return bool
     */
    public function applyPayment(int $installmentId, float $principalAmount, float $interestAmount, string $paymentDate): bool
    {
        $installment = $this->find($installmentId);
        if (!$installment) {
            return false;
        }
        
        $this->beginTransaction();
        
        try {
            // Update paid amounts
            $newPaidPrincipal = $installment['paid_principal'] + $principalAmount;
            $newPaidInterest = $installment['paid_interest'] + $interestAmount;
            $newBalance = $installment['balance_amount'] - ($principalAmount + $interestAmount);
            
            if ($newBalance < 0) {
                $newBalance = 0;
            }
            
            $sql = "UPDATE {$this->table} 
                    SET paid_principal = ?,
                        paid_interest = ?,
                        balance_amount = ?,
                        paid_date = ?,
                        status = ?
                    WHERE id = ?";
            
            $status = $newBalance <= 0 ? 'Paid' : 'Partially_Paid';
            
            $stmt = $this->db->query($sql, [
                $newPaidPrincipal,
                $newPaidInterest,
                $newBalance,
                $paymentDate,
                $status,
                $installmentId
            ]);
            
            $this->commit();
            return $stmt->rowCount() > 0;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get loan summary with installment details
     * 
     * @param int $loanId Loan ID
     * @return array
     */
    public function getLoanSummary(int $loanId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_installments,
                    SUM(total_amount) as total_amount,
                    SUM(paid_principal) as total_paid_principal,
                    SUM(paid_interest) as total_paid_interest,
                    SUM(balance_amount) as total_balance,
                    SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN status = 'Partially_Paid' THEN 1 ELSE 0 END) as partial_count
                FROM {$this->table}
                WHERE loan_id = ?";
        
        $stmt = $this->db->query($sql, [$loanId]);
        $result = $stmt->fetch();
        
        return [
            'total_installments' => (int)($result['total_installments'] ?? 0),
            'total_amount' => (float)($result['total_amount'] ?? 0),
            'total_paid_principal' => (float)($result['total_paid_principal'] ?? 0),
            'total_paid_interest' => (float)($result['total_paid_interest'] ?? 0),
            'total_balance' => (float)($result['total_balance'] ?? 0),
            'paid_count' => (int)($result['paid_count'] ?? 0),
            'pending_count' => (int)($result['pending_count'] ?? 0),
            'overdue_count' => (int)($result['overdue_count'] ?? 0),
            'partial_count' => (int)($result['partial_count'] ?? 0)
        ];
    }
}