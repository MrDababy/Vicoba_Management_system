<?php
/**
 * Database Connection Class
 * 
 * Handles PDO database connections, query execution, and transaction management.
 * Implements Singleton pattern to prevent multiple connections.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Classes;

use PDO;
use PDOException;
use App\Exceptions\DatabaseException;

class Database
{
    /**
     * @var Database|null Singleton instance
     */
    private static ?Database $instance = null;

    /**
     * @var PDO PDO connection instance
     */
    private ?PDO $connection = null;

    /**
     * @var int Query count for debugging
     */
    private int $queryCount = 0;

    /**
     * @var float Query execution time
     */
    private float $queryTime = 0.0;

    /**
     * Private constructor to prevent direct instantiation
     * 
     * @throws DatabaseException
     */
    private function __construct()
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_PORT,
                DB_CHARSET
            );
            
            $options = defined('PDO_OPTIONS') ? PDO_OPTIONS : [];
            
            $this->connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            
            // Set connection attributes
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Failed to establish database connection: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get singleton instance
     * 
     * @return Database
     * @throws DatabaseException
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection instance
     * 
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prepare and execute a query
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Query parameters
     * @return \PDOStatement
     * @throws DatabaseException
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $startTime = microtime(true);
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $this->queryCount++;
            $this->queryTime += (microtime(true) - $startTime);
            
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Query execution failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Begin a transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Get last insert ID
     * 
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Escape a string value
     * 
     * @param string $value
     * @return string
     */
    public function escape(string $value): string
    {
        return $this->connection->quote($value);
    }

    /**
     * Get query execution statistics
     * 
     * @return array
     */
    public function getQueryStats(): array
    {
        return [
            'count' => $this->queryCount,
            'time' => $this->queryTime,
            'avg_time' => $this->queryCount > 0 ? $this->queryTime / $this->queryCount : 0
        ];
    }

    /**
     * Destructor - close connection if persistent
     */
    public function __destruct()
    {
        if (isset($this->connection)) {
            unset($this->connection);
        }
    }

    /**
     * Clone method to prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Wakeup method to prevent unserialization of singleton
     */
    public function __wakeup() {}
}
?>