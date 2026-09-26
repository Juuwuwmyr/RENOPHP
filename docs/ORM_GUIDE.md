# Horizon Framework - Complete ORM Guide

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Models](#models)
4. [Database Relationships](#database-relationships)
5. [Query Builder](#query-builder)
6. [Advanced Features](#advanced-features)
7. [Security](#security)
8. [Performance](#performance)
9. [Migrations](#migrations)
10. [Database Seeding](#database-seeding)
11. [Console Commands](#console-commands)
12. [Best Practices](#best-practices)
13. [Troubleshooting](#troubleshooting)

## Introduction

The Horizon Framework ORM (Object-Relational Mapping) provides a beautiful, simple ActiveRecord implementation for working with your database. Each database table has a corresponding "Model" that is used to interact with that table.

### Key Features

- **Simple & Intuitive**: Clean, readable syntax
- **Secure by Default**: Built-in protection against common vulnerabilities
- **High Performance**: Optimized queries, caching, and connection pooling
- **Flexible**: Supports complex relationships and advanced querying
- **Developer Friendly**: Excellent error messages and debugging tools

## Getting Started

### Installation

Configure your database connection in `config/database.php`:

```php
<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'horizon'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],
];
```

### Environment Configuration

Set up your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=horizon_app
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Models

### Creating Models

Generate a model using the console command:

```bash
php horizon make:model User
```

This creates a model file at `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Horizon\Database\Eloquent\Model;

class User extends Model
{
    protected ?string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];
    
    protected array $hidden = [
        'password',
        'remember_token',
    ];
    
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

### Model Conventions

| Convention | Example |
|------------|---------|
| **Class Name** | `User` (singular, PascalCase) |
| **Table Name** | `users` (plural, snake_case) |
| **Primary Key** | `id` (auto-incrementing integer) |
| **Timestamps** | `created_at`, `updated_at` |

### Basic CRUD Operations

#### Creating Records

```php
// Create a new user
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Mass assignment
$user = User::create([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
]);

// Create multiple records
User::insert([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);
```

#### Reading Records

```php
// Find by primary key
$user = User::find(1);

// Find or fail (throws exception if not found)
$user = User::findOrFail(1);

// Find multiple by keys
$users = User::find([1, 2, 3]);

// Get all records
$users = User::all();

// First record
$user = User::first();

// Where clauses
$users = User::where('status', 'active')->get();
$user = User::where('email', 'john@example.com')->first();
```

#### Updating Records

```php
// Update existing model
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();

// Mass update
$user->update(['name' => 'New Name']);

// Update multiple records
User::where('status', 'inactive')
    ->update(['status' => 'active']);
```

#### Deleting Records

```php
// Delete model instance
$user = User::find(1);
$user->delete();

// Delete by primary key
User::destroy(1);
User::destroy([1, 2, 3]);

// Delete with conditions
User::where('status', 'inactive')->delete();
```

### Mass Assignment Protection

Protect against mass assignment vulnerabilities:

```php
class User extends Model
{
    // Attributes that can be mass assigned
    protected array $fillable = [
        'name',
        'email',
        'bio',
    ];
    
    // Attributes that cannot be mass assigned
    protected array $guarded = [
        'id',
        'password',
        'is_admin',
        'api_token',
    ];
}
```

### Attribute Casting

Cast attributes to specific types:

```php
class User extends Model
{
    protected array $casts = [
        'is_admin' => 'boolean',
        'settings' => 'array',
        'birthday' => 'date',
        'last_login' => 'datetime',
        'balance' => 'decimal:2',
    ];
}

// Usage
$user = User::find(1);
$isAdmin = $user->is_admin; // boolean
$settings = $user->settings; // array
$birthday = $user->birthday; // Carbon date
```

### Mutators and Accessors

Transform data when setting or getting attributes:

```php
class User extends Model
{
    // Mutator - transforms data when setting
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }
    
    // Accessor - transforms data when getting
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    // Accessor with caching
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar 
            ? '/storage/avatars/' . $this->avatar 
            : '/images/default-avatar.png';
    }
}

// Usage
$user->password = 'secret'; // Automatically hashed
$fullName = $user->full_name; // Computed attribute
```

## Database Relationships

### One-to-One Relationships

```php
// User model
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
}

// UserProfile model
class UserProfile extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$user = User::find(1);
$profile = $user->profile;

$profile = UserProfile::find(1);
$user = $profile->user;
```

### One-to-Many Relationships

```php
// User model (one user has many posts)
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Post model (post belongs to one user)
class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts;

foreach ($user->posts as $post) {
    echo $post->title;
}
```

### Many-to-Many Relationships

```php
// User model
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
}

// Role model
class Role extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles;

// Attach/detach roles
$user->roles()->attach([1, 2, 3]);
$user->roles()->detach([2]);
$user->roles()->sync([1, 3, 4]); // Keep only specified roles
```

### Eager Loading (Preventing N+1 Queries)

```php
// Bad: N+1 query problem
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // Each iteration executes a query
}

// Good: Eager loading
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count(); // No additional queries
}

// Multiple relationships
$users = User::with(['posts', 'profile', 'roles'])->get();

// Nested relationships
$users = User::with('posts.comments')->get();

// Conditional eager loading
$users = User::with(['posts' => function ($query) {
    $query->where('published', true);
}])->get();
```

## Query Builder

### Basic Queries

```php
// Select with conditions
$users = User::where('status', 'active')
    ->where('age', '>', 18)
    ->get();

// Or conditions
$users = User::where('status', 'active')
    ->orWhere('is_premium', true)
    ->get();

// In/Not In
$users = User::whereIn('id', [1, 2, 3])->get();
$users = User::whereNotIn('status', ['banned', 'suspended'])->get();

// Null checks
$users = User::whereNull('deleted_at')->get();
$users = User::whereNotNull('email_verified_at')->get();

// Date queries
$users = User::whereDate('created_at', '2024-01-01')->get();
$users = User::whereYear('created_at', 2024)->get();
$users = User::whereMonth('created_at', 12)->get();
```

### Advanced Queries

```php
// Subqueries
$users = User::whereExists(function ($query) {
    $query->select('*')
        ->from('posts')
        ->whereColumn('posts.user_id', 'users.id');
})->get();

// Joins
$users = User::join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// Group by and having
$userCounts = User::select('status', DB::raw('count(*) as total'))
    ->groupBy('status')
    ->having('total', '>', 10)
    ->get();

// Ordering and limiting
$users = User::orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Pagination
$users = User::paginate(15);
$users = User::simplePaginate(15);
```

### Query Scopes

Define reusable query logic:

```php
class User extends Model
{
    // Local scope
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeAdults($query)
    {
        return $query->where('age', '>=', 18);
    }
    
    public function scopeByRole($query, $role)
    {
        return $query->whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        });
    }
}

// Usage
$users = User::active()->adults()->get();
$admins = User::byRole('admin')->get();
```

## Advanced Features

### Soft Deletes

Enable soft deletes to mark records as deleted without actually removing them:

```php
use Horizon\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
    
    protected array $dates = ['deleted_at'];
}

// Usage
$user = User::find(1);
$user->delete(); // Sets deleted_at timestamp

// Query soft deleted records
$deletedUsers = User::onlyTrashed()->get();
$allUsers = User::withTrashed()->get();

// Restore soft deleted records
$user->restore();

// Force delete (permanent)
$user->forceDelete();
```

### Global Scopes

Apply constraints to all queries for a model:

```php
use Horizon\Database\Eloquent\Scope;
use Horizon\Database\Eloquent\Builder;

class ActiveUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('status', 'active');
    }
}

// Apply to model
class User extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveUserScope);
    }
}

