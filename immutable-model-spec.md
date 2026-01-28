# Project: ImmutableModel
**An Eloquent-compatible, read-only model kernel for Laravel 11+**

**Namespace**: `Brighten\ImmutableModel`

Output `<promise>IMMUTABLE_MODEL_COMPLETE</promise>` when all phases are done.

---

## Purpose
Provide first-class, enforceable **immutable** read-only models for Laravel applications—especially SQL views and read-contract tables—with **Eloquent-identical read semantics**, **strict immutability**, and **no Active Record persistence lifecycle**.

This package exists to:
- Enforce architectural read/write boundaries
- Eliminate accidental writes
- Reduce Eloquent hydration and lifecycle overhead
- Preserve familiar Eloquent ergonomics
- Serve as a CQRS read-side primitive

---

## Core Contract
- Read semantics must be identical to Eloquent
- Mutation and persistence must be impossible
- Immutability must be enforced at runtime
- The implementation MUST NOT extend `Illuminate\Database\Eloquent\Model`
- Eloquent internals may be reused compositionally where beneficial
- Some inert Eloquent machinery is acceptable if it is provably unreachable
- Where behavior is described as "identical to Eloquent", Laravel 11+ Eloquent Model behavior is the reference, except where explicitly forbidden.

---

## Eloquent Parity Scope (Hard Boundary)

Where behavior is described as "identical to Eloquent", parity applies **only** to:
- Behaviors explicitly listed in this document
- Behaviors directly exercised by the test suite

All other Eloquent APIs, properties, or behaviors are **out of scope** and MUST either:
- Be absent, or
- Throw `ImmutableModelViolationException` if invoked

No attempt should be made to achieve full surface-area compatibility with `Illuminate\Database\Eloquent\Model`.

---

**The implementation MUST proceed in the following phases, in order**:

1. Core kernel (ImmutableModel, exceptions, attribute storage)
2. Query builder and hydration pipeline
3. Casting subsystem
4. Relationships
5. ImmutableCollection
6. Global scopes
7. Tests
8. Documentation

A phase MUST be complete before the next begins.

---

## Explicit API Assumptions (Non-Negotiable)

### Base Class
- `ImmutableModel` is an **abstract base class**
- All immutable models MUST extend `ImmutableModel`
- Traits MUST NOT be used as an entrypoint
- No ServiceProvider is required; the package works standalone

(Reference skeleton; do not treat as runnable code)
- abstract class ImmutableModel implements ArrayAccess, JsonSerializable
  - protected string $table
  - protected ?string $primaryKey = 'id'
  - protected ?string $connection = null  // null = Laravel's default connection
  - protected bool $incrementing = false
  - protected string $keyType = 'int'
  - protected array $casts = []
  - protected array $with = []
  - protected array $appends = []
  - protected array $hidden = []
  - protected array $visible = []

### Connection Default
- `$connection = null` means use Laravel's default connection (`config('database.default')`)
- This mirrors Eloquent's behavior

### Forbidden Model Properties
- `$fillable`, `$guarded`, `$timestamps`, `$touches`
- Any persistence-related configuration

---

## Identity & Primary Key Semantics

- A primary key is **optional but explicit**
- `$primaryKey = null` means the model is **non-identifiable**

| Operation      | PK Defined | PK Missing |
|----------------|------------|------------|
| `find()`       | Allowed    | **Throws** |
| `findOrFail()` | Allowed    | **Throws** |
| `first()`      | Allowed    | Allowed    |
| `get()`        | Allowed    | Allowed    |

> Any identity-based operation on a model without a primary key MUST throw immediately.

---

## Hydration Boundary (Hard Constraint)

- The constructor is `final` and **not public**
- Direct instantiation is forbidden
- Models are hydrated **only** from query results or explicit static factory methods

### Hydration API (Simplified)

(Reference signatures; not fenced code blocks)
- final protected static function hydrateFromRow(array|stdClass $row): static
- public static function fromRow(array|stdClass $row): static
- public static function fromRows(iterable $rows): ImmutableCollection

The `fromRow()` and `fromRows()` methods are public convenience wrappers that delegate to `hydrateFromRow()`. No context object is required.

### Explicitly Forbidden
- `new ImmutableModel(...)`
- Mass assignment

