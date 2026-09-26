<?php

if (!function_exists('url')) {
    /**
     * Generate a URL for the application.
     */
    function url(?string $path = null, array $parameters = [], ?bool $secure = null): string
    {
        if (is_null($path)) {
            return app('url');
        }

        return app('url')->to($path, $parameters, $secure);
    }
}

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate the URL to an application asset.
     */
    function asset(string $path, ?bool $secure = null): string
    {
        return app('url')->asset($path, $secure);
    }
}

if (!function_exists('secure_asset')) {
    /**
     * Generate the URL to a secure asset.
     */
    function secure_asset(string $path): string
    {
        return app('url')->secureAsset($path);
    }
}

if (!function_exists('secure_url')) {
    /**
     * Generate a HTTPS URL for the application.
     */
    function secure_url(string $path, array $parameters = []): string
    {
        return app('url')->secure($path, $parameters);
    }
}

if (!function_exists('back')) {
    /**
     * Create a new redirect response to the previous location.
     */
    function back(mixed $fallback = false): string
    {
        return app('url')->previous($fallback);
    }
}

if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     */
    function redirect(?string $to = null, int $status = 302, array $headers = [], ?bool $secure = null): mixed
    {
        if (is_null($to)) {
            return app('redirect');
        }

        return app('redirect')->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        if (is_null($abstract)) {
            return \Reno\Foundation\Application::getInstance();
        }

        return \Reno\Foundation\Application::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the available auth instance.
     */
    function auth(?string $guard = null): mixed
    {
        if (is_null($guard)) {
            return app('auth');
        }

        return app('auth')->guard($guard);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): mixed
    {
        $factory = app('Reno\\Contracts\\Http\\ResponseFactoryInterface');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($content, $status, $headers);
    }
}

if (!function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     */
    function request(?string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('request');
        }

        return app('request')->input($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('config');
        }

        return app('config')->get($key, $default);
    }
}
if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     */
    function data_get(mixed $target, string|array|int|null $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', is_int($key) ? (string) $key : $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (!is_iterable($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = data_get($item, $key);
                }

                return in_array('*', $key) ? \Reno\Support\Arr::collapse($result) : $result;
            }

            if (\Reno\Support\Arr::accessible($target) && \Reno\Support\Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}
if (!function_exists('collect')) {
    /**
     * Create a collection from the given value.
     */
    function collect(mixed $value = []): \Reno\Support\Collection
    {
        return new \Reno\Support\Collection($value);
    }
}

if (!function_exists('head')) {
    /**
     * Get the first element of an array.
     */
    function head(array $array): mixed
    {
        return reset($array);
    }
}

if (!function_exists('last')) {
    /**
     * Get the last element from an array.
     */
    function last(array $array): mixed
    {
        return end($array);
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     */
    function tap(mixed $value, callable $callback = null): mixed
    {
        if (is_null($callback)) {
            return $value;
        }

        $callback($value);

        return $value;
    }
}

// ============================================================================
// View Helpers
// ============================================================================

if (!function_exists('view')) {
    /**
     * Create a view instance or get the view factory
     *
     * @param string|null $view
     * @param array $data
     * @return \Reno\View\ViewFactory|\Reno\View\View
     */
    function view(?string $view = null, array $data = [])
    {
        static $factory = null;
        
        // Initialize view factory if not yet created
        if ($factory === null) {
            $viewPaths = [BASE_PATH . '/resources/views'];
            $cachePath = BASE_PATH . '/storage/cache/views';
            
            // Create cache directory if it doesn't exist
            if (!is_dir($cachePath)) {
                @mkdir($cachePath, 0755, true);
            }
            
            $finder = new \Reno\View\FileViewFinder($viewPaths);
            
            // Create Blade compiler
            $bladeCompiler = new \Reno\View\Compilers\BladeCompiler($cachePath);
            
            // Create Blade engine
            $bladeEngine = new \Reno\View\Engines\BladeEngine($bladeCompiler);
            
            // Create view factory with Blade engine
            $factory = new \Reno\View\ViewFactory($finder, $bladeEngine);
        }
        
        if ($view === null) {
            return $factory;
        }

        return $factory->make($view, $data);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML entities for safe output (XSS protection)
     *
     * @param mixed $value
     * @param bool $doubleEncode
     * @return string
     */
    function e(mixed $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return htmlspecialchars(json_encode($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('escape')) {
    /**
     * Alias for e() - escape HTML entities
     *
     * @param mixed $value
     * @param bool $doubleEncode
     * @return string
     */
    function escape(mixed $value, bool $doubleEncode = true): string
    {
        return e($value, $doubleEncode);
    }
}

if (!function_exists('raw')) {
    /**
     * Output raw (unescaped) HTML - USE WITH CAUTION!
     * 
     * Only use when you KNOW the content is safe.
     * Examples: trusted HTML from WYSIWYG editor, SVG icons
     *
     * @param mixed $value
     * @return string
     */
    function raw(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF token hidden input field
     *
     * @return string
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . e($token) . '">';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value
     *
     * @return string
     */
    function csrf_token(): string
    {
        // TODO: Integrate with session when available
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate hidden input for HTTP method spoofing
     *
     * @param string $method
     * @return string
     */
    function method_field(string $method): string
    {
        $method = strtoupper($method);
        return '<input type="hidden" name="_method" value="' . e($method) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve old input value (from session flash data)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function old(string $key, mixed $default = null): mixed
    {
        // TODO: Integrate with session flash when available
        return $default;
    }
}

if (!function_exists('json_encode_safe')) {
    /**
     * JSON encode with proper escaping for use in HTML
     *
     * @param mixed $value
     * @param int $options
     * @return string
     */
    function json_encode_safe(mixed $value, int $options = 0): string
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | $options);
        
        if ($json === false) {
            throw new \RuntimeException('Failed to encode value as JSON: ' . json_last_error_msg());
        }
        
        return $json;
    }
}

// ============================================================================
// Layout Helpers
// ============================================================================

if (!function_exists('extend')) {
    /**
     * Extend a parent layout
     *
     * @param string $layout
     * @return void
     */
    function extend(string $layout): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'extend')) {
            $engine->extend($layout);
        }
    }
}

if (!function_exists('section')) {
    /**
     * Start a section
     *
     * @param string $name
     * @param string|null $content
     * @return void
     */
    function section(string $name, ?string $content = null): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'startSection')) {
            $engine->startSection($name, $content);
        }
    }
}

