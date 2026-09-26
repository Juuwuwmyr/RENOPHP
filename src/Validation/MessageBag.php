<?php

namespace Horizon\Validation;

use Countable;
use JsonSerializable;

/**
 * MessageBag
 * 
 * Container for validation error messages.
 * Provides convenient methods to access and manipulate error messages.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple array-based storage
 * - Intuitive access methods
 * - Clear structure
 */
class MessageBag implements Countable, JsonSerializable
{
    /**
     * All validation error messages
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * Create a new message bag
     *
     * @param array $messages
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $field => $fieldMessages) {
            $this->messages[$field] = (array) $fieldMessages;
        }
    }

    /**
     * Add a message to the bag
     *
     * @param string $field
     * @param string $message
     * @return $this
     */
    public function add(string $field, string $message): self
    {
        if (!isset($this->messages[$field])) {
            $this->messages[$field] = [];
        }

        $this->messages[$field][] = $message;

        return $this;
    }

    /**
     * Merge another MessageBag into this one
     *
     * @param MessageBag $bag
     * @return $this
     */
    public function merge(MessageBag $bag): self
    {
        foreach ($bag->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $this->add($field, $message);
            }
        }

        return $this;
    }

    /**
     * Check if the bag has any messages
     *
     * @return bool
     */
    public function any(): bool
    {
        return !empty($this->messages);
    }

    /**
     * Check if the bag is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    /**
     * Check if a field has any messages
     *
     * @param string $field
     * @return bool
     */
    public function has(string $field): bool
    {
        return isset($this->messages[$field]) && !empty($this->messages[$field]);
    }

    /**
     * Get the first message for a field
     *
     * @param string|null $field
     * @return string|null
     */
    public function first(?string $field = null): ?string
    {
        if ($field === null) {
            // Get the first message from any field
            foreach ($this->messages as $messages) {
                if (!empty($messages)) {
                    return $messages[0];
                }
            }
            return null;
        }

        return $this->messages[$field][0] ?? null;
    }

    /**
     * Get all messages for a field
     *
     * @param string $field
     * @return array
     */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    /**
     * Get all messages for all fields
     *
     * @return array
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->messages as $messages) {
            $all = array_merge($all, $messages);
        }

        return $all;
    }

    /**
     * Get the messages as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->messages;
    }

    /**
     * Get the messages as JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->messages);
    }

    /**
     * Get fields that have errors
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    /**
     * Count the number of messages
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Count the number of fields with errors
     *
     * @return int
     */
    public function countFields(): int
    {
        return count($this->messages);
    }

    /**
     * Get the messages for JSON serialization
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->messages;
    }

    /**
     * Convert to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
