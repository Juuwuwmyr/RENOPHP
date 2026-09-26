<?php

declare(strict_types=1);

namespace Horizon\Http;

use Horizon\Support\Collection;
use Horizon\Http\UploadedFile;
use InvalidArgumentException;

/**
 * HTTP Request
 * 
 * Represents an HTTP request with comprehensive parameter handling,
 * file uploads, headers, cookies, and content negotiation.
 * 
 * Philosophy: "Clear, explicit, and secure by default"
 */
class Request
{
    /**
     * Request method.
     */
    protected string $method;

    /**
     * Request URI.
     */
    protected string $uri;

    /**
     * Request path info.
     */
    protected string $pathInfo;

    /**
     * Query parameters.
     */
    protected array $query = [];

    /**
     * POST/form data.
     */
    protected array $request = [];

    /**
     * Request headers.
     */
    protected array $headers = [];

    /**
     * The matched route for this request.
     */
    protected ?\Horizon\Routing\Route $route = null;

    /**
     * Route parameters.
     */
    protected array $routeParameters = [];

    /**
     * Server parameters.
     */
    protected array $server = [];

    /**
     * Cookies.
     */
    protected array $cookies = [];

    /**
     * Uploaded files.
     */
    protected array $files = [];

    /**
     * Raw request body.
     */
    protected ?string $content = null;

    /**
     * Parsed JSON data.
     */
    protected ?array $json = null;

    /**
     * Route parameters.
     */
    protected array $routeParameters = [];

    /**
     * Request attributes.
     */
    protected array $attributes = [];

    /**
     * The matched route for this request.
     */
    protected ?\Horizon\Routing\Route $route = null;
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $this->convertUploadedFiles($files);
        $this->server = $server;
        $this->content = $content;

