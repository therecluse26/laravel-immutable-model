# Specification: ImmutableModel

**Package:** `brighten/immutable-model`
**Namespace:** `Brighten\ImmutableModel`
**Scope:** read-only Eloquent models for Laravel 11, 12 and 13 on PHP 8.2+ (8.3+ for Laravel 13)

This document is the reference for how the package must behave. The code, the README and `CLAUDE.md` must agree with it. When they disagree, fix the one that is wrong and keep this document current.

---

## 1. Purpose

ImmutableModel gives Laravel applications read-only models with Eloquent's read behavior. Typical uses are SQL views, read-only tables, denormalized projections and the read side of CQRS.

The goals, in order:

1. **Read performance.** Faster hydration and serialization, and less memory per model, than Eloquent.
2. **Eloquent read parity.** Every read gives the same result as Eloquent.
3. **No database writes.** Every write that starts from an immutable model throws.

Performance work must not change visible behavior. An optimization is acceptable only when its output, types, exceptions and edge cases are identical to Eloquent's (see section 12).

---

## 2. Core contract

- `ImmutableModel` extends `Illuminate\Database\Eloquent\Model`. Read behavior comes from inheritance.
- The reference for "identical to Eloquent" is the Eloquent version installed with the package (Laravel 11, 12 or 13).
- Every database write that starts from an immutable model throws `ImmutableModelViolationException`.
- No silent failures: a persistence method that could succeed without running SQL must throw too.
- In-memory changes are allowed. They never reach the database.

---

## 3. Architecture

Writes are blocked in two layers.

### 3.1 `ReadOnlyConnection` (main layer)

Every SQL write in Laravel's query builder goes through one of six methods on `ConnectionInterface`: `insert`, `update`, `delete`, `statement`, `affectingStatement`, `unprepared`.

- `ReadOnlyConnection` implements `ConnectionInterface` and wraps the real `Connection`.
- Its six write methods throw `ImmutableModelViolationException::writeAttempt($operation, $sql)`.
- All other methods delegate to the wrapped connection, unchanged. `select()` and `cursor()` forward every argument, including Laravel 13's `$fetchUsing`.
- `transaction()` and `pretend()` pass the read-only connection (not the wrapped one) to the callback.
- `UsesReadOnlyConnection::newBaseQueryBuilder()` binds every model query to a `ReadOnlyConnection`.

This layer blocks Eloquent builder writes, relation writes, pivot queries, `toBase()`, `getQuery()`, and write methods that later Laravel versions add. Do **not** add per-method blocklists to builders or relations.

### 3.2 Model overrides (second layer)

Some persistence methods can return early without SQL (for example, `save()` on a clean model returns `true`). So `ImmutableModel` overrides each of them to throw `ImmutableModelViolationException::persistenceAttempt($method)`:

`save`, `saveQuietly`, `saveOrFail`, `update`, `updateQuietly`, `updateOrFail`, `delete`, `deleteQuietly`, `deleteOrFail`, `forceDelete`, `forceDeleteQuietly`, `restore`, `restoreQuietly`, `create` (static), `forceCreate` (static), `push`, `pushQuietly`, `touch`, `touchQuietly`, `increment`, `decrement`, `incrementQuietly`, `decrementQuietly`.

`forceDelete`, `forceDeleteQuietly`, `restore` and `restoreQuietly` have no declared return type, for compatibility with the `SoftDeletes` trait.

### 3.3 Plain Eloquent classes

Models use Laravel's own `Eloquent\Builder` and relation classes (`HasMany`, `BelongsTo`, `BelongsToMany`, ...). The only relation class in the package is `ImmutableMorphToMany`, because Laravel hard-codes `MorphPivot` in `MorphToMany::newPivot()`.

---

## 4. Base class

- `ImmutableModel` is an abstract class. Models extend it. There is no trait entry point and no service provider.
- The constructor is Eloquent's public constructor. `new UserView()` works and gives an in-memory model.
- Subclasses declare properties without types, because `Model` declares them untyped. A typed redeclaration is a PHP fatal error.
- `$guarded = []`. `fill()` works in memory, so a relation `create()` reaches `save()` and throws `ImmutableModelViolationException`, not `MassAssignmentException`.
- `$timestamps` keeps Eloquent's default (`true`), so `created_at` and `updated_at` are read as dates. `touch()` throws.
- `$connection = null` means Laravel's default connection, like Eloquent.
- `$fillable` and `$touches` have no database effect, because every write throws.

---

## 5. Identity and primary keys

Primary key behavior is Eloquent's. With `$primaryKey = null`, `get()` and `first()` work. `find()` and `findOrFail()` build invalid SQL and fail with a `QueryException`, as in Eloquent.

---

## 6. Hydration

