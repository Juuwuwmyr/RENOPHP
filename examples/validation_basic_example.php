<?php

/**
 * Horizon Framework - Basic Validation Example
 * 
 * This example demonstrates basic validation features including:
 * - Simple validation rules
 * - Error handling
 * - Custom messages
 * - Custom attribute names
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Horizon\Validation\Validator;
use Horizon\Validation\ValidationException;
use Horizon\Validation\Factory;

echo "========================================================\n";
echo "HORIZON FRAMEWORK - BASIC VALIDATION EXAMPLE\n";
echo "========================================================\n\n";

// ============================================================================
// Example 1: Simple Validation
// ============================================================================

echo "📋 Example 1: Simple Validation\n";
echo "================================\n\n";

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
];

$rules = [
    'name' => 'required|string|min:3|max:50',
    'email' => 'required|email',
    'age' => 'required|numeric|min:18',
];

$validator = new Validator($data, $rules);

if ($validator->passes()) {
    echo "✅ Validation passed!\n";
    echo "Validated data:\n";
    print_r($validator->validated());
} else {
    echo "❌ Validation failed!\n";
    echo "Errors:\n";
    print_r($validator->errors()->toArray());
}

echo "\n";

// ============================================================================
// Example 2: Validation with Errors
// ============================================================================

echo "📋 Example 2: Validation with Errors\n";
echo "=====================================\n\n";

$invalidData = [
    'name' => 'Jo', // Too short
    'email' => 'not-an-email', // Invalid email
    'age' => 15, // Too young
];

$validator = new Validator($invalidData, $rules);

if ($validator->fails()) {
    echo "❌ Validation failed (as expected)!\n\n";
    
    $errors = $validator->errors();
    
    echo "All errors:\n";
    foreach ($errors->all() as $error) {
        echo "  - {$error}\n";
    }
    
    echo "\nErrors by field:\n";
    foreach ($errors->toArray() as $field => $fieldErrors) {
        echo "  {$field}:\n";
        foreach ($fieldErrors as $error) {
            echo "    - {$error}\n";
        }
    }
    
    echo "\nFirst error overall: " . $errors->first() . "\n";
    echo "First error for 'email': " . $errors->first('email') . "\n";
}

echo "\n";

// ============================================================================
// Example 3: Custom Error Messages
// ============================================================================

echo "📋 Example 3: Custom Error Messages\n";
echo "====================================\n\n";

$customMessages = [
    'name.required' => 'Hey! We need your name!',
    'name.min' => 'Your name is too short. At least :0 characters please.',
    'email.required' => 'Please provide your email address.',
    'email.email' => 'That doesn\'t look like a valid email.',
    'age.min' => 'You must be at least :0 years old.',
];

$validator = new Validator($invalidData, $rules, $customMessages);

if ($validator->fails()) {
    echo "Custom error messages:\n";
    foreach ($validator->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// ============================================================================
// Example 4: Custom Attribute Names
// ============================================================================

echo "📋 Example 4: Custom Attribute Names\n";
echo "=====================================\n\n";

$data = [
    'user_email' => 'invalid',
    'user_age' => 10,
];

$rules = [
    'user_email' => 'required|email',
    'user_age' => 'required|min:18',
];

$customAttributes = [
    'user_email' => 'email address',
    'user_age' => 'age',
];

$validator = new Validator($data, $rules, [], $customAttributes);

if ($validator->fails()) {
    echo "With custom attribute names:\n";
    foreach ($validator->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// ============================================================================
// Example 5: Using Validation Factory
// ============================================================================

echo "📋 Example 5: Using Validation Factory\n";
echo "=======================================\n\n";

$factory = new Factory();

try {
    $validatedData = $factory->validate(
        ['username' => 'john_doe', 'password' => 'secret123'],
        [
            'username' => 'required|alpha_dash|min:3|max:20',
            'password' => 'required|min:8',
        ]
    );
    
    echo "✅ Factory validation passed!\n";
    echo "Validated data:\n";
    print_r($validatedData);
} catch (ValidationException $e) {
    echo "❌ Factory validation failed!\n";
    echo "Errors: " . json_encode($e->errors()->toArray()) . "\n";
}

echo "\n";

// ============================================================================
// Example 6: Array Rule Syntax
// ============================================================================

echo "📋 Example 6: Array Rule Syntax\n";
echo "================================\n\n";

$data = [
    'title' => 'Hello World',
    'content' => 'This is some content',
];

// Rules can be defined as arrays instead of pipe-delimited strings
$rulesArray = [
    'title' => ['required', 'string', 'min:5', 'max:100'],
    'content' => ['required', 'string', 'min:10'],
];

$validator = new Validator($data, $rulesArray);

if ($validator->passes()) {
    echo "✅ Array syntax validation passed!\n";
    echo "Title: {$data['title']}\n";
    echo "Content: {$data['content']}\n";
}

echo "\n";

// ============================================================================
// Example 7: Nullable Fields
// ============================================================================

echo "📋 Example 7: Nullable Fields\n";
echo "==============================\n\n";

$data1 = ['bio' => null];
$data2 = ['bio' => 'Software developer'];
$data3 = ['bio' => 'Hi']; // Too short

$rules = [
    'bio' => 'nullable|string|min:10',
];

echo "Data 1 (null bio):\n";
$validator = new Validator($data1, $rules);
echo $validator->passes() ? "  ✅ Passed (null is allowed)\n" : "  ❌ Failed\n";

echo "Data 2 (valid bio):\n";
$validator = new Validator($data2, $rules);
echo $validator->passes() ? "  ✅ Passed\n" : "  ❌ Failed\n";

echo "Data 3 (too short bio):\n";
$validator = new Validator($data3, $rules);
if ($validator->fails()) {
    echo "  ❌ Failed: " . $validator->errors()->first('bio') . "\n";
}

echo "\n";

// ============================================================================
// Example 8: Multiple Rule Validation
// ============================================================================

echo "📋 Example 8: Multiple Rule Validation\n";
echo "=======================================\n\n";

$formData = [
    'username' => 'john_doe_123',
    'email' => 'john@example.com',
    'age' => 25,
    'website' => 'https://example.com',
    'bio' => 'I am a software developer who loves coding.',
    'terms' => true,
];

$formRules = [
    'username' => 'required|alpha_dash|min:3|max:20',
    'email' => 'required|email|lowercase',
    'age' => 'required|integer|between:18,120',
    'website' => 'nullable|url',
    'bio' => 'required|string|min:10|max:500',
    'terms' => 'required|accepted',
];

$validator = new Validator($formData, $formRules);

if ($validator->passes()) {
    echo "✅ Complete form validation passed!\n\n";
    echo "Validated user data:\n";
    foreach ($validator->validated() as $field => $value) {
        $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        echo "  {$field}: {$displayValue}\n";
    }
} else {
    echo "❌ Form validation failed!\n";
    foreach ($validator->errors()->toArray() as $field => $errors) {
        echo "  {$field}:\n";
        foreach ($errors as $error) {
            echo "    - {$error}\n";
        }
    }
}

echo "\n";

// ============================================================================
// Example 9: MessageBag Methods
// ============================================================================

echo "📋 Example 9: MessageBag Methods\n";
echo "=================================\n\n";

$data = [
    'field1' => '',
    'field2' => 'invalid',
    'field3' => 'x',
];

$rules = [
    'field1' => 'required',
    'field2' => 'email',
    'field3' => 'min:5',
];

$validator = new Validator($data, $rules);
$validator->validate();

$errors = $validator->errors();

echo "MessageBag methods:\n";
echo "  any(): " . ($errors->any() ? 'true' : 'false') . "\n";
echo "  isEmpty(): " . ($errors->isEmpty() ? 'true' : 'false') . "\n";
echo "  count(): " . $errors->count() . "\n";
echo "  countFields(): " . $errors->countFields() . "\n";
echo "  keys(): " . implode(', ', $errors->keys()) . "\n";
echo "  has('field1'): " . ($errors->has('field1') ? 'true' : 'false') . "\n";
echo "  has('field99'): " . ($errors->has('field99') ? 'true' : 'false') . "\n";

echo "\n";

// ============================================================================
// Summary
// ============================================================================

echo "========================================================\n";
echo "✅ BASIC VALIDATION EXAMPLES COMPLETE!\n";
echo "========================================================\n\n";

echo "This example demonstrated:\n";
echo "  ✓ Simple validation with pipe-delimited rules\n";
echo "  ✓ Array syntax for rules\n";
echo "  ✓ Error handling and access\n";
echo "  ✓ Custom error messages\n";
echo "  ✓ Custom attribute names\n";
echo "  ✓ Validation Factory usage\n";
echo "  ✓ Nullable fields\n";
echo "  ✓ MessageBag methods\n";
echo "  ✓ Complete form validation\n\n";

echo "Next: See validation_advanced_example.php for advanced features!\n";
