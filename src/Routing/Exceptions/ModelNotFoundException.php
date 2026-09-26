<?php

declare(strict_types=1);

namespace Reno\Routing\Exceptions;

use Exception;
use Throwable;

/**
 * ModelNotFoundException
 * 
 * Thrown when model binding fails to find the requested model.
 * Corresponds to HTTP 404 Not Found for model resources.
 */
class ModelNotFoundException extends Exception
{
    /**
     * Model class name.
     */
    protected string $model;

    /**
     * Parameter key.
     */
    protected string $parameterKey;

    /**
     * Parameter value.
     */
    protected mixed $parameterValue;

    /**
     * Additional context data.
     */
    protected array $context = [];

    /**
     * Create a new ModelNotFoundException.
     */
    public function __construct(
        string $message = 'Model not found',
        string $model = '',
        string $parameterKey = '',
        mixed $parameterValue = null,
        array $context = [],
        int $code = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->model = $model;
        $this->parameterKey = $parameterKey;
        $this->parameterValue = $parameterValue;
        $this->context = $context;
    }

    /**
     * Create exception for specific model and parameter.
     */
    public static function forModel(string $model, string $key, mixed $value, array $context = []): static
    {
        $message = "No {$model} found with {$key}: {$value}";
        
        return new static($message, $model, $key, $value, $context);
    }

    /**
     * Create exception for implicit binding.
     */
    public static function forImplicitBinding(string $model, mixed $value, array $context = []): static
    {
        $message = "No {$model} found for value: {$value}";
        
        return new static($message, $model, 'id', $value, $context);
    }

    /**
     * Get model class name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get parameter key.
     */
    public function getParameterKey(): string
    {
        return $this->parameterKey;
    }

    /**
     * Get parameter value.
     */
    public function getParameterValue(): mixed
    {
        return $this->parameterValue;
    }

    /**
     * Get context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set context data.
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context item.
     */
    public function addContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get HTTP status code.
     */
    public function getStatusCode(): int
    {
        return 404;
    }

    /**
     * Get suggested alternatives (for debugging).
     */
    public function getSuggestedAlternatives(): array
    {
        return $this->context['suggested_alternatives'] ?? [];
    }

    /**
     * Set suggested alternatives.
     */
    public function setSuggestedAlternatives(array $alternatives): static
    {
        $this->context['suggested_alternatives'] = $alternatives;
        return $this;
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'model' => $this->model,
            'parameter_key' => $this->parameterKey,
            'parameter_value' => $this->parameterValue,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Generate developer-friendly error message.
     */
    public function getDeveloperMessage(): string
    {
        $message = "Model not found: {$this->model}\n";
        $message .= "Parameter: {$this->parameterKey} = {$this->parameterValue}\n\n";
        
        if (!empty($this->context['suggested_alternatives'])) {
            $message .= "Suggested alternatives:\n";
            foreach ($this->context['suggested_alternatives'] as $alternative) {
                $message .= "  - {$alternative}\n";
            }
            $message .= "\n";
        }

        $message .= "Possible solutions:\n";
        $message .= "1. Check if the {$this->model} with {$this->parameterKey} '{$this->parameterValue}' exists\n";
        $message .= "2. Verify the model binding configuration\n";
        $message .= "3. Ensure the model class has the correct find method\n";
        $message .= "4. Check database connection and data integrity\n";
        $message .= "5. Consider adding a custom binding resolver\n";

        return $message;
    }

    /**
     * Check if model class exists.
     */
    public function modelClassExists(): bool
    {
        return class_exists($this->model);
    }

    /**
     * Get model class methods (for debugging).
     */
    public function getModelMethods(): array
    {
        if (!$this->modelClassExists()) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($this->model);
            return array_map(
                fn($method) => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
            );
        } catch (\ReflectionException $e) {
            return [];
        }
    }

    /**
     * Convert exception to string.
     */
    public function __toString(): string
    {
        return $this->getDeveloperMessage();
    }
}