<?php

/**
 * Horizon Framework - Request Validation Example
 * 
 * This example demonstrates HTTP Request validation including:
 * - Request::validate() method
 * - File upload validation
 * - Image validation with dimensions
 * - API request validation
 * - Form validation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Horizon\Http\Request;
use Horizon\Http\UploadedFile;
use Horizon\Validation\ValidationException;

echo "========================================================\n";
echo "HORIZON FRAMEWORK - REQUEST VALIDATION EXAMPLE\n";
echo "========================================================\n\n";

// ============================================================================
// Example 1: Basic Request Validation
// ============================================================================

echo "📋 Example 1: Basic Request Validation\n";
echo "=======================================\n\n";

$request = Request::create('/register', 'POST', [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => 'SecureP@ss123',
    'password_confirmation' => 'SecureP@ss123',
    'age' => 25,
    'terms' => true,
]);

try {
    $validated = $request->validate([
        'username' => 'required|alpha_dash|min:3|max:20',
        'email' => 'required|email|lowercase',
        'password' => 'required|min:8|confirmed',
        'age' => 'required|integer|between:18,120',
        'terms' => 'required|accepted',
    ]);
    
    echo "✅ Registration validation passed!\n";
    echo "Validated user data:\n";
    foreach ($validated as $key => $value) {
        $display = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        echo "  {$key}: {$display}\n";
    }
} catch (ValidationException $e) {
    echo "❌ Registration validation failed!\n";
    echo json_encode($e->errors()->toArray(), JSON_PRETTY_PRINT) . "\n";
}

echo "\n";

// ============================================================================
// Example 2: Invalid Request
// ============================================================================

echo "📋 Example 2: Invalid Request Handling\n";
echo "=======================================\n\n";

$invalidRequest = Request::create('/register', 'POST', [
    'username' => 'ab', // Too short
    'email' => 'not-an-email',
    'password' => 'short',
    'age' => 15, // Too young
]);

try {
    $invalidRequest->validate([
        'username' => 'required|alpha_dash|min:3|max:20',
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
        'age' => 'required|integer|between:18,120',
    ]);
} catch (ValidationException $e) {
    echo "❌ Validation failed as expected!\n\n";
    echo "Error details:\n";
    $errors = $e->errors()->toArray();
    foreach ($errors as $field => $messages) {
        echo "  {$field}:\n";
        foreach ($messages as $message) {
            echo "    - {$message}\n";
        }
    }
}

echo "\n";

// ============================================================================
// Example 3: Custom Messages in Request
// ============================================================================

echo "📋 Example 3: Custom Messages\n";
echo "==============================\n\n";

$request = Request::create('/profile', 'POST', [
    'display_name' => 'J',
    'bio' => '',
]);

try {
    $request->validate(
        [
            'display_name' => 'required|min:3',
            'bio' => 'required|min:10',
        ],
        [
            'display_name.required' => 'Please tell us what to call you!',
            'display_name.min' => 'Your display name needs to be at least 3 characters.',
            'bio.required' => 'We\'d love to know more about you!',
            'bio.min' => 'Please write at least 10 characters in your bio.',
        ]
    );
} catch (ValidationException $e) {
    echo "Custom messages:\n";
    foreach ($e->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// ============================================================================
// Example 4: passesValidation() and failsValidation()
// ============================================================================

echo "📋 Example 4: Checking Validation Without Exception\n";
echo "====================================================\n\n";

$requests = [
    Request::create('/api/user', 'POST', ['email' => 'valid@example.com']),
    Request::create('/api/user', 'POST', ['email' => 'invalid-email']),
];

foreach ($requests as $i => $req) {
    $email = $req->input('email');
    $rules = ['email' => 'required|email'];
    
    if ($req->passesValidation($rules)) {
        echo "  Request " . ($i + 1) . " ({$email}): ✅ Valid\n";
    } elseif ($req->failsValidation($rules)) {
        echo "  Request " . ($i + 1) . " ({$email}): ❌ Invalid\n";
        $errors = $req->getValidationErrors($rules);
        echo "    Error: " . $errors->first() . "\n";
    }
}

echo "\n";

// ============================================================================
// Example 5: API Request Validation
// ============================================================================

echo "📋 Example 5: API Request Validation\n";
echo "=====================================\n\n";

$apiRequest = Request::create('/api/posts', 'POST', [], [], [], [
    'HTTP_CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode([
    'title' => 'My Blog Post',
    'content' => 'This is the content of my blog post.',
    'status' => 'published',
    'tags' => ['php', 'framework', 'web'],
]));

try {
    $validated = $apiRequest->validate([
        'title' => 'required|string|min:5|max:200',
        'content' => 'required|string|min:20',
        'status' => 'required|in:draft,published,archived',
        'tags' => 'nullable|array',
    ]);
    
    echo "✅ API request validation passed!\n";
    echo "Post data:\n";
    echo "  Title: {$validated['title']}\n";
    echo "  Status: {$validated['status']}\n";
    echo "  Tags: " . implode(', ', $validated['tags'] ?? []) . "\n";
} catch (ValidationException $e) {
    echo "❌ API validation failed: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Example 6: File Upload Validation (Simulated)
// ============================================================================

echo "📋 Example 6: File Upload Validation\n";
echo "=====================================\n\n";

// Create a simulated file upload
$tempFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($tempFile, 'Test file content');

$fileData = [
    'tmp_name' => $tempFile,
    'name' => 'document.pdf',
    'type' => 'application/pdf',
    'error' => UPLOAD_ERR_OK,
    'size' => 1024 * 50, // 50KB
];

$request = Request::create('/upload', 'POST', [], [], [
    'document' => $fileData,
]);

try {
    $validated = $request->validate([
        'document' => 'required|file|mimes:pdf,doc,docx|max_file_size:2048', // Max 2MB
    ]);
    
    echo "✅ File upload validation passed!\n";
    $file = $validated['document'];
    if ($file instanceof UploadedFile) {
        echo "  Filename: {$file->getClientOriginalName()}\n";
        echo "  Size: " . round($file->getSize() / 1024, 2) . " KB\n";
        echo "  Type: {$file->getMimeType()}\n";
    }
} catch (ValidationException $e) {
    echo "❌ File validation failed!\n";
    foreach ($e->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
} finally {
    @unlink($tempFile);
}

echo "\n";

// ============================================================================
// Example 7: Image Upload with Dimensions
// ============================================================================

echo "📋 Example 7: Image Validation with Dimensions\n";
echo "===============================================\n\n";

// Create a test image
$testImage = tempnam(sys_get_temp_dir(), 'img') . '.png';
$img = imagecreate(800, 600);
imagepng($img, $testImage);
imagedestroy($img);

$imageData = [
    'tmp_name' => $testImage,
    'name' => 'avatar.png',
    'type' => 'image/png',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($testImage),
];

$request = Request::create('/avatar', 'POST', [], [], [
    'avatar' => $imageData,
]);

try {
    $validated = $request->validate([
        'avatar' => 'required|image|mimes:jpg,png|max_file_size:5120|dimensions:min_width=100,max_width=1920,min_height=100,max_height=1080',
    ]);
    
    echo "✅ Image validation passed!\n";
    $image = $validated['avatar'];
    if ($image instanceof UploadedFile) {
        echo "  Image: {$image->getClientOriginalName()}\n";
        $dimensions = $image->getImageDimensions();
        if ($dimensions) {
            echo "  Dimensions: {$dimensions[0]}x{$dimensions[1]}px\n";
        }
    }
} catch (ValidationException $e) {
    echo "❌ Image validation failed!\n";
    foreach ($e->errors()->all() as $error) {
        echo "  - {$error}\n";
    }
} finally {
    @unlink($testImage);
}

echo "\n";

// ============================================================================
// Example 8: Multiple File Uploads
// ============================================================================

echo "📋 Example 8: Multiple File Uploads\n";
echo "====================================\n\n";

$files = [];
for ($i = 1; $i <= 3; $i++) {
    $temp = tempnam(sys_get_temp_dir(), "doc{$i}");
    file_put_contents($temp, "Document {$i} content");
    
    $files["documents[{$i}]"] = [
        'tmp_name' => $temp,
        'name' => "document{$i}.txt",
        'type' => 'text/plain',
        'error' => UPLOAD_ERR_OK,
        'size' => strlen("Document {$i} content"),
    ];
}

$request = Request::create('/documents', 'POST', [], [], $files);

echo "Uploaded files: " . count($request->allFiles()) . "\n";
foreach ($request->allFiles() as $key => $file) {
    if ($file instanceof UploadedFile) {
        echo "  {$key}: {$file->getClientOriginalName()} (" . $file->getSize() . " bytes)\n";
    }
}

// Cleanup
foreach ($files as $fileData) {
    @unlink($fileData['tmp_name']);
}

echo "\n";

// ============================================================================
// Example 9: Validated() Method
// ============================================================================

echo "📋 Example 9: validated() Method (No Exception)\n";
echo "================================================\n\n";

$request = Request::create('/api/user', 'GET', [
    'name' => 'John',
    'age' => 25,
    'extra_field' => 'ignored',
]);

$validated = $request->validated([
    'name' => 'required|string',
    'age' => 'required|integer',
]);

if ($validated !== null) {
    echo "✅ Validation passed!\n";
    echo "Only validated fields returned:\n";
    foreach ($validated as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    echo "\nNotice: 'extra_field' was not included (not in rules)\n";
} else {
    echo "❌ Validation failed!\n";
}

echo "\n";

// ============================================================================
// Summary
// ============================================================================

echo "========================================================\n";
echo "✅ REQUEST VALIDATION EXAMPLES COMPLETE!\n";
echo "========================================================\n\n";

echo "This example demonstrated:\n";
echo "  ✓ Request::validate() with exception handling\n";
echo "  ✓ Custom error messages in requests\n";
echo "  ✓ passesValidation() and failsValidation() methods\n";
echo "  ✓ validated() method (no exception)\n";
echo "  ✓ API request validation with JSON\n";
echo "  ✓ File upload validation\n";
echo "  ✓ Image validation with dimensions\n";
echo "  ✓ Multiple file uploads\n\n";

echo "The validation system is now fully integrated with HTTP requests!\n";
