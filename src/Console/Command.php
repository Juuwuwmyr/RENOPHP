<?php

declare(strict_types=1);

namespace Horizon\Console;

/**
 * Base Console Command
 * 
 * Base class for all console commands in the Horizon Framework.
 * Provides common functionality for argument/option handling, output, and execution.
 */
abstract class Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = '';

    /**
     * The console command description.
     */
    protected string $description = '';

    /**
     * Command arguments passed from CLI.
     */
    protected array $arguments = [];

    /**
     * Command options passed from CLI.
     */
    protected array $options = [];

    /**
     * Output interface for writing to console.
     */
    protected $output;

    /**
     * Create a new command instance.
     */
    public function __construct(array $arguments = [], array $options = [])
    {
        $this->arguments = $arguments;
        $this->options = $options;
        $this->setupOutput();
    }

    /**
     * Execute the console command.
     */
    abstract public function handle(): int;

    /**
     * Get the command signature.
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Get the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get an argument by name.
     */
    protected function argument(string $name): ?string
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * Get all arguments.
     */
    protected function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get an option by name.
     */
    protected function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Get all options.
     */
    protected function options(): array
    {
        return $this->options;
    }

    /**
     * Write a string as information output.
     */
    protected function info(string $message): void
    {
        echo "\033[32m✓ {$message}\033[0m\n";
    }

    /**
     * Write a string as error output.
     */
    protected function error(string $message): void
    {
        echo "\033[31m✗ {$message}\033[0m\n";
    }

    /**
     * Write a string as warning output.
     */
    protected function warn(string $message): void
    {
        echo "\033[33m⚠ {$message}\033[0m\n";
    }

    /**
     * Write a string as comment output.
     */
    protected function comment(string $message): void
    {
        echo "\033[37m{$message}\033[0m\n";
    }

    /**
     * Write a string as question output.
     */
    protected function question(string $message): void
    {
        echo "\033[36m? {$message}\033[0m\n";
    }

    /**
     * Write a line of output.
     */
    protected function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    /**
     * Ask the user a question.
     */
    protected function ask(string $question, ?string $default = null): string
    {
        echo "\033[36m? {$question}\033[0m";
        if ($default !== null) {
            echo " \033[37m({$default})\033[0m";
        }
        echo ": ";

        $input = trim(fgets(STDIN));
        
        return $input !== '' ? $input : ($default ?? '');
    }

    /**
     * Ask the user a yes/no question.
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        echo "\033[36m? {$question}\033[0m \033[37m({$defaultText})\033[0m: ";

        $input = strtolower(trim(fgets(STDIN)));
        
        if ($input === '') {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    /**
     * Ask the user to select from a list of choices.
     */
    protected function choice(string $question, array $choices, ?string $default = null): string
    {
        echo "\033[36m? {$question}\033[0m\n";
        
        foreach ($choices as $index => $choice) {
            $prefix = $choice === $default ? '*' : ' ';
            echo "  {$prefix} [{$index}] {$choice}\n";
        }
        
        echo "Choose an option: ";
        $input = trim(fgets(STDIN));
        
        if ($input === '' && $default !== null) {
            return $default;
        }
        
        if (is_numeric($input) && isset($choices[$input])) {
            return $choices[$input];
        }
        
        if (in_array($input, $choices)) {
            return $input;
        }
        
        $this->error('Invalid choice. Please try again.');
        return $this->choice($question, $choices, $default);
    }

    /**
     * Call another console command.
     */
    protected function call(string $command, array $arguments = []): int
    {
        // In a real implementation, this would invoke the command through the console kernel
        $this->comment("Would call command: {$command} with arguments: " . json_encode($arguments));
        return 0;
    }

    /**
     * Call another console command silently.
     */
    protected function callSilent(string $command, array $arguments = []): int
    {
        // In a real implementation, this would invoke the command silently
        return $this->call($command, $arguments);
    }

    /**
     * Set up output interface.
     */
    protected function setupOutput(): void
    {
        // In a real implementation, this would set up a proper output interface
        $this->output = new class {
            public function write(string $message): void {
                echo $message;
            }
            
            public function writeln(string $message): void {
                echo $message . "\n";
            }
        };
    }

    /**
     * Display a table of data.
     */
    protected function table(array $headers, array $rows): void
    {
        // Simple table implementation for demonstration
        if (empty($headers) || empty($rows)) {
            return;
        }

        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }

        // Draw table
        $this->drawTableRow($headers, $widths);
        $this->drawTableSeparator($widths);
        
        foreach ($rows as $row) {
            $this->drawTableRow($row, $widths);
        }
    }

    /**
     * Draw a table row.
     */
    protected function drawTableRow(array $row, array $widths): void
    {
        echo '| ';
        foreach ($row as $i => $cell) {
            echo str_pad($cell, $widths[$i]) . ' | ';
        }
        echo "\n";
    }

    /**
     * Draw table separator.
     */
    protected function drawTableSeparator(array $widths): void
    {
        echo '|-';
        foreach ($widths as $width) {
            echo str_repeat('-', $width) . '-|-';
        }
        echo "\n";
    }

    /**
     * Create a progress bar.
     */
    protected function progressBar(int $max): object
    {
        return new class($max) {
            private int $max;
            private int $current = 0;
            
            public function __construct(int $max) {
                $this->max = $max;
            }
            
            public function advance(int $step = 1): void {
                $this->current += $step;
                $this->display();
            }
            
            public function finish(): void {
                $this->current = $this->max;
                $this->display();
                echo "\n";
            }
            
            private function display(): void {
                $percent = $this->max > 0 ? ($this->current / $this->max) * 100 : 0;
                $bar = str_repeat('=', (int)($percent / 5)) . str_repeat(' ', 20 - (int)($percent / 5));
                echo sprintf("\r[%s] %d%% (%d/%d)", $bar, $percent, $this->current, $this->max);
            }
        };
    }

    /**
     * Get Laravel-style output instance.
     */
    public function __get(string $name)
    {
        if ($name === 'output') {
            return $this->output;
        }
        
        throw new \InvalidArgumentException("Property {$name} does not exist on " . static::class);
    }
}