<?php

declare(strict_types=1);

namespace Reno\Routing;

use Reno\Http\Request;
use Reno\Routing\Route;
use InvalidArgumentException;
use Closure;

/**
 * ConstraintValidator
 * 
 * Advanced constraint validation system for route parameters.
 * Supports built-in validators and custom validation rules.
 */
class ConstraintValidator
{
    /**
     * Built-in constraint patterns.
     */
    protected static array $patterns = [
        'alpha' => '[a-zA-Z]+',
        'alpha_dash' => '[a-zA-Z_-]+',
        'alpha_num' => '[a-zA-Z0-9]+',
        'numeric' => '[0-9]+',
        'integer' => '-?[0-9]+',
        'decimal' => '-?[0-9]+(\.[0-9]+)?',
        'slug' => '[a-z0-9-]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'email' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
        'url' => 'https?:\/\/[^\s\/$.?#].[^\s]*',
        'ip' => '(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)',
        'date' => '\d{4}-\d{2}-\d{2}',
        'datetime' => '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}',
        'time' => '\d{2}:\d{2}:\d{2}',
        'hex' => '[0-9a-fA-F]+',
        'base64' => '[A-Za-z0-9+\/]+=*',
    ];

    /**
     * Custom validators.
     */
    protected array $customValidators = [];

    /**
     * Database connection resolver.
     */
    protected ?Closure $databaseResolver = null;

    /**
     * Create a new ConstraintValidator instance.
     */
    public function __construct(?Closure $databaseResolver = null)
    {
        $this->databaseResolver = $databaseResolver;
    }

    // ====================================================================
    // Pattern Registration
    // ====================================================================

    /**
     * Register a custom constraint pattern.
     */
    public function pattern(string $name, string $pattern): void
    {
        self::$patterns[$name] = $pattern;
    }

    /**
     * Register multiple patterns.
     */
    public function patterns(array $patterns): void
    {
        self::$patterns = array_merge(self::$patterns, $patterns);
    }

    /**
     * Register a custom validator.
     */
    public function validator(string $name, Closure $validator): void
    {
        $this->customValidators[$name] = $validator;
    }

    /**
     * Register multiple custom validators.
     */
    public function validators(array $validators): void
    {
        foreach ($validators as $name => $validator) {
            $this->validator($name, $validator);
        }
    }

    // ====================================================================
    // Constraint Validation
    // ====================================================================