---

## Required Behavior

### ImmutableModel
- Acts as a read-only, immutable model
- Stores attributes and relations
- Supports:
  - Attribute access (`$model->foo`)
  - Array access (`$model['foo']`) - read-only
  - Accessors (`getXxxAttribute`)
  - `$appends`, `$hidden`, `$visible`
  - `toArray()`, `toJson()`, `JsonSerializable`
- Any attempt to mutate attributes or relations MUST throw immediately
  - `__set()` throws
  - `offsetSet()` throws
  - `offsetUnset()` throws
  - Relation reassignment throws
- No silent failures

---

## Casting

- Full Eloquent casting parity:
  - Scalar casts (`int`, `float`, `bool`, `string`)
  - `datetime` / `immutable_datetime` / `date` / `timestamp`
  - `array`, `json`, `collection`
  - Custom cast classes
- Custom cast classes MUST implement `Illuminate\Contracts\Database\Eloquent\CastsAttributes`
- Only the `get()` method is invoked; `set()` is never called
- Cast resolution and caching MUST mirror Eloquent behavior as closely as possible
- Write-side cast hooks MUST NOT be reachable

---

## Querying

### Builder Wrapper
- Query ergonomics must match Eloquent
- All queries flow through `ImmutableQueryBuilder`
- The underlying Laravel builder MUST NEVER be exposed
- Fluent, chainable API

### Supported Read Methods

**Conditions:**
- `where()`, `orWhere()`, `whereIn()`, `whereNotIn()`, `whereBetween()`
- `whereNull()`, `whereNotNull()`, `whereDate()`, `whereColumn()`
- `when()`, `unless()`

**Selection:**
- `select()`, `addSelect()`
- `distinct()`

**Ordering & Limiting:**
- `orderBy()`, `orderByDesc()`, `latest()`, `oldest()`
- `limit()`, `offset()`, `skip()`, `take()`

**Joins & Grouping:**
- `join()`, `leftJoin()`, `rightJoin()`
- `groupBy()`, `having()`

**Eager Loading:**
- `with()`, `withCount()`

### Terminal Methods

- `get(): ImmutableCollection`
- `first(): ?ImmutableModel`
- `firstOrFail(): ImmutableModel`
- `find(mixed $id): ?ImmutableModel`
- `findOrFail(mixed $id): ImmutableModel`
- `pluck(string $column, ?string $key = null): Collection` (base Collection, not ImmutableCollection)
- `count(): int`
- `exists(): bool`
- `doesntExist(): bool`
- `sum()`, `avg()`, `min()`, `max()`

### Pagination

Full pagination support:
- `paginate(): LengthAwarePaginator` (items are ImmutableCollection)
- `simplePaginate(): Paginator`
- `cursorPaginate(): CursorPaginator`

### Chunking & Lazy Iteration

- `chunk(int $count, callable $callback): bool`
- `cursor(): LazyCollection` (yields ImmutableModels)

### Blocked Methods (throw ImmutableModelViolationException)

- `create()`, `insert()`, `update()`, `delete()`, `upsert()`
- `save()`, `push()`, `touch()`, `increment()`, `decrement()`
- `forceDelete()`, `restore()`, `truncate()`

(Reference shape; not a fenced code block)
- final class ImmutableQueryBuilder
  - where(...): static
  - with(...): static
  - get(): ImmutableCollection
  - first(): ?ImmutableModel
  - firstOrFail(): ImmutableModel
  - find(mixed $id): ?ImmutableModel
  - findOrFail(mixed $id): ImmutableModel
  - paginate(...): LengthAwarePaginator
  - chunk(...): bool
  - cursor(): LazyCollection

---

## Relationships

### Supported Types (v1)
- `belongsTo`
- `hasOne`
- `hasMany`

### Relationship Rules
- Relationship methods use standard Eloquent signatures (e.g. `protected function user(): BelongsTo`)
- Relations may target:
  - Eloquent models
  - ImmutableModels
- Eloquent models MAY define relations pointing to ImmutableModels
- Lazy loading is ENABLED by default
- Eager loading via `with()` MUST behave identically to Eloquent, including:
  - Nested relations (`with('posts.comments')`)
  - Constraint closures (`with(['posts' => fn($q) => $q->where('active', true)])`)
