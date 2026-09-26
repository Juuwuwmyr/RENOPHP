<?php

/**
 * Horizon Framework - Advanced Validation Example
 * 
 * This example demonstrates advanced validation features including:
 * - Conditional validation (sometimes, when, unless)
 * - Custom validation callbacks (after)
 * - Custom validation rules
 * - Nested data validation
 * - File upload validation
 * - Complex validation scenarios
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Horizon\Validation\Validator;
use Horizon\Validation\ValidationException;
use Horizon\Validation\Rules\CustomRule;
use Horizon\Validation\Rules\PasswordRule;
use Horizon\Validation\Rules\PhoneRule;
use Horizon\Validation\Rules\CreditCardRule;

echo "========================================================\n";
echo "HORIZON FRAMEWORK - ADVANCED VALIDATION EXAMPLE\n";
echo "========================================================\n\n";

// ============================================================================
// Example 1: Conditional Validation (sometimes)
// ============================================================================

echo "📋 Example 1: Conditional Validation\n";
echo "=====================================\n\n";

$data = [
    'user_type' => 'business',
    'company_name' => '', // Required only for business users
    'tax_id' => '',       // Required only for business users
];

$validator = new Validator($data, [
    'user_type' => 'required|in:individual,business',
]);

// Add rules conditionally based on user_type
$validator->sometimes(
    fn($data) => isset($data['user_type']) && $data['user_type'] === 'business',
    [
        'company_name' => 'required|string|min:3',
        'tax_id' => 'required|string',
    ]
);

if ($validator->fails()) {
    echo "❌ Validation failed for business user:\n";
    foreach ($validator->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
} else {
    echo "✅ Validation passed\n";
}

echo "\n";

// Individual user (no company fields required)
$individualData = [
    'user_type' => 'individual',
];

$validator2 = new Validator($individualData, [
    'user_type' => 'required|in:individual,business',
]);

$validator2->sometimes(
    fn($data) => isset($data['user_type']) && $data['user_type'] === 'business',
    [
        'company_name' => 'required|string|min:3',
        'tax_id' => 'required|string',
    ]
);

echo "Individual user validation: " . ($validator2->passes() ? "✅ Passed" : "❌ Failed") . "\n\n";

// ============================================================================
// Example 2: when() and unless() Methods
// ============================================================================

echo "📋 Example 2: when() and unless() Methods\n";
echo "==========================================\n\n";

$shippingData = [
    'same_as_billing' => false,
    'shipping_address' => '123 Ship St',
    'shipping_city' => 'Shipville',
];

$validator = new Validator($shippingData, [
    'same_as_billing' => 'required|boolean',
]);

// Add shipping fields only when same_as_billing is false
$validator->unless('same_as_billing', [
    'shipping_address' => 'required|string|min:5',
    'shipping_city' => 'required|string',
    'shipping_zip' => 'required|string',
]);

if ($validator->fails()) {
    echo "❌ Shipping validation failed:\n";
    echo "  Missing: " . $validator->errors()->first('shipping_zip') . "\n";
} else {
    echo "✅ Shipping validation passed\n";
}

echo "\n";

// ============================================================================
// Example 3: Custom Validation Callbacks (after)
// ============================================================================

echo "📋 Example 3: Custom Validation Callbacks\n";
echo "==========================================\n\n";

$passwordData = [
    'username' => 'admin',
    'password' => 'admin123', // Weak password
];

$validator = new Validator($passwordData, [
    'username' => 'required|string',
    'password' => 'required|string|min:8',
]);

// Add custom validation logic after standard validation
$validator->after(function($validator) {
    $data = $validator->getData();
    
    // Check if password contains username
    if (isset($data['username']) && isset($data['password'])) {
        if (stripos($data['password'], $data['username']) !== false) {
            $validator->addError('password', 'Password cannot contain your username.');
        }
    }
    
    // Check for common passwords
    $commonPasswords = ['password', 'admin', '123456', 'qwerty'];
    if (isset($data['password'])) {
        foreach ($commonPasswords as $common) {
            if (stripos($data['password'], $common) !== false) {
                $validator->addError('password', 'Password is too common. Please choose a stronger password.');
                break;
            }
        }
    }
});

if ($validator->fails()) {
    echo "❌ Custom validation failed:\n";
    foreach ($validator->errors()->get('password') as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// ============================================================================
// Example 4: Custom Rules - Closure-based
// ============================================================================

echo "📋 Example 4: Custom Rules (Closure)\n";
echo "=====================================\n\n";

// Register a custom "even number" rule
Validator::extend('even', new CustomRule(
    fn($value) => is_numeric($value) && $value % 2 === 0,
    'The :attribute must be an even number',
    'even'
));

$data = ['quantity' => 7];
$validator = new Validator($data, ['quantity' => 'required|numeric|even']);

if ($validator->fails()) {
    echo "❌ Custom rule failed: " . $validator->errors()->first('quantity') . "\n";
}

// Try with even number
$data2 = ['quantity' => 8];
$validator2 = new Validator($data2, ['quantity' => 'required|numeric|even']);
echo "Even number validation: " . ($validator2->passes() ? "✅ Passed" : "❌ Failed") . "\n\n";

// ============================================================================
// Example 5: Password Rule
// ============================================================================

echo "📋 Example 5: Password Rule\n";
echo "============================\n\n";

$passwordRule = (new PasswordRule())
    ->min(12)
    ->requireUppercase()
    ->requireLowercase()
    ->requireNumbers()
    ->requireSpecialCharacters();

Validator::extend('strong_password', $passwordRule);

$passwords = [
    'weak123' => '❌',
    'StrongP@ssw0rd' => '✅',
    'NoSpecial123ABC' => '❌',
];

foreach ($passwords as $password => $expected) {
    $validator = new Validator(
        ['password' => $password],
        ['password' => 'required|strong_password']
    );
    
    $result = $validator->passes() ? '✅' : '❌';
    $status = $result === $expected ? 'correct' : 'unexpected';
    
    echo "  Password: '{$password}' -> {$result} ({$status})\n";
    
    if ($validator->fails()) {
        echo "    Error: " . $validator->errors()->first('password') . "\n";
    }
}

echo "\n";

// ============================================================================
// Example 6: Phone Number Rule
// ============================================================================

echo "📋 Example 6: Phone Number Validation\n";
echo "======================================\n\n";

$usPhoneRule = new PhoneRule('US');
Validator::extend('phone_us', $usPhoneRule);

$internationalPhoneRule = new PhoneRule('international');
Validator::extend('phone_international', $internationalPhoneRule);

$phones = [
    '+1-555-123-4567' => 'US',
    '555-123-4567' => 'US',
    '+44-20-7123-4567' => 'International',
    '12345' => 'Invalid',
];

foreach ($phones as $phone => $type) {
    $rule = $type === 'US' ? 'phone_us' : 'phone_international';
    $validator = new Validator(
        ['phone' => $phone],
        ['phone' => "required|{$rule}"]
    );
    
    echo "  {$phone} ({$type}): " . ($validator->passes() ? '✅ Valid' : '❌ Invalid') . "\n";
}

echo "\n";

// ============================================================================
// Example 7: Credit Card Validation
// ============================================================================

echo "📋 Example 7: Credit Card Validation\n";
echo "=====================================\n\n";

$visaRule = new CreditCardRule('visa');
Validator::extend('credit_card_visa', $visaRule);

$anyCardRule = new CreditCardRule(); // Any card type
Validator::extend('credit_card', $anyCardRule);

$cards = [
    '4532015112830366' => 'Valid Visa',
    '6011111111111117' => 'Valid Discover',
    '1234567890123456' => 'Invalid (bad Luhn)',
    '4532-0151-1283-0366' => 'Valid Visa (with dashes)',
];

foreach ($cards as $card => $description) {
    $validator = new Validator(
        ['card' => $card],
        ['card' => 'required|credit_card']
    );
    
    echo "  {$description}: " . ($validator->passes() ? '✅ Valid' : '❌ Invalid') . "\n";
}

echo "\n";

// ============================================================================
// Example 8: Nested Data Validation
// ============================================================================

echo "📋 Example 8: Nested Data Validation\n";
echo "=====================================\n\n";

$nestedData = [
    'user' => [
        'name' => 'John',
        'email' => 'john@example.com',
        'address' => [
            'street' => '123 Main St',
            'city' => 'NYC',
            'zip' => '10001',
        ],
    ],
];

$nestedRules = [
    'user.name' => 'required|string|min:2',
    'user.email' => 'required|email',
    'user.address.street' => 'required|string',
    'user.address.city' => 'required|string',
    'user.address.zip' => 'required|string|size:5',
];

$validator = new Validator($nestedData, $nestedRules);

if ($validator->passes()) {
    echo "✅ Nested validation passed!\n";
    echo "User email: {$nestedData['user']['email']}\n";
    echo "User city: {$nestedData['user']['address']['city']}\n";
} else {
    echo "❌ Nested validation failed!\n";
    foreach ($validator->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// ============================================================================
// Example 9: Complex Business Logic Validation
// ============================================================================

echo "📋 Example 9: Complex Business Logic\n";
echo "=====================================\n\n";

$orderData = [
    'product_type' => 'digital',
    'quantity' => 2,
    'price' => 49.99,
    'discount_code' => 'SAVE20',
];

$validator = new Validator($orderData, [
    'product_type' => 'required|in:physical,digital',
    'quantity' => 'required|integer|min:1',
    'price' => 'required|numeric|min:0',
]);

// Add conditional shipping validation
$validator->when('product_type', [
    'shipping_address' => 'required|string|min:10',
    'shipping_method' => 'required|in:standard,express',
], [
    'shipping_address.required' => 'Physical products require a shipping address.',
]);

// Add complex business logic
$validator->after(function($validator) {
    $data = $validator->getData();
    
    // Check discount code validity
    if (isset($data['discount_code'])) {
        $validCodes = ['SAVE10', 'SAVE20', 'SPECIAL'];
        if (!in_array($data['discount_code'], $validCodes)) {
            $validator->addError('discount_code', 'Invalid discount code.');
        }
    }
    
    // Check minimum order value for discounts
    if (isset($data['discount_code']) && isset($data['price']) && isset($data['quantity'])) {
        $total = $data['price'] * $data['quantity'];
        if ($total < 100 && $data['discount_code'] === 'SAVE20') {
            $validator->addError('discount_code', 'SAVE20 code requires a minimum order of $100.');
        }
    }
    
    // Digital products must have quantity of 1
    if (isset($data['product_type']) && $data['product_type'] === 'digital' && $data['quantity'] > 1) {
        $validator->addError('quantity', 'Digital products can only be purchased with quantity of 1.');
    }
});

if ($validator->fails()) {
    echo "❌ Order validation failed:\n";
    foreach ($validator->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
} else {
    echo "✅ Order validation passed!\n";
}

echo "\n";

// ============================================================================
// Summary
// ============================================================================

echo "========================================================\n";
echo "✅ ADVANCED VALIDATION EXAMPLES COMPLETE!\n";
echo "========================================================\n\n";

echo "This example demonstrated:\n";
echo "  ✓ Conditional validation (sometimes, when, unless)\n";
echo "  ✓ Custom validation callbacks (after)\n";
echo "  ✓ Closure-based custom rules\n";
echo "  ✓ Pre-built custom rules (Password, Phone, CreditCard)\n";
echo "  ✓ Nested data validation\n";
echo "  ✓ Complex business logic validation\n\n";

echo "Next: See validation_request_example.php for HTTP Request integration!\n";
