# Horizon Framework - ORM API Reference

## Table of Contents

1. [Model Class](#model-class)
2. [Query Builder](#query-builder)
3. [Relationships](#relationships)
4. [Collections](#collections)
5. [Migrations](#migrations)
6. [Schema Builder](#schema-builder)
7. [Factory](#factory)
8. [Seeder](#seeder)
9. [Security Classes](#security-classes)
10. [Performance Classes](#performance-classes)

## Model Class

### `Horizon\Database\Eloquent\Model`

The base class for all Eloquent models.

#### Properties

```php
// Table configuration
protected ?string $table = null;
protected string $primaryKey = 'id';
protected string $keyType = 'int';
protected bool $incrementing = true;

// Timestamps
protected bool $timestamps = true;
protected ?string $dateFormat = null;
const CREATED_AT = 'created_at';
const UPDATED_AT = 'updated_at';

// Mass assignment
protected array $fillable = [];
protected array $guarded = ['*'];

// Attributes
protected array $attributes = [];
protected array $original = [];
protected array $casts = [];
protected array $hidden = [];
protected array $visible = [];

// Performance
protected bool $cacheQueries = false;
protected int $cacheTtl = 3600;
```

#### Core Methods

##### Creating Models

```php
// Create new model instance
public function __construct(array $attributes = []): void

// Create and save model
public static function create(array $attributes = []): static

// Create multiple models
public static function insert(array $values): bool

// Secure creation with validation
public static function createSecurely(array $attributes = []): static
```

##### Finding Models

```php
// Find by primary key
public static function find(mixed $id, array $columns = ['*']): ?static

// Find or throw exception
public static function findOrFail(mixed $id, array $columns = ['*']): static

// Find multiple by keys
public static function findMany(array $ids, array $columns = ['*']): Collection

// Get all models
public static function all(array $columns = ['*']): Collection

// Get first model
public static function first(array $columns = ['*']): ?static

// First or throw exception
public static function firstOrFail(array $columns = ['*']): static

// First or create new
public static function firstOrCreate(array $attributes = [], array $values = []): static
```

##### Saving Models

```php
// Save model to database
public function save(array $options = []): bool

// Update model attributes
public function update(array $attributes = [], array $options = []): bool

// Secure update with validation
public function updateSecurely(array $attributes = [], array $options = []): bool

// Save or create new
public function saveOrFail(array $options = []): bool
```

##### Deleting Models

```php
// Delete model from database
public function delete(): ?bool

// Force delete (ignoring soft deletes)
public function forceDelete(): ?bool

// Delete multiple models
public static function destroy(mixed $ids): int
```

##### Attribute Methods

```php
// Get attribute value
public function getAttribute(string $key): mixed

// Set attribute value
public function setAttribute(string $key, mixed $value): void

// Get all attributes
public function getAttributes(): array

// Fill model with attributes
public function fill(array $attributes): static

// Get fillable attributes
public function getFillable(): array

// Get guarded attributes
public function getGuarded(): array

// Check if attribute is fillable
public function isFillable(string $key): bool

// Check if attribute is guarded
public function isGuarded(string $key): bool
```

##### Relationship Methods

```php
// Define has one relationship
protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne

// Define has many relationship
protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany

// Define belongs to relationship
protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo

// Define belongs to many relationship
protected function belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): BelongsToMany
```

##### Query Methods

```php
// Get new query builder
public function newQuery(): Builder

// Create where clause
public static function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): Builder

// Create or where clause
public static function orWhere(string $column, mixed $operator = null, mixed $value = null): Builder

// Where in clause
public static function whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false): Builder

// Where between clause
public static function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): Builder

// Order by clause
public static function orderBy(string $column, string $direction = 'asc'): Builder

// Limit results
public static function limit(int $value): Builder

// Skip results
public static function skip(int $value): Builder
```

#### Magic Methods

```php
// Get attribute or relationship
public function __get(string $key): mixed

// Set attribute
public function __set(string $key, mixed $value): void

// Check if attribute exists
public function __isset(string $key): bool

// Unset attribute
public function __unset(string $key): void

// Convert to string
public function __toString(): string

// Handle dynamic method calls
public function __call(string $method, array $parameters): mixed

// Handle static method calls
public static function __callStatic(string $method, array $parameters): mixed
```

## Query Builder

### `Horizon\Database\Eloquent\Builder`

Eloquent query builder for models.

#### Where Clauses

```php
// Basic where clause
public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static

// Or where clause
public function orWhere(string $column, mixed $operator = null, mixed $value = null): static

// Where in clause
public function whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false): static

// Where not in clause
public function whereNotIn(string $column, mixed $values, string $boolean = 'and'): static

// Where null clause
public function whereNull(string $column, string $boolean = 'and', bool $not = false): static

// Where not null clause
public function whereNotNull(string $column, string $boolean = 'and'): static

// Where between clause
public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static

// Where date clause
public function whereDate(string $column, string $operator, mixed $value = null, string $boolean = 'and'): static

// Where exists clause
public function whereExists(Closure $callback, string $boolean = 'and', bool $not = false): static
```

#### Relationship Queries

```php
// Query with relationships
public function with(mixed $relations): static

// Query relationship existence
public function has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', ?Closure $callback = null): static

// Query relationship with conditions
public function whereHas(string $relation, ?Closure $callback = null, string $operator = '>=', int $count = 1): static

// Count relationships
public function withCount(mixed $relations): static
```

#### Aggregates

```php
// Count records
public function count(string $columns = '*'): int

// Get minimum value
public function min(string $column): mixed

// Get maximum value
public function max(string $column): mixed

// Get sum of column
public function sum(string $column): mixed

// Get average value
public function avg(string $column): mixed

// Check if records exist
public function exists(): bool

// Check if no records exist
public function doesntExist(): bool
```

#### Ordering and Limiting

```php
// Order by column
public function orderBy(string $column, string $direction = 'asc'): static

// Order by descending
public function orderByDesc(string $column): static

// Latest records
public function latest(string $column = 'created_at'): static

// Oldest records
public function oldest(string $column = 'created_at'): static

// Limit results
public function limit(int $value): static

// Offset results
public function offset(int $value): static

// Take first N results
public function take(int $value): static

// Skip first N results
public function skip(int $value): static
```

#### Execution Methods

```php
// Get all results
public function get(array $columns = ['*']): Collection

// Get first result
public function first(array $columns = ['*']): ?Model

// Get first or fail
public function firstOrFail(array $columns = ['*']): Model

// Get paginated results
public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator

// Simple pagination
public function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator

// Chunk results
public function chunk(int $count, callable $callback): bool

// Cursor for memory-efficient iteration
public function cursor(): Generator
```

## Relationships

### HasOne

```php
class HasOne extends Relation
{
    // Get relationship results
    public function getResults(): ?Model
    
    // Make new related instance
    public function make(array $attributes = []): Model
    
    // Create new related record
    public function create(array $attributes = []): Model
    
    // Save related model
    public function save(Model $model): Model
}
```

### HasMany

```php
class HasMany extends Relation
{
    // Get relationship results
    public function getResults(): Collection
    
    // Make new related instances
    public function make(array $attributes = []): Model
    
    // Create new related record
    public function create(array $attributes = []): Model
    
    // Create multiple related records
    public function createMany(array $records): Collection
    
    // Save related model
    public function save(Model $model): Model
    
    // Save multiple related models
    public function saveMany(iterable $models): Collection
}
```

### BelongsTo

```php
class BelongsTo extends Relation
{
    // Get relationship results
    public function getResults(): ?Model
    
    // Associate models
    public function associate(Model $model): Model
    
    // Dissociate models
    public function dissociate(): Model
}
```

### BelongsToMany

```php
class BelongsToMany extends Relation
{
    // Get relationship results
    public function getResults(): Collection
    
    // Attach models
    public function attach(mixed $id, array $attributes = [], bool $touch = true): void
    
    // Detach models
    public function detach(mixed $ids = null, bool $touch = true): int
    
    // Sync models
    public function sync(mixed $ids, bool $detaching = true): array
    
    // Toggle attachment
    public function toggle(mixed $ids, bool $touch = true): array
    
    // Update pivot attributes
    public function updateExistingPivot(mixed $id, array $attributes, bool $touch = true): int
}
```

## Collections

### `Horizon\Database\Eloquent\Collection`

Collection class for model results.

#### Basic Methods

```php
// Get all items
public function all(): array

// Get item by key
public function get(mixed $key, mixed $default = null): mixed

// Add item to collection
public function add(mixed $item): static

// Remove item from collection
public function forget(mixed $keys): static

// Check if key exists
public function has(mixed $key): bool

// Check if collection is empty
public function isEmpty(): bool

// Check if collection is not empty
public function isNotEmpty(): bool

// Get collection count
public function count(): int

// Convert to array
public function toArray(): array

// Convert to JSON
public function toJson(int $options = 0): string
```

#### Filtering Methods

```php
// Filter items
public function filter(?callable $callback = null): static

// Reject items
public function reject(callable $callback): static

// Get unique items
public function unique(mixed $key = null): static

// Get items where key equals value
public function where(string $key, mixed $operator = null, mixed $value = null): static

// Get items where key is in array
public function whereIn(string $key, mixed $values): static

// Get items where key is not in array
public function whereNotIn(string $key, mixed $values): static
```

#### Transformation Methods

```php
// Map over items
public function map(callable $callback): static

// Transform items
public function transform(callable $callback): static

// Pluck specific key
public function pluck(mixed $value, ?mixed $key = null): static

// Group items by key
public function groupBy(mixed $groupBy, bool $preserveKeys = false): static

// Key items by attribute
public function keyBy(mixed $keyBy): static

// Sort items
public function sort(?callable $callback = null): static

// Sort by key
public function sortBy(mixed $callback, int $options = SORT_REGULAR, bool $descending = false): static

// Reverse collection
public function reverse(): static
```

#### Mathematical Methods

```php
// Sum values
public function sum(mixed $callback = null): mixed

// Get average
public function avg(mixed $callback = null): mixed

// Get median
public function median(mixed $key = null): mixed

// Get minimum value
public function min(mixed $callback = null): mixed

// Get maximum value
public function max(mixed $callback = null): mixed
```

## Migrations

### `Horizon\Database\Migrations\Migration`

Base migration class.

```php
abstract class Migration
{
    // Run the migration
    abstract public function up(): void;
    
    // Reverse the migration
    abstract public function down(): void;
    
    // Get migration connection
    public function getConnection(): ?string;
}
```

### `Horizon\Database\Migrations\Migrator`

Migration runner class.

```php
// Run migrations
public function run(array $options = []): array

// Rollback migrations
public function rollback(array $options = []): array

// Reset all migrations
public function reset(): array

// Get migration status
public function getRepository(): MigrationRepository

// Check if migrations need to run
public function repositoryExists(): bool
```

## Schema Builder

### `Horizon\Database\Schema\Builder`

Schema builder for creating and modifying tables.

#### Table Operations

```php
// Create new table
public static function create(string $table, Closure $callback): void

// Modify existing table
public static function table(string $table, Closure $callback): void

// Drop table
public static function drop(string $table): void

// Drop table if exists
public static function dropIfExists(string $table): void

// Rename table
public static function rename(string $from, string $to): void

// Check if table exists
public static function hasTable(string $table): bool

// Check if column exists
public static function hasColumn(string $table, string $column): bool
```

### `Horizon\Database\Schema\Blueprint`

Table blueprint for defining table structure.

#### Column Types

```php
// Primary key (auto-increment)
public function id(): ColumnDefinition

// Big integer primary key
public function bigIncrements(string $column = 'id'): ColumnDefinition

// String column
public function string(string $column, int $length = 255): ColumnDefinition

// Text column
public function text(string $column): ColumnDefinition

// Integer column
public function integer(string $column): ColumnDefinition

// Big integer column
public function bigInteger(string $column): ColumnDefinition

// Boolean column
public function boolean(string $column): ColumnDefinition

// Decimal column
public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition

// Float column
public function float(string $column, int $precision = 53): ColumnDefinition

// Date column
public function date(string $column): ColumnDefinition

// DateTime column
public function dateTime(string $column): ColumnDefinition

// Timestamp column
public function timestamp(string $column): ColumnDefinition

// Timestamps (created_at, updated_at)
public function timestamps(): void

// JSON column
public function json(string $column): ColumnDefinition

// Foreign key column
public function foreignId(string $column): ColumnDefinition

// UUID column
public function uuid(string $column): ColumnDefinition
```

#### Column Modifiers

```php
// Make column nullable
public function nullable(bool $value = true): ColumnDefinition

// Set default value
public function default(mixed $value): ColumnDefinition

// Set column as unique
public function unique(?string $indexName = null): ColumnDefinition

// Set column comment
public function comment(string $comment): ColumnDefinition

// Set column after another column
public function after(string $column): ColumnDefinition

// Set column as first
public function first(): ColumnDefinition

// Set column as unsigned
public function unsigned(): ColumnDefinition

// Set column auto increment
public function autoIncrement(): ColumnDefinition
```

#### Indexes

```php
// Primary key
public function primary(mixed $columns, ?string $name = null): Blueprint

// Unique index
public function unique(mixed $columns, ?string $name = null): Blueprint

// Regular index
public function index(mixed $columns, ?string $name = null): Blueprint

// Foreign key constraint
public function foreign(mixed $columns, ?string $name = null): ForeignKeyDefinition

// Drop index
public function dropIndex(mixed $index): Blueprint

// Drop foreign key
public function dropForeign(mixed $index): Blueprint
```

## Factory

### `Horizon\Database\Factories\Factory`

Model factory for generating fake data.

```php
abstract class Factory
{
    // Model class name
    protected string $model;
    
    // Define model's default state
    abstract public function definition(): array;
    
    // Create model instances
    public function count(int $count): static
    
    // Make model instance without saving
    public function make(array $attributes = []): Model
    
    // Create and save model instance
    public function create(array $attributes = []): Model
    
    // Apply state to factory
    public function state(mixed $state): static
    
    // Configure factory
    public function configure(): static
    
    // After making callback
    public function afterMaking(Closure $callback): static
    
    // After creating callback
    public function afterCreating(Closure $callback): static
    
    // Has relationship
    public function has(Factory $factory, ?string $relationship = null): static
    
    // For relationship
    public function for(Model $parent, ?string $relationship = null): static
}
```

## Seeder

### `Horizon\Database\Seeder`

Base seeder class for populating database.

```php
abstract class Seeder
{
    // Run the seeder
    abstract public function run(): void;
    
    // Get database connection
    public function getConnection(?string $name = null): ConnectionInterface
    
    // Disable foreign key checks
    protected function disableForeignKeyChecks(): void
    
    // Enable foreign key checks
    protected function enableForeignKeyChecks(): void
    
    // Truncate table
    protected function truncate(string $table): void
    
    // Get current timestamp
    protected function now(): string
    
    // Call another seeder
    protected function call(string|array $class): void
}
```

## Security Classes

### `Horizon\Database\Eloquent\SecurityAuditor`

Security monitoring and logging.

```php
// Log security event
public static function logEvent(string $event, string $level = 'info', array $context = []): void

// Log mass assignment attempt
public static function logMassAssignment(string $model, string $attribute, bool $allowed, array $context = []): void

// Log security violation
public static function logViolation(string $type, string $attribute, mixed $value, array $context = []): void

// Log SQL injection attempt
public static function logSqlInjection(string $attribute, string $value, array $context = []): void

// Get audit log
public static function getAuditLog(): array

// Get security statistics
public static function getSecurityStats(): array

// Check for suspicious activity
public static function isSuspiciousActivity(?string $sessionId = null): bool

// Clear audit log
public static function clearLog(): void
```

### `Horizon\Database\Eloquent\MassAssignmentException`

Exception thrown for mass assignment violations.

```php
// Create exception for specific attribute
public static function forAttribute(string $model, string $attribute): static

// Create exception for model
public static function forModel(string $model): static

// Create exception for guarded attribute
public static function forGuardedAttribute(string $model, string $attribute): static
```

### `Horizon\Database\Eloquent\SecurityException`

Exception thrown for security violations.

```php
// Get attribute that caused violation
public function getAttribute(): ?string

// Get violation type
public function getViolationType(): ?string

// Create SQL injection exception
public static function sqlInjection(string $attribute, string $value): static

// Create XSS attack exception
public static function xssAttack(string $attribute, string $value): static

// Create invalid format exception
public static function invalidFormat(string $attribute, string $expectedFormat, mixed $value): static
```

## Performance Classes

### `Horizon\Database\Eloquent\PerformanceMonitor`

Performance monitoring and analysis.

```php
// Monitor database operation
public static function monitor(Closure $callback, array $context = []): mixed

// Record query execution
public static function recordQuery(string $sql, array $bindings = [], float $time = 0): void

// Record cache hit
public static function recordCacheHit(string $key): void

// Record cache miss
public static function recordCacheMiss(string $key): void

// Get performance metrics
public static function getMetrics(): array

// Get query log
public static function getQueryLog(): array

// Get slow queries
public static function getSlowQueries(): array

// Get duplicate queries
public static function getDuplicateQueries(): array

// Get performance report
public static function getReport(): array

// Reset monitoring data
public static function reset(): void

// Enable/disable monitoring
public static function enabled(bool $enabled = true): void
```

### `Horizon\Database\ConnectionPool`

Database connection pool manager.

```php
// Get connection from pool
public function getConnection(string $name = 'default'): ConnectionInterface

// Release connection to pool
public function releaseConnection(ConnectionInterface $connection, string $name = 'default'): void

// Warm up connection pool
public function warmUp(string $name = 'default'): void

// Perform health check
public function healthCheck(): array

// Get pool statistics
public function getStats(): array

// Close all connections
public function closeAll(): void

// Execute with pooled connection
public function executeWithConnection(callable $callback, string $poolName = 'default'): mixed
```

---

This API reference provides comprehensive documentation for all public methods and classes in the Horizon Framework ORM system. Use it as a quick reference while developing your applications.