    /**
     * Validate all constraints for a route.
     */
    public function validateRoute(Route $route, Request $request): array
    {
        $parameters = $route->getParameters();
        $constraints = $route->getWheres();
        $errors = [];

        foreach ($parameters as $name => $value) {
            if (isset($constraints[$name])) {
                $result = $this->validateConstraint($name, $value, $constraints[$name], $route, $request);
                
                if ($result !== true) {
                    $errors[$name] = $result;
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single constraint.
     */
    public function validateConstraint(string $name, mixed $value, string $constraint, ?Route $route = null, ?Request $request = null): bool|string
    {
        // Handle multiple constraints separated by pipe
        if (str_contains($constraint, '|')) {
            return $this->validateMultipleConstraints($name, $value, $constraint, $route, $request);
        }

        // Parse constraint with parameters
        [$constraintName, $parameters] = $this->parseConstraint($constraint);

        // Check custom validators first
        if (isset($this->customValidators[$constraintName])) {
            return $this->validateCustomConstraint($name, $value, $constraintName, $parameters, $route, $request);
        }

        // Handle special constraints
        switch ($constraintName) {
            case 'required':
                return $this->validateRequired($value);
                
            case 'min':
                return $this->validateMin($value, $parameters);
                
            case 'max':
                return $this->validateMax($value, $parameters);
                
            case 'between':
                return $this->validateBetween($value, $parameters);
                
            case 'length':
                return $this->validateLength($value, $parameters);
                
            case 'in':
                return $this->validateIn($value, $parameters);
                
            case 'not_in':
                return $this->validateNotIn($value, $parameters);
                
            case 'exists':
                return $this->validateExists($value, $parameters);
                
            case 'unique':
                return $this->validateUnique($value, $parameters, $route);
                
            case 'regex':
                return $this->validateRegex($value, $parameters);
                
            default:
                return $this->validatePattern($value, $constraintName);
        }
    }

    /**
     * Validate multiple constraints.
     */
    protected function validateMultipleConstraints(string $name, mixed $value, string $constraints, ?Route $route, ?Request $request): bool|string
    {
        $constraintList = explode('|', $constraints);
        
        foreach ($constraintList as $constraint) {
            $result = $this->validateConstraint($name, $value, trim($constraint), $route, $request);
            
            if ($result !== true) {
                return $result; // Return first error
            }
        }
        
        return true;
    }

    /**
     * Parse constraint string.
     */
    protected function parseConstraint(string $constraint): array
    {
        if (str_contains($constraint, ':')) {
            [$name, $paramString] = explode(':', $constraint, 2);
            $parameters = array_map('trim', explode(',', $paramString));
            return [trim($name), $parameters];
        }

        return [trim($constraint), []];
    }

    // ====================================================================
    // Built-in Validators
    // ====================================================================

    /**
     * Validate required constraint.
     */
    protected function validateRequired(mixed $value): bool|string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'This field is required.';
        }
        
        return true;
    }

    /**
     * Validate min constraint.
     */
    protected function validateMin(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Min constraint requires a minimum value parameter.');
        }

        $min = (float) $parameters[0];
        
        if (is_numeric($value)) {
            return (float) $value >= $min ? true : "Value must be at least {$min}.";
        }
        
        if (is_string($value)) {
            return strlen($value) >= $min ? true : "Must be at least {$min} characters.";
        }
        
        return 'Invalid value type for min constraint.';
    }

    /**
     * Validate max constraint.
     */
    protected function validateMax(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Max constraint requires a maximum value parameter.');
        }

        $max = (float) $parameters[0];
        
        if (is_numeric($value)) {
            return (float) $value <= $max ? true : "Value must be at most {$max}.";
        }
        
        if (is_string($value)) {
            return strlen($value) <= $max ? true : "Must be at most {$max} characters.";
        }
        
        return 'Invalid value type for max constraint.';
    }

    /**
     * Validate between constraint.
     */
    protected function validateBetween(mixed $value, array $parameters): bool|string
    {
        if (count($parameters) < 2) {
            throw new InvalidArgumentException('Between constraint requires min and max parameters.');
        }

        $min = (float) $parameters[0];
        $max = (float) $parameters[1];
        
        if (is_numeric($value)) {
            $numValue = (float) $value;
            return ($numValue >= $min && $numValue <= $max) ? true : "Value must be between {$min} and {$max}.";
        }
        
        if (is_string($value)) {
            $length = strlen($value);
            return ($length >= $min && $length <= $max) ? true : "Length must be between {$min} and {$max} characters.";
        }
        
        return 'Invalid value type for between constraint.';
    }

    /**
     * Validate length constraint.
     */
    protected function validateLength(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Length constraint requires a length parameter.');
        }

