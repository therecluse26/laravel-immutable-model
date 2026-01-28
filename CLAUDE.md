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

1. **MUST NOT extend `Illuminate\Database\Eloquent\Model`** - The package achieves immutability by NOT inheriting from Eloquent
2. **Read semantics identical to Eloquent** - All read operations should work exactly like Eloquent
3. **All write attempts MUST throw** - No silent failures, every mutation path throws `ImmutableModelViolationException`
4. **No persistence lifecycle** - No save(), update(), delete(), create(), insert(), or any persistence method

## Key Files

| File | Purpose |
|------|---------|
| `src/ImmutableModel.php` | Abstract base class - core immutability enforcement |
| `src/ImmutableQueryBuilder.php` | Read-only query builder wrapping Laravel's builder |
| `src/ImmutableCollection.php` | Read-only collection blocking mutations |
| `src/Relations/` | All relationship types (BelongsTo, HasOne, HasMany, etc.) |
| `src/Exceptions/` | Exception classes for violations and config errors |
| `src/Casts/CastManager.php` | Attribute casting system (read-only) |
| `src/Scopes/ImmutableModelScope.php` | Interface for global query scopes |
| `immutable-model-spec.md` | **Full specification** - read this for detailed requirements |

## Immutability Enforcement Patterns

When modifying this codebase, maintain these patterns:

### Attribute Storage
```php
// CORRECT: Private storage, no external mutation
private array $attributes = [];
private array $relations = [];
```

### Constructor
```php
// CORRECT: Final, prevents subclass manipulation
final public function __construct() { }
```

### Magic Methods
```php
// CORRECT: Throw on any set/unset attempt
public function __set($key, $value): never {
    throw ImmutableModelViolationException::attributeMutation($key);
}

public function __unset($key): never {
    throw ImmutableModelViolationException::attributeMutation($key);
}
```

### ArrayAccess
```php
// CORRECT: Read allowed, write throws
public function offsetGet($offset): mixed { /* allowed */ }
public function offsetSet($offset, $value): never { throw ... }
public function offsetUnset($offset): never { throw ... }
```

### Relation Setting
```php
// Public method throws
public function setRelation(string $relation, mixed $value): never {
    throw ImmutableModelViolationException::relationMutation($relation);
}

// Internal method exists for hydration (protected/private)
protected function setRelationInternal(string $relation, mixed $value): void { ... }
```

### Persistence Methods
```php
// All persistence methods throw
public function save(): never { throw ImmutableModelViolationException::persistenceAttempt('save'); }
public function update(): never { throw ImmutableModelViolationException::persistenceAttempt('update'); }
// ... same for delete, create, insert, etc.
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

## Forbidden Features

These must NEVER be implemented:

- `save()`, `update()`, `delete()`, `create()`, `insert()`, `upsert()`
- Dirty tracking (`isDirty()`, `getDirty()`, etc.)
- Timestamps (`$timestamps`, `touch()`)
- Model events and observers
- Mutators (`setXxxAttribute`)
- Mass assignment (`fill()`, `$fillable`, `$guarded`)
- Boot methods and trait hooks

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
throw ImmutableModelViolationException::collectionMutation($method);

// Configuration errors
throw ImmutableModelConfigurationException::missingPrimaryKey($class);
throw ImmutableModelConfigurationException::forbiddenProperty($property);
```

## Quick Reference

| Want to... | Do this |
|------------|---------|
| Add a query method | Add to `ImmutableQueryBuilder`, ensure no mutation |
| Add a relationship | Create in `src/Relations/`, block mutation methods |
| Add a cast type | Extend `CastManager`, only implement `get()` side |
| Block a method | Override and throw `ImmutableModelViolationException` |
| Test mutation blocking | Use `$this->expectException(ImmutableModelViolationException::class)` |
