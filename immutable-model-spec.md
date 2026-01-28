# Project: ImmutableModel  
**An Eloquent-compatible, read-only model kernel for Laravel 11**

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
- Where behavior is described as "identical to Eloquent", Laravel 11.x Eloquent Model behavior is the reference, except where explicitly forbidden.
---

## Eloquent Parity Scope (Hard Boundary)

Where behavior is described as “identical to Eloquent”, parity applies **only** to:
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

(Reference skeleton; do not treat as runnable code)
- abstract class ImmutableModel
  - protected string $table
  - protected ?string $primaryKey = 'id'
  - protected string $connection = 'default'
  - protected bool $incrementing = false
  - protected string $keyType = 'int'
  - protected array $casts = []
  - protected array $with = []
  - protected array $appends = []
  - protected array $hidden = []
  - protected array $visible = []

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
- Models are hydrated **only** from query results
- Models MAY be hydrated from externally obtained query results only via explicit static factory methods.
- Such methods MUST require an ImmutableBuilderContext and MUST NOT accept raw attribute arrays alone.
- All non-query hydration entrypoints MUST delegate to hydrateFromRow().
- Implementations MAY expose public static hydration helpers (e.g. fromRows, fromBaseQuery), provided they require an ImmutableBuilderContext and delegate internally to hydrateFromRow().


(Reference signature; not a fenced code block)
- final protected static function hydrateFromRow(array|stdClass $row, ImmutableBuilderContext $context): static

### Explicitly Forbidden
- `new ImmutableModel(...)`
- Mass assignment

---

## ImmutableBuilderContext (Required Minimal Contract)

`ImmutableBuilderContext` is a required, opaque context object passed through all hydration paths.

It MUST encapsulate:
- The target `ImmutableModel` class name
- The database connection name
- The underlying base query builder (not exposed publicly)
- Active global scopes
- Active eager-load definitions

`ImmutableBuilderContext` is created internally by the query layer.
Consumers MUST NOT construct or mutate it directly.

Its sole purpose is to:
- Preserve query-time configuration
- Enforce the hydration boundary
- Prevent ad-hoc or unsafe model instantiation

---

## Required Behavior

### ImmutableModel
- Acts as a read-only, immutable model
- Stores attributes and relations
- Supports:
  - Attribute access (`$model->foo`)
  - Accessors (`getXxxAttribute`)
  - `$appends`, `$hidden`, `$visible`
  - `toArray()`, `toJson()`, `JsonSerializable`
- Any attempt to mutate attributes or relations MUST throw immediately
  - `__set()` throws
  - `offsetSet()` throws
  - Relation reassignment throws
- No silent failures

---

## Casting
- Full Eloquent casting parity:
  - Scalar casts (`int`, `float`, `bool`, `string`)
  - `datetime` / `immutable_datetime`
  - `array`, `json`, `collection`
  - Custom cast classes
- Cast resolution and caching MUST mirror Eloquent behavior as closely as possible
- Write-side cast hooks MUST NOT be reachable

---

## Querying

### Builder Wrapper
- Query ergonomics must match Eloquent:
  - `query()`, `where()`, `select()`, `orderBy()`, `limit()`
  - `with()`, `find()`, `first()`, `firstOrFail()`, `get()`
- All queries flow through `ImmutableQueryBuilder`
- The underlying Laravel builder MUST NEVER be exposed
- Fluent, chainable API

(Reference shape; not a fenced code block)
- final class ImmutableQueryBuilder
  - where(...): static
  - with(...): static
  - get(): ImmutableCollection
  - first(): ?ImmutableModel
  - firstOrFail(): ImmutableModel
  - find(mixed $id): ?ImmutableModel
  - findOrFail(mixed $id): ImmutableModel

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
  - Nested relations
  - Constraint closures
- `setRelation()` is final and MUST throw
- N+1 behavior is identical to Eloquent and is considered a usage concern
- Relation methods MAY return relation builders when the related model is a standard Eloquent model.
- Relation methods MUST throw if invoked for relations targeting ImmutableModels.
- Property access ($model->relation) MUST always return resolved related models or collections.
- Immutability is enforced only on ImmutableModels and ImmutableCollections, not on related Eloquent models.


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
  - `push`, `put`, `forget`, `offsetSet`, `offsetUnset`, etc.
- Transformations (`map`, `filter`, etc.) return new ImmutableCollections
- A mutable collection may be obtained only via an explicit `toBase()` call

### Collection Hydration

ImmutableCollection MUST provide a static hydrateFromRows() method.
This method MUST:
- Accept an iterable of rows (array|stdClass)
- Require an ImmutableBuilderContext
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
- Tests MUST verify:
  - Eloquent-equivalent read behavior
  - All mutation paths throw (attributes, relations, collections)
  - Lazy loading and eager loading parity
  - Global scopes apply correctly
- Include performance benchmarks comparing:
  - Hydration time
  - Memory usage
  - Eager loading behavior
  against equivalent Eloquent models

---

## Documentation
Position the package as:

**An architectural boundary and CQRS read-side primitive that enforces immutability while preserving Eloquent ergonomics**

Document clearly:
- Intended use cases (SQL views, read-only tables, denormalized projections)
- Explicit non-goals
- Differences from Eloquent
- Performance characteristics and limitations

---

## Laravel & PHP Compatibility
- PHP ≥ 8.2
- Laravel 11.x only
- Allowed Eloquent internals:
  - `Illuminate\Database\Eloquent\Relations\*`
  - `HasAttributes` (read-only subset)
  - Casting infrastructure

> Reliance on undocumented Eloquent internals is permitted only if wrapped and provably unreachable from mutation paths.

---

## Completion Gate (Claude Loop Critical)
Output `<promise>IMMUTABLE_MODEL_COMPLETE</promise>` **only after**:
- All tests pass
- All forbidden features are provably unreachable
- The full public API is documented
