# Horizon Framework - Validation Guide

**Philosophy: "Less Magic. More Understanding."**

The Horizon Validation system provides clear, explicit validation with excellent error messages and developer-friendly features.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Basic Usage](#basic-usage)
3. [Available Validation Rules](#available-validation-rules)
4. [Custom Error Messages](#custom-error-messages)
5. [Custom Attribute Names](#custom-attribute-names)
6. [Working with Errors](#working-with-errors)
7. [Request Validation](#request-validation)
8. [File Upload Validation](#file-upload-validation)
9. [Database Validation](#database-validation)
10. [Conditional Validation](#conditional-validation)
11. [Custom Validation Rules](#custom-validation-rules)
12. [After Validation Hooks](#after-validation-hooks)
13. [Nested Data Validation](#nested-data-validation)
14. [Best Practices](#best-practices)
15. [API Reference](#api-reference)

---

## Introduction

The Horizon validation system helps you validate incoming data with:

- **50+ built-in validation rules**
- **Clear, readable error messages**
- **File upload and image validation**
- **Database validation (unique/exists)**
- **Custom validation rules**
- **Conditional validation**
- **Seamless HTTP Request integration**

### Core Components

- `Validator` - Main validation engine
- `MessageBag` - Error message storage
- `ValidationException` - Thrown when validation fails
- `ValidationRule` - Interface for custom rules
- `Factory` - Convenient validator creation

---

## Basic Usage

### Simple Validation

```php
use Horizon\Validation\Validator;

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

if ($validator->fails()) {
    $errors = $validator->errors();
    // Handle errors
}

// Get validated data
$validated = $validator->validated();
```

### Array Rule Syntax

Rules can be defined as arrays instead of pipe-delimited strings:

```php
$rules = [
    'name' => ['required', 'string', 'min:3', 'max:50'],
    'email' => ['required', 'email'],
];
```

### Using Validation Factory

```php
use Horizon\Validation\Factory;
use Horizon\Validation\ValidationException;

$factory = new Factory();

try {
    $validated = $factory->validate($data, $rules);
    // Validation passed
} catch (ValidationException $e) {
    $errors = $e->errors();
    // Handle errors
}
```

---

## Available Validation Rules

### Type Validation

#### required
Field must be present and not empty.

```php
'name' => 'required'
```

#### nullable
Field can be null. When null, other rules are skipped.

```php
'bio' => 'nullable|string|min:10'
```

#### present
Field must be present in data (can be empty).

```php
'description' => 'present'
```

#### filled
Field must have a value if present.

```php
'comment' => 'filled|string'
```

#### string
Must be a string value.

```php
'name' => 'string'
```

#### numeric
Must be numeric (int or float).

```php
'price' => 'numeric'
```

#### integer
Must be an integer.

```php
'quantity' => 'integer'
```

#### boolean
Must be boolean (true, false, 1, 0, "1", "0").

```php
'active' => 'boolean'
```

#### array
Must be an array.

```php
'tags' => 'array'
```

### String Validation

#### alpha
Only alphabetic characters.

```php
'name' => 'alpha'
```

#### alpha_num
Only alphanumeric characters.

```php
'username' => 'alpha_num'
```

#### alpha_dash
Alphanumeric with dashes and underscores.

```php
'slug' => 'alpha_dash'
```

#### lowercase
Must be lowercase.

```php
'email' => 'lowercase'
```

#### uppercase
Must be uppercase.

```php
'code' => 'uppercase'
```

#### starts_with:value1,value2
Must start with one of the given values.

```php
'protocol' => 'starts_with:http,https'
```

#### ends_with:value1,value2
Must end with one of the given values.

```php
'filename' => 'ends_with:.jpg,.png'
```

### Size Validation

Works with strings (length), numbers (value), arrays (count).

#### min:value
Minimum size.

```php
'name' => 'min:3'           // Min 3 characters
'age' => 'numeric|min:18'   // Min value 18
'tags' => 'array|min:1'     // Min 1 item
```

#### max:value
Maximum size.

```php
'name' => 'max:50'
'age' => 'numeric|max:120'
'tags' => 'array|max:10'
```

#### between:min,max
Between min and max size.

```php
'age' => 'between:18,65'
'username' => 'between:3,20'
```

#### size:value
Exact size.

```php
'zip_code' => 'size:5'
'quantity' => 'numeric|size:10'
```

### Format Validation

#### email
Must be a valid email address.

```php
'email' => 'email'
```

#### url
Must be a valid URL.

```php
'website' => 'url'
```

#### ip
Must be a valid IP address.

```php
'address' => 'ip'
```

#### ipv4
Must be a valid IPv4 address.

```php
'address' => 'ipv4'
```

#### ipv6
Must be a valid IPv6 address.

```php
'address' => 'ipv6'
```

#### uuid
Must be a valid UUID.

```php
'id' => 'uuid'
```

#### json
Must be a valid JSON string.

```php
'config' => 'json'
```

#### regex:pattern
Must match regular expression.

```php
'code' => 'regex:/^[A-Z]{3}-\d{4}$/'
```

### Comparison Validation

#### same:field
Must match another field.

```php
'password_confirmation' => 'same:password'
```

#### confirmed
Must have a matching `{field}_confirmation` field.

```php
'password' => 'confirmed'
// Looks for 'password_confirmation'
```

#### different:field
Must be different from another field.

```php
'new_password' => 'different:old_password'
```

#### in:value1,value2,...
Must be one of the given values.

```php
'role' => 'in:user,admin,moderator'
```

#### not_in:value1,value2,...
Must not be one of the given values.

```php
'username' => 'not_in:admin,root,system'
```

### Date Validation

#### date
Must be a valid date.

```php
'birthday' => 'date'
```

#### date_format:format
Must match date format.

```php
'birthday' => 'date_format:Y-m-d'
```

#### before:date
Must be before given date.

```php
'start_date' => 'before:2024-12-31'
```

#### after:date
Must be after given date.

```php
'end_date' => 'after:2024-01-01'
```

#### timezone
Must be a valid timezone.

```php
'timezone' => 'timezone'
```

### Boolean Validation

#### accepted
Must be "yes", "on", "1", 1, true, or "true".

```php
'terms' => 'accepted'
```

#### declined
Must be "no", "off", "0", 0, false, or "false".

```php
'marketing' => 'declined'
```

### Numeric Validation

#### multiple_of:value
Must be a multiple of value.

```php
'quantity' => 'multiple_of:5'
```

---

## Custom Error Messages

### Field-Specific Messages

```php
$messages = [
    'email.required' => 'We need your email address!',
    'email.email' => 'Please provide a valid email.',
    'password.min' => 'Password must be at least :0 characters.',
];

$validator = new Validator($data, $rules, $messages);
```

### Rule-Specific Messages

```php
$messages = [
    'required' => 'The :attribute field is required.',
    'email' => 'Please enter a valid :attribute.',
];
```

### Message Placeholders

- `:attribute` - The field name
- `:0`, `:1`, `:2` - Rule parameters

---

## Custom Attribute Names

Make error messages more user-friendly:

```php
$customAttributes = [
    'user_email' => 'email address',
    'user_age' => 'age',
];

$validator = new Validator($data, $rules, [], $customAttributes);

// Error: "The email address must be valid."
// Instead of: "The user_email must be valid."
```

---

## Working with Errors

### MessageBag Methods

```php
$errors = $validator->errors();

// Check if there are any errors
$errors->any();        // true if errors exist
$errors->isEmpty();    // true if no errors

// Get errors
$errors->all();        // All error messages as array
$errors->first();      // First error message
$errors->first('email'); // First error for field
$errors->get('email'); // All errors for field

// Check for specific field
$errors->has('email'); // true if field has errors

// Count errors
$errors->count();      // Total error count
$errors->countFields(); // Number of fields with errors

// Get fields with errors
$errors->keys();       // Array of field names

// Convert to array/JSON
$errors->toArray();
$errors->toJson();
```

### Validation Exception

```php
try {
    $validated = $validator->validated();
} catch (ValidationException $e) {
    $errors = $e->errors();
    $data = $e->getData();
    $firstError = $e->getFirstError();
    
    // For JSON APIs
    return response()->json($e->toArray(), 422);
}
```

---

## Request Validation

### validate() Method

Throws ValidationException on failure:

```php
use Horizon\Http\Request;

public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:200',
        'content' => 'required|string',
        'status' => 'required|in:draft,published',
    ]);
    
    // Use validated data
    Post::create($validated);
}
```

### With Custom Messages

```php
$validated = $request->validate(
    [
        'email' => 'required|email',
        'password' => 'required|min:8',
    ],
    [
        'email.required' => 'Please provide your email.',
        'password.min' => 'Password must be at least 8 characters.',
    ]
);
```

### validated() Method

Returns null instead of throwing exception:

```php
$validated = $request->validated($rules);

if ($validated === null) {
    // Validation failed
    $errors = $request->getValidationErrors($rules);
}
```

### Checking Validation

```php
if ($request->passesValidation($rules)) {
    // Validation passed
}

if ($request->failsValidation($rules)) {
    // Validation failed
    $errors = $request->getValidationErrors($rules);
}
```

### Get Validator Instance

```php
$validator = $request->getValidator($rules);

$validator->sometimes(...);
$validator->after(...);

if ($validator->fails()) {
    // Handle errors
}
```

---

## File Upload Validation

### Basic File Validation

```php
'document' => 'required|file'
```

### Image Validation

```php
'avatar' => 'required|image'
```

### MIME Type Validation

#### Using Extensions (Auto-converted to MIME types)

```php
'document' => 'file|mimes:pdf,doc,docx'
'image' => 'image|mimes:jpg,png,gif'
```

#### Exact MIME Types

```php
'file' => 'mimetypes:application/pdf,image/jpeg'
```

### File Extension Validation

```php
'archive' => 'file|extensions:zip,tar,gz'
```

### File Size Validation

Size in kilobytes:

```php
'document' => 'file|max_file_size:2048'  // Max 2MB
'image' => 'image|max_file_size:5120'    // Max 5MB
```

### Image Dimension Validation

```php
'avatar' => 'image|dimensions:min_width=100,min_height=100,max_width=500,max_height=500'

// Exact dimensions
'banner' => 'image|dimensions:width=1920,height=1080'

// Aspect ratio
'thumbnail' => 'image|dimensions:ratio=16/9'
// Or
'thumbnail' => 'image|dimensions:ratio=1.777'
```

### Complete File Upload Example

```php
$validated = $request->validate([
    'profile_image' => [
        'required',
        'image',
        'mimes:jpg,png',
        'max_file_size:2048',
        'dimensions:min_width=200,max_width=2000,ratio=1',
    ],
    'resume' => [
        'required',
        'file',
        'mimes:pdf,doc,docx',
        'max_file_size:5120',
    ],
]);

$image = $validated['profile_image'];
$resume = $validated['resume'];

// Store files
$imagePath = $image->store('profiles');
$resumePath = $resume->store('resumes');
```

---

## Database Validation

### unique Rule

Check if value is unique in database table.

**Format:** `unique:table,column,except,idColumn`

```php
// Simple uniqueness check
'email' => 'unique:users,email'

// Exclude specific ID (useful for updates)
'email' => 'unique:users,email,123'

// Custom ID column
'email' => 'unique:users,email,123,user_id'
```

**Example: User Registration**

```php
$validator = $request->getValidator([
    'email' => 'required|email|unique:users,email',
    'username' => 'required|unique:users,username',
]);

// Set database connection
$validator->setDatabase($db);

$validated = $validator->validated();
```

**Example: User Update**

```php
$userId = 123;

$validated = $request->validate([
    'email' => "required|email|unique:users,email,{$userId}",
]);
```

### exists Rule

Check if value exists in database table.

**Format:** `exists:table,column`

```php
// Check if category exists
'category_id' => 'required|exists:categories,id'

// Check if user exists
'user_id' => 'required|exists:users,id'
```

**Complete Example:**

```php
$validator = $request->getValidator([
    'email' => 'required|email|unique:users,email',
    'category_id' => 'required|exists:categories,id',
    'author_id' => 'required|exists:users,id',
]);

// Inject database connection
$validator->setDatabase($db);

try {
    $validated = $validator->validated();
} catch (ValidationException $e) {
    // Handle validation errors
}
```

---

## Conditional Validation

### sometimes() Method

Add rules conditionally based on a condition:

```php
$validator = new Validator($data, $baseRules);

$validator->sometimes(
    fn($data) => isset($data['user_type']) && $data['user_type'] === 'business',
    [
        'company_name' => 'required|string',
        'tax_id' => 'required|string',
    ]
);
```

### when() Method

Add rules only if field is present:

```php
$validator->when('shipping_address', [
    'shipping_city' => 'required|string',
    'shipping_zip' => 'required|string',
]);
```

### unless() Method

Add rules only if field is absent or empty:

```php
$validator->unless('same_as_billing', [
    'shipping_address' => 'required|string',
    'shipping_city' => 'required|string',
]);
```

### Complex Conditional Example

```php
$validator = $request->getValidator([
    'product_type' => 'required|in:physical,digital',
    'price' => 'required|numeric',
]);

// Physical products need shipping
$validator->when('product_type', [
    'weight' => 'required|numeric',
    'dimensions' => 'required|string',
    'shipping_class' => 'required|in:standard,express,overnight',
], [
    'weight.required' => 'Physical products require weight.',
]);

// Digital products need license info
$validator->sometimes(
    fn($data) => $data['product_type'] === 'digital',
    [
        'license_type' => 'required|in:single,multi,unlimited',
        'download_limit' => 'required|integer|min:1',
    ]
);

$validated = $validator->validated();
```

---

## Custom Validation Rules

### Using Closure (Simple)

```php
use Horizon\Validation\Rules\CustomRule;

$evenNumber = new CustomRule(
    fn($value) => is_numeric($value) && $value % 2 === 0,
    'The :attribute must be an even number',
    'even'
);

Validator::extend('even', $evenNumber);

$validator = new Validator($data, [
    'quantity' => 'required|numeric|even',
]);
```

### Creating Rule Classes

```php
use Horizon\Validation\ValidationRule;
use Horizon\Validation\Validator;

class UppercaseRule implements ValidationRule
{
    public function passes(string $attribute, mixed $value, array $parameters, Validator $validator): bool
    {
        return is_string($value) && $value === strtoupper($value);
    }

    public function message(string $attribute, array $parameters): string
    {
        return "The {$attribute} must be in uppercase.";
    }

    public function getName(): string
    {
        return 'uppercase_custom';
    }
}

// Register the rule
Validator::extend('uppercase_custom', new UppercaseRule());

// Use it
$validator = new Validator($data, [
    'code' => 'required|uppercase_custom',
]);
```

### Pre-built Custom Rules

#### Password Rule

```php
use Horizon\Validation\Rules\PasswordRule;

$passwordRule = (new PasswordRule())
    ->min(12)
    ->requireUppercase()
    ->requireLowercase()
    ->requireNumbers()
    ->requireSpecialCharacters();

Validator::extend('strong_password', $passwordRule);

$validator = new Validator($data, [
    'password' => 'required|strong_password',
]);
```

#### Phone Number Rule

```php
use Horizon\Validation\Rules\PhoneRule;

$phoneRule = new PhoneRule('US');
Validator::extend('phone_us', $phoneRule);

$validator = new Validator($data, [
    'phone' => 'required|phone_us',
]);

// International format
$intlRule = new PhoneRule('international');
Validator::extend('phone', $intlRule);
```

#### Credit Card Rule

```php
use Horizon\Validation\Rules\CreditCardRule;

$cardRule = new CreditCardRule(['visa', 'mastercard']);
Validator::extend('credit_card', $cardRule);

$validator = new Validator($data, [
    'card_number' => 'required|credit_card',
]);
```

---

## After Validation Hooks

Add custom validation logic after standard validation:

```php
$validator = new Validator($data, $rules);

$validator->after(function($validator) {
    $data = $validator->getData();
    
    // Custom business logic
    if (isset($data['start_date']) && isset($data['end_date'])) {
        if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
            $validator->addError('end_date', 'End date must be after start date.');
        }
    }
    
    // Check against external service
    if (isset($data['coupon_code'])) {
        if (!$this->couponService->isValid($data['coupon_code'])) {
            $validator->addError('coupon_code', 'Invalid coupon code.');
        }
    }
});

if ($validator->fails()) {
    // Handle errors including custom ones
}
```

---

## Nested Data Validation

Validate nested array data using dot notation:

```php
$data = [
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

$rules = [
    'user.name' => 'required|string|min:2',
    'user.email' => 'required|email',
    'user.address.street' => 'required|string',
    'user.address.city' => 'required|string',
    'user.address.zip' => 'required|string|size:5',
];

$validator = new Validator($data, $rules);
$validated = $validator->validated();
```

---

## Best Practices

### 1. Validate Early

Always validate at the entry point (controller/route handler):

```php
public function store(Request $request)
{
    $validated = $request->validate($rules);
    
    // Now you can trust the data
    Post::create($validated);
}
```

### 2. Use Form Request Classes

Create dedicated validation classes:

```php
class StorePostRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for your post.',
            'category_id.exists' => 'The selected category does not exist.',
        ];
    }
}
```

### 3. Keep Rules Simple

Break complex validation into multiple steps:

```php
// Step 1: Basic validation
$validator = new Validator($data, $basicRules);
if ($validator->fails()) {
    return $errors;
}

// Step 2: Business logic validation
$validator->after(function($validator) {
    // Complex checks
});
```

### 4. Use Custom Messages for User-Facing Errors

```php
$messages = [
    'email.unique' => 'This email is already registered. Try logging in instead.',
    'password.min' => 'Your password should be at least 8 characters for security.',
];
```

### 5. Group Related Rules

```php
// Email rules
$emailRules = ['required', 'email', 'lowercase', 'unique:users,email'];

// Password rules  
$passwordRules = ['required', 'string', 'min:8', 'confirmed'];

$rules = [
    'email' => $emailRules,
    'password' => $passwordRules,
];
```

### 6. Use Nullable Appropriately

```php
// Optional field with validation
'bio' => 'nullable|string|min:10|max:500'

// Don't use required with nullable
'bio' => 'nullable|required'  // ❌ Contradictory
```

### 7. Security Considerations

- Always validate file uploads
- Use `unique` rule for user-facing identifiers
- Validate file MIME types, not just extensions
- Set maximum file sizes
- Use `exists` rule for foreign keys
- Sanitize input in after hooks if needed

### 8. Performance Tips

- Cache validation instances if reusing
- Use `sometimes()` to skip expensive rules
- Set database connection once if doing multiple validations
- Use `validated()` to get only validated fields

---

## API Reference

### Validator Class

#### Constructor

```php
new Validator(array $data, array $rules, array $messages = [], array $customAttributes = [])
```

#### Methods

```php
// Validation
$validator->validate(): bool
$validator->passes(): bool
$validator->fails(): bool
$validator->validated(): array  // Throws ValidationException

// Errors
$validator->errors(): MessageBag

// Conditional Rules
$validator->sometimes(bool|callable $condition, array $rules, array $messages = []): self
$validator->when(string $field, array $rules, array $messages = []): self
$validator->unless(string $field, array $rules, array $messages = []): self

// Custom Validation
$validator->after(callable $callback): self
$validator->addError(string $attribute, string $message): self

// Database
$validator->setDatabase(mixed $db): self
$validator->getDatabase(): mixed

// Data
$validator->getData(): array

// Static
Validator::extend(string $name, ValidationRule $rule): void
```

### MessageBag Class

```php
// Check errors
$errors->any(): bool
$errors->isEmpty(): bool
$errors->has(string $field): bool

// Get errors
$errors->all(): array
$errors->first(?string $field = null): ?string
$errors->get(string $field): array
$errors->toArray(): array
$errors->keys(): array

// Count
$errors->count(): int
$errors->countFields(): int

// Modify
$errors->add(string $field, string $message): self
$errors->merge(MessageBag $bag): self
```

### Request Methods

```php
// Validate and throw exception
$request->validate(array $rules, array $messages = [], array $customAttributes = []): array

// Get validator instance
$request->getValidator(array $rules, array $messages = [], array $customAttributes = []): Validator

// Validate without exception
$request->validated(array $rules, array $messages = [], array $customAttributes = []): ?array

// Check validation
$request->passesValidation(array $rules, array $messages = [], array $customAttributes = []): bool
$request->failsValidation(array $rules, array $messages = [], array $customAttributes = []): bool

// Get errors
$request->getValidationErrors(array $rules, array $messages = [], array $customAttributes = []): MessageBag
```

### ValidationException Class

```php
$exception->errors(): MessageBag
$exception->getData(): array
$exception->getErrors(): array
$exception->getFirstError(): ?string
$exception->toArray(): array
$exception->toJson(): string
```

---

## Complete Examples

### User Registration

```php
public function register(Request $request)
{
    $validator = $request->getValidator([
        'name' => 'required|string|min:2|max:50',
        'email' => 'required|email|lowercase|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'age' => 'required|integer|between:18,120',
        'terms' => 'required|accepted',
    ]);
    
    // Set database for unique check
    $validator->setDatabase($this->db);
    
    // Add custom validation
    $validator->after(function($validator) {
        $data = $validator->getData();
        
        // Check password strength
        if (isset($data['password'])) {
            if (!preg_match('/[A-Z]/', $data['password'])) {
                $validator->addError('password', 'Password must contain at least one uppercase letter.');
            }
        }
    });
    
    try {
        $validated = $validator->validated();
        
        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => password_hash($validated['password'], PASSWORD_DEFAULT),
            'age' => $validated['age'],
        ]);
        
        return response()->json(['user' => $user], 201);
        
    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()->toArray(),
        ], 422);
    }
}
```

### File Upload with Processing

```php
public function uploadAvatar(Request $request)
{
    $validated = $request->validate([
        'avatar' => [
            'required',
            'image',
            'mimes:jpg,png',
            'max_file_size:2048',
            'dimensions:min_width=200,max_width=2000,ratio=1',
        ],
    ], [
        'avatar.dimensions' => 'Avatar must be square and between 200x200 and 2000x2000 pixels.',
    ]);
    
    $avatar = $validated['avatar'];
    
    // Store original
    $path = $avatar->store('avatars', ['disk' => 'public']);
    
    // Create thumbnail
    $dimensions = $avatar->getImageDimensions();
    
    return response()->json([
        'path' => $path,
        'size' => $avatar->getSize(),
        'dimensions' => $dimensions,
    ]);
}
```

---

## Troubleshooting

### Common Issues

#### 1. "Database connection required for unique validation"

**Solution:** Set database connection on validator:

```php
$validator->setDatabase($db);
// Or
$request->setAttribute('db', $db);
```

#### 2. Validation always passes for optional fields

**Solution:** Use `nullable` rule:

```php
'bio' => 'nullable|string|min:10'
```

#### 3. File validation not working

**Solution:** Ensure file is UploadedFile instance:

```php
$file = $request->file('document');
if ($file instanceof UploadedFile) {
    // Validation will work
}
```

#### 4. Custom rule not found

**Solution:** Register rule before use:

```php
Validator::extend('custom_rule', new CustomRule(...));
```

#### 5. Nested validation not working

**Solution:** Use dot notation:

```php
'user.address.city' => 'required'
```

---

## Summary

The Horizon validation system provides:

✅ **50+ built-in rules** for common validation needs  
✅ **Clear error messages** that help users understand issues  
✅ **File and image validation** with dimensions and size checks  
✅ **Database validation** with unique and exists rules  
✅ **Custom rules** for application-specific validation  
✅ **Conditional validation** for complex scenarios  
✅ **Request integration** for seamless web application validation  
✅ **Nested data validation** with dot notation  
✅ **Excellent developer experience** with readable, maintainable code  

For more examples, see:
- `examples/validation_basic_example.php`
- `examples/validation_advanced_example.php`
- `examples/validation_request_example.php`

---

**Horizon Framework - "Less Magic. More Understanding."**