`newFromBuilder()` is overridden for speed. It must give a model that behaves exactly like Eloquent's.

- It creates the instance with `ReflectionClass::newInstanceWithoutConstructor()` (reflection cached per class).
- It calls `bootIfNotBooted()`.
- It runs the trait initializers (`initializeXxx()`) per instance, except `initializeHasAttributes()`.
- It copies `$casts` and `$table` from the query's model, so `withCasts()` and `setTable()` behave as in Eloquent.
- It sets `$attributes`, `exists = true`, `wasRecentlyCreated = false` and the connection.
- It skips `syncOriginal()`, `fill()` and the `retrieved` event.

Public factories:

- `fromRow(array|object $row): static` builds one model through `newFromBuilder()` without a query.
- `fromRows(array $rows): Eloquent\Collection` builds a collection of them.

---

## 7. In-memory behavior

Allowed, and never written to the database:

- `$model->foo = 'x'`, `$model['foo'] = 'x'`, `unset($model['foo'])`, `fill()`
- Mutators (`setFooAttribute()`, `Attribute::set()`) and custom cast `set()` run on assignment, as in Eloquent
- `associate()`, `dissociate()`, `setRelation()`
- `fresh()` and `refresh()` (they read from the database)
- `replicate()` (the replica cannot be saved)

### Dirty tracking (disabled, no-op)

- `getDirty()`, `getChanges()` return `[]`. `isDirty()`, `wasChanged()` return `false`. `isClean()` returns `true`.
- `getOriginal()` and `getRawOriginal()` return the current attributes.
- `syncOriginal()`, `syncChanges()` and `discardChanges()` do nothing.

### Events (disabled, no-op)

- `fireModelEvent()` returns `true` and fires nothing. `getEventDispatcher()` returns `null`.
- `observe()`, `setEventDispatcher()`, `flushEventListeners()` and model event registration (`static::retrieved(...)`, ...) do nothing, without an error.

---

## 8. Casting

Eloquent's casting, by inheritance: scalar casts, `datetime`, `immutable_datetime`, `date`, `immutable_date`, `timestamp`, `array`, `json`, `collection`, `object`, enums, and custom classes that implement `CastsAttributes`. Reads call `get()`. In-memory assignment calls `set()`.

### Date serialization

`SerializesDatesNatively::serializeDate()` formats dates with `DateTimeInterface::format()` instead of Carbon's `toJSON()`. The output must be identical to Eloquent's. Year 0 and years outside 1 to 9999 fall back to Eloquent's implementation. `ImmutableModel`, `ImmutablePivot` and `ImmutableMorphPivot` use it.

---

## 9. Querying

All Eloquent read methods work by inheritance: conditions, selects, joins, grouping, ordering, limits, aggregates, `exists()`, `pluck()`, eager loading (`with()`, `withCount()`, constraints, nesting), pagination (`paginate()`, `simplePaginate()`, `cursorPaginate()`), `chunk()`, `lazy()`, `cursor()`, `lockForUpdate()`, and global and local scopes.

Builder writes (`insert()`, `update()`, `delete()`, `upsert()`, `truncate()`, `insertOrIgnoreUsing()`, `increment()`, ...) throw at `ReadOnlyConnection`.

---

## 10. Relationships

### 10.1 Supported types

`belongsTo`, `hasOne`, `hasMany`, `hasOneThrough`, `hasManyThrough`, `belongsToMany`, `morphOne`, `morphMany`, `morphTo`, `morphToMany`, `morphedByMany`. Relations can point from immutable models to Eloquent models, and from Eloquent models to immutable models.

### 10.2 Writes through relations

- A relation to an immutable model queries through the related model's builder, so `create()`, `attach()`, `detach()`, `sync()`, `toggle()`, `updateExistingPivot()` and similar throw at `ReadOnlyConnection`. This holds even when the parent is an Eloquent model.
- A relation to an Eloquent model uses the Eloquent model's connection. Its writes are **not** blocked.

### 10.3 Pivot models

- `ImmutableModel::newPivot()` returns `ImmutablePivot`. `BelongsToMany` asks the related model for the pivot, so a `belongsToMany()` to an immutable model always gives an `ImmutablePivot`.
- `ImmutableModel::newMorphToMany()` returns `ImmutableMorphToMany`, whose `newPivot()` returns `ImmutableMorphPivot`. `MorphToMany` builds the pivot from the parent's relation class, so this only applies when the **parent** is immutable.
- A `morphToMany()` / `morphedByMany()` declared on an Eloquent parent gives a plain `MorphPivot`, even when the related model is immutable. `->using(ImmutableMorphPivot::class)` makes it immutable. The README must say this.
- On an immutable parent, a pivot class set with `using()` must extend `ImmutablePivot` (or `ImmutableMorphPivot` for `morphToMany()`). Any other class throws `ImmutableModelConfigurationException::mutablePivot()` when the relation loads.
- Pivot models are stricter than models: `__set()`, `offsetSet()` and `offsetUnset()` throw `ImmutableModelViolationException::attributeMutation()`. Their `save()`, `delete()` and `update()` throw.

