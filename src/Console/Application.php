<?php

namespace Reno\Console;

use Exception;

/**
 * Console Application
 * 
 * Main CLI application manager.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear command registration
 * - Simple argument parsing
 * - Explicit command execution
 * - Easy to debug
 * 
 * Usage:
 *   $app = new Application('Horizon', '1.0.0');
 *   $app->add(new MyCommand());
 *   $app->run();
 */
class Application
{
    /**
     * Application name
     *
     * @var string
     */
    protected string $name;

    /**
     * Application version
     *
     * @var string
     */
    protected string $version;

    /**
     * Registered commands
     *
     * @var array
     */
    protected array $commands = [];

    /**
     * Default command name
     *
     * @var string
     */
    protected string $defaultCommand = 'list';

    /**
     * Create a new console application
     *
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name = 'Console', string $version = '1.0.0')
    {
        $this->name = $name;
        $this->version = $version;
        
        // Register default commands
        $this->registerDefaultCommands();
    }

    /**
     * Register default commands
     *
     * @return void
     */
    protected function registerDefaultCommands(): void
    {
        $this->add(new Commands\ListCommand());
        $this->add(new Commands\HelpCommand());
    }

    /**
     * Add a command to the application
     *
     * @param Command $command
     * @return Command
     */
    public function add(Command $command): Command
    {
        $command->setApplication($this);
        
        $this->commands[$command->getName()] = $command;
        
        // Register aliases
        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }
        
        return $command;
    }

    /**
     * Run the application
     *
     * @param Input|null $input
     * @param Output|null $output
     * @return int Exit code
     */
    public function run(?Input $input = null, ?Output $output = null): int
    {
        $input = $input ?? new Input();
        $output = $output ?? new Output();
        
        try {
            // Get command name
            $name = $input->getFirstArgument() ?? $this->defaultCommand;
            
            // Find and run command
            $command = $this->find($name);
            
            return $command->run($input, $output);
            
        } catch (Exception $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Find a command by name
     *
     * @param string $name
     * @return Command
     * @throws Exception
     */
    public function find(string $name): Command
    {
        if (!isset($this->commands[$name])) {
            throw new Exception("Command '{$name}' not found.");
        }
        
        return $this->commands[$name];
    }

    /**
     * Check if a command exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Get all registered commands
     *
     * @return array
     */
    public function all(): array
    {
        // Return unique commands (excluding aliases)
        $commands = [];
        foreach ($this->commands as $command) {
            $commands[$command->getName()] = $command;
        }
        return $commands;
    }

    /**
     * Get application name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get application version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get long version string
     *
     * @return string
     */
    public function getLongVersion(): string
    {
        return sprintf('%s <info>%s</info>', $this->name, $this->version);
    }

    /**
     * Set the default command
     *
     * @param string $name
     * @return void
     */
    public function setDefaultCommand(string $name): void
    {
        $this->defaultCommand = $name;
    }
}
