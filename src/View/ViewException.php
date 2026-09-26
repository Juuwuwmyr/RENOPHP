<?php

namespace Reno\View;

use Exception;

/**
 * ViewException
 * 
 * Exception thrown when view rendering fails.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear error messages
 * - Helpful debugging information
 * - No hidden failures
 */
class ViewException extends Exception
{
    /**
     * View name that failed
     *
     * @var string|null
     */
    protected ?string $view = null;

    /**
     * Create exception for view not found
     *
     * @param string $view
     * @param array $paths
     * @return static
     */
    public static function viewNotFound(string $view, array $paths = []): static
    {
        $pathsList = empty($paths) ? 'no paths configured' : implode(', ', $paths);
        
        $exception = new static(
            "View [{$view}] not found. Searched in: {$pathsList}"
        );
        
        $exception->view = $view;
        
        return $exception;
    }

    /**
     * Create exception for rendering error
     *
     * @param string $view
     * @param \Throwable $previous
     * @return static
     */
    public static function renderingError(string $view, \Throwable $previous): static
    {
        $exception = new static(
            "Error rendering view [{$view}]: {$previous->getMessage()}",
            0,
            $previous
        );
        
        $exception->view = $view;
        
        return $exception;
    }

    /**
     * Get the view name
     *
     * @return string|null
     */
    public function getView(): ?string
    {
        return $this->view;
    }
}
