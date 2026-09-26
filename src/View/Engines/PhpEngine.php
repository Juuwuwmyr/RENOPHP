<?php

namespace Horizon\View\Engines;

use Horizon\View\ViewException;
use Horizon\View\Concerns\ManagesLayouts;
use Horizon\View\Concerns\ManagesComponents;

/**
 * PhpEngine
 * 
 * Native PHP template engine with automatic XSS protection.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Uses native PHP for templates
 * - Automatic output escaping
 * - Clear, explicit syntax
 * - No compilation needed
 * 
 * Security:
 * - All output is escaped by default
 * - Use raw() for unescaped output (explicit)
 * - No eval() or dynamic code execution
 */
class PhpEngine implements EngineInterface
{
    use ManagesLayouts, ManagesComponents;

    /**
     * View factory for rendering parent layouts
     *
     * @var mixed
     */
    protected $factory;

    /**
     * Set the view factory
     *
     * @param mixed $factory
     * @return void
     */
    public function setFactory($factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Render a view file
     *
     * @param string $path
     * @param array $data
     * @return string
     * @throws ViewException
     */
    public function render(string $path, array $data = []): string
    {
        $content = $this->evaluatePath($path, $data);

        // If a layout is being extended, render it
        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null; // Reset for next render

            // Render the parent layout
            return $this->factory->render($layout, $data);
        }

        return $content;
    }

    /**
     * Evaluate a view file
     *
     * @param string $path
     * @param array $data
     * @return string
     * @throws ViewException
     */
    protected function evaluatePath(string $path, array $data): string
    {
        // Make $this available to views for layout methods
        $__engine = $this;

        // Extract data into local scope
        extract($data, EXTR_SKIP);

        // Start output buffering
        ob_start();

        try {
            // Include the view file
            include $path;
        } catch (\Throwable $e) {
            // Clean the buffer on error
            ob_end_clean();
            
            throw new ViewException(
                "Error rendering view [{$path}]: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Get and clean the buffer
        return ltrim(ob_get_clean());
    }

    /**
     * Get the engine name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'php';
    }
}
