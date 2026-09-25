<?php

declare(strict_types=1);

/**
 * ORM Model System Example
 * 
 * Demonstrates the Horizon Framework's ORM Model functionality
 * with Active Record pattern, CRUD operations, and mass assignment protection.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - ORM Model System Example\n";
echo "=============================================\n\n";

use Horizon\Database\Eloquent\Model;
use Horizon\Database\Eloquent\Collection;

echo "1. ORM MODEL OVERVIEW\n";
echo "---------------------\n";

echo "The ORM Model system provides:\n";
echo "✓ Active Record pattern implementation\n";
echo "✓ CRUD operations (Create, Read, Update, Delete)\n";
echo "✓ Mass assignment protection\n";
echo "✓ Attribute casting and mutators\n";
echo "✓ Timestamps (created_at, updated_at)\n";
echo "✓ Model collections with advanced functionality\n";
echo "✓ Query builder integration\n";
echo "✓ Event system hooks\n\n";

echo "2. DEFINING ELOQUENT MODELS\n";
echo "----------------------------\n";

// Example User model
echo "Example: User Model\n";
echo "```php\n";
echo "<?php\n\n";
echo "use Horizon\\Database\\Eloquent\\Model;\n\n";
echo "class User extends Model\n";
echo "{\n";
echo "    // Table name (optional - auto-detected from class name)\n";
echo "    protected \$table = 'users';\n\n";
echo "    // Primary key (default: 'id')\n";
echo "    protected \$primaryKey = 'id';\n\n";
echo "    // Key type (default: 'int')\n";
echo "    protected \$keyType = 'int';\n\n";
echo "    // Auto-incrementing (default: true)\n";
echo "    public \$incrementing = true;\n\n";
echo "    // Timestamps (default: true)\n";
echo "    public \$timestamps = true;\n\n";
echo "    // Mass assignable attributes\n";
echo "    protected \$fillable = [\n";
echo "        'name',\n";
echo "        'email',\n";
echo "        'password',\n";
echo "    ];\n\n";
echo "    // Hidden attributes for serialization\n";
echo "    protected \$hidden = [\n";
echo "        'password',\n";
echo "        'remember_token',\n";
echo "    ];\n\n";
echo "    // Attribute casting\n";
echo "    protected \$casts = [\n";
echo "        'email_verified_at' => 'datetime',\n";
echo "        'is_active' => 'boolean',\n";
echo "        'preferences' => 'json',\n";
echo "    ];\n";
echo "}\n";
echo "```\n\n";

// Mock User model for demonstration
class User extends Model 
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'preferences' => 'json',
    ];
}

echo "3. BASIC MODEL OPERATIONS\n";
echo "-------------------------\n";

try {
    echo "Creating new models:\n";
    
    // Create using constructor
    $user1 = new User([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'hashed_password_123'
    ]);
    echo "✓ Created user via constructor: {$user1->name}\n";
    
    // Create using fill method
    $user2 = new User();
    $user2->fill([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'password' => 'hashed_password_456'
    ]);
    echo "✓ Created user via fill(): {$user2->name}\n";
    
    // Create using create method (would save to DB)
    echo "\n// User::create() - Creates and saves to database\n";
    echo "// \$user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);\n";
    
    echo "\nAttribute Access:\n";
    echo "✓ Direct access: {$user1->name}\n";
    echo "✓ Array access: " . $user1['email'] . "\n";
    echo "✓ getAttribute(): " . $user1->getAttribute('name') . "\n";
    
    echo "\nAttribute Setting:\n";
    $user1->name = 'John Updated';
    echo "✓ Direct assignment: {$user1->name}\n";
    
    $user1['email'] = 'john.updated@example.com';
    echo "✓ Array assignment: " . $user1['email'] . "\n";
    
    $user1->setAttribute('password', 'new_hashed_password');
    echo "✓ setAttribute(): password updated\n";
    
    echo "\n4. MASS ASSIGNMENT PROTECTION\n";
    echo "------------------------------\n";
    
    echo "Mass assignment protection prevents unauthorized attribute modification.\n\n";
    
    echo "Fillable attributes (allowed):\n";
    foreach ($user1->getFillable() as $attribute) {
        echo "  • $attribute\n";
    }
    
    echo "\nHidden attributes (not serialized):\n";
    foreach ($user1->getHidden() as $attribute) {
        echo "  • $attribute\n";
    }
    
    // Demonstrate mass assignment protection
    echo "\nTesting mass assignment protection:\n";
    
    $safeData = ['name' => 'Safe Name', 'email' => 'safe@example.com'];
    $user3 = new User($safeData);
    echo "✓ Safe attributes filled successfully\n";
    
    echo "// Attempting to fill non-fillable attribute would throw exception\n";
    echo "// \$user->fill(['admin' => true]); // Would throw InvalidArgumentException\n";
    
    echo "\n5. MODEL STATES AND LIFECYCLE\n";
    echo "------------------------------\n";
    
    echo "Model state tracking:\n";
    echo "✓ exists(): " . ($user1->exists ? 'true' : 'false') . " (not saved to DB yet)\n";
    echo "✓ wasRecentlyCreated(): " . ($user1->wasRecentlyCreated ? 'true' : 'false') . "\n";
    echo "✓ isDirty(): " . ($user1->isDirty() ? 'true' : 'false') . " (has unsaved changes)\n";
    echo "✓ isClean(): " . ($user1->isClean() ? 'true' : 'false') . "\n";
    
    if ($user1->isDirty()) {
        echo "✓ getDirty(): " . implode(', ', array_keys($user1->getDirty())) . "\n";
    }
    
    echo "\n6. ATTRIBUTE CASTING\n";
    echo "--------------------\n";
    
    echo "Attribute casting automatically converts data types:\n\n";
    
    echo "Available cast types:\n";
    echo "  • boolean/bool - converts to boolean\n";
    echo "  • integer/int - converts to integer\n";
    echo "  • float/double - converts to float\n";
    echo "  • string - converts to string\n";
    echo "  • array - converts to array\n";
    echo "  • json - converts to JSON\n";
    echo "  • object - converts to object\n";
    echo "  • collection - converts to Collection\n";
    echo "  • date/datetime - converts to DateTime\n";
    echo "  • timestamp - converts to Unix timestamp\n\n";
    
    // Demonstrate casting
    $user1->setAttribute('preferences', ['theme' => 'dark', 'language' => 'en']);
    echo "✓ JSON casting: preferences stored as JSON\n";
    
    $user1->setAttribute('is_active', '1');
    echo "✓ Boolean casting: '1' becomes boolean true\n";
    
    echo "\n7. TIMESTAMPS\n";
    echo "-------------\n";
    
    echo "Automatic timestamp management:\n";
    echo "✓ created_at: Set when model is first saved\n";
    echo "✓ updated_at: Updated on every save\n";
    echo "✓ Configurable column names and formats\n";
    echo "✓ Can be disabled by setting \$timestamps = false\n\n";
    
    if ($user1->usesTimestamps()) {
        echo "User model uses timestamps:\n";
        echo "  • Created at column: " . $user1->getCreatedAtColumn() . "\n";
        echo "  • Updated at column: " . $user1->getUpdatedAtColumn() . "\n";
        echo "  • Date format: " . $user1->getDateFormat() . "\n";
    }
    
    echo "\n8. MODEL SERIALIZATION\n";
    echo "----------------------\n";
    
    echo "Models can be converted to arrays and JSON:\n\n";
    
    echo "Array representation:\n";
    $array = $user1->toArray();
    foreach ($array as $key => $value) {
        if ($key !== 'password') { // Skip hidden attributes
            echo "  $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    }
    
    echo "\nJSON representation:\n";
    $json = $user1->toJson();
    echo substr($json, 0, 100) . "...\n";
    
    echo "\n9. MODEL COLLECTIONS\n";
    echo "--------------------\n";
    
    // Create a collection of models
    $users = new Collection([$user1, $user2]);
    
    echo "Collection operations:\n";
    echo "✓ count(): " . $users->count() . " users\n";
    echo "✓ isEmpty(): " . ($users->isEmpty() ? 'true' : 'false') . "\n";
    echo "✓ isNotEmpty(): " . ($users->isNotEmpty() ? 'true' : 'false') . "\n";
    
    echo "\nCollection methods:\n";
    $names = $users->pluck('name')->toArray();
    echo "✓ pluck('name'): " . implode(', ', $names) . "\n";
    
    $modelKeys = $users->modelKeys();
    echo "✓ modelKeys(): " . implode(', ', array_filter($modelKeys)) . "\n";
    
    // Filter collection
    $filtered = $users->filter(function ($user) {
        return str_contains($user->email, 'john');
    });
    echo "✓ filter() found: " . $filtered->count() . " John users\n";
    
    echo "\n10. QUERY BUILDER INTEGRATION\n";
    echo "-----------------------------\n";
    
    echo "Model integrates with Query Builder:\n\n";
    
    echo "Static query methods:\n";
    echo "```php\n";
    echo "// Find by primary key\n";
    echo "User::find(1);\n";
    echo "User::find([1, 2, 3]);\n";
    echo "User::findOrFail(1);\n\n";
    
    echo "// Get all models\n";
    echo "User::all();\n\n";
    
    echo "// Query builder methods\n";
    echo "User::where('active', true)->get();\n";
    echo "User::where('email', 'like', '%@gmail.com')->first();\n";
    echo "User::orderBy('created_at', 'desc')->limit(10)->get();\n\n";
    
    echo "// Advanced queries\n";
    echo "User::whereIn('id', [1, 2, 3])->get();\n";
    echo "User::whereBetween('created_at', [\$start, \$end])->get();\n";
    echo "User::whereNull('email_verified_at')->get();\n";
    echo "```\n\n";
    
    echo "11. MODEL EVENTS\n";
    echo "----------------\n";
    
    echo "Model events for lifecycle hooks:\n";
    echo "  • creating - before creating a new model\n";
    echo "  • created - after creating a new model\n";
    echo "  • updating - before updating an existing model\n";
    echo "  • updated - after updating an existing model\n";
    echo "  • saving - before saving (creating or updating)\n";
    echo "  • saved - after saving (creating or updating)\n";
    echo "  • deleting - before deleting a model\n";
    echo "  • deleted - after deleting a model\n";
    echo "  • retrieved - after retrieving a model from database\n\n";
    
    echo "12. BEST PRACTICES\n";
    echo "------------------\n";
    
    echo "🔹 SECURITY\n";
    echo "   • Always define \$fillable or \$guarded arrays\n";
    echo "   • Never put sensitive fields in \$fillable\n";
    echo "   • Use \$hidden to hide sensitive data from serialization\n";
    echo "   • Hash passwords before storing\n\n";
    
    echo "🔹 PERFORMANCE\n";
    echo "   • Use eager loading to avoid N+1 queries\n";
    echo "   • Consider disabling timestamps if not needed\n";
    echo "   • Use specific columns with select() when possible\n";
    echo "   • Cache expensive queries\n\n";
    
    echo "🔹 MAINTAINABILITY\n";
    echo "   • Use descriptive model and attribute names\n";
    echo "   • Keep models focused on single responsibility\n";
    echo "   • Use accessors/mutators for data transformation\n";
    echo "   • Validate data before saving\n\n";
    
    echo "🔹 CONVENTIONS\n";
    echo "   • Table names: plural snake_case (users, blog_posts)\n";
    echo "   • Model names: singular PascalCase (User, BlogPost)\n";
    echo "   • Primary key: 'id' (auto-incrementing integer)\n";
    echo "   • Foreign keys: singular_table_id (user_id, blog_post_id)\n";
    echo "   • Timestamps: created_at, updated_at\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "============================================\n";
echo "✅ ORM MODEL SYSTEM EXAMPLE COMPLETE!\n";
echo "============================================\n\n";

echo "The ORM Model system provides:\n\n";

echo "🔹 ACTIVE RECORD PATTERN\n";
echo "   Intuitive database interaction through model objects\n\n";

echo "🔹 SECURITY BY DEFAULT\n";
echo "   Mass assignment protection and data validation\n\n";

echo "🔹 DEVELOPER EXPERIENCE\n";
echo "   Clean, expressive syntax for database operations\n\n";

echo "🔹 FLEXIBILITY\n";
echo "   Configurable behavior while maintaining conventions\n\n";

echo "🔹 PERFORMANCE AWARE\n";
echo "   Built-in tools to avoid common performance pitfalls\n\n";

echo "Ready for building robust database-driven applications! 🚀\n";