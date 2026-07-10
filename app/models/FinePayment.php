<?php

namespace App\Models;

class FinePayment extends BaseModel
{
    /**
     * Table name
     */
    protected string $table = 'fine_payments';

    /**
     * Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * Fillable fields
     */
    protected array $fillable = [
        'fine_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'reference_no',
        'notes',
        'received_by'
    ];

    /**
     * Validation rules
     */
    protected array $rules = [
        'fine_id' => 'required|integer',
        'amount_paid' => 'required|numeric|min:0.01',
        'payment_date' => 'required|date'
    ];

    /**
     * Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'fine_id' => 'int',
        'amount_paid' => 'float',
        'payment_date' => 'date'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create payment
     */
    public function create(array $data)
    {
        $validated = $this->validate($data);

        $id = parent::create($validated);

        // Update fine status
        $fine = new Fine();
        $fine->updateStatus($data['fine_id']);

        return $id;
    }

    /**
     * Delete payment
     */
    public function delete(int $id): bool
    {
        $payment = $this->find($id);

        if (!$payment) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE id = ?";

        $stmt = $this->db->query($sql, [$id]);

        $fine = new Fine();
        $fine->updateStatus($payment['fine_id']);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get all payments for one fine
     */
    public function getByFine(int $fineId): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE fine_id = ?
            ORDER BY payment_date DESC, id DESC
        ";

        $stmt = $this->db->query($sql, [$fineId]);

        return $stmt->fetchAll();
    }

    /**
     * Get total amount paid for a fine
     */
    public function getTotalPaid(int $fineId): float
    {
        $sql = "
            SELECT COALESCE(SUM(amount_paid),0) AS total
            FROM {$this->table}
            WHERE fine_id = ?
        ";

        $stmt = $this->db->query($sql, [$fineId]);

        $result = $stmt->fetch();

        return (float)($result['total'] ?? 0);
    }

    /**
     * Get a payment
     */
    public function getPayment(int $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get payments by member
     */
    public function getByMember(int $memberId): array
    {
        $sql = "
            SELECT
                fp.*,
                f.amount AS fine_amount,
                ft.name AS fine_type
            FROM fine_payments fp
            JOIN fines f ON fp.fine_id = f.id
            JOIN fine_types ft ON f.fine_type_id = ft.id
            WHERE f.member_id = ?
            ORDER BY fp.payment_date DESC
        ";

        $stmt = $this->db->query($sql, [$memberId]);

        return $stmt->fetchAll();
    }

    /**
     * Get recent payments
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            ORDER BY payment_date DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, [$limit]);

        return $stmt->fetchAll();
    }
}