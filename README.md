# ImmutableModel

**An Eloquent-compatible, read-only model kernel for Laravel 11+**

ImmutableModel provides first-class, enforceable read-only models for Laravel applications. It's perfect for SQL views, read-only tables, denormalized projections, and as a CQRS read-side primitive.

## What "Immutable" Means

ImmutableModel enforces **database immutability**, not strict object immutability:

| Operation | Allowed? | Example |
|-----------|----------|---------|
| Read from database | ✅ Yes | `User::find(1)`, `User::where(...)->get()` |
| In-memory attribute changes | ✅ Yes | `$user->computed_field = 'value'` |
| Reload from database | ✅ Yes | `$user->fresh()`, `$user->refresh()` |
| In-memory copy | ✅ Yes | `$user->replicate()` (the copy cannot be saved) |
| Database persistence | ❌ Throws | `$user->save()`, `$user->update()`, `$user->delete()` |
| Static write methods | ❌ Throws | `User::create()`, `User::insert()` |

This design prevents accidental database writes while remaining compatible with common Laravel patterns like adding computed properties for API responses, serialization, and working with collections.

## Guarantees and Limits

ImmutableModel is a guardrail in application code. It is not a database permission system.

**Blocked:** every SQL write that starts from an immutable model. Each model query runs on a `ReadOnlyConnection`, which throws on `insert`, `update`, `delete`, and any other write statement. This covers:

- Model methods: `save()`, `update()`, `delete()`, `touch()`, `increment()`, ...
- Builder methods: `User::query()->update()`, `upsert()`, `truncate()`, `insertOrIgnoreUsing()`, ...
- Lower-level builders: `User::query()->toBase()->update()`, `->getQuery()->delete()`
- Relations to immutable models, and their pivots: `$post->comments()->create()`, `$post->tags()->attach()`, `sync()`, `updateExistingPivot()`, ...
- Write methods that future Laravel versions add, because they use the same connection

**Not blocked:**

- `$model->getConnection()` returns the real connection. `$model->getConnection()->table('users')->update(...)` writes.
- The `DB` facade and ordinary Eloquent models on the same table.
- Relations to ordinary (mutable) Eloquent models. `$immutablePost->comments()->create(...)` writes if `Comment` is a normal Eloquent model.

**Custom pivots:** a pivot class set with `->using(MyPivot::class)` must extend `ImmutablePivot` (or `ImmutableMorphPivot` for `morphToMany()`). Any other pivot class throws `ImmutableModelConfigurationException` when the relation loads.

For a hard guarantee, connect with a database user that has read-only permissions.

## Why ImmutableModel?

- **Enforce architectural boundaries**: Prevent accidental database writes at the model level
- **Eliminate persistence bugs**: Any save/update/delete attempt throws immediately - no silent failures
- **Improved performance**: about 40-60% faster hydration, up to about 30% faster eager loading
- **Lower memory footprint**: about 22% less memory (~1.3 KB vs ~1.65 KB per model)
- **Familiar API**: Eloquent-compatible read semantics for easy adoption
- **Laravel ecosystem compatible**: Works with API Resources, serialization, and other common patterns

## Installation

```bash
composer require brighten/immutable-model
```

## Requirements

- PHP 8.2+
- Laravel 11+

## Quick Start

```php
use Brighten\ImmutableModel\ImmutableModel;

class UserView extends ImmutableModel
{
    protected $table = 'user_views';

    protected $primaryKey = 'id';

    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
    ];
}

// Query just like Eloquent
$users = UserView::where('active', true)->get();
$user = UserView::find(1);
$user = UserView::with('posts')->first();

// In-memory changes are allowed (for computed fields, API responses, etc.)
$user->computed_field = 'some value';  // Works fine
$user->name = 'Modified';              // Works fine (in-memory only)

// But database persistence is blocked
$user->save();              // Throws ImmutableModelViolationException
$user->update([...]);       // Throws ImmutableModelViolationException
$user->delete();            // Throws ImmutableModelViolationException
UserView::create([...]);    // Throws ImmutableModelViolationException
```

## API Reference

### Model Configuration

