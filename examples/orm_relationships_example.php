<?php

declare(strict_types=1);

/**
 * ORM Relationships Example
 * 
 * Demonstrates the Horizon Framework's ORM relationship functionality
 * including hasOne, hasMany, belongsTo, and belongsToMany relationships.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - ORM Relationships Example\n";
echo "==============================================\n\n";

use Horizon\Database\Eloquent\Model;
use Horizon\Database\Eloquent\Collection;
use Horizon\Database\Eloquent\Relations\HasOne;
use Horizon\Database\Eloquent\Relations\HasMany;
use Horizon\Database\Eloquent\Relations\BelongsTo;
use Horizon\Database\Eloquent\Relations\BelongsToMany;

echo "1. RELATIONSHIP OVERVIEW\n";
echo "------------------------\n";

echo "Horizon ORM supports four main relationship types:\n";
echo "✓ hasOne - One-to-one relationship\n";
echo "✓ hasMany - One-to-many relationship\n";
echo "✓ belongsTo - Inverse relationship (many-to-one)\n";
echo "✓ belongsToMany - Many-to-many relationship with pivot table\n\n";

echo "2. DEFINING MODEL RELATIONSHIPS\n";
echo "-------------------------------\n";

// Mock models for demonstration
class User extends Model
{
    protected $fillable = ['name', 'email'];
    
    /**
     * User has one profile (One-to-One)
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
    
    /**
     * User has many posts (One-to-Many)
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    /**
     * User belongs to many roles (Many-to-Many)
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
    
    /**
     * User has many comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

class Profile extends Model
{
    protected $fillable = ['user_id', 'bio', 'avatar'];
    
    /**
     * Profile belongs to user (Inverse One-to-One)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

class Post extends Model
{
    protected $fillable = ['user_id', 'title', 'content'];
    
    /**
     * Post belongs to user (Inverse One-to-Many)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Post has many comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
    
    /**
     * Post belongs to many tags (Many-to-Many)
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}

class Comment extends Model
{
    protected $fillable = ['user_id', 'post_id', 'content'];
    
    /**
     * Comment belongs to user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Comment belongs to post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}

class Role extends Model
{
    protected $fillable = ['name', 'description'];
    
    /**
     * Role belongs to many users (Many-to-Many)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

class Tag extends Model
{
    protected $fillable = ['name'];
    
    /**
     * Tag belongs to many posts (Many-to-Many)
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}

echo "Example Model Definitions:\n\n";

echo "```php\n";
echo "class User extends Model\n";
echo "{\n";
echo "    // One-to-One: User has one profile\n";
echo "    public function profile(): HasOne\n";
echo "    {\n";
echo "        return \$this->hasOne(Profile::class);\n";
echo "    }\n\n";
echo "    // One-to-Many: User has many posts\n";
echo "    public function posts(): HasMany\n";
echo "    {\n";
echo "        return \$this->hasMany(Post::class);\n";
echo "    }\n\n";
echo "    // Many-to-Many: User belongs to many roles\n";
echo "    public function roles(): BelongsToMany\n";
echo "    {\n";
echo "        return \$this->belongsToMany(Role::class);\n";
echo "    }\n";
echo "}\n\n";

echo "class Profile extends Model\n";
echo "{\n";
echo "    // Inverse One-to-One: Profile belongs to user\n";
echo "    public function user(): BelongsTo\n";
echo "    {\n";
echo "        return \$this->belongsTo(User::class);\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

echo "3. RELATIONSHIP CONVENTIONS\n";
echo "---------------------------\n";

echo "Foreign Key Conventions:\n";
echo "✓ hasOne/hasMany: {model}_id (e.g., user_id)\n";
echo "✓ belongsTo: {relation}_id (e.g., user_id)\n";
echo "✓ belongsToMany: {model1}_{model2} table (e.g., role_user)\n\n";

echo "Table Structure Examples:\n";
echo "```sql\n";
echo "-- users table\n";
echo "CREATE TABLE users (\n";
echo "    id BIGINT PRIMARY KEY AUTO_INCREMENT,\n";
echo "    name VARCHAR(255),\n";
echo "    email VARCHAR(255) UNIQUE,\n";
echo "    created_at TIMESTAMP,\n";
echo "    updated_at TIMESTAMP\n";
echo ");\n\n";

echo "-- profiles table (hasOne relationship)\n";
echo "CREATE TABLE profiles (\n";
echo "    id BIGINT PRIMARY KEY AUTO_INCREMENT,\n";
echo "    user_id BIGINT, -- Foreign key\n";
echo "    bio TEXT,\n";
echo "    avatar VARCHAR(255),\n";
echo "    created_at TIMESTAMP,\n";
echo "    updated_at TIMESTAMP,\n";
echo "    FOREIGN KEY (user_id) REFERENCES users(id)\n";
echo ");\n\n";

echo "-- posts table (hasMany relationship)\n";
echo "CREATE TABLE posts (\n";
echo "    id BIGINT PRIMARY KEY AUTO_INCREMENT,\n";
echo "    user_id BIGINT, -- Foreign key\n";
echo "    title VARCHAR(255),\n";
echo "    content TEXT,\n";
echo "    created_at TIMESTAMP,\n";
echo "    updated_at TIMESTAMP,\n";
echo "    FOREIGN KEY (user_id) REFERENCES users(id)\n";
echo ");\n\n";

echo "-- role_user pivot table (belongsToMany)\n";
echo "CREATE TABLE role_user (\n";
echo "    id BIGINT PRIMARY KEY AUTO_INCREMENT,\n";
echo "    user_id BIGINT,\n";
echo "    role_id BIGINT,\n";
echo "    created_at TIMESTAMP,\n";
echo "    updated_at TIMESTAMP,\n";
echo "    FOREIGN KEY (user_id) REFERENCES users(id),\n";
echo "    FOREIGN KEY (role_id) REFERENCES roles(id),\n";
echo "    UNIQUE KEY (user_id, role_id)\n";
echo ");\n";
echo "```\n\n";

echo "4. USING RELATIONSHIPS\n";
echo "----------------------\n";

try {
    // Create sample data
    $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
    $profile = new Profile(['bio' => 'Software Developer', 'avatar' => 'avatar.jpg']);
    $post1 = new Post(['title' => 'First Post', 'content' => 'Hello World!']);
    $post2 = new Post(['title' => 'Second Post', 'content' => 'Learning ORM']);
    
    echo "Accessing Relationships:\n\n";
    
    echo "// Dynamic properties (lazy loading)\n";
    echo "// \$user = User::find(1);\n";
    echo "// \$profile = \$user->profile; // Executes query when accessed\n";
    echo "// \$posts = \$user->posts; // Executes another query\n\n";
    
    echo "// Relationship methods\n";
    echo "// \$profileQuery = \$user->profile(); // Returns relationship object\n";
    echo "// \$profile = \$user->profile()->where('active', true)->first();\n\n";
    
    echo "5. EAGER LOADING\n";
    echo "----------------\n";
    
    echo "Prevent N+1 query problems with eager loading:\n\n";
    
    echo "```php\n";
    echo "// Load user with profile (2 queries instead of N+1)\n";
    echo "\$users = User::with('profile')->get();\n\n";
    
    echo "// Load multiple relationships\n";
    echo "\$users = User::with(['profile', 'posts'])->get();\n\n";
    
    echo "// Nested eager loading\n";
    echo "\$users = User::with(['posts.comments'])->get();\n\n";
    
    echo "// Conditional eager loading\n";
    echo "\$users = User::with([\n";
    echo "    'posts' => function(\$query) {\n";
    echo "        \$query->where('published', true)->latest();\n";
    echo "    }\n";
    echo "])->get();\n";
    echo "```\n\n";
    
    echo "6. RELATIONSHIP QUERIES\n";
    echo "-----------------------\n";
    
    echo "Query relationships directly:\n\n";
    
    echo "```php\n";
    echo "// Has relationship constraint\n";
    echo "\$users = User::has('posts')->get(); // Users with posts\n";
    echo "\$users = User::has('posts', '>', 5)->get(); // Users with > 5 posts\n\n";
    
    echo "// Where has constraint\n";
    echo "\$users = User::whereHas('posts', function(\$query) {\n";
    echo "    \$query->where('published', true);\n";
    echo "})->get();\n\n";
    
    echo "// Doesn't have constraint\n";
    echo "\$users = User::doesntHave('posts')->get(); // Users without posts\n";
    echo "```\n\n";
    
    echo "7. CREATING RELATED MODELS\n";
    echo "--------------------------\n";
    
    echo "Create and associate related models:\n\n";
    
    echo "```php\n";
    echo "// Create related model (hasOne/hasMany)\n";
    echo "\$user = User::find(1);\n";
    echo "\$profile = \$user->profile()->create([\n";
    echo "    'bio' => 'Developer',\n";
    echo "    'avatar' => 'avatar.jpg'\n";
    echo "]);\n\n";
    
    echo "// Save existing model (hasOne/hasMany)\n";
    echo "\$profile = new Profile(['bio' => 'Designer']);\n";
    echo "\$user->profile()->save(\$profile);\n\n";
    
    echo "// Associate models (belongsTo)\n";
    echo "\$profile = Profile::find(1);\n";
    echo "\$user = User::find(1);\n";
    echo "\$profile->user()->associate(\$user);\n";
    echo "\$profile->save();\n\n";
    
    echo "// Dissociate models (belongsTo)\n";
    echo "\$profile->user()->dissociate();\n";
    echo "\$profile->save();\n";
    echo "```\n\n";
    
    echo "8. MANY-TO-MANY OPERATIONS\n";
    echo "--------------------------\n";
    
    echo "Working with pivot tables:\n\n";
    
    echo "```php\n";
    echo "// Attach relationships\n";
    echo "\$user = User::find(1);\n";
    echo "\$user->roles()->attach([1, 2, 3]); // Attach role IDs\n";
    echo "\$user->roles()->attach(1, ['expires_at' => now()]); // With pivot data\n\n";
    
    echo "// Detach relationships\n";
    echo "\$user->roles()->detach([1, 2]); // Detach specific roles\n";
    echo "\$user->roles()->detach(); // Detach all roles\n\n";
    
    echo "// Sync relationships (attach/detach to match exact set)\n";
    echo "\$user->roles()->sync([1, 2, 3]); // Keep only these roles\n";
    echo "\$user->roles()->syncWithoutDetaching([4, 5]); // Add without removing\n\n";
    
    echo "// Toggle relationships\n";
    echo "\$user->roles()->toggle([1, 2, 3]); // Attach if not present, detach if present\n\n";
    
    echo "// Access pivot data\n";
    echo "foreach (\$user->roles as \$role) {\n";
    echo "    echo \$role->pivot->created_at; // Access pivot attributes\n";
    echo "}\n";
    echo "```\n\n";
    
    echo "9. ADVANCED RELATIONSHIP FEATURES\n";
    echo "---------------------------------\n";
    
    echo "🔹 CUSTOM KEYS\n";
    echo "```php\n";
    echo "// Custom foreign key\n";
    echo "public function posts(): HasMany\n";
    echo "{\n";
    echo "    return \$this->hasMany(Post::class, 'author_id');\n";
    echo "}\n\n";
    
    echo "// Custom local key\n";
    echo "public function posts(): HasMany\n";
    echo "{\n";
    echo "    return \$this->hasMany(Post::class, 'user_id', 'user_uuid');\n";
    echo "}\n";
    echo "```\n\n";
    
    echo "🔹 PIVOT COLUMNS\n";
    echo "```php\n";
    echo "// Include additional pivot columns\n";
    echo "public function roles(): BelongsToMany\n";
    echo "{\n";
    echo "    return \$this->belongsToMany(Role::class)\n";
    echo "                ->withPivot(['expires_at', 'granted_by'])\n";
    echo "                ->withTimestamps();\n";
    echo "}\n";
    echo "```\n\n";
    
    echo "🔹 DEFAULT MODELS\n";
    echo "```php\n";
    echo "// Return default model if relationship is null\n";
    echo "public function profile(): HasOne\n";
    echo "{\n";
    echo "    return \$this->hasOne(Profile::class)->withDefault([\n";
    echo "        'bio' => 'No bio available'\n";
    echo "    ]);\n";
    echo "}\n";
    echo "```\n\n";
    
    echo "10. RELATIONSHIP PERFORMANCE TIPS\n";
    echo "---------------------------------\n";
    
    echo "🔹 USE EAGER LOADING\n";
    echo "   • Always eager load relationships when possible\n";
    echo "   • Use 'with()' to prevent N+1 query problems\n";
    echo "   • Load only needed relationships\n\n";
    
    echo "🔹 CONSTRAIN EAGER LOADS\n";
    echo "   • Add where clauses to eager loading\n";
    echo "   • Limit the number of related records\n";
    echo "   • Select only required columns\n\n";
    
    echo "🔹 USE PROPER INDEXING\n";
    echo "   • Index foreign key columns\n";
    echo "   • Add composite indexes for pivot tables\n";
    echo "   • Consider covering indexes for common queries\n\n";
    
    echo "🔹 AVOID DEEP NESTING\n";
    echo "   • Limit relationship depth in eager loading\n";
    echo "   • Consider separate queries for very deep relationships\n";
    echo "   • Use pagination for large result sets\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "==============================================\n";
echo "✅ ORM RELATIONSHIPS EXAMPLE COMPLETE!\n";
echo "==============================================\n\n";

echo "Horizon ORM Relationships provide:\n\n";

echo "🔹 INTUITIVE SYNTAX\n";
echo "   Clean, readable relationship definitions\n\n";

echo "🔹 AUTOMATIC FOREIGN KEYS\n";
echo "   Convention-based key naming with override options\n\n";

echo "🔹 EAGER LOADING\n";
echo "   Built-in N+1 query prevention\n\n";

echo "🔹 FLEXIBLE QUERYING\n";
echo "   Relationship constraints and conditional loading\n\n";

echo "🔹 PIVOT TABLE SUPPORT\n";
echo "   Full many-to-many relationship functionality\n\n";

echo "Ready to build complex relational applications! 🚀\n";