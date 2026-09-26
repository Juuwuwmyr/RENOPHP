<?php

declare(strict_types=1);

namespace Examples\Models;

/**
 * User Model (Example)
 * 
 * Simple user model for demonstrating route model binding.
 */
class User
{
    /**
     * User data.
     */
    protected array $data;

    /**
     * Sample users database.
     */
    protected static array $users = [
        1 => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'admin'],
        2 => ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'user'],
        3 => ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'role' => 'moderator'],
    ];

    /**
     * Create a new User instance.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Find user by ID.
     */
    public static function find(mixed $id): ?static
    {
        $id = (int) $id;
        
        if (isset(self::$users[$id])) {
            return new static(self::$users[$id]);
        }

        return null;
    }

    /**
     * Find user by field.
     */
    public static function where(string $field, mixed $value): static
    {
        foreach (self::$users as $userData) {
            if (isset($userData[$field]) && $userData[$field] === $value) {
                return new static($userData);
            }
        }

        return new static(); // Empty result for chaining
    }

    /**
     * Get first result.
     */
    public function first(): ?static
    {
        return empty($this->data) ? null : $this;
    }

    /**
     * Get all users.
     */
    public static function all(): array
    {
        return array_map(fn($data) => new static($data), self::$users);
    }

    /**
     * Magic getter.
     */
    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Magic setter.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Check if property exists.
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->data);
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}