        $expectedLength = (int) $parameters[0];
        $actualLength = is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 0);
        
        return $actualLength === $expectedLength ? true : "Length must be exactly {$expectedLength}.";
    }

    /**
     * Validate in constraint.
     */
    protected function validateIn(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('In constraint requires allowed values.');
        }

        return in_array($value, $parameters, true) ? true : 'Value is not in the allowed list.';
    }

    /**
     * Validate not_in constraint.
     */
    protected function validateNotIn(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('NotIn constraint requires forbidden values.');
        }

        return !in_array($value, $parameters, true) ? true : 'Value is not allowed.';
    }

    /**
     * Validate exists constraint (database).
     */
    protected function validateExists(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Exists constraint requires table name.');
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? 'id';
        $connection = $parameters[2] ?? 'default';

        if (!$this->databaseResolver) {
            throw new InvalidArgumentException('Database resolver is required for exists constraint.');
        }

        $db = ($this->databaseResolver)($connection);
        
        if (!$db) {
            throw new InvalidArgumentException("Database connection '{$connection}' not found.");
        }

        // In a real implementation, this would query the database
        // For demonstration, we'll simulate it
        $exists = $this->checkDatabaseExists($db, $table, $column, $value);
        
        return $exists ? true : "The selected value does not exist in {$table}.";
    }

    /**
     * Validate unique constraint (database).
     */
    protected function validateUnique(mixed $value, array $parameters, ?Route $route): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Unique constraint requires table name.');
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? 'id';
        $ignore = $parameters[2] ?? null;
        $connection = $parameters[3] ?? 'default';

        // If ignore parameter is a route parameter name, resolve it
        if ($ignore && $route && str_starts_with($ignore, '{') && str_ends_with($ignore, '}')) {
            $paramName = trim($ignore, '{}');
            $ignore = $route->getParameter($paramName);
        }

        if (!$this->databaseResolver) {
            throw new InvalidArgumentException('Database resolver is required for unique constraint.');
        }

        $db = ($this->databaseResolver)($connection);
        
        if (!$db) {
            throw new InvalidArgumentException("Database connection '{$connection}' not found.");
        }

        // In a real implementation, this would query the database
        $exists = $this->checkDatabaseUnique($db, $table, $column, $value, $ignore);
        
        return !$exists ? true : "The value already exists in {$table}.";
    }

    /**
     * Validate regex constraint.
     */
    protected function validateRegex(mixed $value, array $parameters): bool|string
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Regex constraint requires a pattern parameter.');
        }

        $pattern = $parameters[0];
        
        // Ensure pattern is properly delimited
        if (!str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern . '/';
        }

        return preg_match($pattern, (string) $value) ? true : 'Value format is invalid.';
    }

    /**
     * Validate against predefined patterns.
     */
    protected function validatePattern(mixed $value, string $patternName): bool|string
    {
        if (!isset(self::$patterns[$patternName])) {
            throw new InvalidArgumentException("Unknown constraint pattern: {$patternName}");
        }

        $pattern = self::$patterns[$patternName];
        $regex = '/^' . $pattern . '$/';
        
        return preg_match($regex, (string) $value) ? true : "Value does not match {$patternName} format.";
    }

    /**
     * Validate custom constraint.
     */
    protected function validateCustomConstraint(string $name, mixed $value, string $constraintName, array $parameters, ?Route $route, ?Request $request): bool|string
    {
        $validator = $this->customValidators[$constraintName];
        
        return $validator($value, $parameters, $name, $route, $request);
    }

    // ====================================================================
    // Database Helpers
    // ====================================================================

    /**
     * Check if value exists in database.
     */
    protected function checkDatabaseExists(mixed $db, string $table, string $column, mixed $value): bool
    {
        // Simulate database check - in real implementation, query the database
        return !empty($value);
    }

    /**
     * Check if value is unique in database.
     */
    protected function checkDatabaseUnique(mixed $db, string $table, string $column, mixed $value, mixed $ignore = null): bool
    {
        // Simulate database check - in real implementation, query the database
        return $value !== $ignore;
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get available patterns.
     */
    public static function getPatterns(): array
    {
        return self::$patterns;
    }

    /**
     * Get custom validators.
     */
    public function getCustomValidators(): array
    {
        return $this->customValidators;
    }

    /**
     * Check if pattern exists.
     */
    public static function hasPattern(string $name): bool
    {
        return isset(self::$patterns[$name]);
    }

    /**
     * Check if custom validator exists.
     */
    public function hasValidator(string $name): bool
    {
        return isset($this->customValidators[$name]);
    }

    /**
     * Set database resolver.
     */
    public function setDatabaseResolver(Closure $resolver): void
    {
        $this->databaseResolver = $resolver;
    }
}