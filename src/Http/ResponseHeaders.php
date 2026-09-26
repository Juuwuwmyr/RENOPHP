<?php

declare(strict_types=1);

namespace Reno\Http;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Response Headers
 * 
 * Manages HTTP response headers with case-insensitive access
 * and proper header formatting.
 */
class ResponseHeaders implements Countable, IteratorAggregate
{
    /**
     * Headers storage.
     */
    protected array $headers = [];

    /**
     * Header name cache for case-insensitive lookups.
     */
    protected array $cacheControl = [];

    /**
     * Create a new ResponseHeaders instance.
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Set a header value.
     */
    public function set(string $key, mixed $value): void
    {
        $key = $this->normalizeHeaderName($key);
        $this->headers[$key] = $this->normalizeHeaderValue($value);
    }

    /**
     * Add a header value (allows multiple values).
     */
    public function add(string $key, mixed $value): void
    {
        $key = $this->normalizeHeaderName($key);
        $value = $this->normalizeHeaderValue($value);

        if (isset($this->headers[$key])) {
            if (is_array($this->headers[$key])) {
                $this->headers[$key][] = $value;
            } else {
                $this->headers[$key] = [$this->headers[$key], $value];
            }
        } else {
            $this->headers[$key] = $value;
        }
    }

    /**
     * Get a header value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->normalizeHeaderName($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Check if header exists.
     */
    public function has(string $key): bool
    {
        $key = $this->normalizeHeaderName($key);
        return isset($this->headers[$key]);
    }

    /**
     * Remove a header.
     */
    public function remove(string $key): void
    {
        $key = $this->normalizeHeaderName($key);
        unset($this->headers[$key]);
    }

    /**
     * Get all headers.
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * Get header keys.
     */
    public function keys(): array
    {
        return array_keys($this->headers);
    }

    /**
     * Clear all headers.
     */
    public function clear(): void
    {
        $this->headers = [];
    }

    /**
     * Replace all headers.
     */
    public function replace(array $headers): void
    {
        $this->clear();
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get cache control directives.
     */
    public function getCacheControlDirective(string $key): ?string
    {
        if (!isset($this->cacheControl)) {
            $this->parseCacheControl();
        }

        return $this->cacheControl[$key] ?? null;
    }

    /**
     * Set cache control directive.
     */
    public function setCacheControlDirective(string $key, mixed $value = true): void
    {
        if (!isset($this->cacheControl)) {
            $this->parseCacheControl();
        }

        $this->cacheControl[$key] = $value;
        $this->buildCacheControl();
    }

    /**
     * Remove cache control directive.
     */
    public function removeCacheControlDirective(string $key): void
    {
        if (!isset($this->cacheControl)) {
            $this->parseCacheControl();
        }

        unset($this->cacheControl[$key]);
        $this->buildCacheControl();
    }

    /**
     * Check if response is cacheable.
     */
    public function isCacheable(): bool
    {
        if (!$this->has('Cache-Control')) {
            return false;
        }

        $cacheControl = $this->get('Cache-Control');
        
        return !str_contains($cacheControl, 'no-cache') &&
               !str_contains($cacheControl, 'no-store') &&
               !str_contains($cacheControl, 'private');
    }

    /**
     * Set expires header.
     */
    public function setExpires(?\DateTimeInterface $date): void
    {
        if ($date === null) {
            $this->remove('Expires');
        } else {
            $this->set('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
        }
    }

    /**
     * Get expires header as DateTime.
     */
    public function getExpires(): ?\DateTimeInterface
    {
        $expires = $this->get('Expires');
        
        if ($expires === null) {
            return null;
        }

        try {
            return new \DateTime($expires);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set last modified header.
     */
    public function setLastModified(?\DateTimeInterface $date): void
    {
        if ($date === null) {
            $this->remove('Last-Modified');
        } else {
            $this->set('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
        }
    }

    /**
     * Get last modified header as DateTime.
     */
    public function getLastModified(): ?\DateTimeInterface
    {
        $lastModified = $this->get('Last-Modified');
        
        if ($lastModified === null) {
            return null;
        }

        try {
            return new \DateTime($lastModified);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set ETag header.
     */
    public function setETag(?string $etag, bool $weak = false): void
    {
        if ($etag === null) {
            $this->remove('ETag');
        } else {
            if (!str_starts_with($etag, '"')) {
                $etag = '"' . $etag . '"';
            }
            
            if ($weak) {
                $etag = 'W/' . $etag;
            }
            
            $this->set('ETag', $etag);
        }
    }

    /**
     * Get ETag header.
     */
    public function getETag(): ?string
    {
        return $this->get('ETag');
    }

    /**
     * Count headers.
     */
    public function count(): int
    {
        return count($this->headers);
    }

    /**
     * Get iterator for headers.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->headers);
    }

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        $headers = [];
        
        foreach ($this->headers as $name => $values) {
            foreach ((array) $values as $value) {
                $headers[] = "{$name}: {$value}";
            }
        }
        
        return implode("\r\n", $headers);
    }

    /**
     * Normalize header name for consistent storage.
     */
    protected function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', strtolower($name))));
    }

    /**
     * Normalize header value.
     */
    protected function normalizeHeaderValue(mixed $value): string|array
    {
        if (is_array($value)) {
            return array_map('strval', $value);
        }
        
        return (string) $value;
    }

    /**
     * Parse Cache-Control header.
     */
    protected function parseCacheControl(): void
    {
        $this->cacheControl = [];
        
        $cacheControl = $this->get('Cache-Control');
        if (!$cacheControl) {
            return;
        }

        $directives = explode(',', $cacheControl);
        
        foreach ($directives as $directive) {
            $directive = trim($directive);
            
            if (str_contains($directive, '=')) {
                [$key, $value] = explode('=', $directive, 2);
                $this->cacheControl[trim($key)] = trim($value, '"');
            } else {
                $this->cacheControl[$directive] = true;
            }
        }
    }

    /**
     * Build Cache-Control header from directives.
     */
    protected function buildCacheControl(): void
    {
        $directives = [];
        
        foreach ($this->cacheControl as $key => $value) {
            if ($value === true) {
                $directives[] = $key;
            } else {
                $directives[] = "{$key}={$value}";
            }
        }
        
        $this->set('Cache-Control', implode(', ', $directives));
    }
}