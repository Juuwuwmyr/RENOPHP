<?php

/**
 * Horizon Framework - View System Examples
 * 
 * This file demonstrates the view system capabilities.
 * 
 * Run: php examples/views_example.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Horizon\View\ViewFactory;
use Horizon\View\FileViewFinder;
use Horizon\View\Engines\PhpEngine;
use Horizon\View\Engines\BladeEngine;
use Horizon\View\Compilers\BladeCompiler;
use Horizon\View\Component;

// Create temporary view directories
$viewPath = __DIR__ . '/views';
$cachePath = __DIR__ . '/cache';

if (!is_dir($viewPath)) {
    mkdir($viewPath, 0755, true);
}
if (!is_dir($cachePath)) {
    mkdir($cachePath, 0755, true);
}

echo "=============================================================================\n";
echo "Horizon Framework - View System Examples\n";
echo "=============================================================================\n\n";

// ============================================================================
// Example 1: Basic View Rendering
// ============================================================================

echo "Example 1: Basic View Rendering\n";
echo "-----------------------------------------------------------------------------\n";

// Create a simple view
file_put_contents($viewPath . '/welcome.php', <<<'PHP'
<!DOCTYPE html>
<html>
<head>
    <title><?= e($title) ?></title>
</head>
<body>
    <h1><?= e($message) ?></h1>
    <p>Welcome to <?= e($framework) ?>!</p>
</body>
</html>
PHP
);

// Create view factory
$finder = new FileViewFinder([$viewPath], ['php']);
$engine = new PhpEngine();
$factory = new ViewFactory($finder, $engine);

// Render view
$output = $factory->render('welcome', [
    'title' => 'Welcome Page',
    'message' => 'Hello, World!',
    'framework' => 'Horizon Framework'
]);

echo "Output:\n";
echo substr($output, 0, 200) . "...\n\n";

// ============================================================================
// Example 2: Layouts and Sections
// ============================================================================

echo "Example 2: Layouts and Sections\n";
echo "-----------------------------------------------------------------------------\n";

// Create layout
file_put_contents($viewPath . '/layout.php', <<<'PHP'
<!DOCTYPE html>
<html>
<head>
    <title><?= yield_content('title', 'Default Title') ?></title>
    <?= stack('styles') ?>
</head>
<body>
    <header>
        <h1>Horizon Framework</h1>
    </header>
    
    <main>
        <?= yield_content('content') ?>
    </main>
    
    <footer>
        <p>&copy; 2024 Horizon</p>
    </footer>
    
    <?= stack('scripts') ?>
</body>
</html>
PHP
);

// Create page that extends layout
file_put_contents($viewPath . '/page.php', <<<'PHP'
<?php extend('layout'); ?>

<?php section('title'); ?>
    <?= e($pageTitle) ?>
<?php endsection(); ?>

<?php section('content'); ?>
    <h2><?= e($heading) ?></h2>
    <p><?= e($content) ?></p>
<?php endsection(); ?>

<?php push('scripts'); ?>
    <script>console.log('Page loaded!');</script>
<?php endpush(); ?>
PHP
);

$output = $factory->render('page', [
    'pageTitle' => 'My Page',
    'heading' => 'Welcome',
    'content' => 'This page uses layouts!'
]);

echo "Output (excerpt):\n";
echo substr($output, 0, 300) . "...\n\n";

// ============================================================================
// Example 3: Blade Syntax
// ============================================================================

echo "Example 3: Blade Syntax\n";
echo "-----------------------------------------------------------------------------\n";

// Create Blade view
file_put_contents($viewPath . '/blade.blade.php', <<<'BLADE'
<h1>{{ $title }}</h1>

@if ($showWelcome)
    <p>Welcome, {{ $name }}!</p>
@endif

<ul>
@foreach ($items as $item)
    <li>{{ $item }}</li>
@endforeach
</ul>

@unless ($isGuest)
    <p>You are logged in.</p>
@endunless
BLADE
);

// Create Blade engine
$compiler = new BladeCompiler($cachePath);
$bladeEngine = new BladeEngine($compiler);
$bladeFactory = new ViewFactory($finder, $bladeEngine);

$output = $bladeFactory->render('blade', [
    'title' => 'Blade Example',
    'showWelcome' => true,
    'name' => 'John Doe',
    'items' => ['Apple', 'Banana', 'Orange'],
    'isGuest' => false
]);

echo "Output:\n";
echo $output . "\n\n";

// Check compiled file
$compiledPath = $compiler->getCompiledPath($viewPath . '/blade.blade.php');
if (file_exists($compiledPath)) {
    echo "Compiled to PHP:\n";
    echo substr(file_get_contents($compiledPath), 0, 300) . "...\n\n";
}

// ============================================================================
// Example 4: View Composers
// ============================================================================

echo "Example 4: View Composers\n";
echo "-----------------------------------------------------------------------------\n";

// Register composer
$factory->composer('profile', function ($view) {
    $view->with('timestamp', date('Y-m-d H:i:s'));
    $view->with('version', '1.0.0');
});

// Create profile view
file_put_contents($viewPath . '/profile.php', <<<'PHP'
<div class="profile">
    <h2><?= e($username) ?></h2>
    <p>Generated: <?= e($timestamp) ?></p>
    <p>Version: <?= e($version) ?></p>
</div>
PHP
);

$output = $factory->render('profile', [
    'username' => 'johndoe'
]);

echo "Output:\n";
echo $output . "\n\n";

// ============================================================================
// Example 5: Shared Data
// ============================================================================

echo "Example 5: Shared Data\n";
echo "-----------------------------------------------------------------------------\n";

// Share data with all views
$factory->share([
    'appName' => 'Horizon Framework',
    'appVersion' => '1.0.0'
]);

file_put_contents($viewPath . '/footer.php', <<<'PHP'
<footer>
    <p>&copy; <?= date('Y') ?> <?= e($appName) ?> v<?= e($appVersion) ?></p>
</footer>
PHP
);

$output = $factory->render('footer', []);

echo "Output:\n";
echo $output . "\n\n";

// ============================================================================
// Example 6: Components
// ============================================================================

echo "Example 6: Components\n";
echo "-----------------------------------------------------------------------------\n";

// Create alert component
file_put_contents($viewPath . '/alert.php', <<<'PHP'
<div class="alert alert-<?= e($type) ?>">
    <strong><?= e($title) ?></strong>
    <p><?= $slot ?></p>
</div>
PHP
);

// Create page using component
file_put_contents($viewPath . '/with-component.php', <<<'PHP'
<?php component('alert', ['type' => 'success', 'title' => 'Success!']); ?>
    Your profile has been updated successfully.
<?php echo endcomponent(); ?>

<?php component('alert', ['type' => 'warning', 'title' => 'Warning']); ?>
    Please verify your email address.
<?php echo endcomponent(); ?>
PHP
);

$output = $factory->render('with-component', []);

echo "Output:\n";
echo $output . "\n\n";

// ============================================================================
// Example 7: Security - XSS Protection
// ============================================================================

echo "Example 7: Security - XSS Protection\n";
echo "-----------------------------------------------------------------------------\n";

file_put_contents($viewPath . '/security.php', <<<'PHP'
<h3>Escaped (Safe):</h3>
<p><?= e($userInput) ?></p>

<h3>Raw (Dangerous - Don't use with user input!):</h3>
<p><?= raw($trustedHtml) ?></p>
PHP
);

$output = $factory->render('security', [
    'userInput' => '<script>alert("XSS")</script>',
    'trustedHtml' => '<strong>This is safe HTML</strong>'
]);

echo "Output:\n";
echo $output . "\n\n";

// ============================================================================
// Example 8: View Caching
// ============================================================================

echo "Example 8: View Caching\n";
echo "-----------------------------------------------------------------------------\n";

// First render - compiles
$start = microtime(true);
$output1 = $bladeFactory->render('blade', [
    'title' => 'Cache Test',
    'showWelcome' => true,
    'name' => 'Test User',
    'items' => ['Item 1', 'Item 2'],
    'isGuest' => false
]);
$time1 = microtime(true) - $start;

// Second render - uses cache
$start = microtime(true);
$output2 = $bladeFactory->render('blade', [
    'title' => 'Cache Test',
    'showWelcome' => true,
    'name' => 'Test User',
    'items' => ['Item 1', 'Item 2'],
    'isGuest' => false
]);
$time2 = microtime(true) - $start;

echo "First render (compile): " . number_format($time1 * 1000, 3) . " ms\n";
echo "Second render (cached): " . number_format($time2 * 1000, 3) . " ms\n";
echo "Speedup: " . number_format($time1 / $time2, 1) . "x faster\n\n";

// ============================================================================
// Example 9: Error Handling
// ============================================================================

echo "Example 9: Error Handling\n";
echo "-----------------------------------------------------------------------------\n";

try {
    $factory->render('nonexistent-view', []);
} catch (\Horizon\View\ViewException $e) {
    echo "Caught ViewException:\n";
    echo "Message: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// Example 10: Advanced Blade Directives
// ============================================================================

echo "Example 10: Advanced Blade Directives\n";
echo "-----------------------------------------------------------------------------\n";

file_put_contents($viewPath . '/advanced.blade.php', <<<'BLADE'
{{-- Comments are not in output --}}

@isset($user)
    <p>User: {{ $user->name }}</p>
@endisset

@empty($items)
    <p>No items found.</p>
@else
    <ul>
    @foreach ($items as $item)
        <li>{{ $item }}</li>
        @if ($loop->first)
            (first)
        @endif
    @endforeach
    </ul>
@endempty

@for ($i = 0; $i < 3; $i++)
    <p>Iteration {{ $i }}</p>
@endfor

<p>JSON Data: @json(['key' => 'value'])</p>
BLADE
);

$output = $bladeFactory->render('advanced', [
    'user' => (object)['name' => 'John Doe'],
    'items' => ['A', 'B', 'C']
]);

echo "Output:\n";
echo $output . "\n\n";

// ============================================================================
// Cleanup
// ============================================================================

echo "=============================================================================\n";
echo "Examples completed successfully!\n";
echo "=============================================================================\n\n";

echo "Temporary files created in: {$viewPath}\n";
echo "Compiled cache in: {$cachePath}\n\n";

echo "To clean up, run:\n";
echo "  rm -rf {$viewPath}\n";
echo "  rm -rf {$cachePath}\n";