// All queries will automatically include the scope
$users = User::all(); // Only active users
```

### Model Events

Hook into model lifecycle events:

```php
class User extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($user) {
            $user->uuid = Str::uuid();
        });
        
        static::created(function ($user) {
            // Send welcome email
            Mail::send('welcome', ['user' => $user]);
        });
        
        static::updating(function ($user) {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });
        
        static::deleting(function ($user) {
            // Clean up related data
            $user->posts()->delete();
        });
    }
}
```

## Security

### Mass Assignment Protection

```php
class User extends Model
{
    // Only these fields can be mass assigned
    protected array $fillable = [
        'name',
        'email',
        'bio',
    ];
    
    // These fields are never mass assignable
    protected array $guarded = [
        'id',
        'password',
        'is_admin',
        'api_token',
    ];
}

// Secure creation
$user = User::createSecurely($request->validated());
```

### Input Sanitization

```php
class User extends Model
{
    // Fields that should be sanitized
    protected array $sanitized = [
        'name',
        'bio',
        'website',
    ];
    
    // Fields that should be validated
    protected array $validated = [
        'email',
        'website',
    ];
}
```

### SQL Injection Prevention

The ORM automatically prevents SQL injection by using parameterized queries:

```php
// Safe - uses parameter binding
$users = User::where('email', $userInput)->get();

