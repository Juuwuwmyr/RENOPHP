<?php

declare(strict_types=1);

namespace Reno\Http;

use InvalidArgumentException;

/**
 * HTTP Cookie
 * 
 * Represents an HTTP cookie with secure defaults and comprehensive
 * configuration options.
 */
class Cookie
{
    /**
     * Cookie name.
     */
    protected string $name;

    /**
     * Cookie value.
     */
    protected string $value;

    /**
     * Cookie expiration time.
     */
    protected int $expire;

    /**
     * Cookie path.
     */
    protected string $path;

    /**
     * Cookie domain.
     */
    protected string $domain;

    /**
     * Whether cookie should only be sent over HTTPS.
     */
    protected bool $secure;

    /**
     * Whether cookie should be HTTP only.
     */
    protected bool $httpOnly;

    /**
     * Cookie SameSite attribute.
     */
    protected string $sameSite;

    /**
     * Valid SameSite values.
     */
    protected static array $validSameSite = ['None', 'Lax', 'Strict'];

    /**
     * Create a new Cookie instance.
     */
    public function __construct(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ) {
        $this->validateName($name);
        $this->validateSameSite($sameSite);

        $this->name = $name;
        $this->value = $value;
        $this->expire = $expire;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }

    /**
     * Create a new Cookie instance with factory method.
     */
    public static function make(
        string $name,
        string $value = '',
        int $minutes = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $expire = $minutes > 0 ? time() + ($minutes * 60) : 0;

        return new static($name, $value, $expire, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Create a cookie that expires when browser closes.
     */
    public static function session(
        string $name,
        string $value = '',
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        return new static($name, $value, 0, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Create a persistent cookie.
     */
    public static function forever(
        string $name,
        string $value = '',
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        // Set to expire in 5 years (maximum practical time)
        $expire = time() + (5 * 365 * 24 * 60 * 60);

        return new static($name, $value, $expire, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Create an expired cookie (for deletion).
     */
    public static function forget(
        string $name,
        string $path = '/',
        string $domain = ''
    ): static {
        return new static($name, '', time() - 3600, $path, $domain);
    }

    // ====================================================================
    // Getters
    // ====================================================================

    /**
     * Get cookie name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get cookie value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get expiration time.
     */
    public function getExpiresTime(): int
    {
        return $this->expire;
    }

    /**
     * Get expiration as DateTime.
     */
    public function getExpiresDateTime(): ?\DateTime
    {
        if ($this->expire === 0) {
            return null;
        }

        return new \DateTime('@' . $this->expire);
    }

    /**
     * Get cookie path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get cookie domain.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Check if cookie is secure.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Check if cookie is HTTP only.
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * Get SameSite attribute.
     */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    // ====================================================================
    // Setters (Fluent Interface)
    // ====================================================================

    /**
     * Set cookie value.
     */
    public function withValue(string $value): static
    {
        $clone = clone $this;
        $clone->value = $value;
        return $clone;
    }

    /**
     * Set expiration time.
     */
    public function withExpire(int $expire): static
    {
        $clone = clone $this;
        $clone->expire = $expire;
        return $clone;
    }

    /**
     * Set expiration in minutes from now.
     */
    public function withMinutes(int $minutes): static
    {
        $expire = $minutes > 0 ? time() + ($minutes * 60) : 0;
        return $this->withExpire($expire);
    }

    /**
     * Set cookie path.
     */
    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    /**
     * Set cookie domain.
     */
    public function withDomain(string $domain): static
    {
        $clone = clone $this;
        $clone->domain = $domain;
        return $clone;
    }

    /**
     * Set secure flag.
     */
    public function withSecure(bool $secure = true): static
    {
        $clone = clone $this;
        $clone->secure = $secure;
        return $clone;
    }

    /**
     * Set HTTP only flag.
     */
    public function withHttpOnly(bool $httpOnly = true): static
    {
        $clone = clone $this;
        $clone->httpOnly = $httpOnly;
        return $clone;
    }

    /**
     * Set SameSite attribute.
     */
    public function withSameSite(string $sameSite): static
    {
        $this->validateSameSite($sameSite);
        
        $clone = clone $this;
        $clone->sameSite = $sameSite;
        return $clone;
    }

    // ====================================================================
    // State Checks
    // ====================================================================

    /**
     * Check if cookie is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expire === 0) {
            return false; // Session cookie
        }

        return $this->expire < time();
    }

    /**
     * Check if cookie is a session cookie.
     */
    public function isSession(): bool
    {
        return $this->expire === 0;
    }

    /**
     * Check if cookie is persistent.
     */
    public function isPersistent(): bool
    {
        return $this->expire > 0;
    }

    /**
     * Check if cookie will be cleared.
     */
    public function isCleared(): bool
    {
        return empty($this->value) || $this->isExpired();
    }

    // ====================================================================
    // Output Methods
    // ====================================================================

    /**
     * Convert cookie to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'expire' => $this->expire,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httpOnly' => $this->httpOnly,
            'sameSite' => $this->sameSite,
        ];
    }

    /**
     * Convert cookie to header string.
     */
    public function toHeaderString(): string
    {
        $cookie = $this->name . '=' . rawurlencode($this->value);

        if ($this->expire !== 0) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $this->expire);
            $cookie .= '; Max-Age=' . ($this->expire - time());
        }

        if (!empty($this->path)) {
            $cookie .= '; Path=' . $this->path;
        }

        if (!empty($this->domain)) {
            $cookie .= '; Domain=' . $this->domain;
        }

        if ($this->secure) {
            $cookie .= '; Secure';
        }

        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if (!empty($this->sameSite)) {
            $cookie .= '; SameSite=' . $this->sameSite;
        }

        return $cookie;
    }

    /**
     * Send cookie to browser.
     */
    public function send(): bool
    {
        return setcookie(
            $this->name,
            $this->value,
            [
                'expires' => $this->expire,
                'path' => $this->path,
                'domain' => $this->domain,
                'secure' => $this->secure,
                'httponly' => $this->httpOnly,
                'samesite' => $this->sameSite,
            ]
        );
    }

    // ====================================================================
    // Validation Methods
    // ====================================================================

    /**
     * Validate cookie name.
     */
    protected function validateName(string $name): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Cookie name cannot be empty.');
        }

        // RFC 6265: Cookie name should not contain special characters
        if (preg_match('/[\x00-\x20\x22\x28-\x29\x2c\x2f\x3a-\x40\x5b-\x5d\x7b\x7d\x7f]/', $name)) {
            throw new InvalidArgumentException("Cookie name '{$name}' contains invalid characters.");
        }
    }

    /**
     * Validate SameSite value.
     */
    protected function validateSameSite(string $sameSite): void
    {
        if (!in_array($sameSite, static::$validSameSite, true)) {
            throw new InvalidArgumentException(
                "Invalid SameSite value '{$sameSite}'. Must be one of: " . implode(', ', static::$validSameSite)
            );
        }
    }

    // ====================================================================
    // Static Helper Methods
    // ====================================================================

    /**
     * Parse cookie header string.
     */
    public static function parseHeader(string $header): array
    {
        $cookies = [];
        $parts = explode(';', $header);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '=')) {
                [$name, $value] = explode('=', $part, 2);
                $cookies[trim($name)] = trim($value);
            }
        }

        return $cookies;
    }

    /**
     * Check if cookie name is valid.
     */
    public static function isValidName(string $name): bool
    {
        try {
            (new static($name))->validateName($name);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    // ====================================================================
    // Magic Methods
    // ====================================================================

    /**
     * Convert cookie to string.
     */
    public function __toString(): string
    {
        return $this->toHeaderString();
    }

    /**
     * Clone cookie.
     */
    public function __clone(): void
    {
        // Nothing special needed for cloning
    }

    /**
     * Serialize cookie.
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * Unserialize cookie.
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->value = $data['value'];
        $this->expire = $data['expire'];
        $this->path = $data['path'];
        $this->domain = $data['domain'];
        $this->secure = $data['secure'];
        $this->httpOnly = $data['httpOnly'];
        $this->sameSite = $data['sameSite'];
    }
}