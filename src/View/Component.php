<?php

namespace Reno\View;

/**
 * Component
 * 
 * Base class for view components.
 * Components are reusable view elements with props and slots.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear component structure
 * - Explicit props and slots
 * - Easy to understand and debug
 */
abstract class Component
{
    /**
     * Component attributes/props
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * Component slots
     *
     * @var array
     */
    protected array $slots = [];

    /**
     * Create a new component instance
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the view for the component
     *
     * @return string
     */
    abstract public function render(): string;

    /**
     * Get a component attribute
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set a slot
     *
     * @param string $name
     * @param string $content
     * @return void
     */
    public function setSlot(string $name, string $content): void
    {
        $this->slots[$name] = $content;
    }

    /**
     * Get a slot
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public function slot(string $name, string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    /**
     * Check if slot exists
     *
     * @param string $name
     * @return bool
     */
    public function hasSlot(string $name): bool
    {
        return isset($this->slots[$name]);
    }

    /**
     * Get all slots
     *
     * @return array
     */
    public function slots(): array
    {
        return $this->slots;
    }

    /**
     * Convert component to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Dynamically access attributes
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->attribute($key);
    }

    /**
     * Check if attribute exists
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
