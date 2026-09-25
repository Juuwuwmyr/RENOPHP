<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configure security headers to protect against common vulnerabilities
    | like XSS, clickjacking, MIME type sniffing, etc.
    |
    */

    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'DENY',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
        'strict_transport_security' => 'max-age=31536000; includeSubDomains; preload',
        'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing (CORS) configuration to control
    | which origins can access your API.
    |
    */

    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 86400,
        'supports_credentials' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Cross-Site Request Forgery protection settings.
    |
    */

    'csrf' => [
        'token_name' => '_token',
        'header_name' => 'X-CSRF-TOKEN',
        'cookie_name' => 'XSRF-TOKEN',
        'except' => [
            'api/*',
            'webhooks/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different types of requests.
    |
    */

    'rate_limiting' => [
        'api' => [
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ],
        'login' => [
            'max_attempts' => 5,
            'decay_minutes' => 15,
        ],
        'global' => [
            'max_attempts' => 1000,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Detailed Content Security Policy configuration.
    |
    */

    'csp' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'https:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'media-src' => ["'self'"],
        'object-src' => ["'none'"],
        'child-src' => ["'self'"],
        'frame-ancestors' => ["'none'"],
        'form-action' => ["'self'"],
        'base-uri' => ["'self'"],
        'manifest-src' => ["'self'"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Configure trusted proxy servers for accurate IP detection.
    |
    */

    'trusted_proxies' => [
        // '127.0.0.1',
        // '10.0.0.0/8',
        // '172.16.0.0/12',
        // '192.168.0.0/16',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Hosts
    |--------------------------------------------------------------------------
    |
    | Configure trusted hostnames to prevent Host header attacks.
    |
    */

    'trusted_hosts' => [
        // 'example.com',
        // '*.example.com',
    ],
];