### 10.4 Default foreign keys

`getForeignKey()` removes an `Immutable` prefix from the class name: `ImmutableUser` gives `user_id` (Eloquent gives `immutable_user_id`). This is the one intended difference from Eloquent's defaults. It lets a read model named after its mutable twin use the same schema.

- It affects `hasOne`, `hasMany`, `hasOneThrough`, `hasManyThrough`, and the pivot keys of `belongsToMany`, `morphToMany` and `morphedByMany`.
- It does not affect `belongsTo` (the key comes from the relation method name) or the default pivot table name.

---

## 11. Collections

Query results are Laravel's `Eloquent\Collection`. Collection methods (`push()`, `transform()`, `pop()`, ...) work normally, because they only change memory. The models in the collection keep their rules: `$collection->first()->save()` throws.

---

## 12. Performance rules

- Performance is the main goal. Measure every optimization on a real database (MySQL) with an A/B run of `benchmarks/read-benchmark.php` before keeping it.
- Instrumenting profilers (SPX and similar) overstate small functions that are called very often. Confirm with an A/B run.
- An optimization must give identical observable results to Eloquent. Add a parity test in `tests/Parity/` that compares with a real Eloquent model, including edge cases.

---

## 13. Exceptions

| Class | Parent | Factories |
|-------|--------|-----------|
| `ImmutableModelViolationException` | `LogicException` | `attributeMutation(string $key)`, `persistenceAttempt(string $method)`, `writeAttempt(string $operation, string $sql)` |
| `ImmutableModelConfigurationException` | `RuntimeException` | `mutablePivot(string $pivotClass, string $requiredParent)` |

`writeAttempt()` shows the SQL with `?` placeholders. It must never include binding values.

---

## 14. Guarantees and limits

ImmutableModel is a guardrail in application code, not a database permission system. These are **not** blocked:

- `$model->getConnection()` returns the real connection.
- The `DB` facade, and Eloquent models on the same table.
- Relations to Eloquent models (section 10.2).
- A `morphToMany()` pivot on an Eloquent parent without `using(ImmutableMorphPivot::class)` (section 10.3).

For a hard guarantee, use a database user with read-only permissions.

---

## 15. Testing

- `tests/Unit/`: behavior and write blocking. Every write test asserts the exception **and** that the database did not change (see `assertWriteBlocked()` in `tests/Unit/ReadOnlyConnectionTest.php`).
- `tests/Parity/`: compares each read behavior with an equivalent Eloquent model in `tests/Models/Eloquent/`.
- `tests/Benchmarks/`: in-process comparison with Eloquent (not run in CI).
- The suite runs on SQLite in memory by default. Set `DB_CONNECTION=mysql` or `pgsql` (and `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) to run on a real database.
- CI runs Laravel 11, 12 and 13 on PHP 8.2 to 8.5, the Unit and Parity suites on MySQL 8.4 and Postgres 16, and PHPStan (Larastan, level 6, `src/` only, no baseline) once per Laravel version.

Every new feature needs tests for the correct behavior and for write blocking.

---

## 16. Package layout

```
src/
├── ImmutableModel.php                  # Abstract base class (extends Eloquent\Model)
├── ReadOnlyConnection.php              # Connection wrapper; the 6 SQL write methods throw
├── Concerns/
│   ├── SerializesDatesNatively.php     # Native date formatting for toArray()/JSON
│   └── UsesReadOnlyConnection.php      # Binds model queries to ReadOnlyConnection
├── Exceptions/
│   ├── ImmutableModelViolationException.php
│   └── ImmutableModelConfigurationException.php
└── Relations/
    ├── ImmutableMorphToMany.php        # Only overrides newPivot()
    ├── ImmutablePivot.php              # Immutable pivot for BelongsToMany
    └── ImmutableMorphPivot.php         # Immutable pivot for MorphToMany

benchmarks/read-benchmark.php           # Eloquent vs ImmutableModel on a real database
tests/{Unit,Parity,Benchmarks,Models,database}/
```

`composer.json` requires `php ^8.2` and `illuminate/database` + `illuminate/support` `^11.0|^12.0|^13.0`. There is no service provider and no auto-discovery. Development files are `export-ignore`d in `.gitattributes`, so the Composer dist archive holds only `src/`, `composer.json`, `README.md`, `CHANGELOG.md` and `LICENSE`.
