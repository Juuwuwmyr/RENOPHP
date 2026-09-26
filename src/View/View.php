<?php

namespace Horizon\View;

use ArrayAccess;

/**
 * View
 * 
 * Represents a single view with its data and path.
 * Provides methods for rendering and data manipulation.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear data binding
 * - Explicit rendering
 * - Easy to debug
 */
class View implements ArrayAccess
{
    /**
     * View name
     *
     * @var string
     */
    protected string $view;

    /**
     * View path
     *
     * @var string
     */
    protected string $path;

    /**
     * View data
     *
     * @var array
     */
    protected array $data = [];

    /**
     * View factory
     *
     * @var ViewFactory
     */
    protected ViewFactory $factory;

    /**
     * Create a new view instance
     *
     * @param ViewFactory $factory
     * @param string $view
     * @param string $path
     * @param array $data
     */
    public function __construct(ViewFactory $factory, string $view, string $path, array $data = [])
    {
        $this->factory = $factory;
        $this->view = $view;
        $this->path = $path;
        $this->data = $data;
    }

    /**
     * Get the view name
     *
     * @return string
     */
    public function name(): string
    {
        return $this->view;
    }

    /**
     * Get the view path
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get all view data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Add data to the view
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Render the view
     *
     * @return string
     * @throws ViewException
     */
    public function render(): string
    {
        try {
            return $this->factory->getEngine()->render($this->path, $this->data);
        } catch (\Throwable $e) {
            throw ViewException::renderingError($this->view, $e);
        }
    }

    /**
     * Convert view to string
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            // Cannot throw exceptions from __toString
            return "Error rendering view [{$this->view}]: {$e->getMessage()}";
        }
    }

    /**
     * Get data value
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Set data value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Check if data key exists
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Unset data key
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Check if offset exists (ArrayAccess)
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Get offset value (ArrayAccess)
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Set offset value (ArrayAccess)
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Unset offset (ArrayAccess)
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}
