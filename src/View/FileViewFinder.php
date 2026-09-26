<?php

namespace Reno\View;

/**
 * FileViewFinder
 * 
 * Locates view files in registered paths.
 * Supports multiple file extensions and path hints.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear file resolution
 * - Explicit path management
 * - Helpful error messages
 */
class FileViewFinder
{
    /**
     * View file paths
     *
     * @var array
     */
    protected array $paths = [];

    /**
     * View file extensions
     *
     * @var array
     */
    protected array $extensions = ['blade.php', 'php', 'html'];

    /**
     * View name hints (namespaces)
     *
     * @var array
     */
    protected array $hints = [];

    /**
     * Cache of located views
     *
     * @var array
     */
    protected array $views = [];

    /**
     * Create a new file view finder
     *
     * @param array $paths
     * @param array $extensions
     */
    public function __construct(array $paths = [], array $extensions = [])
    {
        $this->paths = $paths;
        
        if (!empty($extensions)) {
            $this->extensions = $extensions;
        }
    }

    /**
     * Find a view file
     *
     * @param string $name
     * @return string
     * @throws ViewException
     */
    public function find(string $name): string
    {
        // Check cache
        if (isset($this->views[$name])) {
            return $this->views[$name];
        }

        // Check for namespace hint
        if ($this->hasHint($name)) {
            return $this->views[$name] = $this->findNamespacedView($name);
        }

        // Find in regular paths
        return $this->views[$name] = $this->findInPaths($name, $this->paths);
    }

    /**
     * Check if view exists
     *
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        try {
            $this->find($name);
            return true;
        } catch (ViewException $e) {
            return false;
        }
    }

    /**
     * Find view in paths
     *
     * @param string $name
     * @param array $paths
     * @return string
     * @throws ViewException
     */
    protected function findInPaths(string $name, array $paths): string
    {
        foreach ($paths as $path) {
            foreach ($this->getPossibleViewFiles($name) as $file) {
                $viewPath = $path . DIRECTORY_SEPARATOR . $file;
                
                if (file_exists($viewPath)) {
                    return $viewPath;
                }
            }
        }

        throw ViewException::viewNotFound($name, $paths);
    }

    /**
     * Get possible view file names
     *
     * @param string $name
     * @return array
     */
    protected function getPossibleViewFiles(string $name): array
    {
        // Convert dot notation to directory separators
        $name = str_replace('.', DIRECTORY_SEPARATOR, $name);

        $files = [];
        
        foreach ($this->extensions as $extension) {
            $files[] = $name . '.' . $extension;
        }

        return $files;
    }

    /**
     * Check if view name has namespace hint
     *
     * @param string $name
     * @return bool
     */
    protected function hasHint(string $name): bool
    {
        return str_contains($name, '::');
    }

    /**
     * Find namespaced view
     *
     * @param string $name
     * @return string
     * @throws ViewException
     */
    protected function findNamespacedView(string $name): string
    {
        [$namespace, $view] = $this->parseNamespaceSegments($name);

        if (!isset($this->hints[$namespace])) {
            throw new ViewException("No hint path defined for namespace [{$namespace}].");
        }

        return $this->findInPaths($view, (array) $this->hints[$namespace]);
    }

    /**
     * Parse namespace segments from view name
     *
     * @param string $name
     * @return array
     */
    protected function parseNamespaceSegments(string $name): array
    {
        $segments = explode('::', $name);

        if (count($segments) !== 2) {
            throw new \InvalidArgumentException("Invalid namespaced view [{$name}].");
        }

        return $segments;
    }

    /**
     * Add a path to search for views
     *
     * @param string $path
     * @return $this
     */
    public function addPath(string $path): self
    {
        if (!in_array($path, $this->paths)) {
            $this->paths[] = $path;
        }

        return $this;
    }

    /**
     * Prepend a path to search for views
     *
     * @param string $path
     * @return $this
     */
    public function prependPath(string $path): self
    {
        array_unshift($this->paths, $path);
        $this->paths = array_unique($this->paths);

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
        $hints = (array) $hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($this->hints[$namespace], $hints);
        }

        $this->hints[$namespace] = $hints;

        return $this;
    }

    /**
     * Replace namespace hints
     *
     * @param string $namespace
     * @param string|array $hints
     * @return $this
     */
    public function replaceNamespace(string $namespace, string|array $hints): self
    {
        $this->hints[$namespace] = (array) $hints;

        return $this;
    }

    /**
     * Add a file extension
     *
     * @param string $extension
     * @return $this
     */
    public function addExtension(string $extension): self
    {
        if (!in_array($extension, $this->extensions)) {
            $this->extensions[] = $extension;
        }

        return $this;
    }

    /**
     * Get all registered paths
     *
     * @return array
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Get all registered hints
     *
     * @return array
     */
    public function getHints(): array
    {
        return $this->hints;
    }

    /**
     * Get all extensions
     *
     * @return array
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Clear the view cache
     *
     * @return void
     */
    public function flush(): void
    {
        $this->views = [];
    }
}
