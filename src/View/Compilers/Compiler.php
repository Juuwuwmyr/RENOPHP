<?php

namespace Reno\View\Compilers;

/**
 * Compiler
 * 
 * Base compiler for template directives.
 * Compiles template syntax into native PHP code.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Templates compile to readable PHP
 * - No hidden magic or eval()
 * - Easy to debug compiled output
 */
abstract class Compiler
{
    /**
     * Cache path for compiled views
     *
     * @var string
     */
    protected string $cachePath;

    /**
     * Array of footer lines to be added to template
     *
     * @var array
     */
    protected array $footer = [];

    /**
     * Create a new compiler instance
     *
     * @param string $cachePath
     */
    public function __construct(string $cachePath)
    {
        $this->cachePath = $cachePath;
    }

    /**
     * Get the path to the compiled version of a view
     *
     * @param string $path
     * @return string
     */
    public function getCompiledPath(string $path): string
    {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    /**
     * Determine if the view at the given path is expired
     *
     * @param string $path
     * @return bool
     */
    public function isExpired(string $path): bool
    {
        $compiled = $this->getCompiledPath($path);

        // If the compiled file doesn't exist, it's expired
        if (!file_exists($compiled)) {
            return true;
        }

        // Check if the source is newer than the compiled version
        return filemtime($path) > filemtime($compiled);
    }

    /**
     * Compile the view at the given path
     *
     * @param string $path
     * @return void
     */
    public function compile(string $path): void
    {
        $contents = file_get_contents($path);

        $compiled = $this->compileString($contents);

        // Ensure cache directory exists
        if (!is_dir(dirname($this->getCompiledPath($path)))) {
            mkdir(dirname($this->getCompiledPath($path)), 0755, true);
        }

        file_put_contents($this->getCompiledPath($path), $compiled);
    }

    /**
     * Compile the given template string
     *
     * @param string $value
     * @return string
     */
    abstract protected function compileString(string $value): string;

    /**
     * Get the cache path
     *
     * @return string
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Set the cache path
     *
     * @param string $cachePath
     * @return void
     */
    public function setCachePath(string $cachePath): void
    {
        $this->cachePath = $cachePath;
    }
}
