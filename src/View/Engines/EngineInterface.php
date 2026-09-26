<?php

namespace Reno\View\Engines;

/**
 * EngineInterface
 * 
 * Contract for template engines.
 * Engines are responsible for rendering view files into HTML.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple interface
 * - Clear responsibilities
 * - Easy to implement
 */
interface EngineInterface
{
    /**
     * Render a view file
     *
     * @param string $path
     * @param array $data
     * @return string
     */
    public function render(string $path, array $data = []): string;

    /**
     * Get the engine name
     *
     * @return string
     */
    public function getName(): string;
}
