<?php

declare(strict_types=1);

namespace Reno\Middleware;

use Reno\Http\Request;
use Reno\Http\Response;
use Reno\Middleware\MiddlewareInterface;
use Closure;

/**
 * ThrottleMiddleware
 * 
 * Rate limiting middleware that throttles requests based on IP address,
 * user ID, or custom keys. Supports configurable limits and time windows.
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    /**
     * Maximum number of requests.
     */
    protected int $maxAttempts;

    /**
     * Time window in minutes.
     */
    protected int $decayMinutes;

    /**
     * Key generator function.
     */
    protected ?Closure $keyGenerator = null;

    /**
     * Rate limit storage.
     */
    protected static array $attempts = [];

    /**
     * Create a new ThrottleMiddleware instance.
     */
    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1, ?Closure $keyGenerator = null)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->keyGenerator = $keyGenerator;
    }

    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        if ($this->tooManyAttempts($key)) {
            return $this->buildTooManyAttemptsResponse($key);
        }

        $this->incrementAttempts($key);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $this->maxAttempts,
            $this->calculateRemainingAttempts($key)
        );
    }

    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($this->keyGenerator) {
            return ($this->keyGenerator)($request);
        }

        // Default: Use IP address and route
        return sha1(
            $request->ip() . '|' . $request->method() . '|' . $request->path()
        );
    }

    /**
     * Check if too many attempts have been made.
     */
    protected function tooManyAttempts(string $key): bool
    {
        return $this->getAttempts($key) >= $this->maxAttempts;
    }

    /**
     * Get number of attempts for key.
     */
    protected function getAttempts(string $key): int
    {
        $this->cleanExpiredAttempts($key);
        return count(self::$attempts[$key] ?? []);
    }

    /**
     * Increment attempts for key.
     */
    protected function incrementAttempts(string $key): void
    {
        if (!isset(self::$attempts[$key])) {
            self::$attempts[$key] = [];
        }

        self::$attempts[$key][] = time();
        $this->cleanExpiredAttempts($key);
    }

    /**
     * Clean expired attempts for key.
     */
    protected function cleanExpiredAttempts(string $key): void
    {
        if (!isset(self::$attempts[$key])) {
            return;
        }

        $cutoff = time() - ($this->decayMinutes * 60);
        self::$attempts[$key] = array_filter(
            self::$attempts[$key],
            fn($timestamp) => $timestamp > $cutoff
        );

        if (empty(self::$attempts[$key])) {
            unset(self::$attempts[$key]);
        }
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key): int
    {
        return max(0, $this->maxAttempts - $this->getAttempts($key));
    }

    /**
     * Get seconds until reset.
     */
    protected function getSecondsUntilReset(string $key): int
    {
        if (!isset(self::$attempts[$key]) || empty(self::$attempts[$key])) {
            return 0;
        }

        $oldestAttempt = min(self::$attempts[$key]);
        $resetTime = $oldestAttempt + ($this->decayMinutes * 60);
        
        return max(0, $resetTime - time());
    }

    /**
     * Build response for too many attempts.
     */
    protected function buildTooManyAttemptsResponse(string $key): Response
    {
        $retryAfter = $this->getSecondsUntilReset($key);
        
        $response = Response::json([
            'error' => 'Too Many Attempts',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429);

        return $this->addHeaders($response, $this->maxAttempts, 0, $retryAfter);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): Response
    {
        $response->header('X-RateLimit-Limit', (string) $maxAttempts);
        $response->header('X-RateLimit-Remaining', (string) $remainingAttempts);
        $response->header('X-RateLimit-Reset', (string) (time() + ($this->decayMinutes * 60)));

        if ($retryAfter !== null) {
            $response->header('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Create throttle middleware with per-minute limit.
     */
    public static function perMinute(int $maxAttempts): static
    {
        return new static($maxAttempts, 1);
    }

    /**
     * Create throttle middleware with per-hour limit.
     */
    public static function perHour(int $maxAttempts): static
    {
        return new static($maxAttempts, 60);
    }

    /**
     * Create throttle middleware with per-day limit.
     */
    public static function perDay(int $maxAttempts): static
    {
        return new static($maxAttempts, 1440);
    }

    /**
     * Create throttle middleware with custom key generator.
     */
    public static function withKey(int $maxAttempts, int $decayMinutes, Closure $keyGenerator): static
    {
        return new static($maxAttempts, $decayMinutes, $keyGenerator);
    }

    /**
     * Create throttle middleware for authenticated users.
     */
    public static function forUser(int $maxAttempts, int $decayMinutes = 1): static
    {
        return new static($maxAttempts, $decayMinutes, function (Request $request) {
            // In a real implementation, this would get the authenticated user ID
            $userId = $request->header('X-User-ID') ?? 'guest';
            return 'user:' . $userId . '|' . $request->path();
        });
    }

    /**
     * Create throttle middleware for API endpoints.
     */
    public static function forApi(int $maxAttempts = 100, int $decayMinutes = 60): static
    {
        return new static($maxAttempts, $decayMinutes, function (Request $request) {
            $apiKey = $request->bearerToken() ?? $request->header('X-API-Key') ?? 'anonymous';
            return 'api:' . sha1($apiKey) . '|' . $request->path();
        });
    }

    /**
     * Get current rate limit statistics.
     */
    public static function getStats(): array
    {
        $totalKeys = count(self::$attempts);
        $totalAttempts = array_sum(array_map('count', self::$attempts));

        return [
            'tracked_keys' => $totalKeys,
            'total_attempts' => $totalAttempts,
            'memory_usage' => memory_get_usage(),
        ];
    }

    /**
     * Clear all rate limit data.
     */
    public static function clearAll(): void
    {
        self::$attempts = [];
    }

    /**
     * Clear rate limit data for specific key.
     */
    public static function clear(string $key): void
    {
        unset(self::$attempts[$key]);
    }
}