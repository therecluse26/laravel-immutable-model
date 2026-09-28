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
- Relations to immutable models: `$post->comments()->create()`, `$post->tags()->attach()`, `sync()`, `updateExistingPivot()`, ...
- Pivot models of `belongsToMany()` to an immutable model, and of `morphToMany()` / `morphedByMany()` on an immutable model: `$tag->pivot->save()`, `$tag->pivot->delete()`
- Write methods that future Laravel versions add, because they use the same connection

**Not blocked:**

- `$model->getConnection()` returns the real connection. `$model->getConnection()->table('users')->update(...)` writes.
- The `DB` facade and ordinary Eloquent models on the same table.
- Relations to ordinary (mutable) Eloquent models. `$immutablePost->comments()->create(...)` writes if `Comment` is a normal Eloquent model.
- The pivot model of `morphToMany()` / `morphedByMany()` declared on an ordinary Eloquent model, even when the related model is immutable. Laravel builds that pivot from the parent model, so it is a plain `MorphPivot` on the real connection. Add `->using(ImmutableMorphPivot::class)` to the relation to block it. Queries through the relation, such as `attach()` and `detach()`, are still blocked.

**Custom pivots:** on an immutable model, a pivot class set with `->using(MyPivot::class)` must extend `ImmutablePivot` (or `ImmutableMorphPivot` for `morphToMany()`). Any other pivot class throws `ImmutableModelConfigurationException` when the relation loads.

**Pivot models are stricter than models:** `ImmutablePivot` and `ImmutableMorphPivot` also reject in-memory changes. `$tag->pivot->order = 5` and `$tag->pivot['order'] = 5` throw `ImmutableModelViolationException`.

For a hard guarantee, connect with a database user that has read-only permissions.

## Why ImmutableModel?

- **Enforce architectural boundaries**: Prevent accidental database writes at the model level
- **Eliminate persistence bugs**: Any save/update/delete attempt throws immediately - no silent failures
- **Improved performance**: `get()` + `toArray()` takes 61-65% less time than Eloquent on MySQL (about 2.8x faster), eager loading up to about 20% faster
- **Lower memory footprint**: about 22% less memory (~1.35 KB vs ~1.73 KB per model)
- **Familiar API**: Eloquent-compatible read semantics for easy adoption
- **Laravel ecosystem compatible**: Works with API Resources, serialization, and other common patterns

## Installation

```bash
composer require brighten/immutable-model
```

## Requirements

- PHP 8.2+ (8.3+ for Laravel 13)
- Laravel 11, 12 or 13

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

Every Eloquent relationship type works: `belongsTo()`, `hasOne()`, `hasMany()`, `hasOneThrough()`, `hasManyThrough()`, `belongsToMany()`, `morphOne()`, `morphMany()`, `morphTo()`, `morphToMany()` and `morphedByMany()`. The related model can be immutable or an ordinary Eloquent model.

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

#### Default foreign keys

ImmutableModel removes an `Immutable` prefix from the class name when it builds a default foreign key. This lets a read model named after its mutable twin use the same schema:

| Class | Eloquent default | ImmutableModel default |
|-------|------------------|------------------------|
| `ImmutableUser` (table `users`) | `immutable_user_id` | `user_id` |
| `UserView` | `user_view_id` | `user_view_id` |

This applies where Eloquent calls `getForeignKey()`: `hasOne()`, `hasMany()`, the `*Through()` relations, and the pivot keys of `belongsToMany()`, `morphToMany()` and `morphedByMany()`. `belongsTo()` is not affected, because Eloquent builds that key from the relation method name. The default pivot table name keeps the prefix. If your columns really are named `immutable_user_id`, pass the key to the relation explicitly.

#### Mixing immutable and Eloquent models

A model is writable or not because of its class, not because of how you loaded it. An ordinary Eloquent model stays writable when you reach it through an immutable model, and an immutable model stays read-only when you reach it through an Eloquent model:

```php
$post = ImmutablePost::find(1);

$post->meta->first()->save();       // Writes: PostMeta is an ordinary Eloquent model
$post->meta()->create([...]);       // Writes, for the same reason

$category = Category::find(1);      // Ordinary Eloquent model
$category->posts->first()->save();  // Throws: ImmutablePost is immutable
```

To make the related side read-only too, point the relation at an immutable model for the same table:

```php
class ImmutablePostMeta extends ImmutableModel
{
    protected $table = 'post_meta';
}

class ImmutablePost extends ImmutableModel
{
    public function meta(): HasMany
    {
        return $this->hasMany(ImmutablePostMeta::class); // post_id, see "Default foreign keys"
    }
}
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

Custom casters implement `Illuminate\Contracts\Database\Eloquent\CastsAttributes` as usual. Reads call `get()`. An in-memory assignment such as `$model->address = $value` calls `set()`, like Eloquent. Nothing is written to the database.

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
| Events/Observers | No (`observe()` and event listeners are ignored) | Yes |
| Mutators | In memory only | Yes |
| Timestamps | Read as dates; `touch()` throws | Yes |
| Mass assignment | In memory only | Yes |

## Performance

Measured with `benchmarks/read-benchmark.php` on MySQL 8.4 (Docker, same machine), PHP 8.4 with OPcache on and JIT off. The test model has a JSON cast, a datetime cast and two timestamps. Times are medians in milliseconds.

### Where the speed comes from

- **Serialization:** `toArray()` / JSON output is where most read time goes. Eloquent formats every date through Carbon's `isoFormat()`. ImmutableModel produces the identical string with PHP's native formatter.
- **Hydration:** ImmutableModel builds models without Eloquent's constructor work, such as copying every attribute into `$original`. This also saves memory.

### Query and serialize (`get()->toArray()`)

| Rows | Eloquent | ImmutableModel | Change |
|------|----------|----------------|--------|
| 100 | 6.91 | 2.70 | -61% |
| 1,000 | 74.56 | 26.15 | -65% |
| 10,000 | 761.71 | 274.64 | -64% |

### Separate steps

| Rows | Hydration: Eloquent | Hydration: ImmutableModel | `toArray()`: Eloquent | `toArray()`: ImmutableModel |
|------|------|------|------|------|
| 100 | 0.25 | 0.04 | 6.30 | 2.30 |
| 1,000 | 3.04 | 0.78 | 69.52 | 23.38 |
| 10,000 | 36.26 | 18.41 | 706.32 | 237.08 |

Hydration is the time of `Model::query()->get()` minus `DB::table()->get()` for the same rows.

### Eager loading (10 posts per user)

| Users | Eloquent | ImmutableModel | Change |
|-------|----------|----------------|--------|
| 10 | 1.03 | 1.07 | within noise |
| 100 | 8.43 | 7.48 | -11% |
| 1,000 | 91.67 | 71.08 | -22% |

### Memory (10,000 hydrated models)

| Eloquent | ImmutableModel | Change |
|----------|----------------|--------|
| 16.50 MB (1,730 bytes per model) | 12.92 MB (1,354 bytes per model) | -22% |

### Run it yourself

```bash
docker run -d --name bench-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=bench -p 33062:3306 mysql:8.4
DB_PORT=33062 php benchmarks/read-benchmark.php seed
DB_PORT=33062 composer bench
```

The database runs on the same machine, so there is no network delay. Over a network, each query takes longer by a fixed amount, which lowers the percentage gain of `get()`. The absolute time saved stays the same.

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
