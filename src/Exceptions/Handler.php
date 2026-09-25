<?php

declare(strict_types=1);

namespace Horizon\Exceptions;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Exceptions\HttpException;
use Horizon\Http\Response;
use Throwable;

class Handler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     */
    protected array $levels = [];

    /**
     * A list of the exception types that are not reported.
     */
    protected array $dontReport = [];

    /**
     * A list of the internal exception types that should not be reported.
     */
    protected array $internalDontReport = [
        HttpException::class,
    ];

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e): void
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        $this->reportException($e);
    }

    /**
     * Determine if the exception should be reported.
     */
    public function shouldReport(Throwable $e): bool
    {
        return !$this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     */
    protected function shouldntReport(Throwable $e): bool
    {
        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        foreach ($dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Report the exception to the logging system.
     */
    protected function reportException(Throwable $e): void
    {
        // In a full implementation, this would log to the configured logger
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render(RequestInterface $request, Throwable $e): ResponseInterface
    {
        if ($e instanceof HttpException) {
            return $this->renderHttpException($e);
        }

        if ($request->expectsJson()) {
            return $this->renderJsonException($request, $e);
        }

        if ($this->isDebugMode()) {
            return $this->renderDebugException($e);
        }

        return $this->renderGenericException($e);
    }

    /**
     * Render an HTTP exception.
     */
    protected function renderHttpException(HttpException $e): ResponseInterface
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage() ?: $this->getStatusMessage($status);

        return new Response($message, $status, $e->getHeaders());
    }

    /**
     * Render a JSON exception response.
     */
    protected function renderJsonException(RequestInterface $request, Throwable $e): ResponseInterface
    {
        $status = $this->getExceptionStatusCode($e);

        $response = [
            'message' => $e->getMessage(),
            'status' => $status,
        ];

        if ($this->isDebugMode()) {
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = array_slice(array_map(function ($trace) {
                return [
                    'file' => $trace['file'] ?? null,
                    'line' => $trace['line'] ?? null,
                    'function' => $trace['function'] ?? null,
                    'class' => $trace['class'] ?? null,
                ];
            }, $e->getTrace()), 0, 10);
        }

        return (new Response())->json($response, $status);
    }

    /**
     * Render a debug exception with full details.
     */
    protected function renderDebugException(Throwable $e): ResponseInterface
    {
        $status = $this->getExceptionStatusCode($e);

        $html = $this->buildDebugExceptionHtml($e);

        return new Response($html, $status, ['Content-Type' => 'text/html']);
    }

    /**
     * Render a generic exception for production.
     */
    protected function renderGenericException(Throwable $e): ResponseInterface
    {
        $status = $this->getExceptionStatusCode($e);
        $message = $this->getStatusMessage($status);

        $html = $this->buildGenericErrorHtml($status, $message);

        return new Response($html, $status, ['Content-Type' => 'text/html']);
    }

    /**
     * Get the status code for an exception.
     */
    protected function getExceptionStatusCode(Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Get a status message for a given status code.
     */
    protected function getStatusMessage(int $status): string
    {
        $messages = [
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

        return $messages[$status] ?? 'Error';
    }

    /**
     * Build debug exception HTML.
     */
    protected function buildDebugExceptionHtml(Throwable $e): string
    {
        $type = get_class($e);
        $message = htmlspecialchars($e->getMessage());
        $file = htmlspecialchars($e->getFile());
        $line = $e->getLine();
        $trace = htmlspecialchars($e->getTraceAsString());

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Exception: {$type}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #dc3545; color: white; padding: 20px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { padding: 20px; }
        .section { margin-bottom: 30px; }
        .section h2 { margin: 0 0 15px 0; font-size: 18px; color: #495057; border-bottom: 2px solid #e9ecef; padding-bottom: 8px; }
        .file-info { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #007bff; margin: 15px 0; }
        .trace { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: 'Monaco', 'Consolas', monospace; font-size: 12px; line-height: 1.5; overflow-x: auto; }
        .message { font-size: 16px; color: #495057; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$type}</h1>
        </div>
        <div class="content">
            <div class="section">
                <h2>Message</h2>
                <div class="message">{$message}</div>
            </div>
            
            <div class="section">
                <h2>Location</h2>
                <div class="file-info">
                    <strong>File:</strong> {$file}<br>
                    <strong>Line:</strong> {$line}
                </div>
            </div>
            
            <div class="section">
                <h2>Stack Trace</h2>
                <pre class="trace">{$trace}</pre>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build generic error HTML for production.
     */
    protected function buildGenericErrorHtml(int $status, string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error {$status}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { text-align: center; max-width: 500px; margin: 0 auto; }
        .status { font-size: 72px; font-weight: 300; color: #dc3545; margin: 0; }
        .message { font-size: 24px; color: #495057; margin: 20px 0; }
        .description { color: #6c757d; font-size: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="status">{$status}</div>
        <div class="message">{$message}</div>
        <div class="description">Something went wrong. Please try again later.</div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Determine if the application is in debug mode.
     */
    protected function isDebugMode(): bool
    {
        return env('APP_DEBUG', false);
    }
}