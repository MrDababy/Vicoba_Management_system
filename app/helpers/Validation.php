<?php
/**
 * Validation Helper Class
 * 
 * Provides comprehensive input validation rules and sanitization.
 * Supports both server-side validation and validation rule chains.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

use App\Exceptions\ValidationException;

class Validation
{
    /**
     * @var array Validation errors
     */
    private array $errors = [];

    /**
     * @var array Validation rules
     */
    private array $rules = [];

    /**
     * @var array Field values
     */
    private array $values = [];

    /**
     * @var array Custom error messages
     */
    private array $messages = [];

    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(array $data, array $rules, array $messages = []): array
    {
        $this->values = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->errors = [];
        
        foreach ($rules as $field => $ruleSet) {
            $this->validateField($field, $ruleSet);
        }
        
        if (!empty($this->errors)) {
            throw new ValidationException($this->getFirstError(), $this->errors);
        }
        
        return $this->values;
    }

    /**
     * Validate a single field
     * 
     * @param string $field Field name
     * @param string|array $rules Rule(s)
     * @return void
     */
    private function validateField(string $field, $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        $value = $this->values[$field] ?? '';
        
        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * Apply a validation rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @return void
     */
    private function applyRule(string $field, $value, string $rule): void
    {
        // Parse rule with parameters
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
        
        $method = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $method)) {
            $result = $this->$method($value, $parameters);
            
            if ($result !== true) {
                $this->addError($field, $ruleName, $result);
            }
        }
    }

    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $rule Rule that failed
     * @param string $message Error message
     * @return void
     */
    private function addError(string $field, string $rule, string $message): void
    {
        // Check for custom message
        $key = $field . '.' . $rule;
        if (isset($this->messages[$key])) {
            $message = $this->messages[$key];
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * Get first error message
     * 
     * @return string
     */
    public function getFirstError(): string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? 'Validation error occurred';
        }
        return 'Validation error occurred';
    }

    /**
     * Get all errors
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if validation passed
     * 
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     * 
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    // ==================== Validation Rules ====================

    /**
     * Required rule
     */
    private function validateRequired($value, array $parameters): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        return true;
    }

    /**
     * Email rule
     */
    private function validateEmail($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Min length rule
     */
    private function validateMin($value, array $parameters): bool
    {
    // If value is empty, let the required rule handle it
        if ($value === null || $value === '') {
            return false;
        }

        $min = (int)($parameters[0] ?? 0);

        return strlen((string)$value) >= $min;
    }

/**
 * Max length rule
 */
    private function validateMax($value, array $parameters): bool
    {
        // If value is empty, let the required rule handle it
        if ($value === null || $value === '') {
            return true;
        }

        $max = (int)($parameters[0] ?? PHP_INT_MAX);

        return strlen((string)$value) <= $max;
    }
    /**
     * Integer rule
     */
    private function validateInteger($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Float rule
     */
    private function validateFloat($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * Boolean rule
     */
    private function validateBoolean($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
    }

    /**
     * URL rule
     */
    private function validateUrl($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Date rule
     */
    private function validateDate($value, array $parameters): bool
    {
        $format = $parameters[0] ?? 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    /**
     * In rule (allowed values)
     */
    private function validateIn($value, array $parameters): bool
    {
        return in_array($value, $parameters);
    }

    /**
     * Not in rule (disallowed values)
     */
    private function validateNotIn($value, array $parameters): bool
    {
        return !in_array($value, $parameters);
    }

    /**
     * Min value rule
     */
    private function validateMinValue($value, array $parameters): bool
    {
        $min = (float)($parameters[0] ?? 0);
        return (float)$value >= $min;
    }

    /**
     * Max value rule
     */
    private function validateMaxValue($value, array $parameters): bool
    {
        $max = (float)($parameters[0] ?? PHP_FLOAT_MAX);
        return (float)$value <= $max;
    }

    /**
     * Unique rule (check database)
     */
    private function validateUnique($value, array $parameters): bool
    {
        if (empty($parameters) || count($parameters) < 2) {
            return true;
        }
        
        $table = $parameters[0];
        $column = $parameters[1] ?? 'id';
        $except = $parameters[2] ?? null;
        
        // This will be implemented with database connection
        // For now, return true (will be handled by application logic)
        return true;
    }

    /**
     * Same rule (password confirmation)
     */
    private function validateSame($value, array $parameters): bool
    {
        $field = $parameters[0] ?? '';
        return $value === ($this->values[$field] ?? null);
    }

    /**
     * Phone rule (Tanzanian phone number)
     */
    private function validatePhone($value, array $parameters): bool
    {
        $pattern = '/^(?:\+255|0)[67]\d{8}$/';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * National ID rule
     */
    private function validateNationalId($value, array $parameters): bool
    {
        // Tanzanian National ID format: 12-14 digits
        $pattern = '/^\d{12,14}$/';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Password strength rule
     */
    private function validatePassword($value, array $parameters): bool
    {
        $strength = $parameters[0] ?? 'medium';
        
        switch ($strength) {
            case 'weak':
                return strlen($value) >= 6;
            case 'medium':
                return strlen($value) >= 8 &&
                       preg_match('/[A-Z]/', $value) &&
                       preg_match('/[a-z]/', $value) &&
                       preg_match('/[0-9]/', $value);
            case 'strong':
                return strlen($value) >= 10 &&
                       preg_match('/[A-Z]/', $value) &&
                       preg_match('/[a-z]/', $value) &&
                       preg_match('/[0-9]/', $value) &&
                       preg_match('/[^a-zA-Z0-9]/', $value);
            default:
                return true;
        }
    }

    /**
     * Alpha rule (only letters)
     */
    private function validateAlpha($value, array $parameters): bool
    {
        return ctype_alpha($value);
    }

    /**
     * Alpha numeric rule
     */
    private function validateAlphaNum($value, array $parameters): bool
    {
        return ctype_alnum($value);
    }

    /**
     * Alpha dash rule (letters, numbers, dashes, underscores)
     */
    private function validateAlphaDash($value, array $parameters): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    /**
     * Array rule
     */
    private function validateArray($value, array $parameters): bool
    {
        return is_array($value);
    }

    /**
     * Size rule (string length or array count)
     */
    private function validateSize($value, array $parameters): bool
    {
        $size = (int)($parameters[0] ?? 0);
        if (is_array($value)) {
            return count($value) === $size;
        }
        return strlen($value) === $size;
    }

    /**
     * Between rule (numeric)
     */
    private function validateBetween($value, array $parameters): bool
    {
        $min = (float)($parameters[0] ?? 0);
        $max = (float)($parameters[1] ?? PHP_FLOAT_MAX);
        $num = (float)$value;
        return $num >= $min && $num <= $max;
    }

    /**
     * Regex rule
     */
    private function validateRegex($value, array $parameters): bool
    {
        $pattern = $parameters[0] ?? '';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * IPv4 rule
     */
    private function validateIpv4($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * IPv6 rule
     */
    private function validateIpv6($value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * JSON rule
     */
    private function validateJson($value, array $parameters): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Money format rule
     */
    private function validateMoney($value, array $parameters): bool
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1;
    }

    /**
     * Percentage rule
     */
    private function validatePercentage($value, array $parameters): bool
    {
        $num = (float)$value;
        return $num >= 0 && $num <= 100;
    }
}
?>