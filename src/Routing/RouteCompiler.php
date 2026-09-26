<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Routing\Route;
use InvalidArgumentException;

/**
 * RouteCompiler
 * 
 * Compiles route patterns into optimized regular expressions for fast matching.
 * Handles parameters, constraints, and optional segments.
 */
class RouteCompiler
{
    /**
     * Parameter pattern regex.
     */
    protected const PARAMETER_PATTERN = '/\{([^}]+)\}/';

    /**
     * Valid parameter name pattern.
     */
    protected const PARAMETER_NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * Compile route into regex and extract parameter information.
     */
    public function compile(Route $route, array $patterns = []): array
    {
        $uri = $route->getUri();
        $wheres = array_merge($patterns, $route->getWheres());

        // If route has no parameters, return simple match
        if (!$route->hasParameters()) {
            return [
                'regex' => '#^' . preg_quote($uri, '#') . '$#',
                'parameters' => [],
                'optional' => [],
                'uri' => $uri,
            ];
        }

        return $this->compilePattern($uri, $wheres);
    }

    /**
     * Compile URI pattern with parameters.
     */
    protected function compilePattern(string $uri, array $patterns): array
    {
        $parameters = [];
        $optional = [];
        $regex = $uri;

        // Find all parameters
        if (preg_match_all(self::PARAMETER_PATTERN, $uri, $matches, PREG_OFFSET_CAPTURE)) {
            $paramMatches = array_reverse($matches[0]); // Reverse to handle replacements
            
            foreach ($paramMatches as [$fullMatch, $offset]) {
                $paramName = trim($matches[1][array_search($fullMatch, array_column($matches[0], 0))][0]);
                
                // Check for optional parameter
                $isOptional = str_ends_with($paramName, '?');
                if ($isOptional) {
                    $paramName = rtrim($paramName, '?');
                    $optional[] = $paramName;
                }

                // Validate parameter name
                $this->validateParameterName($paramName);

                // Get pattern for parameter
                $pattern = $patterns[$paramName] ?? '[^/]+';

                // Build replacement
                if ($isOptional) {
                    $replacement = '(?:/(' . $pattern . '))?';
                } else {
                    $replacement = '(' . $pattern . ')';
                    $parameters[] = $paramName;
                }

                // Replace in regex
                $regex = substr_replace($regex, $replacement, $offset, strlen($fullMatch));
            }

            // Add optional parameters that weren't already added
            foreach ($optional as $optParam) {
                if (!in_array($optParam, $parameters)) {
                    $parameters[] = $optParam;
                }
            }
        }

        // Ensure regex is properly anchored
        $regex = '#^' . $regex . '$#';

        return [
            'regex' => $regex,
            'parameters' => $parameters,
            'optional' => $optional,
            'uri' => $uri,
        ];
    }

