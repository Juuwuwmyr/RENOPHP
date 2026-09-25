<?php

declare(strict_types=1);

namespace Horizon\Contracts\Http;

interface ResponseInterface
{
    /**
     * Set the response status code.
     */
    public function status(int $code): static;

    /**
     * Get the response status code.
     */
    public function getStatusCode(): int;

    /**
     * Set a header on the response.
     */
    public function header(string $name, string $value): static;

    /**
     * Get a header from the response.
     */
    public function getHeader(string $name): ?string;

    /**
     * Get all headers from the response.
     */
    public function getHeaders(): array;

    /**
     * Set a cookie on the response.
     */
    public function cookie(string $name, string $value, array $options = []): static;

    /**
     * Set the response content.
     */
    public function content(string $content): static;

    /**
     * Get the response content.
     */
    public function getContent(): string;

    /**
     * Return a JSON response.
     */
    public function json(array|object $data, int $status = 200): static;

    /**
     * Return a redirect response.
     */
    public function redirect(string $url, int $status = 302): static;

    /**
     * Send the response to the browser.
     */
    public function send(): void;

    /**
     * Determine if the response is a redirect.
     */
    public function isRedirect(): bool;

    /**
     * Determine if the response is successful.
     */
    public function isSuccessful(): bool;

    /**
     * Determine if the response is a client error.
     */
    public function isClientError(): bool;

    /**
     * Determine if the response is a server error.
     */
    public function isServerError(): bool;
}