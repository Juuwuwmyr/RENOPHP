<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Http\MiddlewareInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;

class TrimStrings implements MiddlewareInterface
{
    /**
     * The attributes that should not be trimmed.
     */
    protected array $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $this->clean($request);

        return $next($request);
    }

    /**
     * Clean the request's data.
     */
    protected function clean(RequestInterface $request): void
    {
        // For now, we'll skip the actual cleaning since we need to modify request internals
        // This would be implemented when we have a mutable request object
    }

    /**
     * Clean the data in the given array.
     */
    protected function cleanParameterBag(array &$bag): void
    {
        foreach ($bag as $key => $value) {
            if (in_array($key, $this->except, true)) {
                continue;
            }

            $bag[$key] = $this->cleanValue($key, $value);
        }
    }

    /**
     * Clean the given value.
     */
    protected function cleanValue(string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->cleanArray($key, $value);
        }

        return $this->transform($key, $value);
    }

    /**
     * Clean the given array.
     */
    protected function cleanArray(string $key, array $array): array
    {
        $cleaned = [];

        foreach ($array as $itemKey => $item) {
            $cleaned[$itemKey] = $this->cleanValue($key . '.' . $itemKey, $item);
        }

        return $cleaned;
    }

    /**
     * Transform the given value.
     */
    protected function transform(string $key, mixed $value): mixed
    {
        if (in_array($key, $this->except, true)) {
            return $value;
        }

        return is_string($value) ? trim($value) : $value;
    }
}