if (!function_exists('endsection')) {
    /**
     * End the current section
     *
     * @return void
     */
    function endsection(): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'stopSection')) {
            $engine->stopSection();
        }
    }
}

if (!function_exists('show')) {
    /**
     * Stop section and return content
     *
     * @return string
     */
    function show(): string
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'stopSection')) {
            return $engine->stopSection();
        }
        return '';
    }
}

if (!function_exists('yield_content')) {
    /**
     * Yield the content of a section
     *
     * @param string $section
     * @param string $default
     * @return string
     */
    function yield_content(string $section, string $default = ''): string
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'yieldContent')) {
            return $engine->yieldContent($section, $default);
        }
        return $default;
    }
}

if (!function_exists('has_section')) {
    /**
     * Check if section exists
     *
     * @param string $section
     * @return bool
     */
    function has_section(string $section): bool
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'hasSection')) {
            return $engine->hasSection($section);
        }
        return false;
    }
}

if (!function_exists('push')) {
    /**
     * Start pushing content to a stack
     *
     * @param string $stack
     * @return void
     */
    function push(string $stack): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'startPush')) {
            $engine->startPush($stack);
        }
    }
}

if (!function_exists('endpush')) {
    /**
     * Stop pushing content to a stack
     *
     * @return void
     */
    function endpush(): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'stopPush')) {
            $engine->stopPush();
        }
    }
}

if (!function_exists('prepend')) {
    /**
     * Start prepending content to a stack
     *
     * @param string $stack
     * @return void
     */
    function prepend(string $stack): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'startPrepend')) {
            $engine->startPrepend($stack);
        }
    }
}

if (!function_exists('endprepend')) {
    /**
     * Stop prepending content to a stack
     *
     * @return void
     */
    function endprepend(): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'stopPrepend')) {
            $engine->stopPrepend();
        }
    }
}

if (!function_exists('stack')) {
    /**
     * Get the content of a stack
     *
     * @param string $stack
     * @return string
     */
    function stack(string $stack): string
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'yieldPushContent')) {
            return $engine->yieldPushContent($stack);
        }
        return '';
    }
}

// ============================================================================
// Component Helpers
// ============================================================================

if (!function_exists('component')) {
    /**
     * Start a component
     *
     * @param string|\Reno\View\Component $component
     * @param array $data
     * @return void
     */
    function component(string|\Reno\View\Component $component, array $data = []): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'startComponent')) {
            $engine->startComponent($component, $data);
        }
    }
}

if (!function_exists('endcomponent')) {
    /**
     * End the current component
     *
     * @return string
     */
    function endcomponent(): string
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'endComponent')) {
            return $engine->endComponent();
        }
        return '';
    }
}

if (!function_exists('slot')) {
    /**
     * Start a component slot
     *
     * @param string $name
     * @param string|null $content
     * @return void
     */
    function slot(string $name, ?string $content = null): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'startSlot')) {
            $engine->startSlot($name, $content);
        }
    }
}

if (!function_exists('endslot')) {
    /**
     * End the current slot
     *
     * @return void
     */
    function endslot(): void
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'endSlot')) {
            $engine->endSlot();
        }
    }
}

if (!function_exists('render_component')) {
    /**
     * Render a component inline
     *
     * @param string|\Reno\View\Component $component
     * @param array $data
     * @return string
     */
    function render_component(string|\Reno\View\Component $component, array $data = []): string
    {
        $engine = view()->getEngine();
        if (method_exists($engine, 'renderComponent')) {
            return $engine->renderComponent($component, $data);
        }
        return '';
    }
}

// ============================================================================
// View Cache Helpers
// ============================================================================

