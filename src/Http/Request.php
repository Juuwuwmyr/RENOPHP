<?php

declare(strict_types=1);

namespace Horizon\Http;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Support\Arr;
use Horizon\Support\Str;

class Request implements RequestInterface
{
    /**
     * The request URI.
     */
    protected string $requestUri;

    /**
     * The request method.
     */
    protected string $method;

    /**
     * The request headers.
     */
    protected array $headers;

    /**
     * The request cookies.
     */
    protected array $cookies;

    /**
     * The request server variables.
     */
    protected array $server;

    /**
     * The request input data.
     */
    protected array $input;

    /**
     * The request files.
     */
    protected array $files;

    /**
     * The decoded JSON payload.
     */
    protected ?array $json = null;

    /**
     * The raw request content.
     */
    protected ?string $content = null;

    /**
     * The route resolver callback.
     */
    protected ?\Closure $routeResolver = null;

    /**
     * Create a new HTTP request instance.
     */
    public function __construct(
        array $query = [],
        array $request = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ) {
        $this->server = $server;
        $this->headers = $this->parseHeaders($server);
        $this->cookies = $cookies;
        $this->files = $files;
        $this->content = $content;
        $this->method = $this->parseMethod();
        $this->requestUri = $this->parseRequestUri();
        
        // Merge all input sources
        $this->input = array_merge($query, $request, $this->parseJsonInput());
    }

    /**
     * Create a request instance from PHP globals.
     */
    public static function capture(): static
    {
        return new static(
            $_GET ?? [],
            $_POST ?? [],
            $_COOKIE ?? [],
            $_FILES ?? [],
            $_SERVER ?? [],
            file_get_contents('php://input') ?: null
        );
    }

    /**
     * Get the request method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the request path.
     */
    public function path(): string
    {
        $pattern = trim($this->getPathInfo(), '/');

        return $pattern === '' ? '/' : '/' . $pattern;
    }

    /**
     * Get the full URL for the request.
     */
    public function url(): string
    {
        return rtrim(preg_replace('/\?.*/', '', $this->fullUrl()), '/');
    }

    /**
     * Get the full URL with query string parameters.
     */
    public function fullUrl(): string
    {
        $query = $this->getQueryString();

        return $query
            ? $this->url() . '?' . $query
            : $this->url();
    }

    /**
     * Get an input item from the request.
     */
    public function input(string $key = null, mixed $default = null): mixed
    {
        return data_get(
            $this->getInputSource(), $key, $default
        );
    }

    /**
     * Get all input data for the request.
     */
    public function all(): array
    {
        return $this->input;
    }

    /**
     * Get all input data as filtered values (XSS protection).
     */
    public function safe(): array
    {
        return array_map(function ($value) {
            return is_string($value) ? $this->sanitizeString($value) : $value;
        }, $this->all());
    }

    /**
     * Get raw input without XSS filtering.
     */
    public function raw(string $key = null, mixed $default = null): mixed
    {
        return data_get($this->input, $key, $default);
    }