        $this->method = $this->determineMethod();
        $this->uri = $this->determineUri();
        $this->pathInfo = $this->determinePathInfo();
        $this->headers = $this->parseHeaders();
    }

    /**
     * Create Request from PHP globals.
     */
    public static function createFromGlobals(): static
    {
        return new static(
            $_GET ?? [],
            $_POST ?? [],
            [],
            $_COOKIE ?? [],
            $_FILES ?? [],
            $_SERVER ?? [],
            file_get_contents('php://input') ?: null
        );
    }

    /**
     * Create a new Request with specified parameters.
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): static {
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Horizon/1.0',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ], $server);

        $server['REQUEST_URI'] = $uri;
        $server['REQUEST_METHOD'] = strtoupper($method);

        $components = parse_url($uri);
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }

        if (isset($components['scheme'])) {
            if ('https' === $components['scheme']) {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }

        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] .= ':' . $components['port'];
        }

        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }

        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }

        if (!isset($components['path'])) {
            $components['path'] = '/';
        }

        $server['REQUEST_URI'] = $components['path'] . (isset($components['query']) ? '?' . $components['query'] : '');
        $server['QUERY_STRING'] = $components['query'] ?? '';

        if ('POST' === $method || 'PUT' === $method || 'PATCH' === $method) {
            if (!isset($server['CONTENT_TYPE'])) {
                $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
            }
        }

        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        return new static($query, $parameters, [], $cookies, $files, $server, $content);
    }

    // ====================================================================
    // HTTP Method Operations
    // ====================================================================

    /**
     * Get the request method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Check if the request method is GET.
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Check if the request method is POST.
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Check if the request method is PUT.
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Check if the request method is PATCH.
     */
    public function isPatch(): bool
    {
        return $this->method === 'PATCH';
    }

    /**
     * Check if the request method is DELETE.
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    /**
     * Check if the request method is HEAD.
     */
    public function isHead(): bool
    {
        return $this->method === 'HEAD';
    }

    /**
     * Check if the request method is OPTIONS.
     */
    public function isOptions(): bool
    {
        return $this->method === 'OPTIONS';
    }

    /**
     * Check if the request method matches.
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    // ====================================================================
    // URI and Path Operations
    // ====================================================================

    /**
     * Get the full request URI.
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get the request path.
     */
    public function path(): string
    {
        return ltrim($this->pathInfo, '/');
    }

    /**
     * Get the full URL including query string.
     */
    public function fullUrl(): string
    {
        return $this->url() . ($this->getQueryString() ? '?' . $this->getQueryString() : '');
    }

    /**
     * Get the base URL without query string.
     */
    public function url(): string
    {
        return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $this->pathInfo;
    }

    /**
     * Get the root URL.
     */
    public function root(): string
    {
        return $this->getSchemeAndHttpHost() . $this->getBaseUrl();
    }

    /**
     * Check if path matches a pattern.
     */
    public function is(string ...$patterns): bool
    {
        $path = $this->path();

        foreach ($patterns as $pattern) {
            if ($pattern === $path) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#u', $path) === 1) {
                return true;
            }
        }

        return false;
    }

    // ====================================================================
    // Parameter Access
    // ====================================================================

    /**
     * Get all input data.
     */
    public function all(): array
    {
        return array_replace_recursive($this->input(), $this->files);
    }

    /**
     * Get input data (query + request).
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_merge($this->query, $this->request);

        if ($this->isJson()) {
            $input = array_merge($input, $this->json() ?? []);
        }

        if ($key === null) {
            return $input;
        }

        return $this->getNestedValue($input, $key, $default);
    }

    /**
     * Get a specific input parameter.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input($key, $default);
    }

    /**
     * Get query parameter.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->getNestedValue($this->query, $key, $default);
    }

    /**
     * Get post parameter.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->getNestedValue($this->request, $key, $default);
    }

    /**
     * Get only specified keys from input.
     */
    public function only(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $input = $this->input();
        $results = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $results[$key] = $input[$key];
            }
        }

        return $results;
    }

    /**
     * Get all input except specified keys.
     */
    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $input = $this->input();

        foreach ($keys as $key) {
            unset($input[$key]);
        }

        return $input;
    }

    /**
     * Check if input has a key.
     */
    public function has(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        $input = $this->input();

        foreach ($keys as $value) {
            if (!array_key_exists($value, $input) || $input[$value] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if input has any of the given keys.
     */
    public function hasAny(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $input = $this->input();

        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if input is filled (not empty).
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

    // ====================================================================
    // File Handling
    // ====================================================================

    /**
     * Get uploaded file.
     */
    public function file(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->files, $key, $default);
    }

    /**
     * Check if request has file.
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file instanceof UploadedFile && $file->isValid();
    }

    /**
     * Get all files.
     */
    public function allFiles(): array
    {
        return $this->files;
    }

    // ====================================================================
    // Header Operations
    // ====================================================================

    /**
     * Get request header.
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower(str_replace('_', '-', $key));
        
        return $this->headers[$key] ?? $default;
    }

    /**
     * Check if request has header.
     */
    public function hasHeader(string $key): bool
    {
        return $this->header($key) !== null;
    }

    /**
     * Get all headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get bearer token from Authorization header.
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');

        if ($authorization && preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    // ====================================================================
    // Content Negotiation
    // ====================================================================

    /**
     * Check if request expects JSON.
     */
    public function expectsJson(): bool
    {
        return $this->accepts(['application/json', 'text/json']) && 
               !$this->accepts(['text/html']);
    }

    /**
     * Check if request wants JSON response.
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();

        return isset($acceptable[0]) && 
               str_contains($acceptable[0], '/json') || 
               str_contains($acceptable[0], '+json');
    }

    /**
     * Check if request accepts content type.
     */
    public function accepts(string|array $contentTypes): bool
    {
        $accepts = $this->getAcceptableContentTypes();

        if (count($accepts) === 0) {
            return true;
        }

        $types = (array) $contentTypes;

        foreach ($accepts as $accept) {
            if ($accept === '*/*' || $accept === '*') {
                return true;
            }

            foreach ($types as $type) {
                if ($this->matchesType($accept, $type) || $accept === strtok($type, '/').'/*') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get acceptable content types.
     */
    public function getAcceptableContentTypes(): array
    {
        return array_keys(AcceptHeader::fromString($this->header('Accept', ''))->all());
    }

    /**
     * Check if request is JSON.
     */
    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), '/json') ||
               str_contains($this->header('Content-Type', ''), '+json');
    }

    /**
     * Check if request is XML.
     */
    public function isXml(): bool
    {
        return str_contains($this->header('Content-Type', ''), '/xml') ||
               str_contains($this->header('Content-Type', ''), '+xml');
    }

    // ====================================================================
    // JSON Handling
    // ====================================================================

    /**
     * Get JSON payload.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (!$this->json) {
            $this->json = json_decode($this->getContent(), true);
        }

        if ($key === null) {
            return $this->json;
        }

        return $this->getNestedValue($this->json ?? [], $key, $default);
    }

    /**
     * Get raw request content.
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }

    /**
     * Set the matched route.
     */
    public function setRoute(\Horizon\Routing\Route $route): void
    {
        $this->route = $route;
    }

    /**
     * Get the matched route.
     */
    public function getMatchedRoute(): ?\Horizon\Routing\Route
    {
        return $this->route;
    }

    // ====================================================================
    // Route Parameters
    // ====================================================================

    /**
     * Get route parameter.
     */
    public function route(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->routeParameters;
        }

        return $this->routeParameters[$key] ?? $default;
    }

    /**
     * Set route parameters.
     */
    public function setRouteParameters(array $parameters): static
    {
        $this->routeParameters = $parameters;

        return $this;
    }

    // ====================================================================
    // Server Information
    // ====================================================================

    /**
     * Get server parameter.
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get client IP address.
     */
    public function ip(): string
    {
        return $this->getClientIp();
    }

    /**
     * Get user agent.
     */
    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    /**
     * Check if request is secure (HTTPS).
     */
    public function secure(): bool
    {
        return $this->isSecure();
    }

    /**
     * Check if request is from localhost.
     */
    public function isLocal(): bool
    {
        $ip = $this->ip();
        
        return $ip === '127.0.0.1' || 
               $ip === '::1' || 
               str_starts_with($ip, '192.168.') ||
               str_starts_with($ip, '10.') ||
               str_starts_with($ip, '172.');
    }

    // ====================================================================
    // Cookie Operations
    // ====================================================================

    /**
     * Get cookie value.
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Check if request has cookie.
     */
    public function hasCookie(string $key): bool
    {
        return array_key_exists($key, $this->cookies);
    }

    /**
     * Get all cookies.
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    // ====================================================================
    // Request State
    // ====================================================================

    /**
     * Get request attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set request attribute.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Check if request has attribute.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    // ====================================================================
    // Protected Helper Methods
    // ====================================================================

    /**
     * Determine the request method.
     */
    protected function determineMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            // Check for method override
            if (isset($this->request['_method'])) {
                $method = strtoupper($this->request['_method']);
            } elseif ($this->header('X-HTTP-Method-Override')) {
                $method = strtoupper($this->header('X-HTTP-Method-Override'));
            }
        }

        return $method;
    }

    /**
     * Determine the request URI.
     */
    protected function determineUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Determine path info.
     */
    protected function determinePathInfo(): string
    {
        $requestUri = $this->uri;

        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        return $requestUri ?: '/';
    }

    /**
     * Parse headers from server variables.
     */
    protected function parseHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = substr($key, 5);
                $header = strtolower(str_replace('_', '-', $header));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $header = strtolower(str_replace('_', '-', $key));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Convert uploaded files to UploadedFile instances.
     */
    protected function convertUploadedFiles(array $files): array
    {
        $converted = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $converted[$key] = $this->convertUploadedFiles($file);
            } else {
                $converted[$key] = new UploadedFile(
                    $file['tmp_name'] ?? '',
                    $file['name'] ?? '',
                    $file['type'] ?? null,
                    $file['error'] ?? UPLOAD_ERR_OK,
                    $file['size'] ?? 0
                );
            }
        }

        return $converted;
    }

    /**
     * Get nested array value using dot notation.
     */
    protected function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Check if input value is empty string.
     */
    protected function isEmptyString(string $key): bool
    {
        $value = $this->input($key);

        return !is_bool($value) && !is_array($value) && trim((string) $value) === '';
    }

    /**
     * Get client IP address.
     */
    protected function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($ipKeys as $key) {
            if (!empty($this->server[$key])) {
                $ips = explode(',', $this->server[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if request is secure.
     */
    protected function isSecure(): bool
    {
        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($this->server['SERVER_PORT']) && (int) $this->server['SERVER_PORT'] === 443) {
            return true;
        }

        if ($this->header('X-Forwarded-Proto') === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Get scheme and HTTP host.
     */
    protected function getSchemeAndHttpHost(): string
    {
        return $this->getScheme() . '://' . $this->getHttpHost();
    }

    /**
     * Get request scheme.
     */
    protected function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Get HTTP host.
     */
    protected function getHttpHost(): string
    {
        $scheme = $this->getScheme();
        $port = $this->getPort();

        if (('http' === $scheme && 80 == $port) || ('https' === $scheme && 443 == $port)) {
            return $this->getHost();
        }

        return $this->getHost() . ':' . $port;
    }

    /**
     * Get host.
     */
    protected function getHost(): string
    {
        if ($host = $this->header('Host')) {
            if (strpos($host, ':') !== false) {
                $host = strstr($host, ':', true);
            }
            return $host;
        }

        return $this->server['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * Get port.
     */
    protected function getPort(): int
    {
        if ($host = $this->header('Host')) {
            if (strpos($host, ':') !== false) {
                return (int) substr(strstr($host, ':'), 1);
            }
        }

        return (int) ($this->server['SERVER_PORT'] ?? 80);
    }

    /**
     * Get base URL.
     */
    protected function getBaseUrl(): string
    {
        return rtrim(dirname($this->server['SCRIPT_NAME'] ?? ''), '/');
    }

    /**
     * Get query string.
     */
    protected function getQueryString(): ?string
    {
        return $this->server['QUERY_STRING'] ?? null;
    }

    /**
     * Check if content type matches.
     */
    protected function matchesType(string $actual, string $type): bool
    {
        if ($actual === $type) {
            return true;
        }

        $split = explode('/', $actual);

        return isset($split[1]) && preg_match('#' . preg_quote($split[0], '#') . '/.+\+' . preg_quote($split[1], '#') . '#', $type);
    }

    /**
     * Magic method for property access.
     */
    public function __get(string $key): mixed
    {
        return $this->input($key);
    }

    /**
     * Magic method for property check.
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }
}

/**
 * Accept Header Parser
 * 
 * Simple implementation for parsing Accept headers.
 */
class AcceptHeader
{
    protected array $items = [];

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function fromString(string $headerValue): static
    {
        $items = [];
        
        if (empty($headerValue)) {
            return new static($items);
        }

        foreach (explode(',', $headerValue) as $item) {
            $item = trim($item);
            if (empty($item)) {
                continue;
            }

            $parts = explode(';', $item);
            $value = trim($parts[0]);
            $quality = 1.0;

            // Parse quality factor
            for ($i = 1; $i < count($parts); $i++) {
                $part = trim($parts[$i]);
                if (strpos($part, 'q=') === 0) {
                    $quality = (float) substr($part, 2);
                    break;
                }
            }

            $items[$value] = $quality;
        }

        // Sort by quality factor (highest first)
        arsort($items);

        return new static($items);
    }

    public function all(): array
    {
        return $this->items;
    }
}