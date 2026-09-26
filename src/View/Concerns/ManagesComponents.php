<?php

namespace Reno\View\Concerns;

use Reno\View\Component;

/**
 * ManagesComponents
 * 
 * Trait for managing view components.
 * Provides component rendering with props and slots.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear component instantiation
 * - Explicit prop passing
 * - Slot management
 */
trait ManagesComponents
{
    /**
     * Component stack
     *
     * @var array
     */
    protected array $componentStack = [];

    /**
     * Current component data
     *
     * @var array
     */
    protected array $componentData = [];

    /**
     * Slot stack
     *
     * @var array
     */
    protected array $slotStack = [];

    /**
     * Start a component
     *
     * @param string|Component $component
     * @param array $data
     * @return void
     */
    public function startComponent(string|Component $component, array $data = []): void
    {
        if (is_string($component)) {
            $component = $this->resolveComponent($component, $data);
        }

        $this->componentStack[] = $component;
        $this->componentData[count($this->componentStack) - 1] = $data;

        // Start capturing component content
        ob_start();
    }

    /**
     * Resolve a component from string
     *
     * @param string $component
     * @param array $data
     * @return Component
     */
    protected function resolveComponent(string $component, array $data): Component
    {
        // Check if it's a class name
        if (class_exists($component)) {
            return new $component($data);
        }

        // Create anonymous component
        return new class($component, $data) extends Component {
            protected string $view;

            public function __construct(string $view, array $attributes = [])
            {
                parent::__construct($attributes);
                $this->view = $view;
            }

            public function render(): string
            {
                return view($this->view, array_merge(
                    $this->attributes,
                    ['slot' => $this->slot('default')],
                    $this->slots
                ))->render();
            }
        };
    }

    /**
     * End the current component
     *
     * @return string
     */
    public function endComponent(): string
    {
        $component = array_pop($this->componentStack);
        $index = count($this->componentStack);
        
        unset($this->componentData[$index]);

        if (!$component) {
            throw new \RuntimeException('Cannot end component without starting one.');
        }

        // Get captured content as default slot
        $content = ob_get_clean();
        
        if ($content) {
            $component->setSlot('default', $content);
        }

        return $component->render();
    }

    /**
     * Start a component slot
     *
     * @param string $name
     * @param string|null $content
     * @return void
     */
    public function startSlot(string $name, ?string $content = null): void
    {
        if ($content !== null) {
            $this->slot($name, $content);
            return;
        }

        $this->slotStack[] = $name;
        ob_start();
    }

    /**
     * End the current slot
     *
     * @return void
     */
    public function endSlot(): void
    {
        if (empty($this->slotStack)) {
            throw new \RuntimeException('Cannot end slot without starting one.');
        }

        $name = array_pop($this->slotStack);
        $content = ob_get_clean();

        $this->slot($name, $content);
    }

    /**
     * Set slot content for current component
     *
     * @param string $name
     * @param string $content
     * @return void
     */
    protected function slot(string $name, string $content): void
    {
        if (empty($this->componentStack)) {
            throw new \RuntimeException('Cannot set slot outside of component.');
        }

        $component = end($this->componentStack);
        $component->setSlot($name, $content);
    }

    /**
     * Render a component inline
     *
     * @param string|Component $component
     * @param array $data
     * @return string
     */
    public function renderComponent(string|Component $component, array $data = []): string
    {
        if (is_string($component)) {
            $component = $this->resolveComponent($component, $data);
        }

        return $component->render();
    }

    /**
     * Get current component
     *
     * @return Component|null
     */
    public function getCurrentComponent(): ?Component
    {
        return end($this->componentStack) ?: null;
    }

    /**
     * Check if currently rendering a component
     *
     * @return bool
     */
    public function isRenderingComponent(): bool
    {
        return !empty($this->componentStack);
    }
}