```php
class MyModel extends ImmutableModel
{
    // Required: The database table
    protected $table = 'my_table';

    // Optional: Primary key (null = non-identifiable model)
    protected $primaryKey = 'id';

    // Optional: Database connection (null = default)
    protected $connection = null;

    // Optional: Attribute casting
    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
    ];

    // Optional: Relations to eager load by default
    protected $with = ['author'];

    // Optional: Accessors to append to array/JSON output
    protected $appends = ['full_name'];

    // Optional: Hidden attributes
    protected $hidden = ['internal_id'];

    // Optional: Visible attributes (whitelist)
    protected $visible = ['id', 'name', 'email'];
}
```

### Querying

All standard Eloquent read operations are supported:

```php
// Finding records
MyModel::find($id);
MyModel::findOrFail($id);
MyModel::first();
MyModel::all();

// Where clauses
MyModel::where('status', 'active')
    ->where('created_at', '>', now()->subWeek())
    ->orWhere('featured', true)
    ->whereIn('category_id', [1, 2, 3])
    ->whereNotNull('published_at')
    ->get();

// Ordering & limiting
MyModel::orderBy('created_at', 'desc')
    ->limit(10)
    ->offset(20)
    ->get();

// Aggregates
MyModel::count();
MyModel::sum('price');
MyModel::avg('rating');
MyModel::max('views');
```

### Relationships

Supported relationship types:

```php
class Post extends ImmutableModel
{
    protected $table = 'posts';

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function featuredImage()
    {
        return $this->hasOne(Image::class, 'post_id', 'id');
    }
}

// Eager loading
$posts = Post::with('author', 'comments')->get();

// Eager loading with constraints
$posts = Post::with(['comments' => fn($q) => $q->where('approved', true)])->get();

// Lazy loading (works, but watch for N+1)
$post = Post::find(1);
$author = $post->author;

// Relation queries
$comments = $post->comments()->where('approved', true)->get();
```

### Casting

Full Eloquent casting support:

```php
protected $casts = [
    // Scalar types
    'count' => 'int',
    'price' => 'float',
    'active' => 'bool',
    'name' => 'string',

    // Date/time
    'published_at' => 'datetime',
    'birthday' => 'date',
    'updated_at' => 'immutable_datetime',
    'timestamp' => 'timestamp',

    // Complex types
    'settings' => 'array',
    'metadata' => 'json',
    'tags' => 'collection',

    // Custom casters
    'address' => AddressCast::class,
];
```

Custom casters must implement `Illuminate\Contracts\Database\Eloquent\CastsAttributes`. Only the `get()` method is called.

### Collections

Query results return Laravel's standard `Eloquent\Collection`. All collection methods work normally - immutability is enforced on **database operations**, not on in-memory manipulation:

```php
$users = User::all();

// All collection operations work normally
$active = $users->filter(fn($u) => $u->active);
$sorted = $users->sortBy('name');
$names = $users->pluck('name');
$mapped = $users->map(fn($u) => $u->toArray());
$users->push($newUser);     // Works - this is in-memory only
$users->transform(fn($u) => $u);  // Works

// In-memory model changes are allowed
$users->first()->name = 'New';       // Works (in-memory only)
$users->first()->computed = 'value'; // Works (add computed fields)

// But database persistence is blocked
$users->first()->save();   // Throws ImmutableModelViolationException
$users->first()->delete(); // Throws ImmutableModelViolationException
```

### Pagination

Full pagination support:

```php
$paginated = MyModel::paginate(15);
$simple = MyModel::simplePaginate(15);
$cursor = MyModel::cursorPaginate(15);
```

### Chunking & Lazy Loading

```php
// Chunk for batch processing
MyModel::chunk(1000, function ($models) {
    foreach ($models as $model) {
        // Process
    }
});

// Cursor for memory-efficient iteration
foreach (MyModel::cursor() as $model) {
    // Process one at a time
}
```

### Global Scopes

Apply query constraints automatically using Laravel's native `Scope` interface:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('tenant_id', auth()->user()->tenant_id);
    }
}

class TenantModel extends ImmutableModel
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}

