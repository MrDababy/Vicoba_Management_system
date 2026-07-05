<?php
/**
 * Fine Type Model
 * 
 * Manages fine type definitions including default amounts and descriptions.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

class FineType extends BaseModel
{
    /**
     * @var string Table name
     */
    protected string $table = 'fine_types';

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
        'default_amount',
        'is_percentage',
        'status'
    ];

    /**
     * @var array Validation rules
     */
    protected array $rules = [
        'name' => 'required|max:100|unique',
        'description' => 'max:500',
        'default_amount' => 'required|numeric|min:0',
        'is_percentage' => 'boolean',
        'status' => 'in:Active,Inactive'
    ];

    /**
     * @var array Field casts
     */
    protected array $casts = [
        'id' => 'int',
        'default_amount' => 'float',
        'is_percentage' => 'bool'
    ];

    /**
     * Get active fine types
     * 
     * @return array
     */
    public function getActive(): array
    {
        return $this->all(['status' => 'Active'], 'name', 'ASC');
    }

    /**
     * Get fine type by name
     * 
     * @param string $name Fine type name
     * @return array|null
     */
    public function getByName(string $name): ?array
    {
        return $this->findBy('name', $name);
    }

    /**
     * Check if fine type has active fines
     * 
     * @param int $id Fine type ID
     * @return bool
     */
    public function hasActiveFines(int $id): bool
    {
        $sql = "SELECT COUNT(*) as count FROM fines WHERE fine_type_id = ? AND status IN ('Pending', 'Partially_Paid')";
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get fine type statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive
                FROM {$this->table}";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'active' => (int)($result['active'] ?? 0),
            'inactive' => (int)($result['inactive'] ?? 0)
        ];
    }
}