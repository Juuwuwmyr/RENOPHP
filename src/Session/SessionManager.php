<?php

namespace Horizon\Session;

use SessionHandlerInterface;

/**
 * SessionManager
 * 
 * Manages session lifecycle with security best practices.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Secure by default
 * - Clear session lifecycle
 * - Easy to understand
 * 
 * Security Features:
 * - HTTP-only cookies
 * - Secure flag for HTTPS
 * - SameSite attribute
 * - Session regeneration
 * - Session fixation protection
 * - Configurable session lifetime
 */
class SessionManager
{
    /**
     * Session configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Session ID
     *
     * @var string|null
     */
    protected ?string $id = null;

    /**
     * Session name
     *
     * @var string
     */
    protected string $name = 'horizon_session';

    /**
     * Session data
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * Session started flag
     *
     * @var bool
     */
    protected bool $started = false;

    /**
     * Custom session handler
     *
     * @var SessionHandlerInterface|null
     */
    protected ?SessionHandlerInterface $handler = null;

    /**
     * Create a new session manager
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default secure configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'lifetime' => 120, // 2 hours in minutes
            'path' => '/',
            'domain' => null,
            'secure' => true, // HTTPS only
            'http_only' => true, // No JavaScript access
            'same_site' => 'lax', // CSRF protection
        ];
    }

    /**
     * Start the session
     *
     * @return bool
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // Configure session settings
        $this->configureSession();

        // Set custom handler if provided
        if ($this->handler) {
            session_set_save_handler($this->handler, true);
        }

        // Start the session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Load session data
        $this->attributes = $_SESSION ?? [];
        $this->id = session_id();
        $this->started = true;

        return $this->started;
    }

    /**
     * Configure session settings
     *
     * @return void
     */
    protected function configureSession(): void
    {
        // Set session name
        session_name($this->name);

        // Set session cookie parameters for security
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'] * 60,
            'path' => $this->config['path'],
            'domain' => $this->config['domain'] ?? '',
            'secure' => $this->config['secure'],
            'httponly' => $this->config['http_only'],
            'samesite' => $this->config['same_site'],
        ]);

        // Additional security settings
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_trans_sid', '0');
        
        // Disable URL-based session IDs (security)
        ini_set('session.use_only_cookies', '1');
    }

    /**
     * Get a session value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Put a value in the session
     *
     * @param string|array $key
     * @param mixed $value
     * @return void
     */
    public function put($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->attributes[$k] = $v;
            }
        } else {
            $this->attributes[$key] = $value;
        }

        $this->sync();
    }

    /**
     * Check if a key exists in the session
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Remove a value from the session
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void
    {
        unset($this->attributes[$key]);
        $this->sync();
    }

    /**
     * Remove all items from the session
     *
     * @return void
     */
    public function flush(): void
    {
        $this->attributes = [];
        $this->sync();
    }

    /**
     * Get all session data
     *
     * @return array
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Flash data for the next request
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, $value): void
    {
        $this->put($key, $value);
        $this->put('_flash.new.' . $key, true);
    }

    /**
     * Reflash all flash data
     *
     * @return void
     */
    public function reflash(): void
    {
        $this->mergeNewFlashes($this->get('_flash.old', []));
        $this->put('_flash.old', []);
    }

    /**
     * Merge new flash keys
     *
     * @param array $keys
     * @return void
     */
    protected function mergeNewFlashes(array $keys): void
    {
        foreach ($keys as $key => $value) {
            $this->put('_flash.new.' . $key, true);
        }
    }

    /**
     * Age flash data
     *
     * @return void
     */
    public function ageFlashData(): void
    {
        $this->forget('_flash.old');

        $new = $this->get('_flash.new', []);
        $this->put('_flash.old', $new);
        $this->put('_flash.new', []);

        // Remove old flash data
        foreach (array_keys($new) as $key) {
            $actualKey = str_replace('_flash.new.', '', $key);
            $this->forget($actualKey);
        }
    }

    /**
     * Regenerate the session ID
     *
     * @param bool $destroy
     * @return bool
     */
    public function regenerate(bool $destroy = false): bool
    {
        return session_regenerate_id($destroy);
    }

    /**
     * Migrate the session (regenerate with destroy)
     *
     * @param bool $destroy
     * @return bool
     */
    public function migrate(bool $destroy = true): bool
    {
        return $this->regenerate($destroy);
    }

    /**
     * Invalidate the session
     *
     * @return bool
     */
    public function invalidate(): bool
    {
        $this->flush();
        return $this->migrate(true);
    }

    /**
     * Get the session ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id ?? '';
    }

    /**
     * Set the session ID
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
        
        if ($this->started) {
            session_id($id);
        }
    }

    /**
     * Get the session name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the session name
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Determine if the session has been started
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Save the session data
     *
     * @return void
     */
    public function save(): void
    {
        $this->ageFlashData();
        $this->sync();
        
        if ($this->started) {
            session_write_close();
            $this->started = false;
        }
    }

    /**
     * Sync session data with $_SESSION
     *
     * @return void
     */
    protected function sync(): void
    {
        $_SESSION = $this->attributes;
    }

    /**
     * Set a custom session handler
     *
     * @param SessionHandlerInterface $handler
     * @return void
     */
    public function setHandler(SessionHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Get the CSRF token
     *
     * @return string
     */
    public function token(): string
    {
        $token = $this->get('_token');

        if (empty($token)) {
            $token = $this->regenerateToken();
        }

        return $token;
    }

    /**
     * Regenerate the CSRF token
     *
     * @return string
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->put('_token', $token);
        
        return $token;
    }

    /**
     * Get the previous URL
     *
     * @return string|null
     */
    public function previousUrl(): ?string
    {
        return $this->get('_previous.url');
    }

    /**
     * Set the previous URL
     *
     * @param string $url
     * @return void
     */
    public function setPreviousUrl(string $url): void
    {
        $this->put('_previous.url', $url);
    }

    /**
     * Magic method to get session values
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * Magic method to set session values
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $this->put($key, $value);
    }

    /**
     * Magic method to check if a key is set
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Magic method to unset a key
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key): void
    {
        $this->forget($key);
    }
}
