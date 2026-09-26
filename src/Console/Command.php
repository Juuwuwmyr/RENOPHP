<?php

namespace Reno\Console;

/**
 * Command
 * 
 * Base class for all console commands.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear command structure
 * - Simple input/output
 * - Easy to extend
 * - Testable
 */
abstract class Command
{
    /**
     * The console application
     *
     * @var Application|null
     */
    protected ?Application $application = null;

    /**
     * Command name
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Command description
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Command aliases
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * Command arguments
     *
     * @var array
     */
    protected array $arguments = [];

    /**
     * Command options
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Input instance
     *
     * @var Input|null
     */
    protected ?Input $input = null;

    /**
     * Output instance
     *
     * @var Output|null
     */
    protected ?Output $output = null;

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        // Override in subclass
    }

    /**
     * Execute the command
     *
     * @param Input $input
     * @param Output $output
     * @return int Exit code
     */
    abstract protected function execute(Input $input, Output $output): int;

    /**
     * Run the command
     *
     * @param Input $input
     * @param Output $output
     * @return int Exit code
     */
    public function run(Input $input, Output $output): int
    {
        $this->input = $input;
        $this->output = $output;
        
        // Configure command
        $this->configure();
        
        // Execute command
        return $this->execute($input, $output);
    }

    /**
     * Set the command name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the command name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the command description
     *
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the command description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set command aliases
     *
     * @param array $aliases
     * @return $this
     */
    public function setAliases(array $aliases): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * Get command aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Add a command argument
     *
     * @param string $name
     * @param bool $required
     * @param string $description
     * @param mixed $default
     * @return $this
     */
    public function addArgument(string $name, bool $required = false, string $description = '', $default = null): self
    {
        $this->arguments[$name] = [
            'required' => $required,
            'description' => $description,
            'default' => $default,
        ];
        
        return $this;
    }

    /**
     * Get command arguments
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Add a command option
     *
     * @param string $name
     * @param string|null $shortcut
     * @param bool $required
     * @param string $description
     * @param mixed $default
     * @return $this
     */
    public function addOption(string $name, ?string $shortcut = null, bool $required = false, string $description = '', $default = null): self
    {
        $this->options[$name] = [
            'shortcut' => $shortcut,
            'required' => $required,
            'description' => $description,
            'default' => $default,
        ];
        
        return $this;
    }

    /**
     * Get command options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set the application
     *
     * @param Application $application
     * @return void
     */
    public function setApplication(Application $application): void
    {
        $this->application = $application;
    }

    /**
     * Get the application
     *
     * @return Application|null
     */
    public function getApplication(): ?Application
    {
        return $this->application;
    }

    /**
     * Get an argument value
     *
     * @param string $name
     * @return mixed
     */
    protected function argument(string $name)
    {
        return $this->input?->getArgument($name);
    }

    /**
     * Get an option value
     *
     * @param string $name
     * @return mixed
     */
    protected function option(string $name)
    {
        return $this->input?->getOption($name);
    }

    /**
     * Write a message to output
     *
     * @param string $message
     * @return void
     */
    protected function line(string $message = ''): void
    {
        $this->output?->writeln($message);
    }

    /**
     * Write an info message
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        $this->output?->info($message);
    }

    /**
     * Write a success message
     *
     * @param string $message
     * @return void
     */
    protected function success(string $message): void
    {
        $this->output?->success($message);
    }

    /**
     * Write a warning message
     *
     * @param string $message
     * @return void
     */
    protected function warn(string $message): void
    {
        $this->output?->warning($message);
    }

    /**
     * Write an error message
     *
     * @param string $message
     * @return void
     */
    protected function error(string $message): void
    {
        $this->output?->error($message);
    }
}
