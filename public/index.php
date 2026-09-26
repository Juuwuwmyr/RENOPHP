<?php

/**
 * ============================================================================
 * RENOPHP Framework - Application Entry Point
 * ============================================================================
 * 
 * "Less Magic. More Understanding."
 * 
 * This is the front controller for all HTTP requests. All web requests are
 * routed through this file by the web server (Apache/Nginx).
 * 
 * @package   RENOPHP
 * @author    Moreno Jumyr
 * @copyright 2024 RENOPHP
 * @license   MIT License
 * @link      https://github.com/yourusername/renophp
 */

// ============================================================================
// 1. Define Application Constants
// ============================================================================

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// ============================================================================
// 2. Register Composer Autoloader
// ============================================================================

require BASE_PATH . '/vendor/autoload.php';

// ============================================================================
// 3. Load Environment Configuration
// ============================================================================

try {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
} catch (Exception $e) {
    // Fallback if .env file doesn't exist
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'production';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? false;
}

// ============================================================================
// 4. Error Handling Configuration
// ============================================================================

$isDebugMode = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($isDebugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ============================================================================
// 5. Load Application Routes
// ============================================================================

$router = require BASE_PATH . '/routes/web.php';

// ============================================================================
// 6. Create HTTP Request from Globals
// ============================================================================

$request = Reno\Http\Request::createFromGlobals();

// ============================================================================
// 7. Route Matching & Request Handling
// ============================================================================

try {
    // Match the incoming request to a registered route
    $route = $router->matchRequest($request);
    
    // Bind route parameters to the request
    $request->setRouteParameters($route->getParameters());
    
    // Get the route action (Closure or Controller@method)
    $action = $route->getAction();
    
    // Execute the route action
    if ($action instanceof Closure) {
        // Execute closure with route parameters
        $response = call_user_func_array($action, array_values($route->getParameters()));
        
    } elseif (is_array($action) && count($action) === 2) {
        // Execute controller method [ControllerClass::class, 'method']
        [$controllerClass, $method] = $action;
        
        // Instantiate controller
        $controller = new $controllerClass();
        
        // Call controller method with route parameters
        $response = call_user_func_array([$controller, $method], array_values($route->getParameters()));
        
    } else {
        throw new Exception("Invalid route action type");
    }
    
    // ========================================================================
    // 8. Response Normalization
    // ========================================================================
    
    // Convert various response types to Response object
    if (!($response instanceof Reno\Http\Response)) {
        
        if (is_array($response)) {
            // Array → JSON Response
            $response = Reno\Http\Response::json($response);
            
        } elseif ($response instanceof Reno\View\View) {
            // View → HTML Response
            $response = new Reno\Http\Response($response->render());
            
        } elseif (is_string($response) || is_numeric($response)) {
            // String/Number → Plain Response
            $response = new Reno\Http\Response((string)$response);
            
        } elseif ($response === null) {
            // Null → 204 No Content
            $response = Reno\Http\Response::noContent();
            
        } else {
            // Other → Convert to string
            $response = new Reno\Http\Response((string)$response);
        }
    }
    
    // ========================================================================
    // 9. Send Response to Client
    // ========================================================================
    
    $response->send();
    
} catch (Reno\Http\Exceptions\NotFoundHttpException $e) {
    // ========================================================================
    // 404 Not Found - Route doesn't exist
    // ========================================================================
    
    http_response_code(404);
    
    if ($isDebugMode) {
        renderDebugError(
            '404 - Route Not Found',
            'The requested route does not exist.',
            [
                'Request Method' => $request->method(),
                'Request Path' => $request->path(),
                'Request URI' => $request->uri(),
                'Available Routes' => 'Run: php console route:list'
            ]
        );
    } else {
        renderProductionError(
            '404 - Page Not Found',
            'The page you\'re looking for doesn\'t exist.'
        );
    }
    
} catch (Reno\Http\Exceptions\MethodNotAllowedException $e) {
    // ========================================================================
    // 405 Method Not Allowed - Wrong HTTP method for route
    // ========================================================================
    
    http_response_code(405);
    
    if ($isDebugMode) {
        renderDebugError(
            '405 - Method Not Allowed',
            $e->getMessage(),
            [
                'Request Method' => $request->method(),
                'Request Path' => $request->path(),
                'Allowed Methods' => implode(', ', $e->getAllowedMethods() ?? [])
            ]
        );
    } else {
        renderProductionError(
            '405 - Method Not Allowed',
            'The HTTP method used is not allowed for this resource.'
        );
    }
    
} catch (Reno\Validation\ValidationException $e) {
    // ========================================================================
    // 422 Validation Failed
    // ========================================================================
    
    http_response_code(422);
    
    if ($request->wantsJson()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ]);
    } else {
        renderDebugError(
            '422 - Validation Error',
            'The given data was invalid.',
            ['Validation Errors' => $e->errors()]
        );
    }
    
} catch (PDOException $e) {
    // ========================================================================
    // Database Error
    // ========================================================================
    
    http_response_code(500);
    
    // Never expose database details in production
    if ($isDebugMode) {
        renderDebugError(
            '🗄️ Database Error',
            $e->getMessage(),
            [
                'Error Code' => $e->getCode(),
                'File' => $e->getFile(),
                'Line' => $e->getLine()
            ],
            $e
        );
    } else {
        // Log the error (TODO: Implement logging)
        error_log("Database Error: " . $e->getMessage());
        
        renderProductionError(
            '500 - Service Unavailable',
            'We\'re having trouble connecting to our database. Please try again later.'
        );
    }
    
} catch (Exception $e) {
    // ========================================================================
    // 500 Internal Server Error - Catch-all for unexpected errors
    // ========================================================================
    
    http_response_code(500);
    
    if ($isDebugMode) {
        renderDebugError(
            '🐛 Application Error',
            $e->getMessage(),
            [
                'Exception Type' => get_class($e),
                'File' => $e->getFile(),
                'Line' => $e->getLine()
            ],
            $e
        );
    } else {
        // Log the error (TODO: Implement logging)
        error_log("Application Error: " . $e->getMessage());
        
        renderProductionError(
            '500 - Internal Server Error',
            'Something went wrong. Our team has been notified.'
        );
    }
}

