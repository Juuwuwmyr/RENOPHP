<?php

declare(strict_types=1);

namespace Horizon\Http;

use Horizon\Contracts\Http\ResponseInterface;

class Response implements ResponseInterface
{
    /**
     * The response status code.
     */
    protected int $statusCode = 200;

    /**
     * The response headers.
     */
    protected array $headers = [];

    /**
     * The response cookies.
     */
    protected array $cookies = [];

    /**
     * The response content.
     */
    protected string $content = '';

    /**
     * HTTP status code texts.
     */
    protected static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * Create a new response instance.
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $status;
        $this->headers = $headers;
    }

    /**
     * Create a new response instance.
     */
    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    /**
     * Set the response status code.
     */
    public function status(int $code): static
    {
        $this->statusCode = $code;

        return $this;
    }

    /**
     * Get the response status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set a header on the response.
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Get a header from the response.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers from the response.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set multiple headers on the response.
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }

        return $this;
    }

    /**
     * Set a cookie on the response.
     */
    public function cookie(string $name, string $value, array $options = []): static
    {
        $this->cookies[$name] = array_merge([
            'value' => $value,
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $options);

        return $this;
    }

    /**
     * Set the response content.
     */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the response content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Return a JSON response.
     */
    public function json(array|object $data, int $status = 200): static
    {
        $this->statusCode = $status;
        $this->content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->header('Content-Type', 'application/json');

        return $this;
    }

    /**
     * Return a redirect response.
     */
    public function redirect(string $url, int $status = 302): static
    {
        $this->statusCode = $status;
        $this->header('Location', $url);
        $this->content = sprintf(
            '<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />
        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        );

        return $this;
    }

    /**
     * Create a file download response.
     */
    public function download(string $pathToFile, ?string $name = null, array $headers = []): static
    {
        $name = $name ?: basename($pathToFile);

        $this->header('Content-Type', 'application/octet-stream');
        $this->header('Content-Disposition', 'attachment; filename="' . $name . '"');
        $this->header('Content-Length', (string) filesize($pathToFile));

        foreach ($headers as $key => $value) {
            $this->header($key, $value);
        }

        $this->content = file_get_contents($pathToFile);

        return $this;
    }

    /**
     * Create a streamed response.
     */
    public function stream(callable $callback, int $status = 200, array $headers = []): static
    {
        $this->statusCode = $status;

        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }

        // For a streamed response, we'd normally set up the callback
        // For now, we'll execute it and capture the output
        ob_start();
        $callback();
        $this->content = ob_get_clean();

        return $this;
    }

    /**
     * Send the response to the browser.
     */
    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            static::closeOutputBuffers(0, true);
        }
    }

    /**
     * Send the response headers.
     */
    protected function sendHeaders(): static
    {
        // Only send headers if they haven't been sent yet
        if (headers_sent()) {
            return $this;
        }

        // Send status line
        header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $this->getStatusText()));

        // Send headers
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, false);
        }

        // Send cookies
        foreach ($this->cookies as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                $cookie['expires'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }

        return $this;
    }

    /**
     * Send the response content.
     */
    protected function sendContent(): static
    {
        echo $this->content;

        return $this;
    }

    /**
     * Get the status text for the status code.
     */
    protected function getStatusText(): string
    {
        return static::$statusTexts[$this->statusCode] ?? 'Unknown Status';
    }

    /**
     * Determine if the response is a redirect.
     */
    public function isRedirect(): bool
    {
        return in_array($this->statusCode, [201, 301, 302, 303, 307, 308]);
    }

    /**
     * Determine if the response is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Determine if the response is a client error.
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Determine if the response is a server error.
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Determine if the response indicates a client or server error.
     */
    public function isError(): bool
    {
        return $this->isClientError() || $this->isServerError();
    }

    /**
     * Determine if the response is OK.
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Determine if the response is forbidden.
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Determine if the response is not found.
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Determine if the response is empty.
     */
    public function isEmpty(): bool
    {
        return in_array($this->statusCode, [204, 304]);
    }

    /**
     * Close output buffers.
     */
    protected static function closeOutputBuffers(int $targetLevel, bool $flush): void
    {
        $status = ob_get_status(true);
        $level = count($status);
        $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);

        while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }

    /**
     * Convert the response to a string.
     */
    public function __toString(): string
    {
        return $this->content;
    }
}