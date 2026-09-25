<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Http\MiddlewareInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Exceptions\HttpException;
use Horizon\Support\Str;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected array $except = [];

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        if (
            $this->isReading($request) ||
            $this->runningUnitTests() ||
            $this->inExceptArray($request) ||
            $this->tokensMatch($request)
        ) {
            return $this->addCookieToResponse($request, $next($request));
        }

        throw new HttpException(419, 'CSRF token mismatch.');
    }

    /**
     * Determine if the HTTP request uses a 'read' verb.
     */
    protected function isReading(RequestInterface $request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']);
    }

    /**
     * Determine if the application is running unit tests.
     */
    protected function runningUnitTests(): bool
    {
        return app()->environment('testing');
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray(RequestInterface $request): bool
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch(RequestInterface $request): bool
    {
        $token = $this->getTokenFromRequest($request);

        return is_string($this->getSessionToken($request)) &&
               is_string($token) &&
               hash_equals($this->getSessionToken($request), $token);
    }

    /**
     * Get the CSRF token from the request.
     */
    protected function getTokenFromRequest(RequestInterface $request): ?string
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (!$token && $header = $request->header('X-XSRF-TOKEN')) {
            try {
                $token = $this->decryptCookie($header);
            } catch (\Exception $e) {
                $token = null;
            }
        }

        return $token;
    }

    /**
     * Get the CSRF token from the session.
     */
    protected function getSessionToken(RequestInterface $request): ?string
    {
        // This would normally get the token from the session
        // For now, we'll use a simple implementation
        return $request->session()->token() ?? null;
    }

    /**
     * Decrypt the cookie value.
     */
    protected function decryptCookie(string $cookie): string
    {
        // Simple base64 decode for now - in production use proper encryption
        return base64_decode($cookie);
    }

    /**
     * Add the CSRF token to the response cookies.
     */
    protected function addCookieToResponse(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = config('session');

        $response->cookie(
            'XSRF-TOKEN',
            $this->encryptToken($this->getSessionToken($request)),
            [
                'expires' => time() + 60 * $config['lifetime'],
                'path' => $config['path'],
                'domain' => $config['domain'],
                'secure' => $config['secure'] ?? $request->secure(),
                'httponly' => false,
                'samesite' => $config['same_site'] ?? null,
            ]
        );

        return $response;
    }

    /**
     * Encrypt the CSRF token for the cookie.
     */
    protected function encryptToken(?string $token): string
    {
        // Simple base64 encode for now - in production use proper encryption
        return base64_encode($token ?? '');
    }

    /**
     * Generate a new CSRF token.
     */
    public function generateToken(): string
    {
        return Str::random(40);
    }
}