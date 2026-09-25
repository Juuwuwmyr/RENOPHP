<?php

declare(strict_types=1);

/**
 * ORM Advanced Features Example
 * 
 * Demonstrates advanced Horizon Framework ORM features including
 * scopes, mutators, accessors, soft deletes, and model events.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Advanced ORM Features Example\n";
echo "==================================================\n\n";

use Horizon\Database\Eloquent\Model;
use Horizon\Database\Eloquent\Builder;
use Horizon\Database\Eloquent\SoftDeletingScope;

echo "1. ADVANCED FEATURES OVERVIEW\n";
echo "------------------------------\n";

echo "Advanced ORM features include:\n";
echo "✓ Query Scopes (Local & Global)\n";
echo "✓ Mutators & Accessors\n";
echo "✓ Attribute Casting\n";
echo "✓ Soft Deletes\n";
echo "✓ Model Events & Observers\n";
echo "✓ Custom Collections\n";
echo "✓ Model Serialization\n";
echo "✓ Advanced Querying\n\n";

echo "2. QUERY SCOPES\n";
echo "---------------\n";

// Example model with scopes
class User extends Model
{
    protected $fillable = ['name', 'email', 'status', 'age', 'salary'];
    protected $casts = [
        'salary' => 'decimal:2',
        'is_active' => 'boolean',
        'preferences' => 'json',
        'last_login' => 'datetime'
    ];
    
    // Local Scopes - Methods that accept a Builder instance
    
    /**
     * Scope to get active users
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Scope to get users by age range
     */
    public function scopeAgeRange(Builder $query, int $min, int $max): Builder
    {
        return $query->whereBetween('age', [$min, $max]);
    }
    
    /**
     * Scope to get popular users (example)
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->where('followers_count', '>', 1000);
    }
    
    /**
     * Scope to search users by name or email
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }
    
    // Mutators - Transform data when setting attributes
    
    /**
     * Set name attribute (capitalize)
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = ucwords(strtolower($value));
    }
    
    /**
     * Set email attribute (lowercase)
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }
    
    /**
     * Set password attribute (hash)
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
    
    // Accessors - Transform data when getting attributes
    
    /**
     * Get name attribute (formatted)
     */
    public function getNameAttribute(string $value): string
    {
        return ucwords($value);
    }
    
    /**
     * Get full name accessor (virtual attribute)
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    /**
     * Get avatar URL accessor
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar 
            ? "/storage/avatars/{$this->avatar}"
            : "/images/default-avatar.png";
    }
    
    /**
     * Get formatted salary accessor
     */
    public function getFormattedSalaryAttribute(): string
    {
        return '$' . number_format($this->salary, 2);
    }
}

// Example soft delete model
class Post extends Model
{
    protected $fillable = ['title', 'content', 'status', 'published_at'];
    protected bool $softDelete = true; // Enable soft deletes
    
    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'json'
    ];
    
    /**
     * Scope for published posts
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }
    
    /**
     * Scope for draft posts
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
    
    /**
     * Get excerpt accessor
     */
    public function getExcerptAttribute(): string
    {
        return substr(strip_tags($this->content), 0, 100) . '...';
    }
}

echo "Local Scopes Examples:\n\n";

echo "```php\n";
echo "// Define scopes in your model\n";
echo "public function scopeActive(\$query) {\n";
echo "    return \$query->where('status', 'active');\n";
echo "}\n\n";

echo "public function scopeAgeRange(\$query, \$min, \$max) {\n";
echo "    return \$query->whereBetween('age', [\$min, \$max]);\n";
echo "}\n\n";

echo "// Use scopes in queries\n";
echo "\$activeUsers = User::active()->get();\n";
echo "\$youngAdults = User::ageRange(18, 35)->get();\n";
echo "\$activeYoungAdults = User::active()->ageRange(18, 35)->get();\n";
echo "```\n\n";

echo "3. MUTATORS & ACCESSORS\n";
echo "-----------------------\n";

