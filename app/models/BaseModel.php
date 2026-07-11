<?php
/**
 * Base Model Class
 * 
 * Provides core CRUD operations, database interaction, and common
 * functionality for all models. Implements query building and
 * relationship handling.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Models;

use App\Classes\Database;
use App\Helpers\Encryption;
use App\Helpers\Validation;
use App\Exceptions\DatabaseException;

abstract class BaseModel
{
    /**
     * @var Database Database instance
     */
    protected Database $db;

    /**
     * @var string Table name
     */
    protected string $table;

    /**
     * @var string Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable fields
     */
    protected array $fillable = [];

    /**
     * @var array Encrypted fields
     */
    protected array $encrypted = [];

    /**
     * @var array Validation rules
     */
    protected array $rules = [];

    /**
     * @var array Field types for casting
     */
    protected array $casts = [];

    /**
     * @var array Relationships
     */
    protected array $with = [];

    /**
     * @var Validation Validation instance
     */
    protected Validation $validator;

    /**
     * @var Encryption Encryption instance
     */
    protected Encryption $encryptor;

    /**
     * @var array Current model data
     */
    protected array $data = [];

    /**
     * @var bool Whether the model exists in database
     */
    protected bool $exists = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->validator = new Validation();
        $this->encryptor = new Encryption();
        
        if (empty($this->table)) {
            throw new \Exception('Table name must be defined in model');
        }
    }

    /**
     * Create a new record
     * 
     * @param array $data Data to insert
     * @return int|bool Last insert ID or false
     * @throws DatabaseException
     * @throws \Exception
     */
    public function create(array $data)
    {
        // Validate
        $data = $this->validate($data);

        // Cast data
        $data = $this->castData($data);

        // Encrypt sensitive fields
        $data = $this->encryptData($data);

        // Build insert query
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->query($sql, array_values($data));

        if ($stmt) {
            $this->data = $data;
            $this->exists = true;

            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Find a record by primary key
     * 
     * @param int $id Record ID
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ? LIMIT 1',
            $this->table,
            $this->primaryKey
        );
        
        $stmt = $this->db->query($sql, [$id]);
        $data = $stmt->fetch();
        
        if ($data) {
            $this->data = $data;
            $this->exists = true;
            return $this->decryptData($data);
        }
        
        return null;
    }

    /**
     * Find a record by primary key using a convenience alias.
     *
     * @param int $id Record ID
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Find by field value
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return array|null
     */
    public function findBy(string $field, $value): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ? LIMIT 1',
            $this->table,
            $field
        );
        
        $stmt = $this->db->query($sql, [$value]);
        $data = $stmt->fetch();
        
        if ($data) {
            $this->data = $data;
            $this->exists = true;
            return $this->decryptData($data);
        }
        
        return null;
    }

    /**
     * Get all records with optional conditions
     * 
     * @param array $conditions WHERE conditions
     * @param string $orderBy Order by clause
     * @param string $direction Sort direction
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array
     */
    public function all(
        array $conditions = [],
        string $orderBy = '',
        string $direction = 'ASC',
        int $limit = 0,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Build WHERE clause
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $where[] = "{$field} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $where[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        // Add ORDER BY
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy} {$direction}";
        }
        
        // Add LIMIT
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll();
        
        // Decrypt data
        return array_map([$this, 'decryptData'], $results);
    }

    /**
     * Update a record
     * 
     * @param int $id Record ID
     * @param array $data Data to update
     * @return bool
     * @throws DatabaseException
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        // Validate data
        $validatedData = $this->validate($data, true);
        
        // Apply casts
        $validatedData = $this->castData($validatedData);
        
        // Encrypt sensitive fields
        $validatedData = $this->encryptData($validatedData);
        
        // Build update query
        $set = [];
        $params = [];
        
        foreach ($validatedData as $field => $value) {
            $set[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->table,
            implode(', ', $set),
            $this->primaryKey
        );
        
        $stmt = $this->db->query($sql, $params);
        
        if ($stmt->rowCount() > 0) {
            $this->data = $this->find($id);
            return true;
        }
        
        return false;
    }

    /**
     * Delete a record
     * 
     * @param int $id Record ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = ?',
            $this->table,
            $this->primaryKey
        );
        
        $stmt = $this->db->query($sql, [$id]);
        
        if ($stmt->rowCount() > 0) {
            $this->exists = false;
            $this->data = [];
            return true;
        }
        
        return false;
    }

    /**
     * Count records with conditions
     * 
     * @param array $conditions WHERE conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $where[] = "{$field} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $where[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        
        return (int)($result['total'] ?? 0);
    }

    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param bool $update Whether this is an update
     * @return array Validated data
     * @throws \Exception
     */
    protected function validate(array $data, bool $update = false): array
    {
        $rules = $this->rules;
        
        // For update, remove required rules unless field is present
        if ($update) {
            foreach ($rules as $field => $ruleString) {
                $rules[$field] = str_replace('required|', '', $ruleString);
                $rules[$field] = str_replace('|required', '', $rules[$field]);
            }
        }
        
        return $this->validator->validate($data, $rules);
    }

    /**
     * Encrypt sensitive fields
     * 
     * @param array $data Data to encrypt
     * @return array
     */
    protected function encryptData(array $data): array
    {
        foreach ($this->encrypted as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->encryptor->encrypt($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Decrypt sensitive fields
     * 
     * @param array $data Data to decrypt
     * @return array
     */
    protected function decryptData(array $data): array
    {
        foreach ($this->encrypted as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                try {
                    $data[$field] = $this->encryptor->decrypt($data[$field]);
                } catch (\Exception $e) {
                    // If decryption fails, leave as is
                }
            }
        }
        return $data;
    }

    /**
     * Cast data to appropriate types
     * 
     * @param array $data Data to cast
     * @return array
     */
    protected function castData(array $data): array
    {
        foreach ($this->casts as $field => $type) {
            if (isset($data[$field])) {
                switch ($type) {
                    case 'int':
                    case 'integer':
                        $data[$field] = (int)$data[$field];
                        break;
                    case 'float':
                    case 'double':
                        $data[$field] = (float)$data[$field];
                        break;
                    case 'bool':
                    case 'boolean':
                        $data[$field] = (bool)$data[$field];
                        break;
                    case 'string':
                        $data[$field] = (string)$data[$field];
                        break;
                    case 'array':
                    case 'json':
                        $data[$field] = json_encode($data[$field]);
                        break;
                    case 'date':
                        $data[$field] = date('Y-m-d', strtotime($data[$field]));
                        break;
                    case 'datetime':
                        $data[$field] = date('Y-m-d H:i:s', strtotime($data[$field]));
                        break;
                }
            }
        }
        return $data;
    }

    /**
     * Begin a database transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->db->rollback();
    }

    /**
     * Get the table name
     * 
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key
     * 
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get model data
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Check if model exists
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Magic method to get attributes
     * 
     * @param string $key Attribute name
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Magic method to set attributes
     * 
     * @param string $key Attribute name
     * @param mixed $value Attribute value
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}
?>