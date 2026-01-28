# ImmutableModel

**An Eloquent-compatible, read-only model kernel for Laravel 11+**

ImmutableModel provides first-class, enforceable immutable read-only models for Laravel applications. It's perfect for SQL views, read-only tables, denormalized projections, and as a CQRS read-side primitive.

## Why ImmutableModel?

- **Enforce architectural boundaries**: Prevent accidental writes at the model level
- **Eliminate mutation bugs**: Any write attempt throws immediately - no silent failures
- **Improved performance**: 47-74% faster hydration, 25-70% faster eager loading
- **Lower memory footprint**: ~41% less memory (~1 KB vs ~1.65 KB per model)
- **Familiar API**: Eloquent-compatible read semantics for easy adoption
- **Type safety**: Strict immutability enforced at runtime

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
    protected string $table = 'user_views';

    protected ?string $primaryKey = 'id';

    protected array $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
    ];
}

// Query just like Eloquent
$users = UserView::where('active', true)->get();
$user = UserView::find(1);
$user = UserView::with('posts')->first();

// But writes are impossible
$user->name = 'New Name';  // Throws ImmutableModelViolationException
$user->save();              // Throws ImmutableModelViolationException
UserView::create([...]);    // Throws ImmutableModelViolationException
```

## API Reference

### Model Configuration

```php
class MyModel extends ImmutableModel
{
    // Required: The database table
    protected string $table = 'my_table';

    // Optional: Primary key (null = non-identifiable model)
    protected ?string $primaryKey = 'id';

    // Optional: Database connection (null = default)
    protected ?string $connection = null;

    // Optional: Attribute casting
    protected array $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
    ];

    // Optional: Relations to eager load by default
    protected array $with = ['author'];

    // Optional: Accessors to append to array/JSON output
    protected array $appends = ['full_name'];

    // Optional: Hidden attributes
    protected array $hidden = ['internal_id'];

    // Optional: Visible attributes (whitelist)
    protected array $visible = ['id', 'name', 'email'];
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
    protected string $table = 'posts';

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
protected array $casts = [
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

Query results return `ImmutableCollection`, which blocks mutation:

```php
$users = User::all();

// Allowed: filtering and transformations that preserve models
$active = $users->filter(fn($u) => $u->active);  // ImmutableCollection
$sorted = $users->sortBy('name');                 // ImmutableCollection

// Allowed: transformations that change types
$names = $users->pluck('name');  // Base Collection
$mapped = $users->map(fn($u) => $u->toArray());  // Base Collection

// Blocked: mutations
$users->push($newUser);   // Throws ImmutableModelViolationException
$users[0] = $other;       // Throws ImmutableModelViolationException

// Escape hatch: explicit conversion to mutable collection
$mutable = $users->toBase();
$mutable->push($newUser);  // Works
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

Apply query constraints automatically:

```php
use Brighten\ImmutableModel\Scopes\ImmutableModelScope;
use Brighten\ImmutableModel\ImmutableQueryBuilder;

class TenantScope implements ImmutableModelScope
{
    public function apply(ImmutableQueryBuilder $builder): void
    {
        $builder->where('tenant_id', auth()->user()->tenant_id);
    }
}

class TenantModel extends ImmutableModel
{
    protected static array $globalScopes = [
        TenantScope::class,
    ];
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
| Timestamps | No | Yes |
| Mass assignment | No | Yes |

## Performance

Benchmarks show ImmutableModel is significantly faster for read operations:

### Hydration Speed

| Rows | Eloquent | ImmutableModel | Improvement |
|------|----------|----------------|-------------|
| 100 | 0.30ms | 0.09ms | -70% |
| 1,000 | 3.09ms | 0.80ms | -74% |
| 10,000 | 34.27ms | 9.37ms | -73% |
| 100,000 | 447.29ms | 236.63ms | -47% |

### Memory Usage

| Rows | Eloquent | ImmutableModel | Per Model (E) | Per Model (I) | Savings |
|------|----------|----------------|---------------|---------------|---------|
| 100 | 166 KB | 97 KB | 1.66 KB | 998 B | 41% |
| 1,000 | 1.61 MB | 973 KB | 1.65 KB | 996 B | 41% |
| 10,000 | 16.2 MB | 9.56 MB | 1.66 KB | 1003 B | 41% |
| 100,000 | 161.5 MB | 95.1 MB | 1.65 KB | 997 B | 41% |

### Eager Loading (10 posts per user)

| Users | Models | Eloquent | Immutable | Time Δ | Eloquent Mem | Immutable Mem | Mem Δ |
|-------|--------|----------|-----------|--------|--------------|---------------|-------|
| 10 | 110 | 1.68ms | 1.27ms | -25% | 184 KB | 105 KB | 43% |
| 100 | 1,100 | 5.75ms | 2.23ms | -61% | 1.76 MB | 1.02 MB | 42% |
| 1,000 | 11,000 | 64.22ms | 19.34ms | -70% | 17.53 MB | 10.23 MB | 42% |

## Use Cases

ImmutableModel is ideal for:

- **SQL Views**: Represent database views as read-only models
- **Read Replicas**: Query read-only database replicas safely
- **CQRS Read Models**: Enforce read-side immutability in CQRS architectures
- **Denormalized Projections**: Work with pre-computed, read-only data
- **API Responses**: Ensure response data cannot be accidentally modified
- **Caching**: Safely cache model instances knowing they won't change

## Not Intended For

- Models that need write operations
- Models using Eloquent events/observers
- Models requiring dirty tracking or timestamps
- Drop-in replacement for all Eloquent models

## Exceptions

| Exception | When Thrown |
|-----------|-------------|
| `ImmutableModelViolationException` | Any write/mutation attempt |
| `ImmutableModelConfigurationException` | Invalid model configuration |

## Contributing

Contributions are welcome! Please ensure all tests pass before submitting a PR:

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
