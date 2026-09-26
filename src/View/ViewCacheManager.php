<?php

namespace Reno\View;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * ViewCacheManager
 * 
 * Manages view compilation cache.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear cache management
 * - Production-ready caching
 * - Easy debugging
 * - Explicit control
 */
class ViewCacheManager
{
    /**
     * Cache path for compiled views
     *
     * @var string
     */
    protected string $cachePath;

    /**
     * Create a new view cache manager
     *
     * @param string $cachePath
     */
    public function __construct(string $cachePath)
    {
        $this->cachePath = rtrim($cachePath, '/\\');
    }

    /**
     * Clear all compiled views
     *
     * @return int Number of files deleted
     */
    public function flush(): int
    {
        if (!is_dir($this->cachePath)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cachePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                unlink($file->getRealPath());
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear expired compiled views
     *
     * @return int Number of files deleted
     */
    public function clearExpired(): int
    {
        if (!is_dir($this->cachePath)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cachePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // If file is older than 24 hours, consider it expired
                if (time() - $file->getMTime() > 86400) {
                    unlink($file->getRealPath());
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        if (!is_dir($this->cachePath)) {
            return [
                'files' => 0,
                'size' => 0,
                'oldest' => null,
                'newest' => null,
            ];
        }

        $files = 0;
        $size = 0;
        $oldest = null;
        $newest = null;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cachePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files++;
                $size += $file->getSize();
                
                $mtime = $file->getMTime();
                if ($oldest === null || $mtime < $oldest) {
                    $oldest = $mtime;
                }
                if ($newest === null || $mtime > $newest) {
                    $newest = $mtime;
                }
            }
        }

        return [
            'files' => $files,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'oldest' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
            'newest' => $newest ? date('Y-m-d H:i:s', $newest) : null,
        ];
    }

    /**
     * Check if cache directory is writable
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        if (!is_dir($this->cachePath)) {
            return is_writable(dirname($this->cachePath));
        }

        return is_writable($this->cachePath);
    }

    /**
     * Ensure cache directory exists
     *
     * @return bool
     */
    public function ensureCacheDirectoryExists(): bool
    {
        if (!is_dir($this->cachePath)) {
            return mkdir($this->cachePath, 0755, true);
        }

        return true;
    }

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
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Warm up the cache by compiling all views
     *
     * @param array $viewPaths
     * @param callable $compiler
     * @return int Number of views compiled
     */
    public function warmUp(array $viewPaths, callable $compiler): int
    {
        $count = 0;

        foreach ($viewPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'])) {
                    try {
                        $compiler($file->getRealPath());
                        $count++;
                    } catch (\Throwable $e) {
                        // Skip files that fail to compile
                        continue;
                    }
                }
            }
        }

        return $count;
    }
}