// Safe - even with raw queries
$users = User::whereRaw('status = ? AND created_at > ?', ['active', $date])->get();
```

### Security Auditing

```php
use Horizon\Database\Eloquent\SecurityAuditor;

// Log security events
SecurityAuditor::logMassAssignment('User', 'is_admin', false);
SecurityAuditor::logSqlInjection('email', $suspiciousInput);
SecurityAuditor::logViolation('xss_attempt', 'bio', $maliciousInput);

// Get security report
$report = SecurityAuditor::getReport();
```

## Performance

### Query Caching

```php
class User extends Model
{
    // Enable query caching
    protected bool $cacheQueries = true;
    protected int $cacheTtl = 3600; // 1 hour
}

// Cache specific queries
$users = User::remember(300)->where('status', 'active')->get();
```

### Eager Loading Optimization

```php
// Load relationships efficiently
$users = User::with(['posts', 'profile'])->get();

// Conditional eager loading
$users = User::with(['posts' => function ($query) {
    $query->select('id', 'user_id', 'title')
        ->where('published', true);
}])->get();
```

### Batch Operations

```php
// Batch load multiple models
$users = User::findManyOptimized([1, 2, 3, 4, 5]);

// Batch updates
User::batchUpdate([
    ['id' => 1, 'status' => 'active'],
    ['id' => 2, 'status' => 'inactive'],
]);
```

### Chunking Large Datasets

```php
// Process large datasets in chunks
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Process each user
        $user->processData();
    }
});

// Cursor-based iteration for memory efficiency
foreach (User::cursor() as $user) {
    // Memory-efficient iteration
    $user->process();
}
```

### Performance Monitoring

```php
use Horizon\Database\Eloquent\PerformanceMonitor;

// Monitor performance
$result = PerformanceMonitor::monitor(function () {
    return User::with('posts')->get();
});

// Get performance metrics
$metrics = PerformanceMonitor::getMetrics();
$report = PerformanceMonitor::getReport();
```

### Connection Pooling

```php
use Horizon\Database\ConnectionPool;

$pool = new ConnectionPool([
    'max_connections' => 10,
    'min_connections' => 2,
]);

$result = $pool->executeWithConnection(function ($connection) {
    return $connection->select('SELECT * FROM users LIMIT 10');
});
```

## Migrations

### Creating Migrations

```bash
# Create a new table migration
php horizon make:migration create_users_table --create=users

# Modify existing table
php horizon make:migration add_status_to_users_table --table=users
```

### Migration Structure

```php
<?php

use Horizon\Database\Migrations\Migration;
use Horizon\Database\Schema\Blueprint;
use Horizon\Database\Schema\Builder;

return new class extends Migration
{
    public function up(): void
    {
        Builder::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->decimal('balance', 10, 2)->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('email');
            $table->index(['status', 'created_at']);
        });
    }
    
    public function down(): void
    {
        Builder::dropIfExists('users');
    }
};
```

### Running Migrations

```bash
# Run all pending migrations
php horizon migrate

# Rollback last batch
php horizon migrate:rollback

# Rollback specific steps
php horizon migrate:rollback --step=3

# Fresh migration (drop all tables and re-migrate)
php horizon migrate:fresh

# Fresh migration with seeding
php horizon migrate:fresh --seed
```

## Database Seeding

### Creating Seeders

```bash
# Create a seeder
php horizon make:seeder UserSeeder

# Create a factory
php horizon make:factory UserFactory --model=User
```

### Factory Definition

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Horizon\Database\Factories\Factory;

class UserFactory extends Factory
{
    protected string $model = User::class;
    
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => $this->faker->dateTime(),
            'password' => bcrypt('password'),
            'is_admin' => $this->faker->boolean(10), // 10% chance
            'balance' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
    
    // State methods
    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }
    
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
```

### Seeder Implementation

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Horizon\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create specific users
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
        
        // Create users using factory
        User::factory()->count(50)->create();
        
        // Create admin users
        User::factory()->admin()->count(5)->create();
        
        // Create with relationships
        User::factory()
            ->count(10)
            ->has(Post::factory()->count(3))
            ->create();
    }
}
```

### Running Seeders

```bash
# Run all seeders
php horizon db:seed

