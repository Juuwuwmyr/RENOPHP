<?php

namespace Horizon\Console;

/**
 * Input
 * 
 * Handles command-line input (arguments and options).
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear argument parsing
 * - Simple option handling
 * - Easy to test
 */
class Input
{
    /**
     * Raw arguments from command line
     *
     * @var array
     */
    protected array $tokens = [];

    /**
     * Parsed arguments
     *
     * @var array
     */
    protected array $arguments = [];

    /**
     * Parsed options
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Create a new input instance
     *
     * @param array|null $argv
     */
    public function __construct(?array $argv = null)
    {
        $this->tokens = $argv ?? $_SERVER['argv'] ?? [];
        
        // Remove script name (first argument)
        array_shift($this->tokens);
        
        $this->parse();
    }

    /**
     * Parse the input tokens
     *
     * @return void
     */
    protected function parse(): void
    {
        foreach ($this->tokens as $token) {
            if ($this->isOption($token)) {
                $this->parseOption($token);
            } else {
                $this->arguments[] = $token;
            }
        }
    }

    /**
     * Check if a token is an option
     *
     * @param string $token
     * @return bool
     */
    protected function isOption(string $token): bool
    {
        return str_starts_with($token, '-');
    }

    /**
     * Parse an option token
     *
     * @param string $token
     * @return void
     */
    protected function parseOption(string $token): void
    {
        // Handle --option=value format
        if (str_contains($token, '=')) {
            [$name, $value] = explode('=', ltrim($token, '-'), 2);
            $this->options[$name] = $value;
        } else {
            // Handle --option or -o format
            $name = ltrim($token, '-');
            $this->options[$name] = true;
        }
    }

    /**
     * Get the first argument (usually command name)
     *
     * @return string|null
     */
    public function getFirstArgument(): ?string
    {
        return $this->arguments[0] ?? null;
    }

    /**
     * Get an argument by index
     *
     * @param int $index
     * @param mixed $default
     * @return mixed
     */
    public function getArgument(int $index, $default = null)
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * Get all arguments
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get an option value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Check if an option exists
     *
     * @param string $name
     * @return bool
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get raw tokens
     *
     * @return array
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Check if input is interactive (TTY)
     *
     * @return bool
     */
    public function isInteractive(): bool
    {
        return function_exists('posix_isatty') && posix_isatty(STDIN);
    }
}
