<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Http\MiddlewareInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Exceptions\HttpException;

class ValidateSignature implements MiddlewareInterface
{
    /**
     * The names of the query string parameters to ignore.
     */
    protected array $ignore = [];

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next, ...$ignore): ResponseInterface
    {
        $ignoreParams = array_merge($this->ignore, $ignore);

        if ($this->hasValidSignature($request, $ignoreParams)) {
            return $next($request);
        }

        throw new HttpException(401, 'Invalid signature.');
    }

    /**
     * Determine if the given request has a valid signature.
     */
    protected function hasValidSignature(RequestInterface $request, array $ignoreParams = []): bool
    {
        $original = rtrim($request->url() . '?' . $this->buildQueryString($request, $ignoreParams), '?');

        $signature = hash_hmac('sha256', $original, $this->getAppKey());

        return hash_equals(
            $signature,
            (string) $request->query('signature', '')
        );
    }

    /**
     * Build the query string for signature verification.
     */
    protected function buildQueryString(RequestInterface $request, array $ignore): string
    {
        $query = $request->query();

        // Remove signature and ignored parameters
        unset($query['signature']);
        
        foreach ($ignore as $param) {
            unset($query[$param]);
        }

        // Sort parameters by key
        ksort($query);

        return http_build_query($query);
    }

    /**
     * Get the application key.
     */
    protected function getAppKey(): string
    {
        return config('app.key', 'default-key');
    }
}