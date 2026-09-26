<?php

namespace Reno\Validation;

/**
 * Validator
 * 
 * Core validation engine for the Horizon Framework.
 * Validates data against a set of rules and collects error messages.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear rule syntax
 * - Explicit error messages
 * - Easy to understand validation flow
 * - No hidden magic
 * 
 * Usage:
 * ```php
 * $validator = new Validator($data, [
 *     'email' => 'required|email',
 *     'age' => 'required|numeric|min:18',
 * ]);
 * 
 * if ($validator->fails()) {
 *     $errors = $validator->errors();
 * }
 * ```
 */
class Validator
{
    /**
     * The data under validation
     *
     * @var array
     */
    protected array $data;

    /**
     * The validation rules
     *
     * @var array
     */
    protected array $rules;

    /**
     * Custom error messages
     *
     * @var array
     */
    protected array $messages;

    /**
     * Custom attribute names
     *
     * @var array
     */
    protected array $customAttributes;

    /**
     * The error message bag
     *
     * @var MessageBag|null
     */
    protected ?MessageBag $errorBag = null;

    /**
     * Registered validation rules
     *
     * @var array<string, ValidationRule>
     */
    protected static array $extensions = [];

    /**
     * Whether validation has been run
     *
     * @var bool
     */
    protected bool $hasRun = false;

    /**
     * Database connection for database rules
     *
     * @var mixed
     */
    protected mixed $db = null;

    /**
     * Conditional rules
     *
     * @var array
     */
    protected array $conditionalRules = [];

    /**
     * Custom rule callbacks
     *
     * @var array
     */
    protected array $customCallbacks = [];

    /**
     * Create a new validator instance
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     */
    public function __construct(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->data = $data;
        $this->rules = $this->parseRules($rules);
        $this->messages = $messages;
        $this->customAttributes = $customAttributes;
    }

    /**
     * Run the validation
     *
     * @return bool
     */
    public function validate(): bool
    {
        if ($this->hasRun) {
            return !$this->errorBag->any();
        }

        $this->errorBag = new MessageBag();

        foreach ($this->rules as $attribute => $rules) {
            $value = $this->getValue($attribute);

            foreach ($rules as $rule) {
                $this->validateRule($attribute, $value, $rule);
            }
        }

        // Run custom validation callbacks
        $this->runAfterCallbacks();

        $this->hasRun = true;

        return !$this->errorBag->any();
    }

