<?php
/**
 * Loan Type Model
 * 
 * Manages loan product definitions including interest rates and limits.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

class LoanType extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'loan_types';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [
        'name',
        'description',
        'default_rate',
        'min_amount',
        'max_amount',
        'status'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'name' => 'required|max:100|unique',
        'description' => 'max:500',
        'default_rate' => 'required|numeric|min:0|max:100',
        'min_amount' => 'required|numeric|min:0',
        'max_amount' => 'required|numeric|min:0|gt:min_amount',
        'status' => 'in:Active,Inactive'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'default_rate' => 'float',
        'min_amount' => 'float',
        'max_amount' => 'float'
    ];

    /**
     * Get active loan types
     * 
     * @return array
     */
    public function getActive(): array
    {
        return $this->all(['status' => 'Active'], 'name', 'ASC');
    }

    /**
     * Get loan type by name
     * 
     * @param string $name Loan type name
     * @return array|null
     */
    public function getByName(string $name): ?array
    {
        return $this->findBy('name', $name);
    }

    /**
     * Check if loan type has active loans
     * 
     * @param int $id Loan type ID
     * @return bool
     */
    public function hasActiveLoans(int $id): bool
    {
        $sql = "SELECT COUNT(*) as count FROM loans WHERE loan_type_id = ? AND status IN ('Active', 'Pending')";
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }
}