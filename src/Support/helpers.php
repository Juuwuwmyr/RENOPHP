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
            return \Horizon\Foundation\Application::getInstance();
        }

        return \Horizon\Foundation\Application::getInstance()->make($abstract, $parameters);
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
        $factory = app('Horizon\\Contracts\\Http\\ResponseFactoryInterface');

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

                return in_array('*', $key) ? \Horizon\Support\Arr::collapse($result) : $result;
            }

            if (\Horizon\Support\Arr::accessible($target) && \Horizon\Support\Arr::exists($target, $segment)) {
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
    function collect(mixed $value = []): \Horizon\Support\Collection
    {
        return new \Horizon\Support\Collection($value);
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