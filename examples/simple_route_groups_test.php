<?php

use Horizon\Routing\RouteGroup;

// Test RouteGroup mergeAttributes method
echo "Testing Route Group Attribute Merging\n";
echo "=====================================\n\n";

// Test 1: Basic prefix merge
$old = ['prefix' => 'api'];
$new = ['prefix' => 'v1'];
$merged = RouteGroup::mergeAttributes($new, $old);
echo "1. Prefix merge:\n";
echo "   Old: " . json_encode($old) . "\n";
echo "   New: " . json_encode($new) . "\n";
echo "   Merged: " . json_encode($merged) . "\n\n";

// Test 2: Middleware merge
$old = ['middleware' => ['auth']];
$new = ['middleware' => ['api', 'throttle']];
$merged = RouteGroup::mergeAttributes($new, $old);
echo "2. Middleware merge:\n";
echo "   Old: " . json_encode($old) . "\n";
echo "   New: " . json_encode($new) . "\n";
echo "   Merged: " . json_encode($merged) . "\n\n";

// Test 3: Namespace merge
$old = ['namespace' => 'App\\Http\\Controllers'];
$new = ['namespace' => 'Api\\V1'];
$merged = RouteGroup::mergeAttributes($new, $old);
echo "3. Namespace merge:\n";
echo "   Old: " . json_encode($old) . "\n";
echo "   New: " . json_encode($new) . "\n";
echo "   Merged: " . json_encode($merged) . "\n\n";

// Test 4: Complex merge with multiple attributes
$old = [
    'prefix' => 'api',
    'middleware' => ['cors'],
    'namespace' => 'App\\Http\\Controllers'
];
$new = [
    'prefix' => 'v1',
    'middleware' => ['auth'],
    'where' => ['id' => '[0-9]+']
];
$merged = RouteGroup::mergeAttributes($new, $old);
echo "4. Complex merge:\n";
echo "   Old: " . json_encode($old, JSON_PRETTY_PRINT) . "\n";
echo "   New: " . json_encode($new, JSON_PRETTY_PRINT) . "\n";
echo "   Merged: " . json_encode($merged, JSON_PRETTY_PRINT) . "\n\n";

// Test 5: As (name prefix) merge
$old = ['as' => 'api.'];
$new = ['as' => 'v1.'];
$merged = RouteGroup::mergeAttributes($new, $old);
echo "5. Name prefix merge:\n";
echo "   Old: " . json_encode($old) . "\n";
echo "   New: " . json_encode($new) . "\n";
echo "   Merged: " . json_encode($merged) . "\n\n";

echo "Route Group Attribute Merging Tests Completed!\n";