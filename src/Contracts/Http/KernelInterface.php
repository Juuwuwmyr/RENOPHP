<?php

declare(strict_types=1);

namespace Horizon\Contracts\Http;

interface KernelInterface
{
    /**
     * Handle an incoming HTTP request.
     */
    public function handle(RequestInterface $request): ResponseInterface;

    /**
     * Perform any final actions for the request lifecycle.
     */
    public function terminate(RequestInterface $request, ResponseInterface $response): void;

    /**
     * Get the bootstrap classes for the application.
     */
    public function bootstrappers(): array;
}