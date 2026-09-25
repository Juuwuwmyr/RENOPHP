<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Http\MiddlewareInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Exceptions\HttpException;

class ThrottleRequests implements MiddlewareInterface
{
    /**
     * The rate limiter instance.
     */
    protected array $cache = [];

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, string $prefix = ''): ResponseInterface
    {
        if ($this->tooManyAttempts($request, $maxAttempts, $decayMinutes, $prefix)) {
            throw $this->buildTooManyAttemptsException($request, $maxAttempts, $decayMinutes, $prefix);
        }

        $this->hit($request, $decayMinutes, $prefix);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($request, $maxAttempts, $prefix)
        );
    }

    /**
     * Determine if the given request has too many attempts.
     */
    protected function tooManyAttempts(RequestInterface $request, int $maxAttempts, int $decayMinutes, string $prefix): bool
    {
        return $this->attempts($request, $prefix) >= $maxAttempts;
    }

    /**
     * Get the number of attempts for the given signature.
     */
    protected function attempts(RequestInterface $request, string $prefix): int
    {
        $key = $this->resolveRequestSignature($request, $prefix);
        
        return $this->cache[$key]['attempts'] ?? 0;
    }

    /**
     * Increment the counter for a given signature.
     */
    protected function hit(RequestInterface $request, int $decayMinutes, string $prefix): void
    {
        $key = $this->resolveRequestSignature($request, $prefix);
        $expiresAt = time() + ($decayMinutes * 60);

        if (!isset($this->cache[$key]) || $this->cache[$key]['expires_at'] < time()) {
            $this->cache[$key] = [
                'attempts' => 1,
                'expires_at' => $expiresAt,
            ];
        } else {
            $this->cache[$key]['attempts']++;
        }
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(RequestInterface $request, int $maxAttempts, string $prefix): int
    {
        return max(0, $maxAttempts - $this->attempts($request, $prefix));
    }

    /**
     * Create a 'too many attempts' exception.
     */
    protected function buildTooManyAttemptsException(RequestInterface $request, int $maxAttempts, int $decayMinutes, string $prefix): HttpException
    {
        $retryAfter = $this->getTimeUntilNextRetry($request, $decayMinutes, $prefix);

        $headers = [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ];

        return new HttpException(
            429,
            'Too Many Attempts.',
            null,
            $headers
        );
    }

    /**
     * Get the number of seconds until the next retry.
     */
    protected function getTimeUntilNextRetry(RequestInterface $request, int $decayMinutes, string $prefix): int
    {
        $key = $this->resolveRequestSignature($request, $prefix);
        
        if (!isset($this->cache[$key])) {
            return $decayMinutes * 60;
        }

        return max(0, $this->cache[$key]['expires_at'] - time());
    }

    /**
     * Add the limit headers to the given response.
     */
    protected function addHeaders(ResponseInterface $response, int $maxAttempts, int $remainingAttempts): ResponseInterface
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }

    /**
     * Resolve the request signature.
     */
    protected function resolveRequestSignature(RequestInterface $request, string $prefix): string
    {
        $user = $request->user();
        
        if ($user) {
            return sha1($prefix . $user->getAuthIdentifier());
        }

        return sha1(
            $prefix . $request->server('SERVER_NAME') . '|' . $request->ip()
        );
    }
}