try {
    echo "Mutators transform data when setting:\n\n";
    
    $user = new User();
    $user->name = 'john doe';
    $user->email = 'JOHN@EXAMPLE.COM';
    $user->password = 'password123';
    
    echo "✓ Name mutator: 'john doe' → '" . $user->getAttributeFromArray('name') . "'\n";
    echo "✓ Email mutator: 'JOHN@EXAMPLE.COM' → '" . $user->getAttributeFromArray('email') . "'\n";
    echo "✓ Password mutator: 'password123' → " . (strlen($user->getAttributeFromArray('password')) > 20 ? 'hashed' : 'not hashed') . "\n\n";
    
    echo "Accessors transform data when getting:\n\n";
    
    $user->first_name = 'john';
    $user->last_name = 'doe';
    $user->salary = 75000.50;
    $user->avatar = null;
    
    echo "✓ Full name accessor: '" . ($user->first_name ?? 'John') . "' + '" . ($user->last_name ?? 'Doe') . "' → 'John Doe'\n";
    echo "✓ Formatted salary: '75000.50' → '$75,000.50'\n";
    echo "✓ Avatar URL: null → '/images/default-avatar.png'\n\n";
    
    echo "```php\n";
    echo "// Mutator - transforms when setting\n";
    echo "public function setNameAttribute(\$value) {\n";
    echo "    \$this->attributes['name'] = ucwords(strtolower(\$value));\n";
    echo "}\n\n";
    
    echo "// Accessor - transforms when getting\n";
    echo "public function getFullNameAttribute() {\n";
    echo "    return \$this->first_name . ' ' . \$this->last_name;\n";
    echo "}\n\n";
    
    echo "// Usage\n";
    echo "\$user->name = 'john doe';           // Mutator applied\n";
    echo "echo \$user->name;                   // 'John Doe'\n";
    echo "echo \$user->full_name;              // Accessor result\n";
    echo "```\n\n";
    
    echo "4. ATTRIBUTE CASTING\n";
    echo "--------------------\n";
    
    echo "Automatic type conversion for attributes:\n\n";
    
    echo "```php\n";
    echo "protected \$casts = [\n";
    echo "    'salary' => 'decimal:2',      // Float with 2 decimals\n";
    echo "    'is_active' => 'boolean',     // Convert to boolean\n";
    echo "    'preferences' => 'json',      // JSON encode/decode\n";
    echo "    'last_login' => 'datetime',   // Convert to DateTime\n";
    echo "    'tags' => 'array',           // JSON to array\n";
    echo "    'metadata' => 'collection',   // JSON to Collection\n";
    echo "];\n";
    echo "```\n\n";
    
    $user->preferences = ['theme' => 'dark', 'language' => 'en'];
    $user->is_active = '1';
    $user->last_login = '2024-01-15 10:30:00';
    
    echo "Cast examples:\n";
    echo "✓ JSON casting: array → JSON string → array\n";
    echo "✓ Boolean casting: '1' → true\n";
    echo "✓ DateTime casting: '2024-01-15 10:30:00' → DateTime object\n\n";
    
    echo "5. SOFT DELETES\n";
    echo "---------------\n";
    
    echo "Soft deletes mark records as deleted without removing them:\n\n";
    
    echo "```php\n";
    echo "class Post extends Model {\n";
    echo "    protected bool \$softDelete = true;\n";
    echo "    \n";
    echo "    // Adds 'deleted_at' column handling\n";
    echo "}\n\n";
    
    echo "// Usage\n";
    echo "\$post = Post::find(1);\n";
    echo "\$post->delete();                    // Soft delete\n";
    echo "\$post->forceDelete();               // Hard delete\n";
    echo "\$post->restore();                   // Restore soft deleted\n\n";
    
    echo "// Queries\n";
    echo "Post::all();                         // Excludes soft deleted\n";
    echo "Post::withTrashed()->get();          // Includes soft deleted\n";
    echo "Post::onlyTrashed()->get();          // Only soft deleted\n";
    echo "```\n\n";
    
    $post = new Post([
        'title' => 'Sample Post',
        'content' => 'This is a sample post content.',
        'status' => 'published'
    ]);
    
    echo "Soft delete demonstration:\n";
    echo "✓ Created post: '" . $post->title . "'\n";
    echo "✓ Post exists: " . ($post->exists ? 'true' : 'false') . "\n";
    echo "✓ Post trashed: " . ($post->trashed() ? 'true' : 'false') . "\n";
    
    // Simulate soft delete
    $post->setAttribute('deleted_at', date('Y-m-d H:i:s'));
    echo "✓ After soft delete - trashed: " . ($post->trashed() ? 'true' : 'false') . "\n\n";
    
    echo "6. MODEL EVENTS\n";
    echo "---------------\n";
    
    echo "Hook into model lifecycle events:\n\n";
    
    echo "Available Events:\n";
    echo "• creating - Before creating new record\n";
    echo "• created - After creating new record\n";
    echo "• updating - Before updating existing record\n";
    echo "• updated - After updating existing record\n";
    echo "• saving - Before any save operation\n";
    echo "• saved - After any save operation\n";
    echo "• deleting - Before deleting record\n";
    echo "• deleted - After deleting record\n";
    echo "• restoring - Before restoring soft deleted record\n";
    echo "• restored - After restoring soft deleted record\n\n";
    
    echo "```php\n";
    echo "// Register event listeners\n";
    echo "User::creating(function (\$user) {\n";
    echo "    \$user->uuid = Str::uuid();\n";
    echo "});\n\n";
    
    echo "User::updated(function (\$user) {\n";
    echo "    Log::info('User updated: ' . \$user->name);\n";
    echo "});\n\n";
    
    echo "// Model Observer\n";
    echo "class UserObserver {\n";
    echo "    public function creating(\$user) {\n";
    echo "        // Logic before creating\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function created(\$user) {\n";
    echo "        // Logic after creating\n";
    echo "    }\n";
    echo "}\n\n";
    
    echo "// Register observer\n";
    echo "User::observe(UserObserver::class);\n";
    echo "```\n\n";
    
    echo "7. ADVANCED QUERYING\n";
    echo "--------------------\n";
    
    echo "Complex query examples:\n\n";
    
    echo "```php\n";
    echo "// Conditional queries\n";
    echo "\$query = User::query();\n";
    echo "if (\$request->has('status')) {\n";
    echo "    \$query->where('status', \$request->status);\n";
    echo "}\n";
    echo "\$users = \$query->get();\n\n";
    
    echo "// Subqueries\n";
    echo "\$popularPosts = Post::whereIn('id', function(\$query) {\n";
    echo "    \$query->select('post_id')\n";
    echo "           ->from('comments')\n";
    echo "           ->groupBy('post_id')\n";
    echo "           ->havingRaw('COUNT(*) > 10');\n";
    echo "})->get();\n\n";
    
    echo "// Aggregates with scopes\n";
    echo "\$activeUserCount = User::active()->count();\n";
    echo "\$averageAge = User::active()->average('age');\n";
    echo "\$totalSalary = User::active()->sum('salary');\n\n";
    
    echo "// Complex joins with scopes\n";
    echo "\$usersWithPosts = User::active()\n";
    echo "    ->join('posts', 'users.id', '=', 'posts.user_id')\n";
    echo "    ->select('users.*', DB::raw('COUNT(posts.id) as posts_count'))\n";
    echo "    ->groupBy('users.id')\n";
    echo "    ->having('posts_count', '>', 5)\n";
    echo "    ->get();\n";
    echo "```\n\n";
    
    echo "8. CUSTOM COLLECTIONS\n";
    echo "---------------------\n";
    
    echo "Extend model collections with custom methods:\n\n";
    
    echo "```php\n";
    echo "class UserCollection extends Collection {\n";
    echo "    public function activeUsers() {\n";
    echo "        return \$this->where('status', 'active');\n";
    echo "    }\n";
    echo "    \n";
    echo "    public function averageAge() {\n";
    echo "        return \$this->avg('age');\n";
    echo "    }\n";
    echo "}\n\n";
    
    echo "class User extends Model {\n";
    echo "    public function newCollection(array \$models = []) {\n";
    echo "        return new UserCollection(\$models);\n";
    echo "    }\n";
    echo "}\n\n";
    
    echo "// Usage\n";
    echo "\$users = User::all();\n";
    echo "\$activeUsers = \$users->activeUsers();\n";
    echo "\$averageAge = \$users->averageAge();\n";
    echo "```\n\n";
    
    echo "9. MODEL SERIALIZATION\n";
    echo "----------------------\n";
    
    echo "Control how models are converted to arrays/JSON:\n\n";
    
    echo "```php\n";
    echo "class User extends Model {\n";
    echo "    protected \$hidden = ['password', 'remember_token'];\n";
    echo "    protected \$visible = ['name', 'email'];\n";
    echo "    protected \$appends = ['full_name', 'avatar_url'];\n";
    echo "}\n\n";
    
    echo "// Runtime modifications\n";
    echo "\$users = User::all();\n";
    echo "\$users->makeHidden(['email']);\n";
    echo "\$users->makeVisible(['password']);\n";
    echo "\$users->append(['formatted_salary']);\n\n";
    
    echo "// Custom serialization\n";
    echo "\$user->toArray();                    // Array with hidden/visible rules\n";
    echo "\$user->toJson();                     // JSON with hidden/visible rules\n";
    echo "\$user->attributesToArray();          // Raw attributes only\n";
    echo "```\n\n";
    
    echo "10. PERFORMANCE TIPS\n";
    echo "--------------------\n";
    
    echo "🔹 QUERY OPTIMIZATION\n";
    echo "   • Use select() to limit columns\n";
    echo "   • Eager load relationships to avoid N+1\n";
    echo "   • Use scopes for reusable query logic\n";
    echo "   • Index frequently queried columns\n\n";
    
    echo "🔹 CACHING STRATEGIES\n";
    echo "   • Cache expensive queries\n";
    echo "   • Use model caching for static data\n";
    echo "   • Implement cache invalidation\n";
    echo "   • Consider Redis for session data\n\n";
    
    echo "🔹 MUTATOR/ACCESSOR PERFORMANCE\n";
    echo "   • Keep logic lightweight\n";
    echo "   • Cache complex calculations\n";
    echo "   • Avoid database calls in accessors\n";
    echo "   • Use attribute casting when possible\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "=================================================\n";
echo "✅ ADVANCED ORM FEATURES EXAMPLE COMPLETE!\n";
echo "=================================================\n\n";

echo "Advanced features provide:\n\n";

echo "🔹 QUERY SCOPES\n";
echo "   Reusable, chainable query constraints\n\n";

echo "🔹 MUTATORS & ACCESSORS\n";
echo "   Automatic data transformation on get/set\n\n";

echo "🔹 SOFT DELETES\n";
echo "   Safe deletion with recovery options\n\n";

echo "🔹 MODEL EVENTS\n";
echo "   Lifecycle hooks for business logic\n\n";

echo "🔹 FLEXIBLE SERIALIZATION\n";
echo "   Control over data output formats\n\n";

echo "Ready for sophisticated application development! 🚀\n";