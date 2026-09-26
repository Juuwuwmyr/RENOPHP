<?php

declare(strict_types=1);

namespace Reno\Http;

use Reno\Http\ResponseHeaders;
use Reno\Http\Cookie;
use JsonSerializable;

/**
 * HTTP Response
 * 
 * Represents an HTTP response with comprehensive content handling,
 * headers, cookies, and status codes.
 * 
 * Philosophy: "Explicit, flexible, and secure by default"
 */
class Response
{
    /**
     * HTTP status codes.
     */
    public const HTTP_CONTINUE = 100;
    public const HTTP_SWITCHING_PROTOCOLS = 101;
    public const HTTP_PROCESSING = 102;
    public const HTTP_EARLY_HINTS = 103;
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_RESET_CONTENT = 205;
    public const HTTP_PARTIAL_CONTENT = 206;
    public const HTTP_MULTI_STATUS = 207;
    public const HTTP_ALREADY_REPORTED = 208;
    public const HTTP_IM_USED = 226;
    public const HTTP_MULTIPLE_CHOICES = 300;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_USE_PROXY = 305;
    public const HTTP_RESERVED = 306;
    public const HTTP_TEMPORARY_REDIRECT = 307;
    public const HTTP_PERMANENTLY_REDIRECT = 308;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_PAYMENT_REQUIRED = 402;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_NOT_ACCEPTABLE = 406;
    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    public const HTTP_REQUEST_TIMEOUT = 408;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_LENGTH_REQUIRED = 411;
    public const HTTP_PRECONDITION_FAILED = 412;
    public const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_REQUEST_URI_TOO_LONG = 414;
    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const HTTP_EXPECTATION_FAILED = 417;
    public const HTTP_I_AM_A_TEAPOT = 418;
    public const HTTP_MISDIRECTED_REQUEST = 421;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_LOCKED = 423;
    public const HTTP_FAILED_DEPENDENCY = 424;
    public const HTTP_TOO_EARLY = 425;
    public const HTTP_UPGRADE_REQUIRED = 426;
    public const HTTP_PRECONDITION_REQUIRED = 428;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    public const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    public const HTTP_GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;
    public const HTTP_VARIANT_ALSO_NEGOTIATES = 506;
    public const HTTP_INSUFFICIENT_STORAGE = 507;
    public const HTTP_LOOP_DETECTED = 508;
    public const HTTP_NOT_EXTENDED = 510;
    public const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;

    /**
     * Response content.
     */
    protected string $content;

    /**
     * HTTP status code.
     */
    protected int $statusCode;

    /**
     * Response headers.
     */
    protected ResponseHeaders $headers;

    /**
     * Response cookies.
     */
    protected array $cookies = [];

    /**
     * HTTP protocol version.
     */
    protected string $version = '1.1';

    /**
     * Status text phrases.
     */
    protected static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * Create a new Response instance.
     */
    public function __construct(
        string $content = '',
        int $status = 200,
        array $headers = []
    ) {
        $this->content = $content;
        $this->statusCode = $status;
        $this->headers = new ResponseHeaders($headers);
        
        $this->setDefaultHeaders();
    }

    // ====================================================================
    // Static Factory Methods
    // ====================================================================

    /**
     * Create a JSON response.
     */
    public static function json(
        mixed $data = null,
        int $status = 200,
        array $headers = [],
        int $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ): static {
        $response = new static('', $status, $headers);
        
        $response->header('Content-Type', 'application/json');
        
        if ($data !== null) {
            $json = json_encode($data, $options);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('JSON encoding failed: ' . json_last_error_msg());
            }
            
            $response->setContent($json);
        }

        return $response;
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(
        string $url,
        int $status = 302,
        array $headers = []
    ): static {
        $response = new static('', $status, $headers);
        $response->header('Location', $url);

        return $response;
    }

    /**
     * Create a view response.
     */
    public static function view(
        string $view,
        array $data = [],
        int $status = 200,
        array $headers = []
    ): static {
        // In a real implementation, this would render the view
        $content = "<!-- View: {$view} with data: " . json_encode($data) . " -->";
        
        $response = new static($content, $status, $headers);
        $response->header('Content-Type', 'text/html; charset=utf-8');

        return $response;
    }

    /**
     * Create a download response.
     */
    public static function download(
        string $file,
        ?string $name = null,
        array $headers = [],
        ?string $disposition = 'attachment'
    ): static {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("File not found: {$file}");
        }

        $name = $name ?: basename($file);
        $content = file_get_contents($file);
        
        $response = new static($content, 200, $headers);
        
        $response->header('Content-Type', mime_content_type($file) ?: 'application/octet-stream');
        $response->header('Content-Disposition', "{$disposition}; filename=\"{$name}\"");
        $response->header('Content-Length', (string) strlen($content));

