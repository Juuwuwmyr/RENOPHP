<?php

declare(strict_types=1);

namespace Reno\Http;

use RuntimeException;
use InvalidArgumentException;

/**
 * Uploaded File
 * 
 * Represents an uploaded file with validation, security checks,
 * and convenient manipulation methods.
 */
class UploadedFile
{
    /**
     * Original file name.
     */
    protected string $originalName;

    /**
     * MIME type.
     */
    protected ?string $mimeType;

    /**
     * File size in bytes.
     */
    protected int $size;

    /**
     * Upload error code.
     */
    protected int $error;

    /**
     * Temporary file path.
     */
    protected string $path;

    /**
     * Whether file has been moved.
     */
    protected bool $moved = false;

    /**
     * Upload error messages.
     */
    protected static array $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    /**
     * Create a new UploadedFile instance.
     */
    public function __construct(
        string $path,
        string $originalName,
        ?string $mimeType = null,
        int $error = UPLOAD_ERR_OK,
        int $size = 0
    ) {
        $this->path = $path;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->error = $error;
        $this->size = $size;

        if ($error === UPLOAD_ERR_OK && !is_file($path)) {
            throw new InvalidArgumentException("The file '{$path}' does not exist.");
        }
    }

    /**
     * Get the original file name.
     */
    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Get the file extension from original name.
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    /**
     * Get the MIME type.
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Get the file size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the upload error code.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Get the upload error message.
     */
    public function getErrorMessage(): ?string
    {
        return static::$errorMessages[$this->error] ?? null;
    }

    /**
     * Check if the upload was successful.
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->path);
    }

    /**
     * Check if there was an upload error.
     */
    public function hasError(): bool
    {
        return $this->error !== UPLOAD_ERR_OK;
    }

    /**
     * Get the temporary file path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the real path of the uploaded file.
     */
    public function getRealPath(): string|false
    {
        return realpath($this->path);
    }

    /**
     * Move the uploaded file to a new location.
     */
    public function move(string $directory, ?string $name = null): static
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot move invalid uploaded file.');
        }

        if ($this->moved) {
            throw new RuntimeException('Cannot move uploaded file: file has already been moved.');
        }

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Unable to create directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException("Directory is not writable: {$directory}");
        }

        $name = $name ?: $this->getHashName();
        $destination = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($this->path, $destination)) {
            throw new RuntimeException("Could not move uploaded file to: {$destination}");
        }

        $this->path = $destination;
        $this->moved = true;

        return $this;
    }

    /**
     * Store the uploaded file.
     */
    public function store(string $path = '', array $options = []): string|false
    {
        $options = array_merge([
            'disk' => 'local',
            'name' => null,
            'visibility' => 'private',
        ], $options);

        $name = $options['name'] ?: $this->getHashName();
        $fullPath = $path ? $path . '/' . $name : $name;

        // In a real implementation, this would use the storage system
        $directory = storage_path('app/' . dirname($fullPath));
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($this->move($directory, basename($fullPath))) {
            return $fullPath;
        }

        return false;
    }

    /**
     * Store the uploaded file publicly.
     */
    public function storePublicly(string $path = '', array $options = []): string|false
    {
        $options['visibility'] = 'public';
        return $this->store($path, $options);
    }

    /**
     * Store the uploaded file as a specific name.
     */
    public function storeAs(string $path, string $name, array $options = []): string|false
    {
        $options['name'] = $name;
        return $this->store($path, $options);
    }

    /**
     * Store the uploaded file publicly as a specific name.
     */
    public function storePubliclyAs(string $path, string $name, array $options = []): string|false
    {
        $options['name'] = $name;
        return $this->storePublicly($path, $options);
    }

    /**
     * Get a hash name for the file.
     */
    public function getHashName(?string $path = null): string
    {
        if ($path) {
            $path = ltrim($path . '/', '/');
        }

        $hash = hash_file('md5', $this->path);
        $extension = $this->getClientOriginalExtension();

        return $path . $hash . ($extension ? '.' . $extension : '');
    }

    /**
     * Get file content.
     */
    public function getContent(): string
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot read invalid uploaded file.');
        }

        $content = file_get_contents($this->path);
        
        if ($content === false) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        return $content;
    }

    /**
     * Check if file is an image.
     */
    public function isImage(): bool
    {
        $imageTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
            'image/svg+xml'
        ];

        return in_array($this->getMimeType(), $imageTypes);
    }

    /**
     * Get image dimensions if file is an image.
     */
    public function getImageDimensions(): array|false
    {
        if (!$this->isImage() || !$this->isValid()) {
            return false;
        }

        $dimensions = getimagesize($this->path);
        
        if ($dimensions === false) {
            return false;
        }

        return [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'mime' => $dimensions['mime']
        ];
    }

    /**
     * Validate file type.
     */
    public function validateMimeType(array $allowedTypes): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Get MIME type from file content (more reliable)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $this->path);
        finfo_close($finfo);

        return in_array($detectedMime, $allowedTypes);
    }

    /**
     * Validate file extension.
     */
    public function validateExtension(array $allowedExtensions): bool
    {
        $extension = strtolower($this->getClientOriginalExtension());
        $allowedExtensions = array_map('strtolower', $allowedExtensions);
        
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Validate file size.
     */
    public function validateSize(int $maxSize): bool
    {
        return $this->size <= $maxSize;
    }

    /**
     * Security scan for malicious content.
     */
    public function isSafe(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Check for PHP tags in files
        $content = $this->getContent();
        
        if (preg_match('/<\?php|<\?=|<script.*language\s*=.*php.*>/i', $content)) {
            return false;
        }

        // Check for dangerous extensions
        $dangerousExtensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phps',
            'pl', 'py', 'jsp', 'asp', 'sh', 'cgi'
        ];

        $extension = strtolower($this->getClientOriginalExtension());
        if (in_array($extension, $dangerousExtensions)) {
            return false;
        }

        return true;
    }

    /**
     * Generate a secure filename.
     */
    public function generateSafeName(?string $prefix = null): string
    {
        $extension = $this->getClientOriginalExtension();
        $hash = hash('sha256', $this->originalName . time() . random_bytes(16));
        $name = substr($hash, 0, 32);

        if ($prefix) {
            $name = $prefix . '_' . $name;
        }

        return $extension ? $name . '.' . $extension : $name;
    }

    /**
     * Check if file has been moved.
     */
    public function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * Get file info as array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'error' => $this->error,
            'error_message' => $this->getErrorMessage(),
            'path' => $this->path,
            'is_valid' => $this->isValid(),
            'is_image' => $this->isImage(),
            'is_safe' => $this->isSafe(),
        ];
    }

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return $this->originalName;
    }
}

/**
 * Helper function for getting storage path.
 */
if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return getcwd() . '/storage' . ($path ? '/' . ltrim($path, '/') : '');
    }
}