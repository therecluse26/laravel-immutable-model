# CLAUDE.md - Laravel Immutable Model

## Project Overview

**Package:** `brighten/immutable-model`
**Namespace:** `Brighten\ImmutableModel`
**Purpose:** Read-only, immutable Eloquent-compatible models for Laravel 11+

This package enforces architectural read/write boundaries by providing models that:
- Have Eloquent-identical read semantics
- Make mutation and persistence impossible
- Throw exceptions on any write attempt (no silent failures)

## Non-Negotiable Rules

These constraints are absolute and must never be violated:

1. **Extends `Illuminate\Database\Eloquent\Model`** - The package achieves immutability by extending Eloquent and overriding all persistence methods to throw exceptions. This provides automatic read parity while blocking all writes.
2. **Read semantics identical to Eloquent** - All read operations work exactly like Eloquent (by inheritance)
3. **All write attempts MUST throw** - No silent failures, every mutation path throws `ImmutableModelViolationException`
4. **No persistence lifecycle** - All persistence methods (save, update, delete, create, insert, etc.) throw exceptions

## Key Files

| File | Purpose |
|------|---------|
| `src/ImmutableModel.php` | Abstract base class extending Eloquent\Model with persistence blocked |
| `src/ImmutableEloquentBuilder.php` | Query builder extending Eloquent\Builder with bulk mutations blocked |
| `src/Relations/` | Immutable relationship classes (extend Eloquent relations, block mutations) |
| `src/Relations/ImmutablePivot.php` | Immutable pivot model for BelongsToMany |
| `src/Relations/ImmutableMorphPivot.php` | Immutable pivot model for MorphToMany |
| `src/Exceptions/` | Exception classes for violations and config errors |
| `immutable-model-spec.md` | **Full specification** - read this for detailed requirements |

**Note:** Query results return Laravel's standard `Eloquent\Collection`. Collection methods like `transform()`, `push()`, etc. work normally - the immutability is enforced on the models and database operations, not on in-memory collection manipulation.

## Immutability Enforcement Patterns

The package extends `Eloquent\Model` and enforces immutability through method overrides:

### Architecture
```php
// ImmutableModel extends Eloquent\Model
abstract class ImmutableModel extends Model
{
    public $timestamps = false;  // Disable auto-timestamps

    // Override persistence methods to throw
    // Override event methods to no-op
    // Override dirty tracking to no-op
    // Use custom relation factories
}
```

### Persistence Methods (all throw)
```php
public function save(array $options = []): never {
    throw ImmutableModelViolationException::persistenceAttempt('save');
}

public function update(array $attributes = [], array $options = []): never {
    throw ImmutableModelViolationException::persistenceAttempt('update');
}
// ... same for delete, create, insert, forceDelete, restore, etc.
```

### Events (disabled via no-op)
```php
protected function fireModelEvent($event, $halt = true): bool {
    return true; // Pretend success, do nothing
}

public static function getEventDispatcher(): ?Dispatcher {
    return null; // No dispatcher
}
```

### Dirty Tracking (disabled via no-op)
```php
public function getDirty(): array { return []; }
public function isDirty($attributes = null): bool { return false; }
public function isClean($attributes = null): bool { return true; }
```

### Query Builder
```php
// ImmutableEloquentBuilder blocks bulk mutations
public function insert(array $values): never { throw ...; }
public function update(array $values): never { throw ...; }
public function delete(): never { throw ...; }
public function truncate(): never { throw ...; }
```

### Relation Classes
```php
// Each relation class extends its Eloquent counterpart and blocks mutations
class ImmutableBelongsTo extends BelongsTo {
    public function associate($model): never { throw ...; }
    public function dissociate(): never { throw ...; }
}
```

## Supported Features

### Relationships
- `belongsTo()` - ImmutableBelongsTo
- `hasOne()` - ImmutableHasOne
- `hasMany()` - ImmutableHasMany
- `belongsToMany()` - ImmutableBelongsToMany (with ImmutablePivot)
- `hasOneThrough()` - ImmutableHasOneThrough
- `hasManyThrough()` - ImmutableHasManyThrough
- `morphOne()` - ImmutableMorphOne
- `morphMany()` - ImmutableMorphMany
- `morphTo()` - ImmutableMorphTo
- `morphToMany()` - ImmutableMorphToMany

### Casting
- Scalar: `int`, `float`, `bool`, `string`
- Dates: `datetime`, `date`, `timestamp`, `immutable_datetime`, `immutable_date`
- Complex: `array`, `json`, `collection`, `object`
- Custom: Classes implementing `CastsAttributes` (only `get()` method called)

### Query Methods
- All standard WHERE clauses
- Ordering, limiting, grouping
- Eager loading with `with()`, `withCount()`
- Pagination, chunking, cursor iteration
- Aggregates: `count()`, `sum()`, `avg()`, `min()`, `max()`

## Disabled Features

These features are disabled (throw exceptions or return no-op values):

- **Persistence methods** - `save()`, `update()`, `delete()`, `create()`, `insert()`, `upsert()` all throw
- **Dirty tracking** - `isDirty()` returns false, `getDirty()` returns empty array (no-op, not absent)
- **Timestamps** - `$timestamps = false`, `touch()` throws
- **Model events** - All event methods are no-ops (events never fire)
- **Mass assignment** - `fill()` works for internal hydration but `$fillable`/`$guarded` are not used
- **Mutators** - `setXxxAttribute` methods are not called (no mutation path reaches them)

Note: These methods exist (inherited from Eloquent) but are overridden to be safe.

## Testing

### Run Tests
```bash
./vendor/bin/phpunit
```

### Run Specific Suite
```bash
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Benchmarks
```

### Test Structure
- `tests/Unit/` - All unit tests
- `tests/Benchmarks/` - Performance comparison with Eloquent
- `tests/Models/` - Test model definitions
- `tests/database/migrations/` - Test schema

### Test Requirements
- Every new feature needs tests for:
  1. Correct positive behavior
  2. Mutation blocking (must throw appropriate exception)
- Tests run on PHP 8.2, 8.3, 8.4

## When Adding Features

1. **Read the spec first** - `immutable-model-spec.md` has detailed requirements
2. **Maintain immutability** - Every mutation path must throw
3. **Use correct exceptions:**
   - `ImmutableModelViolationException` - For mutation attempts
   - `ImmutableModelConfigurationException` - For invalid configuration
4. **Add comprehensive tests** - Both success cases and violation cases
5. **Follow existing patterns** - Look at similar implementations in the codebase

## Exception Usage

```php
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;

// Mutation attempts
throw ImmutableModelViolationException::attributeMutation($key);
throw ImmutableModelViolationException::relationMutation($relation);
throw ImmutableModelViolationException::persistenceAttempt($method);

// Configuration errors
throw ImmutableModelConfigurationException::missingPrimaryKey($class);
throw ImmutableModelConfigurationException::forbiddenProperty($property);
```

## Quick Reference

| Want to... | Do this |
|------------|---------|
| Add a query method | Eloquent methods are inherited automatically; block mutations in `ImmutableEloquentBuilder` |
| Add a relationship | Create in `src/Relations/`, extend Eloquent relation, block mutation methods |
| Add a cast type | Uses Eloquent's native casting - just define in model's `$casts` array |
| Block a method | Override and throw `ImmutableModelViolationException` |
| Test mutation blocking | Use `$this->expectException(ImmutableModelViolationException::class)` |