        return $response;
    }

    /**
     * Create a stream response.
     */
    public static function stream(
        callable $callback,
        int $status = 200,
        array $headers = []
    ): static {
        $response = new static('', $status, $headers);
        
        ob_start();
        $callback();
        $content = ob_get_clean();
        
        $response->setContent($content);

        return $response;
    }

    /**
     * Create an empty response.
     */
    public static function noContent(int $status = 204, array $headers = []): static
    {
        return new static('', $status, $headers);
    }

    // ====================================================================
    // Content Operations
    // ====================================================================

    /**
     * Set response content.
     */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get response content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Append content to response.
     */
    public function append(string $content): static
    {
        $this->content .= $content;
        return $this;
    }

    /**
     * Prepend content to response.
     */
    public function prepend(string $content): static
    {
        $this->content = $content . $this->content;
        return $this;
    }

    // ====================================================================
    // Status Operations
    // ====================================================================

    /**
     * Set status code.
     */
    public function setStatusCode(int $code, ?string $text = null): static
    {
        $this->statusCode = $code;
        
        if ($text === null && isset(static::$statusTexts[$code])) {
            $text = static::$statusTexts[$code];
        }

        return $this;
    }

    /**
     * Get status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get status text.
     */
    public function getStatusText(): string
    {
        return static::$statusTexts[$this->statusCode] ?? 'Unknown Status';
    }

    /**
     * Check if response is successful (2xx).
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a redirect (3xx).
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if response is a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Check if response is OK (200).
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Check if response is Not Found (404).
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Check if response is Forbidden (403).
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    // ====================================================================
    // Header Operations
    // ====================================================================

    /**
     * Set a header.
     */
    public function header(string $key, mixed $value): static
    {
        $this->headers->set($key, $value);
        return $this;
    }

    /**
     * Add a header (allows duplicates).
     */
    public function addHeader(string $key, mixed $value): static
    {
        $this->headers->add($key, $value);
        return $this;
    }

    /**
     * Get a header value.
     */
    public function getHeader(string $key): mixed
    {
        return $this->headers->get($key);
    }

    /**
     * Check if header exists.
     */
    public function hasHeader(string $key): bool
    {
        return $this->headers->has($key);
    }

    /**
     * Remove a header.
     */
    public function removeHeader(string $key): static
    {
        $this->headers->remove($key);
        return $this;
    }

    /**
     * Get all headers.
     */
    public function headers(): ResponseHeaders
    {
        return $this->headers;
    }

    // ====================================================================
    // Cookie Operations
    // ====================================================================

    /**
     * Set a cookie.
     */
    public function cookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $this->cookies[$name] = new Cookie(
            $name,
            $value,
            $expire,
            $path,
            $domain,
            $secure,
            $httpOnly,
            $sameSite
        );

        return $this;
    }

    /**
     * Set a cookie that expires when browser closes.
     */
    public function sessionCookie(
        string $name,
        string $value,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        return $this->cookie($name, $value, 0, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Set a cookie that expires in the future.
     */
    public function persistentCookie(
        string $name,
        string $value,
        int $minutes = 60,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $expire = time() + ($minutes * 60);
        return $this->cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Expire a cookie (delete it).
     */
    public function forgetCookie(
        string $name,
        string $path = '/',
        string $domain = ''
    ): static {
        return $this->cookie($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Get all cookies.
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    // ====================================================================
    // Content Type Helpers
    // ====================================================================

    /**
     * Set content type to JSON.
     */
    public function asJson(): static
    {
        return $this->header('Content-Type', 'application/json');
    }

    /**
     * Set content type to HTML.
     */
    public function asHtml(): static
    {
        return $this->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Set content type to plain text.
     */
    public function asText(): static
    {
        return $this->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Set content type to XML.
     */
    public function asXml(): static
    {
        return $this->header('Content-Type', 'application/xml');
    }

    // ====================================================================
    // Security Headers
    // ====================================================================

    /**
     * Add security headers.
     */
    public function withSecurityHeaders(): static
    {
        return $this
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-XSS-Protection', '1; mode=block')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Content-Security-Policy', "default-src 'self'");
    }

    /**
     * Set CORS headers.
     */
    public function withCors(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $allowCredentials = false,
        int $maxAge = 86400
    ): static {
        $this->header('Access-Control-Allow-Origin', implode(', ', $allowedOrigins));
        $this->header('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $this->header('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        $this->header('Access-Control-Max-Age', (string) $maxAge);

        if ($allowCredentials) {
            $this->header('Access-Control-Allow-Credentials', 'true');
        }

        return $this;
    }

    // ====================================================================
    // Response Output
    // ====================================================================

    /**
     * Send the response to the browser.
     */
    public function send(): static
    {
        $this->sendHeaders();
        $this->sendContent();

        return $this;
    }

    /**
     * Send headers to the browser.
     */
    public function sendHeaders(): static
    {
        // Don't send headers if already sent
        if (headers_sent()) {
            return $this;
        }

        // Send status line
        header("HTTP/{$this->version} {$this->statusCode} {$this->getStatusText()}", true, $this->statusCode);

        // Send headers
        foreach ($this->headers->all() as $name => $values) {
            $replace = true;
            foreach ((array) $values as $value) {
                header("{$name}: {$value}", $replace);
                $replace = false;
            }
        }

        // Send cookies
        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }

        return $this;
    }

    /**
     * Send content to the browser.
     */
    public function sendContent(): static
    {
        echo $this->content;
        return $this;
    }

    // ====================================================================
    // Protected Methods
    // ====================================================================

    /**
     * Set default headers.
     */
    protected function setDefaultHeaders(): void
    {
        $this->headers->set('Date', gmdate('D, d M Y H:i:s') . ' GMT');
        $this->headers->set('Server', 'Horizon/1.0');
    }

    // ====================================================================
    // Magic Methods
    // ====================================================================

    /**
     * Convert response to string.
     */
    public function __toString(): string
    {
        return $this->content;
    }
}