- `setRelation()` is final and MUST throw
- N+1 behavior is identical to Eloquent and is considered a usage concern

### Relation Method Behavior
- Relation methods (e.g., `$model->posts()`) ALWAYS return a query builder
- This allows chaining: `$model->posts()->where('active', true)->get()`
- The builder blocks mutation methods (`create()`, `save()`, etc.) at the builder level
- Property access (`$model->posts`) returns resolved related models or collections

### Cross-Model Relations
- ImmutableModel → ImmutableModel: ✓
- ImmutableModel → Eloquent: ✓
- Eloquent → ImmutableModel: ✓ (via standard Eloquent relations)

### Immutability Scope
- ImmutableModel attributes and relations are immutable
- Attempting to replace a relation on an ImmutableModel MUST throw
  - e.g. `$read->user = $otherUser` throws
  - e.g. `$read->setRelation('user', $otherUser)` throws
- Related models returned from relations may be mutable **if they are Eloquent models**
  - e.g. `$read->user->name = 'Bob'` is allowed if `user` is an Eloquent model
  - e.g. `$read->user->save()` is allowed if `user` is an Eloquent model
- Related models returned from relations MUST be immutable if they are ImmutableModels
  - e.g. `$read->comments[0]->body = 'hi'` throws if `comments` are ImmutableModels

---

## Collections

### ImmutableCollection
- Wraps `Illuminate\Support\Collection`
- Implements:
  - `IteratorAggregate`
  - `Countable`
  - `ArrayAccess` (read-only)

### Collection Rules
- Any attempt to add/remove/replace items MUST throw immediately:
  - `push`, `put`, `forget`, `offsetSet`, `offsetUnset`, `pop`, `shift`, etc.
- A mutable collection may be obtained only via an explicit `toBase()` call

### Transformation Type Preservation
- Transformations that preserve ImmutableModel items return `ImmutableCollection`:
  - `filter()`, `reject()`, `where()`, `whereIn()`, `take()`, `skip()`, `unique()`, `sortBy()`, `values()`
- Transformations that may change item types ALWAYS return base `Collection`:
  - `map()`, `pluck()`, `keys()`, `flatMap()`, `groupBy()`

> Rationale: Runtime type inference for map() adds complexity without significant benefit.
> Users can wrap results manually if needed.

### Collection Hydration

ImmutableCollection MUST provide a static `fromRows()` method.
This method MUST:
- Accept an iterable of rows (array|stdClass)
- Accept the target ImmutableModel class name
- Hydrate each row via the target ImmutableModel::hydrateFromRow()
- Return an ImmutableCollection of ImmutableModels
- Reject mixed model types

ImmutableCollection MUST NOT expose any mutation-capable bulk hydration or replacement APIs.

---

## Global Scopes (Query-Only)

- Supported in v1 with strict limits:
  - Query-only
  - Applied to the underlying builder
  - No model booting
  - No lifecycle hooks
  - No access to model instances
- Static registration only
- No closures
- Can be temporarily disabled via `withoutGlobalScope()`

(Reference interfaces; not fenced code blocks)
- interface ImmutableModelScope { apply(ImmutableQueryBuilder $builder): void; }
- protected static array $globalScopes = [ TenantScope::class ];

---

## Forbidden Features (Hard Fail)
- No persistence of any kind:
  - `save`, `update`, `delete`, `create`, `insert`, `upsert`, etc.
- No dirty tracking
- No timestamps
- No model events or observers
- No model boot methods or trait boot hooks
- No attribute mutators (`setXxxAttribute`)
- No mass assignment
- No silent failure of writes — **ALL write attempts MUST throw**
- Any inherited or reused Eloquent method that could mutate state MUST be explicitly overridden to throw, even if it is believed to be unreachable.

---

## Exceptions

- class ImmutableModelViolationException extends LogicException
- class ImmutableModelConfigurationException extends RuntimeException

Rules:
- Mutation attempts → `ImmutableModelViolationException`
- Invalid model configuration (PK misuse, forbidden properties, forbidden API use) → `ImmutableModelConfigurationException`

---

## Performance Expectations
- Reduced per-row memory footprint vs Eloquent
- Faster hydration for large read sets
- No accidental queries beyond Eloquent-equivalent behavior
- Not expected to beat raw Query Builder

