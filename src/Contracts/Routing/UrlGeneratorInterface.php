<?php

declare(strict_types=1);

namespace Reno\Contracts\Routing;

use Reno\Contracts\Http\RequestInterface;

interface UrlGeneratorInterface
{
    /**
     * Generate a URL for the given route.
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string;

    /**
     * Generate a URL for a given route instance.
     */
    public function toRoute(RouteInterface $route, array $parameters = [], bool $absolute = true): string;

    /**
     * Generate an absolute URL to the given path.
     */
    public function to(string $path, mixed $extra = [], ?bool $secure = null): string;

    /**
     * Generate a secure, absolute URL to the given path.
     */
    public function secure(string $path, array $parameters = []): string;

    /**
     * Generate the URL to an application asset.
     */
    public function asset(string $path, ?bool $secure = null): string;

    /**
     * Generate the URL to a secure asset.
     */
    public function secureAsset(string $path): string;

    /**
     * Get the previous URL from the session.
     */
    public function previous(mixed $fallback = false): string;

    /**
     * Get the current request instance.
     */
    public function getRequest(): RequestInterface;

    /**
     * Set the current request instance.
     */
    public function setRequest(RequestInterface $request): void;
}