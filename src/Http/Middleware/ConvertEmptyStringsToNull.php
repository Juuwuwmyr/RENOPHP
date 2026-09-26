<?php

declare(strict_types=1);

namespace Reno\Http\Middleware;

use Reno\Contracts\Http\MiddlewareInterface;
use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Http\ResponseInterface;

class ConvertEmptyStringsToNull implements MiddlewareInterface
{
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
            $bag[$key] = $this->cleanValue($value);
        }
    }

    /**
     * Clean the given value.
     */
    protected function cleanValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->cleanArray($value);
        }

        return $this->transform($value);
    }

    /**
     * Clean the given array.
     */
    protected function cleanArray(array $array): array
    {
        $cleaned = [];

        foreach ($array as $key => $value) {
            $cleaned[$key] = $this->cleanValue($value);
        }

        return $cleaned;
    }

    /**
     * Transform the given value.
     */
    protected function transform(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}