// ============================================================================
// Helper Functions for Error Rendering
// ============================================================================

/**
 * Render detailed debug error page
 */
function renderDebugError(string $title, string $message, array $details = [], ?Throwable $exception = null): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f7fafc; padding: 20px; line-height: 1.6; }
            .container { max-width: 1200px; margin: 0 auto; }
            .error-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; }
            .error-header h1 { font-size: 28px; margin-bottom: 10px; }
            .error-header p { opacity: 0.9; font-size: 16px; }
            .error-body { background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .error-message { background: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin-bottom: 25px; border-radius: 4px; }
            .error-message strong { color: #c53030; display: block; margin-bottom: 5px; }
            .details { margin-bottom: 25px; }
            .details h3 { color: #2d3748; margin-bottom: 15px; font-size: 18px; }
            .details table { width: 100%; border-collapse: collapse; }
            .details td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
            .details td:first-child { font-weight: 600; color: #4a5568; width: 200px; }
            .details td:last-child { color: #1a202c; font-family: 'Courier New', monospace; font-size: 14px; }
            .stack-trace { background: #2d3748; color: #e2e8f0; padding: 20px; border-radius: 6px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.8; }
            .footer { text-align: center; margin-top: 20px; color: #718096; font-size: 14px; }
            .badge { display: inline-block; background: #667eea; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-header">
                <h1><?= htmlspecialchars($title) ?></h1>
                <p><span class="badge">DEBUG MODE</span> Detailed error information below</p>
            </div>
            
            <div class="error-body">
                <div class="error-message">
                    <strong>Error Message:</strong>
                    <?= htmlspecialchars($message) ?>
                </div>
                
                <?php if (!empty($details)): ?>
                <div class="details">
                    <h3>📋 Error Details</h3>
                    <table>
                        <?php foreach ($details as $key => $value): ?>
                        <tr>
                            <td><?= htmlspecialchars($key) ?></td>
                            <td><?= htmlspecialchars(is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if ($exception): ?>
                <div class="details">
                    <h3>📚 Stack Trace</h3>
                    <div class="stack-trace"><?= htmlspecialchars($exception->getTraceAsString()) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="footer">
                    <p><strong>RENOPHP Framework</strong> | Execution Time: <?= round((microtime(true) - APP_START) * 1000, 2) ?>ms</p>
                    <p style="margin-top: 10px; font-size: 12px;">
                        💡 Tip: Set <code>APP_DEBUG=false</code> in .env to hide this detailed error page in production
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Render simple production error page
 */
function renderProductionError(string $title, string $message): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-card { background: white; padding: 50px; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; max-width: 500px; }
            .error-icon { font-size: 80px; margin-bottom: 20px; }
            h1 { color: #2d3748; font-size: 32px; margin-bottom: 15px; }
            p { color: #4a5568; font-size: 18px; line-height: 1.6; margin-bottom: 30px; }
            .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: transform 0.2s; }
            .button:hover { transform: translateY(-2px); }
            .footer { margin-top: 30px; color: #718096; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">😔</div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="/" class="button">← Back to Home</a>
            <div class="footer">
                <p>RENOPHP Framework</p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
