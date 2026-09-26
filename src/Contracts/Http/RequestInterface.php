<?php

declare(strict_types=1);

namespace Reno\Contracts\Http;

interface RequestInterface
{
    /**
     * Get the request method.
     */
    public function method(): string;

    /**
     * Get the request path.
     */
    public function path(): string;

    /**
     * Get the full URL for the request.
     */
    public function url(): string;

    /**
     * Get the full URL with query string parameters.
     */
    public function fullUrl(): string;

    /**
     * Get an input item from the request.
     */
    public function input(string $key = null, mixed $default = null): mixed;

    /**
     * Get all input data for the request.
     */
    public function all(): array;

    /**
     * Determine if the request contains a given input item key.
     */
    public function has(string|array $key): bool;

    /**
     * Get a header from the request.
     */
    public function header(string $key = null, mixed $default = null): mixed;

    /**
     * Get all headers from the request.
     */
    public function headers(): array;

    /**
     * Get a cookie from the request.
     */
    public function cookie(string $key = null, mixed $default = null): mixed;

    /**
     * Get a file from the request.
     */
    public function file(string $key = null): mixed;

    /**
     * Determine if the request has a file.
     */
    public function hasFile(string $key): bool;

    /**
     * Get the client IP address.
     */
    public function ip(): string;

    /**
     * Get the User Agent.
     */
    public function userAgent(): ?string;

    /**
     * Determine if the request is over HTTPS.
     */
    public function secure(): bool;

    /**
     * Determine if the request expects JSON.
     */
    public function expectsJson(): bool;

    /**
     * Determine if the request is AJAX.
     */
    public function ajax(): bool;

    /**
     * Get the request content.
     */
    public function getContent(): string;

    /**
     * Get JSON payload.
     */
    public function json(string $key = null, mixed $default = null): mixed;

    /**
     * Get the session associated with the request.
     */
    public function session(): mixed;

    /**
     * Get the user making the request.
     */
    public function user(): mixed;
}