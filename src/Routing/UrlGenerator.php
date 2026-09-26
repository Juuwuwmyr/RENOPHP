<?php

declare(strict_types=1);

namespace Reno\Routing;

use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteCollectionInterface;
use Reno\Contracts\Routing\RouteInterface;
use Reno\Contracts\Routing\UrlGeneratorInterface;
use Reno\Routing\Exceptions\RouteNotFoundException;
use Reno\Support\Arr;
use InvalidArgumentException;

class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * The route collection.
     */
    protected RouteCollectionInterface $routes;

    /**
     * The request instance.
     */
    protected RequestInterface $request;

    /**
     * Characters that should not be URL encoded.
     */
    protected array $dontEncode = [
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
        '%3F' => '?',
        '%26' => '&',
        '%23' => '#',
        '%25' => '%',
    ];

    /**
     * Create a new URL generator instance.
     */
    public function __construct(RouteCollectionInterface $routes, RequestInterface $request)
    {
        $this->routes = $routes;
        $this->request = $request;
    }

    /**
     * Get the current request instance.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Set the current request instance.
     */
    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Generate a URL for the given route.
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        if (!$route = $this->routes->getByName($name)) {
            throw new RouteNotFoundException("Route [{$name}] not found.");
        }

        return $this->toRoute($route, $parameters, $absolute);
    }

    /**
     * Generate a URL for a given route instance.
     */
    public function toRoute(RouteInterface $route, array $parameters = [], bool $absolute = true): string
    {
        $domain = $this->getRouteDomain($route, $parameters);
        
        // Compile the route to get the compiled pattern
        $route->compileRoute();
        $compiled = $route->getCompiled();
        
        $uri = $this->addQueryString(
            $this->buildUriFromRoute($route, $parameters),
            $parameters
        );

        if (preg_match('/\{.*?\}/', $uri)) {
            throw new InvalidArgumentException('Route parameters not provided: ' . $uri);
        }

        $root = $this->formatRoot($this->formatScheme($absolute), $domain);

        return $this->format($root, '/' . ltrim($uri, '/'));
    }

    /**
     * Build the URI from the given route.
     */
    protected function buildUriFromRoute(RouteInterface $route, array $parameters): string
    {
        $uri = $route->uri();
        
        // Replace route parameters with actual values
        foreach ($parameters as $key => $value) {
            if (is_null($value)) {
                continue;
            }

            // Handle optional parameters
            $uri = preg_replace(
                '/\{' . preg_quote($key) . '\?\}/',
                $this->formatParameter($value),
                $uri
            );

            // Handle required parameters
            $uri = preg_replace(
                '/\{' . preg_quote($key) . '\}/',
                $this->formatParameter($value),
                $uri
            );
        }

        // Remove unused optional parameters
        $uri = preg_replace('/\{[^}]*\?\}/', '', $uri);

        return $uri;
    }

    /**
     * Format a parameter for inclusion in a URL.
     */
    protected function formatParameter(mixed $parameter): string
    {
        if ($parameter instanceof \BackedEnum) {
            return $parameter->value;
        }

        return rawurlencode((string) $parameter);
    }

    /**
     * Get the domain for a route.
     */
    protected function getRouteDomain(RouteInterface $route, array &$parameters): ?string
    {
        return $route->domain();
    }

    /**
     * Add a query string to the URI.
     */
    protected function addQueryString(string $uri, array &$parameters): string
    {
        // Remove parameters that were used in the URI
        $this->removeUsedParameters($uri, $parameters);

        if (empty($parameters)) {
            return $uri;
        }

        $query = Arr::query($parameters);

        return $uri . (str_contains($uri, '?') ? '&' : '?') . $query;
    }

    /**
     * Remove used parameters from the parameter array.
     */
    protected function removeUsedParameters(string $uri, array &$parameters): void
    {
        foreach ($parameters as $key => $value) {
            if (str_contains($uri, '{' . $key . '}') || str_contains($uri, '{' . $key . '?}')) {
                unset($parameters[$key]);
            }
        }
    }

    /**
     * Generate an absolute URL to the given path.
     */
    public function to(string $path, mixed $extra = [], ?bool $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $tail = implode('/', array_map('rawurlencode', (array) $extra));

        $root = $this->formatRoot($this->formatScheme($secure));

        [$path, $query] = $this->extractQueryString($path);

        return $this->format(
            $root, '/' . trim($path . '/' . $tail, '/')
        ) . $query;
    }

    /**
     * Generate a secure, absolute URL to the given path.
     */
    public function secure(string $path, array $parameters = []): string
    {
        return $this->to($path, $parameters, true);
    }

    /**
     * Generate the URL to an application asset.
     */
    public function asset(string $path, ?bool $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $root = $this->formatRoot($this->formatScheme($secure));

        return $this->removeIndex($root) . '/' . trim($path, '/');
    }

    /**
     * Remove the index.php file from a path.
     */
    protected function removeIndex(string $root): string
    {
        $i = 'index.php';

        return str_contains($root, $i) ? str_replace('/' . $i, '', $root) : $root;
    }

    /**
     * Generate the URL to a secure asset.
     */
    public function secureAsset(string $path): string
    {
        return $this->asset($path, true);
    }

    /**
     * Get the scheme for a raw URL.
     */
    protected function formatScheme(?bool $secure = null): string
    {
        if (!is_null($secure)) {
            return $secure ? 'https://' : 'http://';
        }

        if (is_null($this->request)) {
            return 'http://';
        }

        return $this->request->getScheme() . '://';
    }

    /**
     * Get the formatted root of the request.
     */
    public function formatRoot(string $scheme, ?string $root = null): string
    {
        if (is_null($root)) {
            if (is_null($this->request)) {
                $root = 'localhost';
            } else {
                $root = $this->request->getHost();

                if ($port = $this->request->getPort()) {
                    $root .= ':' . $port;
                }
            }
        }

        $start = str_starts_with($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~' . $start . '~', $scheme, $root, 1);
    }

    /**
     * Format the given URL segments into a single URL.
     */
    public function format(string $root, string $path, ?string $route = null): string
    {
        $path = '/' . trim($path, '/');

        if ($this->formatHostUsing) {
            $root = call_user_func($this->formatHostUsing, $root);
        }

        if ($this->formatPathUsing) {
            $path = call_user_func($this->formatPathUsing, $path);
        }

        return trim($root . $path, '/');
    }

    /**
     * Determine if the given path is a valid URL.
     */
    public function isValidUrl(string $path): bool
    {
        if (!preg_match('~^(#|//|https?://|(mailto|tel|sms):)~', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }

    /**
     * Extract the query string from the given path.
     */
    protected function extractQueryString(string $path): array
    {
        if (($queryPosition = strpos($path, '?')) !== false) {
            return [
                substr($path, 0, $queryPosition),
                substr($path, $queryPosition),
            ];
        }

        return [$path, ''];
    }

    /**
     * Get the base URL for the request.
     */
    public function previous(mixed $fallback = false): string
    {
        $referrer = $this->request->headers->get('referer');

        $url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();

        if ($url) {
            return $url;
        } elseif ($fallback) {
            return $this->to($fallback);
        } else {
            return $this->to('/');
        }
    }

    /**
     * Get the previous URL from the session.
     */
    protected function getPreviousUrlFromSession(): ?string
    {
        return $this->session?->previousUrl();
    }

    /**
     * Set the session resolver callback.
     */
    public function setSessionResolver(callable $sessionResolver): static
    {
        $this->sessionResolver = $sessionResolver;

        return $this;
    }

    /**
     * Set the encryption key resolver.
     */
    public function setKeyResolver(callable $keyResolver): static
    {
        $this->keyResolver = $keyResolver;

        return $this;
    }

    /**
     * Clone a new instance with the given request.
     */
    public function withRequest(RequestInterface $request): static
    {
        return new static($this->routes, $request);
    }

    /**
     * Set the root controller namespace.
     */
    public function setRootControllerNamespace(string $rootNamespace): static
    {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }

    /**
     * Callback to use to format hosts.
     */
    protected $formatHostUsing;

    /**
     * Callback to use to format paths.
     */
    protected $formatPathUsing;

    /**
     * The session resolver callable.
     */
    protected $sessionResolver;

    /**
     * The encryption key resolver callable.
     */
    protected $keyResolver;

    /**
     * The root controller namespace.
     */
    protected string $rootNamespace = '';

    /**
     * Set a custom callback to format hosts.
     */
    public function formatHostUsing(callable $callback): static
    {
        $this->formatHostUsing = $callback;

        return $this;
    }

    /**
     * Set a custom callback to format paths.
     */
    public function formatPathUsing(callable $callback): static
    {
        $this->formatPathUsing = $callback;

        return $this;
    }
}