if (!function_exists('view_cache_clear')) {
    /**
     * Clear the view cache
     *
     * @return int Number of files deleted
     */
    function view_cache_clear(): int
    {
        $cachePath = storage_path('framework/views');
        
        if (!is_dir($cachePath)) {
            return 0;
        }

        $manager = new \Reno\View\ViewCacheManager($cachePath);
        return $manager->flush();
    }
}

if (!function_exists('view_cache_stats')) {
    /**
     * Get view cache statistics
     *
     * @return array
     */
    function view_cache_stats(): array
    {
        $cachePath = storage_path('framework/views');
        $manager = new \Reno\View\ViewCacheManager($cachePath);
        return $manager->getStats();
    }
}

// ============================================================================
// Authentication Helpers
// ============================================================================

if (!function_exists('auth')) {
    /**
     * Get the auth manager instance or a guard
     *
     * @param string|null $guard
     * @return \Reno\Auth\AuthManager|\Reno\Auth\Contracts\Guard
     */
    function auth(?string $guard = null)
    {
        $auth = app('auth');
        
        if (is_null($guard)) {
            return $auth;
        }

        return $auth->guard($guard);
    }
}

if (!function_exists('user')) {
    /**
     * Get the currently authenticated user
     *
     * @param string|null $guard
     * @return \Reno\Auth\Contracts\Authenticatable|null
     */
    function user(?string $guard = null)
    {
        return auth($guard)->user();
    }
}

if (!function_exists('guest')) {
    /**
     * Determine if the current user is a guest
     *
     * @param string|null $guard
     * @return bool
     */
    function guest(?string $guard = null): bool
    {
        return auth($guard)->guest();
    }
}

if (!function_exists('check')) {
    /**
     * Determine if the current user is authenticated
     *
     * @param string|null $guard
     * @return bool
     */
    function check(?string $guard = null): bool
    {
        return auth($guard)->check();
    }
}

// ============================================================================
// Session Helpers
// ============================================================================

if (!function_exists('session')) {
    /**
     * Get / set the session value
     *
     * @param string|array|null $key
     * @param mixed $default
     * @return mixed|\Reno\Session\SessionManager
     */
    function session($key = null, $default = null)
    {
        $session = app('session');

        if (is_null($key)) {
            return $session;
        }

        if (is_array($key)) {
            $session->put($key);
            return null;
        }

        return $session->get($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value
     *
     * @return string
     */
    function csrf_token(): string
    {
        return session()->token();
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve an old input item
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function old(?string $key = null, $default = null)
    {
        $old = session()->get('_old_input', []);

        if (is_null($key)) {
            return $old;
        }

        return $old[$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Flash data to the session
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    function flash(string $key, $value): void
    {
        session()->flash($key, $value);
    }
}

// ============================================================================
// Hashing Helpers
// ============================================================================

if (!function_exists('hash_make')) {
    /**
     * Hash a value using the default hasher
     *
     * @param string $value
     * @param array $options
     * @return string
     */
    function hash_make(string $value, array $options = []): string
    {
        return app('hash')->make($value, $options);
    }
}

if (!function_exists('hash_check')) {
    /**
     * Check a plain value against a hash
     *
     * @param string $value
     * @param string $hashedValue
     * @return bool
     */
    function hash_check(string $value, string $hashedValue): bool
    {
        return app('hash')->check($value, $hashedValue);
    }
}

if (!function_exists('hash_needs_rehash')) {
    /**
     * Check if a hash needs to be rehashed
     *
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    function hash_needs_rehash(string $hashedValue, array $options = []): bool
    {
        return app('hash')->needsRehash($hashedValue, $options);
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Hash a value using bcrypt
     *
     * @param string $value
     * @param array $options
     * @return string
     */
    function bcrypt(string $value, array $options = []): string
    {
        return app('hash')->driver('bcrypt')->make($value, $options);
    }
}

// ============================================================================
// Array Helpers
// ============================================================================

if (!function_exists('array_except')) {
    /**
     * Get all of the given array except for a specified array of keys
     *
     * @param array $array
     * @param array|string $keys
     * @return array
     */
    function array_except(array $array, $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        array_shift($keys); // Remove $array from args
        
        return array_diff_key($array, array_flip($keys));
    }
}

// ============================================================================
// Authorization Helpers
// ============================================================================

if (!function_exists('gate')) {
    /**
     * Get the gate instance
     *
     * @return \Reno\Auth\Access\Gate
     */
    function gate()
    {
        return app('gate');
    }
}

if (!function_exists('can')) {
    /**
     * Determine if the given ability should be granted for the current user
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    function can(string $ability, ...$arguments): bool
    {
        return gate()->allows($ability, ...$arguments);
    }
}

if (!function_exists('cannot')) {
    /**
     * Determine if the given ability should be denied for the current user
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    function cannot(string $ability, ...$arguments): bool
    {
        return gate()->denies($ability, ...$arguments);
    }
}

if (!function_exists('authorize')) {
    /**
     * Authorize a given action or throw an exception
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return void
     * @throws \Reno\Auth\Access\AuthorizationException
     */
    function authorize(string $ability, ...$arguments): void
    {
        if (cannot($ability, ...$arguments)) {
            throw new \Reno\Auth\Access\AuthorizationException(
                "This action is unauthorized."
            );
        }
    }
}
