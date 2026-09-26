<?php

declare(strict_types=1);

namespace Reno\Http\Middleware;

use Reno\Contracts\Http\MiddlewareInterface;
use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Http\ResponseInterface;
use Reno\Http\Exceptions\HttpException;

class PreventRequestsDuringMaintenance implements MiddlewareInterface
{
    /**
     * The URIs that should be accessible during maintenance mode.
     */
    protected array $except = [];

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        if ($this->inMaintenanceMode() && !$this->inExceptArray($request)) {
            $data = $this->getMaintenanceData();
            
            throw new HttpException(
                503,
                'Service Unavailable',
                null,
                [
                    'Retry-After' => $data['retry'] ?? 60,
                ]
            );
        }

        return $next($request);
    }

    /**
     * Determine if the application is in maintenance mode.
     */
    protected function inMaintenanceMode(): bool
    {
        return file_exists($this->getMaintenanceFilePath());
    }

    /**
     * Get the maintenance mode file path.
     */
    protected function getMaintenanceFilePath(): string
    {
        return storage_path('framework/maintenance.php');
    }

    /**
     * Get the maintenance mode data.
     */
    protected function getMaintenanceData(): array
    {
        $path = $this->getMaintenanceFilePath();
        
        if (!file_exists($path)) {
            return [];
        }

        $data = include $path;

        return is_array($data) ? $data : [];
    }

    /**
     * Determine if the request has a URI that should be accessible during maintenance mode.
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
}