    /**
     * Determine if the request contains a given input item key.
     */
    public function has(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->all();

        foreach ($keys as $value) {
            if (!Arr::has($input, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request contains any of the given inputs.
     */
    public function hasAny(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $input = $this->all();

        return Arr::hasAny($input, $keys);
    }

    /**
     * Determine if the request contains a non-empty value for an input item.
     */
    public function filled(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the given input key is an empty string for "filled".
     */
    protected function isEmptyString(string $key): bool
    {
        $value = $this->input($key);

        return !is_bool($value) && !is_array($value) && trim((string) $value) === '';
    }

    /**
     * Get a subset containing the provided keys with values from the input data.
     */
    public function only(array|string $keys): array
    {
        $results = [];

        $input = $this->all();

        $placeholder = new \stdClass;

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value = data_get($input, $key, $placeholder);

            if ($value !== $placeholder) {
                Arr::set($results, $key, $value);
            }
        }

        return $results;
    }

    /**
     * Get all of the input except for a specified array of keys.
     */
    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = $this->all();

        Arr::forget($results, $keys);

        return $results;
    }

    /**
     * Get a header from the request.
     */
    public function header(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->headers;
        }

        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Get all headers from the request.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Determine if the request contains a header.
     */
    public function hasHeader(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->headers);
    }

    /**
     * Get the bearer token from the request headers.
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }

        return null;
    }

    /**
     * Get a cookie from the request.
     */
    public function cookie(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->cookies;
        }

        return $this->cookies[$key] ?? $default;
    }

    /**
     * Determine if a cookie is set on the request.
     */
    public function hasCookie(string $key): bool
    {
        return array_key_exists($key, $this->cookies);
    }

    /**
     * Get a file from the request.
     */
    public function file(string $key = null): mixed
    {
        if (is_null($key)) {
            return $this->files;
        }

        return $this->files[$key] ?? null;
    }

    /**
     * Determine if the request has a file.
     */
    public function hasFile(string $key): bool
    {
        if (!array_key_exists($key, $this->files)) {
            return false;
        }

        $file = $this->file($key);

        return $file instanceof UploadedFile && $file->isValid();
    }

    /**
     * Get the client IP address.
     */
    public function ip(): string
    {
        return $this->getClientIp();
    }

    /**
     * Get the client IPs.
     */
    public function ips(): array
    {
        return $this->getClientIps();
    }

    /**
     * Get the User Agent.
     */
    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    /**
     * Determine if the request is over HTTPS.
     */
    public function secure(): bool
    {
        return $this->isSecure();
    }

    /**
     * Determine if the request expects JSON.
     */
    public function expectsJson(): bool
    {
        return ($this->ajax() && !$this->pjax() && $this->acceptsAnyContentType()) ||
               $this->wantsJson();
    }

    /**
     * Determine if the request wants JSON.
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();

        return isset($acceptable[0]) && Str::contains($acceptable[0], ['/json', '+json']);
    }

    /**
     * Determine if the request is AJAX.
     */
    public function ajax(): bool
    {
        return 'XMLHttpRequest' === $this->header('X-Requested-With');
    }

    /**
     * Determine if the request is PJAX.
     */
    public function pjax(): bool
    {
        return $this->hasHeader('X-PJAX');
    }

    /**
     * Determine if the request is the result of a prefetch call.
     */
    public function prefetch(): bool
    {
        return strcasecmp($this->server('HTTP_X_MOZ') ?? '', 'prefetch') === 0 ||
               strcasecmp($this->header('Purpose') ?? '', 'prefetch') === 0;
    }

    /**
     * Get the request content.
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }

    /**
     * Get JSON payload.
     */
    public function json(string $key = null, mixed $default = null): mixed
    {
        if (!isset($this->json)) {
            $this->json = json_decode($this->getContent(), true);
        }

        if (is_null($key)) {
            return $this->json;
        }

        return data_get($this->json, $key, $default);
    }

    /**
     * Determine if the request is sending JSON.
     */
    public function isJson(): bool
    {
        return Str::contains($this->header('CONTENT_TYPE') ?? '', ['/json', '+json']);
    }

    /**
     * Get the session associated with the request.
     */
    public function session(): mixed
    {
        throw new \RuntimeException('Session support is not implemented yet.');
    }

    /**
     * Get the user making the request.
     */
    public function user(): mixed
    {
        throw new \RuntimeException('Authentication support is not implemented yet.');
    }

    /**
     * Get the route handling the request.
     */
    public function route(string $param = null, mixed $default = null): mixed
    {
        $route = call_user_func($this->getRouteResolver());

        if (is_null($route) || is_null($param)) {
            return $route;
        }

        return $route->parameter($param, $default);
    }

    /**
     * Get the route resolver callback.
     */
    public function getRouteResolver(): \Closure
    {
        return $this->routeResolver ?: function () {
            return null;
        };
    }

    /**
     * Set the route resolver callback.
     */
    public function setRouteResolver(\Closure $callback): static
    {
        $this->routeResolver = $callback;

        return $this;
    }

    /**
     * Get the host name.
     */
    public function getHost(): string
    {
        return $this->server('HTTP_HOST', 'localhost');
    }

    /**
     * Determine if the current request URL and query string match a pattern.
     */
    public function fullUrlIs(string $pattern): bool
    {
        $url = $this->fullUrl();
        
        return Str::is($pattern, $url);
    }

    /**
     * Determine if the current request URI matches a pattern.
     */
    public function is(string ...$patterns): bool
    {
        $path = $this->path();
        
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get a server variable from the request.
     */
    public function server(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    /**
     * Parse headers from server variables.
     */
    protected function parseHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Parse the request method.
     */
    protected function parseMethod(): string
    {
        $method = strtoupper($this->server('REQUEST_METHOD', 'GET'));

        if ($method === 'POST') {
            if ($override = $this->input('_method')) {
                $method = strtoupper($override);
            } elseif ($override = $this->header('X-HTTP-Method-Override')) {
                $method = strtoupper($override);
            }
        }

        return $method;
    }

    /**
     * Parse the request URI.
     */
    protected function parseRequestUri(): string
    {
        return $this->server('REQUEST_URI', '/');
    }

    /**
     * Parse JSON input if the request is JSON.
     */
    protected function parseJsonInput(): array
    {
        if ($this->isJson() && $this->getContent()) {
            return json_decode($this->getContent(), true) ?: [];
        }

        return [];
    }

    /**
     * Get the input source for the request.
     */
    protected function getInputSource(): array
    {
        return $this->input;
    }

    /**
     * Sanitize a string value to prevent XSS.
     */
    protected function sanitizeString(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Get the path info for the request.
     */
    protected function getPathInfo(): string
    {
        if (null === ($requestUri = $this->getRequestUri())) {
            return '/';
        }

        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        return $requestUri;
    }

    /**
     * Get the request URI.
     */
    protected function getRequestUri(): string
    {
        return $this->requestUri;
    }

    /**
     * Get the query string for the request.
     */
    protected function getQueryString(): ?string
    {
        return $this->server('QUERY_STRING');
    }

    /**
     * Determine if the request is secure.
     */
    protected function isSecure(): bool
    {
        if ($this->isFromTrustedProxy() && $proto = $this->getTrustedHeaderValue('HTTPS')) {
            return in_array(strtolower($proto), ['on', '1', 'true']);
        }

        $https = $this->server('HTTPS');

        return !empty($https) && strtolower($https) !== 'off';
    }

    /**
     * Get the client IP address.
     */
    protected function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if ($ip = $this->server($key)) {
                foreach (explode(',', $ip) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $this->server('REMOTE_ADDR', '127.0.0.1');
    }

    /**
     * Get the client IPs.
     */
    protected function getClientIps(): array
    {
        $ip = $this->server('HTTP_X_FORWARDED_FOR') ?: $this->server('REMOTE_ADDR');

        return $ip ? array_map('trim', explode(',', $ip)) : [];
    }

    /**
     * Get acceptable content types.
     */
    protected function getAcceptableContentTypes(): array
    {
        return array_map('trim', explode(',', $this->header('accept', '')));
    }

    /**
     * Determine if the request accepts any content type.
     */
    protected function acceptsAnyContentType(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();

        return count($acceptable) === 0 || (
            isset($acceptable[0]) && ($acceptable[0] === '*/*' || $acceptable[0] === '*')
        );
    }

    /**
     * Determine if we're dealing with a trusted proxy.
     */
    protected function isFromTrustedProxy(): bool
    {
        // This would check against configured trusted proxies
        // For now, return false as a secure default
        return false;
    }

    /**
     * Get trusted header value.
     */
    protected function getTrustedHeaderValue(string $header): ?string
    {
        // This would implement trusted proxy header parsing
        // For now, return null as a secure default
        return null;
    }
}