// Bypass scopes when needed
TenantModel::withoutGlobalScopes()->get();
TenantModel::withoutGlobalScope(TenantScope::class)->get();
```

### Hydration from Raw Data

Create models from existing data without database queries:

```php
// Single model
$user = User::fromRow(['id' => 1, 'name' => 'John']);

// Collection of models
$users = User::fromRows([
    ['id' => 1, 'name' => 'John'],
    ['id' => 2, 'name' => 'Jane'],
]);
```

## Comparison: ImmutableModel vs Eloquent

| Feature | ImmutableModel | Eloquent |
|---------|---------------|----------|
| Read queries | Yes | Yes |
| Relationships | Yes | Yes |
| Eager loading | Yes | Yes |
| Attribute casting | Yes | Yes |
| Accessors | Yes | Yes |
| Pagination | Yes | Yes |
| Global scopes | Yes | Yes |
| Write operations | **Throws** | Yes |
| Dirty tracking | No | Yes |
| Events/Observers | No | Yes |
| Mutators | No | Yes |
| Timestamps | Read as dates; `touch()` throws | Yes |
| Mass assignment | In memory only | Yes |

## Performance

Benchmarks show ImmutableModel is faster and uses less memory for read operations. Figures are medians of 3 runs of `tests/Benchmarks/HydrationBenchmark.php` (PHP 8.4, Laravel 11.48, SQLite in memory). Run `./vendor/bin/phpunit --testsuite Benchmarks` to measure on your own setup.

### Hydration Speed

| Rows | Eloquent | ImmutableModel | Improvement |
|------|----------|----------------|-------------|
| 100 | 0.52ms | 0.24ms | -57% |
| 1,000 | 5.26ms | 2.50ms | -54% |
| 10,000 | 78.40ms | 30.73ms | -59% |
| 100,000 | 668.11ms | 413.57ms | -42% |

### Memory Usage

| Rows | Eloquent | ImmutableModel | Per Model (E) | Per Model (I) | Savings |
|------|----------|----------------|---------------|---------------|---------|
| 100 | 166 KB | 129 KB | 1.66 KB | 1.29 KB | 22% |
| 1,000 | 1.61 MB | 1.26 MB | 1.65 KB | 1.29 KB | 22% |
| 10,000 | 16.2 MB | 12.6 MB | 1.66 KB | 1.29 KB | 22% |
| 100,000 | 161.5 MB | 125.6 MB | 1.65 KB | 1.29 KB | 22% |

### Eager Loading (10 posts per user)

| Users | Models | Eloquent | Immutable | Time Δ | Eloquent Mem | Immutable Mem | Mem Δ |
|-------|--------|----------|-----------|--------|--------------|---------------|-------|
| 10 | 110 | 2.76ms | 2.63ms | -4% | 184 KB | 145 KB | 22% |
| 100 | 1,100 | 11.42ms | 8.24ms | -29% | 1.76 MB | 1.36 MB | 22% |
| 1,000 | 11,000 | 99.87ms | 72.30ms | -28% | 17.53 MB | 13.59 MB | 22% |

## Use Cases

ImmutableModel is ideal for:

- **SQL Views**: Represent database views as read-only models
- **Read Replicas**: Query read-only database replicas safely
- **CQRS Read Models**: Enforce read-side immutability in CQRS architectures
- **Denormalized Projections**: Work with pre-computed, read-only data
- **API Responses**: Build response data with computed fields, knowing it won't accidentally persist
- **Architectural Boundaries**: Enforce that certain models are never written to from application code

## Not Intended For

- Models that need write operations
- Models using Eloquent events/observers
- Models requiring dirty tracking or automatic timestamp updates
- Drop-in replacement for all Eloquent models

## Exceptions

| Exception | When Thrown |
|-----------|-------------|
| `ImmutableModelViolationException` | Any database write attempt (save, update, delete, create, attach, etc.). Blocked SQL is shown with `?` placeholders; binding values are never included. |
| `ImmutableModelConfigurationException` | Invalid configuration: a custom pivot class that does not extend `ImmutablePivot` / `ImmutableMorphPivot` |

## Contributing

Contributions are welcome! Please ensure all tests pass before submitting a PR:

```bash
./vendor/bin/phpunit
```

## License

MIT License. See [LICENSE](LICENSE) for details.