---

## Testing
- SQLite in-memory database

### Test Model Schema

```php
// Users table
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->json('settings')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();
});

// Posts table (user hasMany posts)
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('title');
    $table->text('body');
    $table->boolean('published')->default(false);
    $table->timestamps();
});

// Comments table (post hasMany comments, user hasMany comments)
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->text('body');
    $table->timestamps();
});

// Profiles table (user hasOne profile)
Schema::create('profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained();
    $table->string('bio')->nullable();
    $table->date('birthday')->nullable();
});
```

### Test Categories
Tests MUST verify:
- Eloquent-equivalent read behavior
- All mutation paths throw (attributes, relations, collections)
- Lazy loading and eager loading parity
- Global scopes apply correctly
- Pagination works correctly
- Chunking and cursor iteration work correctly
- All query builder methods behave correctly

### Performance Benchmarks (Formal)

Include a formal benchmark suite comparing against Eloquent:

```php
class HydrationBenchmark
{
    // Hydration benchmarks at various scales
    public function benchmarkHydration100(): void;
    public function benchmarkHydration1000(): void;
    public function benchmarkHydration10000(): void;

    // Memory usage comparison
    public function benchmarkMemoryUsage(): void;

    // Eager loading comparison
    public function benchmarkEagerLoading(): void;
}
```

Benchmark output MUST include a table comparing:
- Hydration time (ms)
- Memory per model (bytes)
- Eager loading overhead

---

## Documentation
Position the package as:

**An architectural boundary and CQRS read-side primitive that enforces immutability while preserving Eloquent ergonomics**

Document clearly:
- Intended use cases (SQL views, read-only tables, denormalized projections)
- Explicit non-goals
- Differences from Eloquent
- Performance characteristics and limitations

### Documentation Deliverables
- `README.md` at package root with:
  - Installation instructions
  - Quick start example
  - Full API reference
  - Comparison table vs Eloquent
- Inline DocBlocks on all public methods
- No separate documentation site required

---

## Package Configuration

### composer.json
- Name: `brighten/immutable-model`
- Require: `php ^8.2`, `illuminate/database ^11.0`, `illuminate/support ^11.0`
- Autoload PSR-4: `Brighten\\ImmutableModel\\` → `src/`
- No Laravel auto-discovery required (no ServiceProvider)

---

## Laravel & PHP Compatibility
- PHP ≥ 8.2
- Laravel 11+
- Allowed Eloquent internals:
  - `Illuminate\Database\Eloquent\Relations\*`
  - `HasAttributes` (read-only subset)
  - Casting infrastructure
  - `Illuminate\Contracts\Database\Eloquent\CastsAttributes` interface

> Reliance on undocumented Eloquent internals is permitted only if wrapped and provably unreachable from mutation paths.

---

## Directory Structure

```
src/
├── ImmutableModel.php              # Abstract base class
├── ImmutableQueryBuilder.php       # Query wrapper
├── ImmutableCollection.php         # Immutable collection
├── Exceptions/
│   ├── ImmutableModelViolationException.php
│   └── ImmutableModelConfigurationException.php
├── Casts/
│   └── CastManager.php             # Cast resolution
├── Relations/
│   ├── ImmutableBelongsTo.php
│   ├── ImmutableHasOne.php
│   └── ImmutableHasMany.php
└── Scopes/
    └── ImmutableModelScope.php     # Interface

tests/
├── TestCase.php
├── Models/
│   ├── ImmutableUser.php
│   ├── ImmutablePost.php
│   ├── ImmutableComment.php
│   └── ImmutableProfile.php
├── Unit/
│   ├── HydrationTest.php
│   ├── ImmutabilityTest.php
│   ├── QueryBuilderTest.php
│   ├── RelationshipTest.php
│   ├── CastingTest.php
│   ├── CollectionTest.php
│   └── GlobalScopeTest.php
└── Benchmarks/
    └── HydrationBenchmark.php
```

---

## Completion Gate (Claude Loop Critical)
Output `<promise>IMMUTABLE_MODEL_COMPLETE</promise>` **only after**:
- All tests pass
- All forbidden features are provably unreachable
- The full public API is documented