# Run specific seeder
php horizon db:seed --class=UserSeeder

# Fresh migration with seeding
php horizon migrate:fresh --seed
```

## Console Commands

### Available Commands

```bash
# Model generation
php horizon make:model User
php horizon make:model Post --migration --factory --seeder

# Migration commands
php horizon make:migration create_users_table --create=users
php horizon migrate
php horizon migrate:rollback

# Seeding commands
php horizon make:seeder UserSeeder
php horizon make:factory UserFactory --model=User
php horizon db:seed

# All-in-one model creation
php horizon make:model Article --all  # Creates model, migration, factory, seeder, controller
```

### Command Options

| Option | Description |
|--------|-------------|
| `--migration` or `-m` | Create migration file |
| `--factory` or `-f` | Create factory file |
| `--seeder` or `-s` | Create seeder file |
| `--controller` or `-c` | Create controller file |
| `--resource` or `-r` | Create resource controller |
| `--all` or `-a` | Create all related files |
| `--force` | Overwrite existing files |

## Best Practices

### Model Organization

```php
class User extends Model
{
    // 1. Table configuration
    protected ?string $table = 'users';
    protected string $primaryKey = 'id';
    
    // 2. Mass assignment protection
    protected array $fillable = ['name', 'email'];
    protected array $guarded = ['id', 'password'];
    
    // 3. Attribute casting
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];
    
    // 4. Hidden attributes
    protected array $hidden = ['password', 'remember_token'];
    
    // 5. Relationships
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    // 6. Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    // 7. Mutators/Accessors
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }
    
    // 8. Model events
    protected static function booted(): void
    {
        static::creating(function ($user) {
            $user->uuid = Str::uuid();
        });
    }
}
```

### Query Optimization

```php
// Good: Select only needed columns
$users = User::select(['id', 'name', 'email'])->get();

// Good: Use eager loading
$users = User::with(['posts', 'profile'])->get();

// Good: Use indexes in WHERE clauses
$users = User::where('status', 'active')->get(); // Ensure 'status' is indexed

// Good: Use chunking for large datasets
User::chunk(1000, function ($users) {
    // Process users
});

// Bad: N+1 queries
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // Executes query for each user
}
```

### Security Best Practices

1. **Always use mass assignment protection**
2. **Validate input before database operations**
3. **Use parameterized queries (automatic in ORM)**
4. **Implement proper authentication and authorization**
5. **Enable security auditing in production**
6. **Regularly review security logs**

### Performance Best Practices

1. **Use eager loading to prevent N+1 queries**
2. **Implement query caching for expensive operations**
3. **Use database indexes appropriately**
4. **Chunk large datasets to manage memory**
5. **Monitor query performance regularly**
6. **Use connection pooling in high-traffic applications**

## Troubleshooting

### Common Issues

#### Mass Assignment Error
```php
// Error: Add [field] to fillable property
// Solution: Add field to $fillable array or use $guarded = []

class User extends Model
{
    protected array $fillable = ['name', 'email', 'field_name'];
}
```

#### N+1 Query Problem
```php
// Problem: Multiple queries for related data
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // N+1 queries
}

// Solution: Use eager loading
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count(); // Single query
}
```

#### Memory Issues with Large Datasets
```php
// Problem: Loading too much data at once
$users = User::all(); // Memory intensive

// Solution: Use chunking
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});
```

### Debugging Tools

```php
// Enable query logging
DB::enableQueryLog();

// Get executed queries
$queries = DB::getQueryLog();

// Performance monitoring
$metrics = PerformanceMonitor::getMetrics();

// Security audit
$audit = SecurityAuditor::getAuditLog();
```

### Performance Debugging

```php
// Profile query execution
$profile = $user->profile(function() {
    return User::with('posts')->get();
});

// Check for slow queries
$slowQueries = PerformanceMonitor::getSlowQueries();

// Detect N+1 queries
$duplicates = PerformanceMonitor::getDuplicateQueries();
```

---

## Conclusion

The Horizon Framework ORM provides a powerful, secure, and performant way to interact with your database. By following the patterns and best practices outlined in this guide, you can build robust applications that scale well and remain secure.

For more advanced topics and examples, refer to the individual example files in the `examples/` directory.

**Happy coding with Horizon! 🚀**