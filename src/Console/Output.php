<?php

namespace Horizon\Console;

/**
 * Output
 * 
 * Handles command-line output with colors and formatting.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple output methods
 * - Optional color support
 * - Easy formatting
 */
class Output
{
    /**
     * Verbosity levels
     */
    const VERBOSITY_QUIET = 0;
    const VERBOSITY_NORMAL = 1;
    const VERBOSITY_VERBOSE = 2;
    const VERBOSITY_VERY_VERBOSE = 3;
    const VERBOSITY_DEBUG = 4;

    /**
     * Current verbosity level
     *
     * @var int
     */
    protected int $verbosity = self::VERBOSITY_NORMAL;

    /**
     * Color support enabled
     *
     * @var bool
     */
    protected bool $decorated = true;

    /**
     * Color codes for formatting
     *
     * @var array
     */
    protected array $colors = [
        'black' => '0;30',
        'red' => '0;31',
        'green' => '0;32',
        'yellow' => '0;33',
        'blue' => '0;34',
        'magenta' => '0;35',
        'cyan' => '0;36',
        'white' => '0;37',
        'default' => '0;39',
    ];

    /**
     * Background color codes
     *
     * @var array
     */
    protected array $backgrounds = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'white' => '47',
        'default' => '49',
    ];

    /**
     * Create a new output instance
     *
     * @param int $verbosity
     * @param bool|null $decorated
     */
    public function __construct(int $verbosity = self::VERBOSITY_NORMAL, ?bool $decorated = null)
    {
        $this->verbosity = $verbosity;
        
        // Auto-detect color support
        if ($decorated === null) {
            $this->decorated = $this->hasColorSupport();
        } else {
            $this->decorated = $decorated;
        }
    }

    /**
     * Write a message to output
     *
     * @param string $message
     * @param bool $newline
     * @return void
     */
    public function write(string $message, bool $newline = false): void
    {
        $message = $this->format($message);
        
        if ($newline) {
            $message .= PHP_EOL;
        }
        
        echo $message;
    }

    /**
     * Write a message with a newline
     *
     * @param string $message
     * @return void
     */
    public function writeln(string $message = ''): void
    {
        $this->write($message, true);
    }

    /**
     * Write an info message
     *
     * @param string $message
     * @return void
     */
    public function info(string $message): void
    {
        $this->writeln("<info>{$message}</info>");
    }

    /**
     * Write a success message
     *
     * @param string $message
     * @return void
     */
    public function success(string $message): void
    {
        $this->writeln("<success>{$message}</success>");
    }

    /**
     * Write a warning message
     *
     * @param string $message
     * @return void
     */
    public function warning(string $message): void
    {
        $this->writeln("<warning>{$message}</warning>");
    }

    /**
     * Write an error message
     *
     * @param string $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->writeln("<error>{$message}</error>");
    }

    /**
     * Write a comment message
     *
     * @param string $message
     * @return void
     */
    public function comment(string $message): void
    {
        $this->writeln("<comment>{$message}</comment>");
    }

    /**
     * Format a message with color tags
     *
     * @param string $message
     * @return string
     */
    protected function format(string $message): string
    {
        if (!$this->decorated) {
            return strip_tags($message);
        }

        // Replace color tags
        $message = preg_replace_callback('/<(\w+)>(.*?)<\/\1>/s', function ($matches) {
            return $this->applyStyle($matches[1], $matches[2]);
        }, $message);

        return $message;
    }

    /**
     * Apply a style to text
     *
     * @param string $style
     * @param string $text
     * @return string
     */
    protected function applyStyle(string $style, string $text): string
    {
        $codes = [];

        switch ($style) {
            case 'info':
                $codes[] = $this->colors['cyan'];
                break;
            case 'success':
                $codes[] = $this->colors['green'];
                break;
            case 'warning':
                $codes[] = $this->colors['yellow'];
                break;
            case 'error':
                $codes[] = $this->colors['white'];
                $codes[] = $this->backgrounds['red'];
                break;
            case 'comment':
                $codes[] = $this->colors['yellow'];
                break;
            default:
                if (isset($this->colors[$style])) {
                    $codes[] = $this->colors[$style];
                }
        }

        if (empty($codes)) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[0m", implode(';', $codes), $text);
    }

    /**
     * Check if the terminal supports colors
     *
     * @return bool
     */
    protected function hasColorSupport(): bool
    {
        // Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            return
                function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT) ||
                false !== getenv('ANSICON') ||
                'ON' === getenv('ConEmuANSI') ||
                'xterm' === getenv('TERM');
        }

        // Unix-like
        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    /**
     * Set verbosity level
     *
     * @param int $level
     * @return void
     */
    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    /**
     * Get verbosity level
     *
     * @return int
     */
    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    /**
     * Check if output is quiet
     *
     * @return bool
     */
    public function isQuiet(): bool
    {
        return $this->verbosity === self::VERBOSITY_QUIET;
    }

    /**
     * Check if output is verbose
     *
     * @return bool
     */
    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }

    /**
     * Check if output is very verbose
     *
     * @return bool
     */
    public function isVeryVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * Check if output is debug
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->verbosity === self::VERBOSITY_DEBUG;
    }

    /**
     * Enable/disable decoration
     *
     * @param bool $decorated
     * @return void
     */
    public function setDecorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }

    /**
     * Check if decoration is enabled
     *
     * @return bool
     */
    public function isDecorated(): bool
    {
        return $this->decorated;
    }
}
