<?php

namespace Reno\View\Concerns;

/**
 * ManagesLayouts
 * 
 * Trait for managing template layouts, sections, and content injection.
 * Provides layout inheritance similar to Blade templates.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear parent/child relationships
 * - Explicit section management
 * - Stack-based content injection
 */
trait ManagesLayouts
{
    /**
     * The layout being extended
     *
     * @var string|null
     */
    protected ?string $layout = null;

    /**
     * All sections
     *
     * @var array
     */
    protected array $sections = [];

    /**
     * The section stack
     *
     * @var array
     */
    protected array $sectionStack = [];

    /**
     * Content stacks
     *
     * @var array
     */
    protected array $stacks = [];

    /**
     * Extend a parent layout
     *
     * @param string $layout
     * @return void
     */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Start a section
     *
     * @param string $section
     * @param string|null $content
     * @return void
     */
    public function startSection(string $section, ?string $content = null): void
    {
        if ($content === null) {
            ob_start();
            $this->sectionStack[] = $section;
        } else {
            $this->sections[$section] = $content;
        }
    }

    /**
     * Stop (end) the current section
     *
     * @param bool $overwrite
     * @return string
     */
    public function stopSection(bool $overwrite = false): string
    {
        if (empty($this->sectionStack)) {
            throw new \RuntimeException('Cannot end a section without first starting one.');
        }

        $last = array_pop($this->sectionStack);
        $content = ob_get_clean();

        if ($overwrite) {
            $this->sections[$last] = $content;
        } else {
            $this->extendSection($last, $content);
        }

        return $content;
    }

    /**
     * Stop section and append to parent
     *
     * @return string
     */
    public function appendSection(): string
    {
        if (empty($this->sectionStack)) {
            throw new \RuntimeException('Cannot end a section without first starting one.');
        }

        $last = array_pop($this->sectionStack);
        $content = ob_get_clean();

        if (isset($this->sections[$last])) {
            $this->sections[$last] .= $content;
        } else {
            $this->sections[$last] = $content;
        }

        return $content;
    }

    /**
     * Extend a section with new content
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendSection(string $section, string $content): void
    {
        if (isset($this->sections[$section])) {
            $content = str_replace('@parent', $this->sections[$section], $content);
        }

        $this->sections[$section] = $content;
    }

    /**
     * Get the content of a section
     *
     * @param string $section
     * @param string $default
     * @return string
     */
    public function yieldContent(string $section, string $default = ''): string
    {
        return $this->sections[$section] ?? $default;
    }

    /**
     * Check if section exists
     *
     * @param string $section
     * @return bool
     */
    public function hasSection(string $section): bool
    {
        return isset($this->sections[$section]);
    }

    /**
     * Get all sections
     *
     * @return array
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Flush all sections
     *
     * @return void
     */
    public function flushSections(): void
    {
        $this->sections = [];
        $this->sectionStack = [];
    }

    /**
     * Start pushing content to a stack
     *
     * @param string $stack
     * @return void
     */
    public function startPush(string $stack): void
    {
        ob_start();
        $this->sectionStack[] = 'push:' . $stack;
    }

    /**
     * Stop pushing content to a stack
     *
     * @return string
     */
    public function stopPush(): string
    {
        if (empty($this->sectionStack)) {
            throw new \RuntimeException('Cannot end a push without first starting one.');
        }

        $last = array_pop($this->sectionStack);

        if (!str_starts_with($last, 'push:')) {
            throw new \RuntimeException('Cannot end a push when currently in a section.');
        }

        $stack = substr($last, 5);
        $content = ob_get_clean();

        if (!isset($this->stacks[$stack])) {
            $this->stacks[$stack] = [];
        }

        $this->stacks[$stack][] = $content;

        return $content;
    }

    /**
     * Prepend content to a stack
     *
     * @param string $stack
     * @return void
     */
    public function startPrepend(string $stack): void
    {
        ob_start();
        $this->sectionStack[] = 'prepend:' . $stack;
    }

    /**
     * Stop prepending content to a stack
     *
     * @return string
     */
    public function stopPrepend(): string
    {
        if (empty($this->sectionStack)) {
            throw new \RuntimeException('Cannot end a prepend without first starting one.');
        }

        $last = array_pop($this->sectionStack);

        if (!str_starts_with($last, 'prepend:')) {
            throw new \RuntimeException('Cannot end a prepend when not in a prepend block.');
        }

        $stack = substr($last, 8);
        $content = ob_get_clean();

        if (!isset($this->stacks[$stack])) {
            $this->stacks[$stack] = [];
        }

        array_unshift($this->stacks[$stack], $content);

        return $content;
    }

    /**
     * Get the content of a stack
     *
     * @param string $stack
     * @return string
     */
    public function yieldPushContent(string $stack): string
    {
        if (!isset($this->stacks[$stack])) {
            return '';
        }

        return implode('', $this->stacks[$stack]);
    }

    /**
     * Flush a specific stack
     *
     * @param string $stack
     * @return void
     */
    public function flushStack(string $stack): void
    {
        unset($this->stacks[$stack]);
    }

    /**
     * Flush all stacks
     *
     * @return void
     */
    public function flushStacks(): void
    {
        $this->stacks = [];
    }

    /**
     * Get the parent layout (if extending)
     *
     * @return string|null
     */
    public function getLayout(): ?string
    {
        return $this->layout;
    }
}