    /**
     * Validate a single rule
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $rule ['name' => string, 'parameters' => array]
     * @return void
     */
    protected function validateRule(string $attribute, mixed $value, array $rule): void
    {
        $ruleName = $rule['name'];
        $parameters = $rule['parameters'];

        // Check if it's a custom rule
        if (isset(self::$extensions[$ruleName])) {
            $ruleInstance = self::$extensions[$ruleName];
            
            if (!$ruleInstance->passes($attribute, $value, $parameters, $this)) {
                $message = $this->getErrorMessage($attribute, $ruleName, $parameters, $ruleInstance);
                $this->errorBag->add($attribute, $message);
            }
            return;
        }

        // Built-in rule validation
        $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));

        if (method_exists($this, $method)) {
            $passes = $this->$method($attribute, $value, $parameters);

            if (!$passes) {
                $message = $this->getErrorMessage($attribute, $ruleName, $parameters);
                $this->errorBag->add($attribute, $message);
            }
        } else {
            throw new \InvalidArgumentException("Validation rule [{$ruleName}] does not exist.");
        }
    }

    /**
     * Get the error message for a failed validation
     *
     * @param string $attribute
     * @param string $rule
     * @param array $parameters
     * @param ValidationRule|null $ruleInstance
     * @return string
     */
    protected function getErrorMessage(
        string $attribute,
        string $rule,
        array $parameters,
        ?ValidationRule $ruleInstance = null
    ): string {
        $customKey = "{$attribute}.{$rule}";

        // Check for custom message
        if (isset($this->messages[$customKey])) {
            return $this->formatMessage($this->messages[$customKey], $attribute, $parameters);
        }

        if (isset($this->messages[$rule])) {
            return $this->formatMessage($this->messages[$rule], $attribute, $parameters);
        }

        // Use rule instance message if available
        if ($ruleInstance !== null) {
            return $ruleInstance->message($attribute, $parameters);
        }

        // Use default message
        return $this->getDefaultMessage($attribute, $rule, $parameters);
    }

    /**
     * Get the default error message
     *
     * @param string $attribute
     * @param string $rule
     * @param array $parameters
     * @return string
     */
    protected function getDefaultMessage(string $attribute, string $rule, array $parameters): string
    {
        $attribute = $this->getDisplayableAttribute($attribute);

        $messages = [
            'required' => "The {$attribute} field is required.",
            'email' => "The {$attribute} must be a valid email address.",
            'numeric' => "The {$attribute} must be a number.",
            'integer' => "The {$attribute} must be an integer.",
            'string' => "The {$attribute} must be a string.",
            'boolean' => "The {$attribute} must be true or false.",
            'array' => "The {$attribute} must be an array.",
            'min' => "The {$attribute} must be at least {$parameters[0]}.",
            'max' => "The {$attribute} must not be greater than {$parameters[0]}.",
            'between' => "The {$attribute} must be between {$parameters[0]} and {$parameters[1]}.",
            'size' => "The {$attribute} must be {$parameters[0]}.",
            'in' => "The selected {$attribute} is invalid.",
            'not_in' => "The selected {$attribute} is invalid.",
            'regex' => "The {$attribute} format is invalid.",
            'confirmed' => "The {$attribute} confirmation does not match.",
            'same' => "The {$attribute} and {$parameters[0]} must match.",
            'different' => "The {$attribute} and {$parameters[0]} must be different.",
            'date' => "The {$attribute} is not a valid date.",
            'date_format' => "The {$attribute} does not match the format {$parameters[0]}.",
            'before' => "The {$attribute} must be a date before {$parameters[0]}.",
            'after' => "The {$attribute} must be a date after {$parameters[0]}.",
            'url' => "The {$attribute} must be a valid URL.",
            'ip' => "The {$attribute} must be a valid IP address.",
            'ipv4' => "The {$attribute} must be a valid IPv4 address.",
            'ipv6' => "The {$attribute} must be a valid IPv6 address.",
            'json' => "The {$attribute} must be a valid JSON string.",
            'alpha' => "The {$attribute} may only contain letters.",
            'alpha_num' => "The {$attribute} may only contain letters and numbers.",
            'alpha_dash' => "The {$attribute} may only contain letters, numbers, dashes and underscores.",
            'accepted' => "The {$attribute} must be accepted.",
            'declined' => "The {$attribute} must be declined.",
            'uuid' => "The {$attribute} must be a valid UUID.",
            'timezone' => "The {$attribute} must be a valid timezone.",
            'lowercase' => "The {$attribute} must be lowercase.",
            'uppercase' => "The {$attribute} must be uppercase.",
            'starts_with' => "The {$attribute} must start with one of the following: " . implode(', ', $parameters) . ".",
            'ends_with' => "The {$attribute} must end with one of the following: " . implode(', ', $parameters) . ".",
            'present' => "The {$attribute} field must be present.",
            'filled' => "The {$attribute} field must have a value.",
            'multiple_of' => "The {$attribute} must be a multiple of {$parameters[0]}.",
            'file' => "The {$attribute} must be a file.",
            'image' => "The {$attribute} must be an image.",
            'mimes' => "The {$attribute} must be a file of type: " . implode(', ', $parameters) . ".",
            'mimetypes' => "The {$attribute} must be a file of type: " . implode(', ', $parameters) . ".",
            'extensions' => "The {$attribute} must have one of the following extensions: " . implode(', ', $parameters) . ".",
            'max_file_size' => "The {$attribute} must not be larger than {$parameters[0]} kilobytes.",
            'dimensions' => "The {$attribute} has invalid image dimensions.",
            'unique' => "The {$attribute} has already been taken.",
            'exists' => "The selected {$attribute} is invalid.",
        ];

        return $messages[$rule] ?? "The {$attribute} is invalid.";
    }

    /**
     * Format a message with placeholders
     *
     * @param string $message
     * @param string $attribute
     * @param array $parameters
     * @return string
     */
    protected function formatMessage(string $message, string $attribute, array $parameters): string
    {
        $message = str_replace(':attribute', $this->getDisplayableAttribute($attribute), $message);

        foreach ($parameters as $i => $parameter) {
            $message = str_replace(":{$i}", $parameter, $message);
        }

        return $message;
    }

    /**
     * Get the displayable name of the attribute
     *
     * @param string $attribute
     * @return string
     */
    protected function getDisplayableAttribute(string $attribute): string
    {
        if (isset($this->customAttributes[$attribute])) {
            return $this->customAttributes[$attribute];
        }

        // Convert snake_case to Title Case
        return str_replace('_', ' ', $attribute);
    }

    /**
     * Parse validation rules into a structured format
     *
     * @param array $rules
     * @return array
     */
    protected function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $attribute => $ruleSet) {
            $parsed[$attribute] = $this->parseRuleSet($ruleSet);
        }

        return $parsed;
    }

    /**
     * Parse a rule set for an attribute
     *
     * @param mixed $rules
     * @return array
     */
    protected function parseRuleSet(mixed $rules): array
    {
        if (is_array($rules)) {
            return array_map(fn($rule) => $this->parseRule($rule), $rules);
        }

        // Parse pipe-delimited rules
        $rules = is_string($rules) ? explode('|', $rules) : [$rules];

        return array_map(fn($rule) => $this->parseRule($rule), $rules);
    }

    /**
     * Parse a single rule
     *
     * @param mixed $rule
     * @return array ['name' => string, 'parameters' => array]
     */
    protected function parseRule(mixed $rule): array
    {
        if ($rule instanceof ValidationRule) {
            return [
                'name' => $rule->getName(),
                'parameters' => [],
                'instance' => $rule,
            ];
        }

        if (is_string($rule)) {
            // Parse "rule:param1,param2" format
            if (str_contains($rule, ':')) {
                [$name, $params] = explode(':', $rule, 2);
                return [
                    'name' => $name,
                    'parameters' => explode(',', $params),
                ];
            }

            return [
                'name' => $rule,
                'parameters' => [],
            ];
        }

        throw new \InvalidArgumentException('Invalid rule format.');
    }

    /**
     * Get a value from the data array
     *
     * @param string $attribute
     * @return mixed
     */
    protected function getValue(string $attribute): mixed
    {
        // Support nested attributes with dot notation
        if (str_contains($attribute, '.')) {
            return $this->getNestedValue($attribute);
        }

        return $this->data[$attribute] ?? null;
    }

    /**
     * Get a nested value using dot notation
     *
     * @param string $attribute
     * @return mixed
     */
    protected function getNestedValue(string $attribute): mixed
    {
        $keys = explode('.', $attribute);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Check if validation fails
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->validate();
    }

    /**
     * Check if validation passes
     *
     * @return bool
     */
    public function passes(): bool
    {
        return $this->validate();
    }

    /**
     * Get the error message bag
     *
     * @return MessageBag
     */
    public function errors(): MessageBag
    {
        if (!$this->hasRun) {
            $this->validate();
        }

        return $this->errorBag;
    }

    /**
     * Get the validated data
     *
     * @return array
     * @throws ValidationException
     */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors(), $this->data);
        }

        // Return only the data that has validation rules
        $validated = [];

        foreach (array_keys($this->rules) as $attribute) {
            if (str_contains($attribute, '.')) {
                // Handle nested attributes
                $this->setNestedValue($validated, $attribute, $this->getValue($attribute));
            } else {
                $validated[$attribute] = $this->getValue($attribute);
            }
        }

        return $validated;
    }

    /**
     * Set a nested value using dot notation
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    /**
     * Register a custom validation rule
     *
     * @param string $name
     * @param ValidationRule $rule
     * @return void
     */
    public static function extend(string $name, ValidationRule $rule): void
    {
        self::$extensions[$name] = $rule;
    }

    /**
     * Get all data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set the database connection
     *
     * @param mixed $db
     * @return $this
     */
    public function setDatabase(mixed $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Get the database connection
     *
     * @return mixed
     */
    public function getDatabase(): mixed
    {
        return $this->db;
    }

    /**
     * Add validation rules conditionally
     *
     * @param bool|callable $condition
     * @param array $rules
     * @param array $messages
     * @return $this
     */
    public function sometimes(bool|callable $condition, array $rules, array $messages = []): self
    {
        $shouldApply = is_callable($condition) ? $condition($this->data) : $condition;

        if ($shouldApply) {
            $this->rules = array_merge($this->rules, $this->parseRules($rules));
            $this->messages = array_merge($this->messages, $messages);
        }

        return $this;
    }

    /**
     * Add rules only if a field is present
     *
     * @param string $field
     * @param array $rules
     * @param array $messages
     * @return $this
     */
    public function when(string $field, array $rules, array $messages = []): self
    {
        return $this->sometimes(
            fn($data) => isset($data[$field]) && !empty($data[$field]),
            $rules,
            $messages
        );
    }

    /**
     * Add rules only if a field is absent or empty
     *
     * @param string $field
     * @param array $rules
     * @param array $messages
     * @return $this
     */
    public function unless(string $field, array $rules, array $messages = []): self
    {
        return $this->sometimes(
            fn($data) => !isset($data[$field]) || empty($data[$field]),
            $rules,
            $messages
        );
    }

    /**
     * Add a custom validation callback
     *
     * @param string $attribute
     * @param callable $callback
     * @param string $message
     * @return $this
     */
    public function after(callable $callback): self
    {
        $this->customCallbacks[] = $callback;
        return $this;
    }

    /**
     * Run custom validation callbacks after main validation
     *
     * @return void
     */
    protected function runAfterCallbacks(): void
    {
        foreach ($this->customCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Manually add an error to a field
     *
     * @param string $attribute
     * @param string $message
     * @return $this
     */
    public function addError(string $attribute, string $message): self
    {
        if ($this->errorBag === null) {
            $this->errorBag = new MessageBag();
        }

        $this->errorBag->add($attribute, $message);

        return $this;
    }

    /**
     * Validate if value is present (not null, not empty string, not empty array)
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateRequired(string $attribute, mixed $value, array $parameters): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * Validate email address
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateEmail(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate numeric value
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateNumeric(string $attribute, mixed $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    /**
     * Validate integer value
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateInteger(string $attribute, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate string value
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateString(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value);
    }

    /**
     * Validate boolean value
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateBoolean(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    /**
     * Validate array value
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateArray(string $attribute, mixed $value, array $parameters): bool
    {
        return is_array($value);
    }

    /**
     * Validate minimum value/length
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateMin(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $min = $parameters[0];

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * Validate maximum value/length
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateMax(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $max = $parameters[0];

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * Validate value is between min and max
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateBetween(string $attribute, mixed $value, array $parameters): bool
    {
        if (count($parameters) < 2) {
            return false;
        }

        [$min, $max] = $parameters;

        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            return $length >= $min && $length <= $max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= $min && $count <= $max;
        }

        return false;
    }

    /**
     * Validate exact size/length
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateSize(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $size = $parameters[0];

        if (is_numeric($value)) {
            return $value == $size;
        }

        if (is_string($value)) {
            return mb_strlen($value) == $size;
        }

        if (is_array($value)) {
            return count($value) == $size;
        }

        return false;
    }

    /**
     * Validate value is in a list
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateIn(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters, true);
    }

    /**
     * Validate value is not in a list
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateNotIn(string $attribute, mixed $value, array $parameters): bool
    {
        return !in_array($value, $parameters, true);
    }

    /**
     * Validate against a regular expression
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateRegex(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || !isset($parameters[0])) {
            return false;
        }

        return preg_match($parameters[0], $value) > 0;
    }

    /**
     * Validate field confirmation matches
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateConfirmed(string $attribute, mixed $value, array $parameters): bool
    {
        $confirmationField = $attribute . '_confirmation';
        $confirmation = $this->getValue($confirmationField);

        return $value === $confirmation;
    }

    /**
     * Validate two fields match
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateSame(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $other = $this->getValue($parameters[0]);

        return $value === $other;
    }

    /**
     * Validate two fields are different
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateDifferent(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $other = $this->getValue($parameters[0]);

        return $value !== $other;
    }

    /**
     * Validate URL
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateUrl(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate IP address
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateIp(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate IPv4 address
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateIpv4(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate IPv6 address
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateIpv6(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Validate JSON string
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateJson(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate alphabetic characters
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateAlpha(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM]+$/u', $value) > 0;
    }

    /**
     * Validate alphanumeric characters
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateAlphaNum(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN]+$/u', $value) > 0;
    }

    /**
     * Validate alphanumeric with dashes and underscores
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateAlphaDash(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN_-]+$/u', $value) > 0;
    }

    /**
     * Validate date
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateDate(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return strtotime($value) !== false;
    }

    /**
     * Validate date format
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateDateFormat(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || !isset($parameters[0])) {
            return false;
        }

        $date = \DateTime::createFromFormat($parameters[0], $value);

        return $date && $date->format($parameters[0]) === $value;
    }

    /**
     * Validate date is before another date
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateBefore(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $timestamp = strtotime($value);
        $beforeTimestamp = strtotime($parameters[0]);

        return $timestamp !== false && $beforeTimestamp !== false && $timestamp < $beforeTimestamp;
    }

    /**
     * Validate date is after another date
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateAfter(string $attribute, mixed $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $timestamp = strtotime($value);
        $afterTimestamp = strtotime($parameters[0]);

        return $timestamp !== false && $afterTimestamp !== false && $timestamp > $afterTimestamp;
    }

    /**
     * Validate field is nullable (allows null values)
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateNullable(string $attribute, mixed $value, array $parameters): bool
    {
        // Nullable always passes - it's a modifier, not a validation
        return true;
    }

    /**
     * Validate field is accepted (for checkboxes)
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateAccepted(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, ['yes', 'on', '1', 1, true, 'true'], true);
    }

    /**
     * Validate field is declined
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateDeclined(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, ['no', 'off', '0', 0, false, 'false'], true);
    }

    /**
     * Validate UUID
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateUuid(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) > 0;
    }

    /**
     * Validate timezone
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateTimezone(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return in_array($value, \DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Validate lowercase string
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateLowercase(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return $value === mb_strtolower($value);
    }

    /**
     * Validate uppercase string
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateUppercase(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return $value === mb_strtoupper($value);
    }

    /**
     * Validate starts with
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateStartsWith(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }

        foreach ($parameters as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate ends with
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateEndsWith(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }

        foreach ($parameters as $suffix) {
            if (str_ends_with($value, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate field is present (different from required - can be empty)
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validatePresent(string $attribute, mixed $value, array $parameters): bool
    {
        return array_key_exists($attribute, $this->data);
    }

    /**
     * Validate field is filled (not empty if present)
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateFilled(string $attribute, mixed $value, array $parameters): bool
    {
        if (!array_key_exists($attribute, $this->data)) {
            return true; // Not present is okay
        }

        return $this->validateRequired($attribute, $value, $parameters);
    }

    /**
     * Validate multiple of
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateMultipleOf(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_numeric($value) || !isset($parameters[0])) {
            return false;
        }

        $divisor = $parameters[0];

        return fmod($value, $divisor) == 0;
    }

    /**
     * Validate uploaded file
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateFile(string $attribute, mixed $value, array $parameters): bool
    {
        // Check if it's an UploadedFile instance
        if (!$value instanceof \Reno\Http\UploadedFile) {
            return false;
        }

        return $value->isValid();
    }

    /**
     * Validate uploaded image file
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateImage(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($attribute, $value, $parameters)) {
            return false;
        }

        return $value->isImage();
    }

    /**
     * Validate file MIME types
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateMimes(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($attribute, $value, $parameters)) {
            return false;
        }

        if (empty($parameters)) {
            return false;
        }

        // Convert extensions to MIME types
        $allowedMimes = [];
        $mimeMap = $this->getMimeTypeMap();

        foreach ($parameters as $param) {
            if (str_contains($param, '/')) {
                // It's already a MIME type
                $allowedMimes[] = $param;
            } elseif (isset($mimeMap[$param])) {
                // It's an extension, convert to MIME types
                $allowedMimes = array_merge($allowedMimes, (array) $mimeMap[$param]);
            }
        }

        return $value->validateMimeType($allowedMimes);
    }

    /**
     * Validate file extensions
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateMimetypes(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($attribute, $value, $parameters)) {
            return false;
        }

        return $value->validateMimeType($parameters);
    }

    /**
     * Validate file extensions
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateExtensions(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($attribute, $value, $parameters)) {
            return false;
        }

        if (empty($parameters)) {
            return false;
        }

        return $value->validateExtension($parameters);
    }

    /**
     * Validate maximum file size (in kilobytes)
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateMaxFileSize(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($attribute, $value, $parameters)) {
            return false;
        }

        if (!isset($parameters[0])) {
            return false;
        }

        $maxSizeKb = $parameters[0];
        $maxSizeBytes = $maxSizeKb * 1024;

        return $value->validateSize($maxSizeBytes);
    }

    /**
     * Validate image dimensions
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateDimensions(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->validateImage($attribute, $value, $parameters)) {
            return false;
        }

        $dimensions = $value->getImageDimensions();

        if ($dimensions === false) {
            return false;
        }

        [$width, $height] = $dimensions;

        // Parse dimension constraints
        $constraints = $this->parseDimensionConstraints($parameters);

        if (isset($constraints['width']) && $width != $constraints['width']) {
            return false;
        }

        if (isset($constraints['height']) && $height != $constraints['height']) {
            return false;
        }

        if (isset($constraints['min_width']) && $width < $constraints['min_width']) {
            return false;
        }

        if (isset($constraints['min_height']) && $height < $constraints['min_height']) {
            return false;
        }

        if (isset($constraints['max_width']) && $width > $constraints['max_width']) {
            return false;
        }

        if (isset($constraints['max_height']) && $height > $constraints['max_height']) {
            return false;
        }

        if (isset($constraints['ratio'])) {
            $ratio = $width / $height;
            $expectedRatio = $constraints['ratio'];

            // Allow small floating point differences
            if (abs($ratio - $expectedRatio) > 0.01) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse dimension constraints from parameters
     *
     * @param array $parameters
     * @return array
     */
    protected function parseDimensionConstraints(array $parameters): array
    {
        $constraints = [];

        foreach ($parameters as $param) {
            if (str_contains($param, '=')) {
                [$key, $value] = explode('=', $param, 2);
                $key = trim($key);
                $value = trim($value);

                if ($key === 'ratio') {
                    // Handle ratio like "16/9" or "1.5"
                    if (str_contains($value, '/')) {
                        [$w, $h] = explode('/', $value);
                        $constraints['ratio'] = (float) $w / (float) $h;
                    } else {
                        $constraints['ratio'] = (float) $value;
                    }
                } else {
                    $constraints[$key] = (int) $value;
                }
            }
        }

        return $constraints;
    }

    /**
     * Validate value is unique in database
     * 
     * Format: unique:table,column,except,idColumn
     * Examples:
     *   unique:users,email
     *   unique:users,email,1
     *   unique:users,email,1,id
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateUnique(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->db) {
            throw new \RuntimeException('Database connection required for unique validation. Use setDatabase().');
        }

        if (!isset($parameters[0])) {
            throw new \InvalidArgumentException('Unique rule requires table parameter.');
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $attribute;
        $except = $parameters[2] ?? null;
        $idColumn = $parameters[3] ?? 'id';

        try {
            // Build query
            $query = $this->db->table($table)->where($column, '=', $value);

            // Exclude specific ID (for updates)
            if ($except !== null) {
                $query->where($idColumn, '!=', $except);
            }

            $count = $query->count();

            return $count === 0;

        } catch (\Exception $e) {
            throw new \RuntimeException("Unique validation failed: {$e->getMessage()}");
        }
    }

    /**
     * Validate value exists in database
     * 
     * Format: exists:table,column
     * Examples:
     *   exists:users,id
     *   exists:categories,slug
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateExists(string $attribute, mixed $value, array $parameters): bool
    {
        if (!$this->db) {
            throw new \RuntimeException('Database connection required for exists validation. Use setDatabase().');
        }

        if (!isset($parameters[0])) {
            throw new \InvalidArgumentException('Exists rule requires table parameter.');
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $attribute;

        try {
            $count = $this->db->table($table)
                ->where($column, '=', $value)
                ->count();

            return $count > 0;

        } catch (\Exception $e) {
            throw new \RuntimeException("Exists validation failed: {$e->getMessage()}");
        }
    }

    /**
     * Get MIME type mapping for common file extensions
     *
     * @return array
     */
    protected function getMimeTypeMap(): array
    {
        return [
            // Images
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => ['image/bmp', 'image/x-bmp', 'image/x-ms-bmp'],
            'svg' => ['image/svg+xml'],
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],

            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'rtf' => 'application/rtf',

            // Archives
            'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
            'rar' => ['application/x-rar-compressed', 'application/x-rar'],
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => ['application/gzip', 'application/x-gzip'],

            // Audio
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'wav' => ['audio/wav', 'audio/x-wav'],
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',

            // Video
            'mp4' => 'video/mp4',
            'avi' => ['video/x-msvideo', 'video/avi'],
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',

            // Code
            'php' => ['application/x-httpd-php', 'text/x-php'],
            'js' => ['application/javascript', 'text/javascript'],
            'json' => 'application/json',
            'xml' => ['application/xml', 'text/xml'],
            'html' => 'text/html',
            'css' => 'text/css',
        ];
    }
}

