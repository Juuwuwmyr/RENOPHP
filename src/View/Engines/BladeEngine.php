<?php

namespace Horizon\View\Engines;

use Horizon\View\ViewException;
use Horizon\View\Compilers\BladeCompiler;
use Horizon\View\Concerns\ManagesLayouts;
use Horizon\View\Concerns\ManagesComponents;

/**
 * BladeEngine
 * 
 * Template engine that compiles Blade-like syntax to PHP.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Compiles templates to readable PHP
 * - Cached for performance
 * - No runtime compilation overhead
 * - Easy to debug
 */
class BladeEngine implements EngineInterface
{
    use ManagesLayouts, ManagesComponents;

    /**
     * The Blade compiler
     *
     * @var BladeCompiler
     */
    protected BladeCompiler $compiler;

    /**
     * View factory for rendering parent layouts
     *
     * @var mixed
     */
    protected $factory;

    /**
     * Create a new Blade engine instance
     *
     * @param BladeCompiler $compiler
     */
    public function __construct(BladeCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

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
        // Compile the view if needed
        if ($this->compiler->isExpired($path)) {
            $this->compiler->compile($path);
        }

        // Get the compiled path
        $compiled = $this->compiler->getCompiledPath($path);

        // Render the compiled view
        $content = $this->evaluatePath($compiled, $data);

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
     * Evaluate a compiled view file
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
            // Include the compiled view file
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
     * Get the compiler instance
     *
     * @return BladeCompiler
     */
    public function getCompiler(): BladeCompiler
    {
        return $this->compiler;
    }

    /**
     * Get the engine name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'blade';
    }
}
