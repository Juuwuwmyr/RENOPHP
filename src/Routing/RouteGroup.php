<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Closure;

class RouteGroup
{
    protected array $attributes = [];
    protected Router $router;

    public function __construct(Router $router, array $attributes = [])
    {
        $this->router = $router;
        $this->attributes = $attributes;
    }

    /**
     * Register a group of routes with shared attributes.
     */
    public static function register(Router $router, array $attributes, Closure $callback): void
    {
        $group = new static($router, $attributes);
        $group->execute($callback);
    }

    /**
     * Execute the group callback with the group attributes.
     */
    protected function execute(Closure $callback): void
    {
        // Store the current group stack
        $previousGroupStack = $this->router->getGroupStack();
        
        // Merge current attributes with the group stack
        $this->router->pushGroup($this->attributes);

        try {
            $callback($this->router);
        } finally {
            // Restore the previous group stack
            $this->router->setGroupStack($previousGroupStack);
        }
    }

    /**
     * Merge route attributes with group attributes.
     */
    public static function mergeAttributes(array $new, array $old): array
    {
        $merged = [];

        // Merge prefix
        $merged['prefix'] = static::mergePrefix($new, $old);

        // Merge namespace
        $merged['namespace'] = static::mergeNamespace($new, $old);

        // Merge middleware
        $merged['middleware'] = static::mergeMiddleware($new, $old);

        // Merge where constraints
        $merged['where'] = static::mergeWhere($new, $old);

        // Merge as (name prefix)
        $merged['as'] = static::mergeAs($new, $old);

        // Merge domain
        if (isset($new['domain'])) {
            $merged['domain'] = $new['domain'];
        } elseif (isset($old['domain'])) {
            $merged['domain'] = $old['domain'];
        }

        return array_filter($merged, function ($value) {
            return !is_null($value) && $value !== '' && $value !== [];
        });
    }

    /**
     * Merge prefix attributes.
     */
    protected static function mergePrefix(array $new, array $old): string
    {
        $oldPrefix = $old['prefix'] ?? '';
        $newPrefix = $new['prefix'] ?? '';

        if ($oldPrefix === '' && $newPrefix === '') {
            return '';
        }

        $oldPrefix = trim($oldPrefix, '/');
        $newPrefix = trim($newPrefix, '/');

        if ($oldPrefix === '') {
            return $newPrefix;
        }

        if ($newPrefix === '') {
            return $oldPrefix;
        }

        return $oldPrefix . '/' . $newPrefix;
    }

    /**
     * Merge namespace attributes.
     */
    protected static function mergeNamespace(array $new, array $old): ?string
    {
        $oldNamespace = $old['namespace'] ?? null;
        $newNamespace = $new['namespace'] ?? null;

        if ($oldNamespace === null && $newNamespace === null) {
            return null;
        }

        if ($oldNamespace === null) {
            return $newNamespace;
        }

        if ($newNamespace === null) {
            return $oldNamespace;
        }

        return trim($oldNamespace, '\\') . '\\' . trim($newNamespace, '\\');
    }

    /**
     * Merge middleware attributes.
     */
    protected static function mergeMiddleware(array $new, array $old): array
    {
        $oldMiddleware = static::normalizeMiddleware($old['middleware'] ?? []);
        $newMiddleware = static::normalizeMiddleware($new['middleware'] ?? []);

        return array_values(array_unique(array_merge($oldMiddleware, $newMiddleware)));
    }

    /**
     * Normalize middleware to array format.
     */
    protected static function normalizeMiddleware($middleware): array
    {
        if (is_string($middleware)) {
            return [$middleware];
        }

        return is_array($middleware) ? $middleware : [];
    }

    /**
     * Merge where constraints.
     */
    protected static function mergeWhere(array $new, array $old): array
    {
        $oldWhere = $old['where'] ?? [];
        $newWhere = $new['where'] ?? [];

        return array_merge($oldWhere, $newWhere);
    }

    /**
     * Merge as (name prefix) attributes.
     */
    protected static function mergeAs(array $new, array $old): ?string
    {
        $oldAs = $old['as'] ?? null;
        $newAs = $new['as'] ?? null;

        if ($oldAs === null && $newAs === null) {
            return null;
        }

        if ($oldAs === null) {
            return $newAs;
        }

        if ($newAs === null) {
            return $oldAs;
        }

        return $oldAs . $newAs;
    }
}