    /**
     * Validate parameter name.
     */
    protected function validateParameterName(string $name): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Parameter name cannot be empty.');
        }

        if (!preg_match(self::PARAMETER_NAME_PATTERN, $name)) {
            throw new InvalidArgumentException(
                "Parameter name '{$name}' is invalid. Must start with letter or underscore, followed by letters, numbers, or underscores."
            );
        }

        // Reserved parameter names that might conflict
        $reserved = ['controller', 'action', 'method', 'uri', 'route'];
        if (in_array(strtolower($name), $reserved)) {
            throw new InvalidArgumentException("Parameter name '{$name}' is reserved.");
        }
    }

    /**
     * Extract parameters from URI using compiled route.
     */
    public function extractParameters(string $uri, array $compiled): array
    {
        if (empty($compiled['parameters'])) {
            return [];
        }

        if (!preg_match($compiled['regex'], $uri, $matches)) {
            return [];
        }

        $parameters = [];
        $optional = $compiled['optional'] ?? [];

        foreach ($compiled['parameters'] as $index => $name) {
            $value = $matches[$index + 1] ?? null;
            
            // Handle optional parameters
            if ($value === null || $value === '') {
                if (in_array($name, $optional)) {
                    continue; // Skip optional parameters that weren't provided
                }
            }

            $parameters[$name] = $value;
        }

        return $parameters;
    }

    /**
     * Check if URI matches compiled route.
     */
    public function matches(string $uri, array $compiled): bool
    {
        return preg_match($compiled['regex'], $uri) === 1;
    }

    /**
     * Generate URI from route pattern and parameters.
     */
    public function generate(string $pattern, array $parameters = []): string
    {
        $uri = $pattern;

        // Replace parameters in pattern
        foreach ($parameters as $name => $value) {
            $uri = str_replace(['{' . $name . '}', '{' . $name . '?}'], $value, $uri);
        }

        // Remove any remaining optional parameters
        $uri = preg_replace('/\{[^}]+\?\}/', '', $uri);

        // Check for missing required parameters
        if (preg_match('/\{([^}]+)\}/', $uri, $matches)) {
            throw new InvalidArgumentException("Missing required parameter: {$matches[1]}");
        }

        // Clean up multiple slashes
        $uri = preg_replace('#/+#', '/', $uri);

        return $uri;
    }

    /**
     * Get parameter names from URI pattern.
     */
    public function getParameterNames(string $uri): array
    {
        if (!preg_match_all(self::PARAMETER_PATTERN, $uri, $matches)) {
            return [];
        }

        return array_map(function ($param) {
            return rtrim($param, '?'); // Remove optional marker
        }, $matches[1]);
    }

    /**
     * Get optional parameter names from URI pattern.
     */
    public function getOptionalParameterNames(string $uri): array
    {
        if (!preg_match_all('/\{([^}]+\?)\}/', $uri, $matches)) {
            return [];
        }

        return array_map(function ($param) {
            return rtrim($param, '?');
        }, $matches[1]);
    }

    /**
     * Check if URI pattern has parameters.
     */
    public function hasParameters(string $uri): bool
    {
        return str_contains($uri, '{');
    }

    /**
     * Optimize regex for performance.
     */
    public function optimizeRegex(string $regex): string
    {
        // Remove unnecessary capturing groups if not needed
        // This is a simple optimization - more advanced ones could be added
        return $regex;
    }

    /**
     * Validate URI pattern.
     */
    public function validatePattern(string $uri): array
    {
        $errors = [];

        // Check for unmatched braces
        $openBraces = substr_count($uri, '{');
        $closeBraces = substr_count($uri, '}');
        
        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unmatched braces in URI pattern.';
        }

        // Check for empty parameters
        if (preg_match('/\{\s*\}/', $uri)) {
            $errors[] = 'Empty parameter names are not allowed.';
        }

        // Check for nested parameters
        if (preg_match('/\{[^}]*\{/', $uri)) {
            $errors[] = 'Nested parameters are not allowed.';
        }

        // Validate parameter names
        if (preg_match_all(self::PARAMETER_PATTERN, $uri, $matches)) {
            foreach ($matches[1] as $paramName) {
                $cleanName = rtrim($paramName, '?');
                try {
                    $this->validateParameterName($cleanName);
                } catch (InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                }
            }

            // Check for duplicate parameter names
            $paramNames = array_map(fn($name) => rtrim($name, '?'), $matches[1]);
            if (count($paramNames) !== count(array_unique($paramNames))) {
                $errors[] = 'Duplicate parameter names are not allowed.';
            }
        }

        return $errors;
    }

    /**
     * Get compilation statistics.
     */
    public function getStats(array $compiled): array
    {
        return [
            'regex' => $compiled['regex'],
            'regex_length' => strlen($compiled['regex']),
            'parameter_count' => count($compiled['parameters']),
            'optional_count' => count($compiled['optional'] ?? []),
            'has_parameters' => !empty($compiled['parameters']),
            'complexity' => $this->calculateComplexity($compiled['regex']),
        ];
    }

    /**
     * Calculate regex complexity score.
     */
    protected function calculateComplexity(string $regex): int
    {
        $complexity = 0;
        
        // Count special regex characters
        $complexity += substr_count($regex, '(');
        $complexity += substr_count($regex, '[');
        $complexity += substr_count($regex, '*');
        $complexity += substr_count($regex, '+');
        $complexity += substr_count($regex, '?');
        $complexity += substr_count($regex, '|');
        
        return $complexity;
    }
}