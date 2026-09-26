<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Concerns;

use Horizon\Database\Eloquent\MassAssignmentException;
use Horizon\Database\Eloquent\SecurityException;

/**
 * Model Security Concerns
 * 
 * Provides comprehensive security features for Eloquent models including
 * mass assignment protection, input sanitization, and security auditing.
 */
trait HasSecurity
{
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = ['*'];

    /**
     * Indicates if all mass assignment is enabled.
     */
    protected static bool $unguarded = false;

    /**
     * The attributes that should be sanitized on assignment.
     */
    protected array $sanitized = [];

    /**
     * The attributes that should be validated on assignment.
     */
    protected array $validated = [];

    /**
     * Security audit log for tracking sensitive operations.
     */
    protected array $securityAudit = [];

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // Check if attribute is fillable
            if ($this->isFillable($key)) {
                // Sanitize the value if needed
                $value = $this->sanitizeAttribute($key, $value);
                
                // Validate the value if needed
                $this->validateAttribute($key, $value);
                
                // Set the attribute
                $this->setAttribute($key, $value);
                
                // Log security-sensitive operations
                $this->logSecurityOperation('fill', $key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key,
                    get_class($this)
                ));
            }
        }

        return $this;
    }

    /**
     * Get the fillable attributes from the given array.
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->getFillable()) > 0 && !static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return $attributes;
    }

    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() === ['*'];
    }

    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->getFillable())) {
            return true;
        }

        // If the attribute is explicitly listed in the "guarded" array then we can
        // return false immediately. This means this attribute is definitely not
        // fillable and there is no point in going any further in this method.
        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->getFillable()) && 
               !str_starts_with($key, '_');
    }

    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        if (empty($this->getGuarded())) {
            return false;
        }

        return $this->getGuarded() === ['*'] || 
               in_array($key, $this->getGuarded());
    }

    /**
     * Get the fillable attributes for the model.
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Set the fillable attributes for the model.
     */
    public function fillable(array $fillable): static
    {
        $this->fillable = $fillable;
        return $this;
    }

    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Set the guarded attributes for the model.
     */
    public function guard(array $guarded): static
    {
        $this->guarded = $guarded;
        return $this;
    }

    /**
     * Sanitize an attribute value before assignment.
     */
    protected function sanitizeAttribute(string $key, mixed $value): mixed
    {
        if (!in_array($key, $this->sanitized)) {
            return $value;
        }

        // Basic sanitization based on attribute type
        if (is_string($value)) {
            // Remove null bytes
            $value = str_replace("\0", '', $value);
            
            // Trim whitespace
            $value = trim($value);
            
            // HTML sanitization for content fields
            if ($this->isContentField($key)) {
                $value = $this->sanitizeHtml($value);
            }
            
            // Email sanitization
            if ($this->isEmailField($key)) {
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            }
            
            // URL sanitization
            if ($this->isUrlField($key)) {
                $value = filter_var($value, FILTER_SANITIZE_URL);
            }
        }

        return $value;
    }

    /**
     * Validate an attribute value before assignment.
     */
    protected function validateAttribute(string $key, mixed $value): void
    {
        if (!in_array($key, $this->validated)) {
            return;
        }

        // Basic validation based on attribute type
        if ($this->isEmailField($key) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new SecurityException("Invalid email format for attribute: {$key}");
        }

        if ($this->isUrlField($key) && !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new SecurityException("Invalid URL format for attribute: {$key}");
        }

        // Check for SQL injection patterns
        if (is_string($value) && $this->containsSqlInjection($value)) {
            throw new SecurityException("Potential SQL injection detected in attribute: {$key}");
        }

        // Check for XSS patterns
        if (is_string($value) && $this->containsXss($value)) {
            throw new SecurityException("Potential XSS attack detected in attribute: {$key}");
        }
    }

    /**
     * Check if an attribute is a content field that needs HTML sanitization.
     */
    protected function isContentField(string $key): bool
    {
        $contentFields = ['content', 'description', 'body', 'text', 'message', 'comment'];
        
        return in_array($key, $contentFields) || 
               str_contains($key, 'content') || 
               str_contains($key, 'description') ||
               str_contains($key, 'text');
    }

    /**
     * Check if an attribute is an email field.
     */
    protected function isEmailField(string $key): bool
    {
        return $key === 'email' || str_contains($key, 'email');
    }

    /**
     * Check if an attribute is a URL field.
     */
    protected function isUrlField(string $key): bool
    {
        $urlFields = ['url', 'website', 'link', 'homepage'];
        
        return in_array($key, $urlFields) || 
               str_contains($key, 'url') || 
               str_contains($key, 'link');
    }

    /**
     * Sanitize HTML content.
     */
    protected function sanitizeHtml(string $value): string
    {
        // Remove potentially dangerous tags
        $dangerousTags = [
            'script', 'iframe', 'object', 'embed', 'form', 'input', 
            'button', 'textarea', 'select', 'option', 'link', 'meta'
        ];

        foreach ($dangerousTags as $tag) {
            $value = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', '', $value);
            $value = preg_replace('/<' . $tag . '\b[^>]*\/?>/', '', $value);
        }

        // Remove javascript: and data: protocols
        $value = preg_replace('/javascript:/i', '', $value);
        $value = preg_replace('/data:/i', '', $value);
        $value = preg_replace('/vbscript:/i', '', $value);

        // Remove event handlers
        $value = preg_replace('/on\w+\s*=/i', '', $value);

        return $value;
    }

    /**
     * Check if a string contains potential SQL injection patterns.
     */
    protected function containsSqlInjection(string $value): bool
    {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b.*\bWHERE\b)/i',
            '/(\bINSERT\b.*\bINTO\b.*\bVALUES\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bCREATE\b.*\bTABLE\b)/i',
            '/(\bALTER\b.*\bTABLE\b)/i',
            '/(;|\||&)\s*(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER)\b/i',
            '/\b(EXEC|EXECUTE)\b/i',
            '/\b(SCRIPT|JAVASCRIPT|VBSCRIPT)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string contains potential XSS patterns.
     */
    protected function containsXss(string $value): bool
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
            '/<\s*link[^>]+rel\s*=\s*["\']?\s*stylesheet/i',
            '/<\s*object/i',
            '/<\s*embed/i',
            '/<\s*applet/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log a security operation for auditing.
     */
    protected function logSecurityOperation(string $operation, string $attribute, mixed $value): void
    {
        if ($this->shouldAuditAttribute($attribute)) {
            $this->securityAudit[] = [
                'operation' => $operation,
                'attribute' => $attribute,
                'value_type' => gettype($value),
                'timestamp' => time(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ];
        }
    }

    /**
     * Determine if an attribute should be audited.
     */
    protected function shouldAuditAttribute(string $attribute): bool
    {
        $auditedAttributes = [
            'password', 'email', 'username', 'role', 'permissions',
            'status', 'is_admin', 'is_active', 'balance', 'credit_card'
        ];

        return in_array($attribute, $auditedAttributes) ||
               str_contains($attribute, 'password') ||
               str_contains($attribute, 'secret') ||
               str_contains($attribute, 'token') ||
               str_contains($attribute, 'key');
    }

    /**
     * Get the security audit log.
     */
    public function getSecurityAudit(): array
    {
        return $this->securityAudit;
    }

    /**
     * Clear the security audit log.
     */
    public function clearSecurityAudit(): void
    {
        $this->securityAudit = [];
    }

    /**
     * Disable mass assignment protection.
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Enable mass assignment protection.
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Determine if current state is "unguarded".
     */
    public static function isUnguarded(): bool
    {
        return static::$unguarded;
    }

    /**
     * Run the given callable while being unguarded.
     */
    public static function unguarded(callable $callback): mixed
    {
        if (static::$unguarded) {
            return $callback();
        }

        static::unguard();

        try {
            return $callback();
        } finally {
            static::reguard();
        }
    }

    /**
     * Create a new model instance with enhanced security validation.
     */
    public static function createSecurely(array $attributes = []): static
    {
        $instance = new static();
        
        // Perform additional security checks
        $instance->performSecurityChecks($attributes);
        
        return $instance->create($attributes);
    }

    /**
     * Update the model with enhanced security validation.
     */
    public function updateSecurely(array $attributes = [], array $options = []): bool
    {
        // Perform additional security checks
        $this->performSecurityChecks($attributes);
        
        return $this->update($attributes, $options);
    }

    /**
     * Perform comprehensive security checks on attributes.
     */
    protected function performSecurityChecks(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            // Check for suspicious attribute names
            if ($this->isSuspiciousAttributeName($key)) {
                throw new SecurityException("Suspicious attribute name detected: {$key}");
            }

            // Check for oversized values
            if ($this->isValueOversized($key, $value)) {
                throw new SecurityException("Value too large for attribute: {$key}");
            }

            // Check for binary data in text fields
            if ($this->isBinaryInTextField($key, $value)) {
                throw new SecurityException("Binary data detected in text field: {$key}");
            }
        }
    }

    /**
     * Check if an attribute name is suspicious.
     */
    protected function isSuspiciousAttributeName(string $key): bool
    {
        $suspiciousPatterns = [
            '/^(__|\\$)/',  // Names starting with __ or $
            '/\b(eval|exec|system|passthru|shell_exec)\b/i',
            '/\b(script|javascript|vbscript)\b/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value is oversized for its attribute.
     */
    protected function isValueOversized(string $key, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Default size limits (can be overridden per model)
        $limits = [
            'email' => 255,
            'name' => 255,
            'title' => 255,
            'description' => 65535,
            'content' => 16777215,  // MEDIUMTEXT
        ];

        $limit = $limits[$key] ?? 1000; // Default limit
        
        return strlen($value) > $limit;
    }

    /**
     * Check if binary data is present in a text field.
     */
    protected function isBinaryInTextField(string $key, mixed $value): bool
    {
        if (!is_string($value) || !$this->isTextField($key)) {
            return false;
        }

        // Check for null bytes and control characters
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1;
    }

    /**
     * Determine if an attribute is a text field.
     */
    protected function isTextField(string $key): bool
    {
        $textFields = ['name', 'title', 'description', 'content', 'email', 'message'];
        
        return in_array($key, $textFields) ||
               str_contains($key, 'name') ||
               str_contains($key, 'title') ||
               str_contains($key, 'text');
    }
}