<?php

namespace Reno\View;

use Reno\View\Engines\EngineInterface;
use Reno\View\Engines\PhpEngine;

/**
 * ViewFactory
 * 
 * Main view manager that creates views, manages shared data,
 * and coordinates the view rendering process.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Central view coordination
 * - Clear data flow
 * - Simple API
 */
class ViewFactory
{
    /**
     * View finder
     *
     * @var FileViewFinder
     */
    protected FileViewFinder $finder;

    /**
     * Template engine
     *
     * @var EngineInterface
     */
    protected EngineInterface $engine;

    /**
     * Shared data for all views
     *
     * @var array
     */
    protected array $shared = [];

    /**
     * View composers
     *
     * @var array
     */
    protected array $composers = [];

    /**
     * View creators
     *
     * @var array
     */
    protected array $creators = [];

    /**
     * Number of active rendering operations
     *
     * @var int
     */
    protected int $renderCount = 0;

    /**
     * Create a new view factory
     *
     * @param FileViewFinder $finder
     * @param EngineInterface|null $engine
     */
    public function __construct(FileViewFinder $finder, ?EngineInterface $engine = null)
    {
        $this->finder = $finder;
        $this->engine = $engine ?? new PhpEngine();
        
        // Inject factory into engine for layout support
        if (method_exists($this->engine, 'setFactory')) {
            $this->engine->setFactory($this);
        }
    }

    /**
     * Create a view instance
     *
     * @param string $view
     * @param array $data
     * @return View
     * @throws ViewException
     */
    public function make(string $view, array $data = []): View
    {
        $path = $this->finder->find($view);

        // Merge shared data
        $data = array_merge($this->shared, $data);

        // Call view creators
        $this->callCreator($view, $data);

        $viewInstance = new View($this, $view, $path, $data);

        // Call view composers
        $this->callComposer($view, $viewInstance);

        return $viewInstance;
    }

    /**
     * Check if view exists
     *
     * @param string $view
     * @return bool
     */
    public function exists(string $view): bool
    {
        return $this->finder->exists($view);
    }

    /**
     * Get the first view that exists
     *
     * @param array $views
     * @param array $data
     * @return View
     * @throws ViewException
     */
    public function first(array $views, array $data = []): View
    {
        foreach ($views as $view) {
            if ($this->exists($view)) {
                return $this->make($view, $data);
            }
        }

        throw new ViewException('None of the views exist: ' . implode(', ', $views));
    }

    /**
     * Render a view
     *
     * @param string $view
     * @param array $data
     * @return string
     * @throws ViewException
     */
    public function render(string $view, array $data = []): string
    {
        return $this->make($view, $data)->render();
    }

    /**
     * Share data with all views
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function share(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }

        return $this;
    }

    /**
     * Register a view composer
     *
     * @param string|array $views
     * @param callable $callback
     * @return $this
     */
    public function composer(string|array $views, callable $callback): self
    {
        $views = (array) $views;

        foreach ($views as $view) {
            if (!isset($this->composers[$view])) {
                $this->composers[$view] = [];
            }

            $this->composers[$view][] = $callback;
        }

        return $this;
    }

    /**
     * Register a view creator
     *
     * @param string|array $views
     * @param callable $callback
     * @return $this
     */
    public function creator(string|array $views, callable $callback): self
    {
        $views = (array) $views;

        foreach ($views as $view) {
            if (!isset($this->creators[$view])) {
                $this->creators[$view] = [];
            }

            $this->creators[$view][] = $callback;
        }

        return $this;
    }

    /**
     * Call view composers
     *
     * @param string $view
     * @param View $viewInstance
     * @return void
     */
    protected function callComposer(string $view, View $viewInstance): void
    {
        $this->callCallbacks($view, $this->composers, $viewInstance);
    }

    /**
     * Call view creators
     *
     * @param string $view
     * @param array &$data
     * @return void
     */
    protected function callCreator(string $view, array &$data): void
    {
        $this->callCallbacks($view, $this->creators, $data);
    }

    /**
     * Call registered callbacks for a view
     *
     * @param string $view
     * @param array $callbacks
     * @param mixed $payload
     * @return void
     */
    protected function callCallbacks(string $view, array $callbacks, mixed $payload): void
    {
        foreach ($this->getCallbacksForView($view, $callbacks) as $callback) {
            $callback($payload);
        }
    }

    /**
     * Get callbacks for a view
     *
     * @param string $view
     * @param array $callbacks
     * @return array
     */
    protected function getCallbacksForView(string $view, array $callbacks): array
    {
        $viewCallbacks = [];

        // Exact match
        if (isset($callbacks[$view])) {
            $viewCallbacks = array_merge($viewCallbacks, $callbacks[$view]);
        }

        // Wildcard match
        foreach ($callbacks as $pattern => $patternCallbacks) {
            if (str_contains($pattern, '*') && $this->matchesPattern($pattern, $view)) {
                $viewCallbacks = array_merge($viewCallbacks, $patternCallbacks);
            }
        }

        return $viewCallbacks;
    }

    /**
     * Check if view matches pattern
     *
     * @param string $pattern
     * @param string $view
     * @return bool
     */
    protected function matchesPattern(string $pattern, string $view): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return preg_match('#^' . $pattern . '\z#', $view) === 1;
    }

    /**
     * Increment the rendering counter
     *
     * @return void
     */
    public function incrementRender(): void
    {
        $this->renderCount++;
    }

    /**
     * Decrement the rendering counter
     *
     * @return void
     */
    public function decrementRender(): void
    {
        $this->renderCount--;
    }

    /**
     * Check if currently rendering
     *
     * @return bool
     */
    public function isRendering(): bool
    {
        return $this->renderCount > 0;
    }

    /**
     * Get the view finder
     *
     * @return FileViewFinder
     */
    public function getFinder(): FileViewFinder
    {
        return $this->finder;
    }

    /**
     * Get the template engine
     *
     * @return EngineInterface
     */
    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    /**
     * Set the template engine
     *
     * @param EngineInterface $engine
     * @return $this
     */
    public function setEngine(EngineInterface $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * Get all shared data
     *
     * @return array
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Add a location to look for views
     *
     * @param string $path
     * @return $this
     */
    public function addLocation(string $path): self
    {
        $this->finder->addPath($path);

        return $this;
    }

    /**
     * Add a namespace hint
     *
     * @param string $namespace
     * @param string|array $hints
     * @return $this
     */
    public function addNamespace(string $namespace, string|array $hints): self
    {
        $this->finder->addNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Flush the view cache
     *
     * @return void
     */
    public function flushFinderCache(): void
    {
        $this->finder->flush();
    }
}
