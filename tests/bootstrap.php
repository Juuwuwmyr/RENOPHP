<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Suite Bootstrap
|--------------------------------------------------------------------------
|
| This file is used to bootstrap the test suite. It sets up the autoloader
| and any necessary configuration for running tests.
|
*/

require_once __DIR__ . '/../vendor/autoload.php';

// Set up error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone to avoid warnings
date_default_timezone_set('UTC');

// Define testing environment
if (!defined('HORIZON_TESTING')) {
    define('HORIZON_TESTING', true);
}

// Set up test environment variables
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';