# Mechanical Parity Audit Plan

## Purpose

This document defines a comprehensive audit plan to achieve **perfect mechanical parity** between ImmutableModel and Laravel Eloquent for all read operations.

**Goal:** Every method implemented in ImmutableModel must behave **identically** to its Eloquent counterpart — same inputs must produce same outputs, same edge cases must produce same results (or errors), and same SQL must be generated.

**What "mechanical parity" means:**
- Not just "the method exists" — the method must **behave the same way**
- Not just "it works for typical inputs" — **edge cases must match**
- Not just "it returns similar data" — **exact return types, values, and structures must match**
- Not just "errors are thrown" — **same exception types and conditions**

---

## Audit Methodology

For each method being audited:

1. **Read Eloquent source code** for the corresponding method
2. **Compare implementation logic** line-by-line
3. **Identify edge case handling** in Eloquent
4. **Write comparison test** that runs identical operations on both
5. **Document any intentional differences** (mutations blocked, etc.)

---

## Classes to Audit

### 1. ImmutableModel (`src/ImmutableModel.php`)
**Maps to:** `Illuminate\Database\Eloquent\Model`

| Category | Methods |
|----------|---------|
| **Instantiation** | `__construct`, `newInstance`, `newFromBuilder`, `fromRow`, `fromRows`, `hydrate` |
| **Attribute Access** | `getAttribute`, `getRawAttribute`, `getOriginal`, `getRawOriginal`, `getAttributes`, `getAttributeValue` |
| **Attribute Accessors** | `__get`, `__isset`, `hasAccessor`, `callAccessor`, `hasAnyGetMutator` |
| **Casting** | `hasCast`, `getCasts`, `getDates` |
| **Keys & Tables** | `getTable`, `getKeyName`, `getKey`, `getKeyType`, `getQualifiedKeyName`, `getForeignKey` |
| **Connections** | `getConnection`, `getConnectionName`, `setConnection`, `resolveConnection`, `setConnectionResolver`, `getConnectionResolver` |
| **Relations** | `belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, `hasOneThrough`, `hasManyThrough`, `morphOne`, `morphMany`, `morphTo`, `morphToMany`, `morphedByMany` |
| **Relation Access** | `getRelation`, `getRelations`, `relationLoaded`, `getRelationValue`, `getWith` |
| **Serialization** | `toArray`, `toJson`, `jsonSerialize`, `attributesToArray`, `relationsToArray`, `serializeValue`, `serializeDate`, `getArrayableAttributes` |
| **Scopes** | `hasNamedScope`, `callNamedScope` |
| **Morph** | `getMorphClass`, `getMorphs`, `joiningTable`, `joiningTableSegment` |
| **Timestamps** | `getCreatedAtColumn`, `getUpdatedAtColumn`, `getDateFormat` |
| **Utilities** | `qualifyColumn`, `newCollection`, `newQuery` |
| **Static Query** | `query`, `find`, `findOrFail`, `first`, `all`, `where`, `with`, `withoutGlobalScopes`, `withoutGlobalScope` |
| **Magic Methods** | `__call`, `__callStatic`, `__set`, `__unset` |
| **ArrayAccess** | `offsetExists`, `offsetGet`, `offsetSet`, `offsetUnset` |
| **Blocked (verify throws)** | `save`, `update`, `delete`, `create`, `fill`, `push`, `touch`, `increment`, `decrement`, `forceDelete`, `restore` |

---

### 2. ImmutableQueryBuilder (`src/ImmutableQueryBuilder.php`)
**Maps to:** `Illuminate\Database\Eloquent\Builder`

| Category | Methods |
|----------|---------|
| **Execution** | `get`, `first`, `firstOrFail`, `find`, `findOrFail`, `findMany`, `value`, `valueOrFail`, `sole`, `firstWhere` |
| **Eager Loading** | `with`, `withCount` |
| **Scopes** | `applyGlobalScopes`, `withoutGlobalScopes`, `withoutGlobalScope` |
| **Builder Proxy** | All methods forwarded via `__call` to base query builder |
| **Subqueries** | `selectSub`, `addCountSubquery` (and all relation-specific count methods) |
| **Model Access** | `getModel`, `getBaseQuery`, `newQueryWithoutRelationships` |
| **Hydration** | `hydrateModel`, `loadRelations`, `loadNestedRelations`, `loadRelationOnCollection`, `loadRelation` |
| **Blocked (verify throws)** | `insert`, `update`, `delete`, `upsert`, `truncate`, `forceDelete`, `increment`, `decrement` |

---

### 3. CastManager (`src/Casts/CastManager.php`)
**Maps to:** Eloquent's internal casting logic

| Cast Type | Method |
|-----------|--------|
| **Boolean** | `castToBoolean` |
| **Numeric** | `castToDecimal` (with precision) |
| **Object** | `castToObject` |
| **Array/JSON** | `castToArray` |
| **Collection** | `castToCollection` |
| **Date** | `castToDate`, `asDateTime` |
| **Datetime** | `castToDatetime`, `asDateTime` |
| **Immutable Date** | `castToImmutableDate`, `asImmutableDateTime` |
| **Immutable Datetime** | `castToImmutableDatetime`, `asImmutableDateTime` |
| **Timestamp** | `castToTimestamp` |
| **Custom** | `castWithCustomCaster`, `getCaster` |

---

### 4. Relations (10 classes in `src/Relations/`)

Each relation class must be audited for:
- `__construct` - Parameter handling
- `getResults` - Lazy loading behavior
- `getQuery` - Base query construction
- `getConstrainedQuery` - Constraint application
- `eagerLoadOnCollection` - Eager loading algorithm
- `__call` - Method forwarding
- All getter methods for keys/tables

| Class | Maps To |
|-------|---------|
| `ImmutableBelongsTo` | `Illuminate\Database\Eloquent\Relations\BelongsTo` |
| `ImmutableHasOne` | `Illuminate\Database\Eloquent\Relations\HasOne` |
| `ImmutableHasMany` | `Illuminate\Database\Eloquent\Relations\HasMany` |
| `ImmutableBelongsToMany` | `Illuminate\Database\Eloquent\Relations\BelongsToMany` |
| `ImmutableHasOneThrough` | `Illuminate\Database\Eloquent\Relations\HasOneThrough` |
| `ImmutableHasManyThrough` | `Illuminate\Database\Eloquent\Relations\HasManyThrough` |
| `ImmutableMorphOne` | `Illuminate\Database\Eloquent\Relations\MorphOne` |
| `ImmutableMorphMany` | `Illuminate\Database\Eloquent\Relations\MorphMany` |
| `ImmutableMorphTo` | `Illuminate\Database\Eloquent\Relations\MorphTo` |
| `ImmutableMorphToMany` | `Illuminate\Database\Eloquent\Relations\MorphToMany` |

---

### 5. ImmutablePivot (`src/Relations/ImmutablePivot.php`)
**Maps to:** `Illuminate\Database\Eloquent\Relations\Pivot`

| Category | Methods |
|----------|---------|
| **Access** | `__get`, `__isset`, `getAttribute`, `getAttributes`, `hasAttribute` |
| **Metadata** | `getTable`, `getForeignKey`, `getRelatedKey` |
| **Serialization** | `toArray`, `jsonSerialize` |
| **ArrayAccess** | `offsetExists`, `offsetGet`, `offsetSet`, `offsetUnset` |

---

## Audit Checklist

### Phase 1: ImmutableModel Core

**Status: COMPLETE** (Audited 2026-01-29, 64 parity tests in MechanicalParityTest.php)

**Fixes Applied:**
- `getAttribute()`: Added early return for falsy keys (line 649)
- `getDates()`: Changed to return timestamp columns only, added `$timestamps` property (line 338)
- `getRelation()`: Now throws for unloaded relations (matches Eloquent) (line 800)
- `attributesToArray()`: Changed from protected to public (line 1345)
- `relationsToArray()`: Changed from protected to public (line 1448)
- `hydrateModel()`: Now passes connection name to hydrated models (QueryBuilder line 625)

- [x] **1.1 Attribute Access**
  - [x] `getAttribute()` - Compare with Eloquent for: existing attrs, missing attrs, cast attrs, accessor attrs, relation attrs
  - [x] `__get()` - Same scenarios as getAttribute
  - [x] `getRawAttribute()` - Verify returns uncast value
  - [x] `getOriginal()` - Verify behavior (note: intentionally different for immutable)
  - [x] `getRawOriginal()` - Same as getOriginal
  - [x] `getAttributes()` - Full attribute array
  - [x] `hasAccessor()` - Detection of `getXxxAttribute` methods
  - [x] `callAccessor()` - Accessor invocation

- [x] **1.2 Casting**
  - [x] `hasCast()` - Cast detection for all types
  - [x] `getCasts()` - Cast definition retrieval
  - [x] `getDates()` - Returns timestamp columns (fixed to match Eloquent)
  - [ ] All cast types via CastManager (see Phase 3)

- [x] **1.3 Keys & Tables**
  - [x] `getTable()` - Default table name inference
  - [x] `getKeyName()` - Primary key name
  - [x] `getKey()` - Primary key value (test null key behavior)
  - [x] `getKeyType()` - Key type detection
  - [x] `getQualifiedKeyName()` - Table-qualified key
  - [x] `getForeignKey()` - Foreign key name inference (intentionally strips "Immutable" prefix)

- [x] **1.4 Connections**
  - [x] `getConnection()` - Connection resolution
  - [x] `getConnectionName()` - Connection name retrieval (fixed: now propagated during hydration)
  - [x] `setConnection()` - Connection setting
  - [x] `resolveConnection()` - Static resolution
  - [x] Connection resolver static methods

- [x] **1.5 Serialization**
  - [x] `toArray()` - Full serialization with:
    - [x] Simple attributes
    - [x] Cast attributes
    - [x] Accessor attributes
    - [x] Loaded relations
    - [x] Nested relations
    - [x] Hidden attributes
    - [x] Visible attributes
    - [x] Appended attributes
  - [x] `toJson()` - JSON encoding
  - [x] `jsonSerialize()` - JsonSerializable implementation
  - [x] `attributesToArray()` - Made public (was protected)
  - [x] `relationsToArray()` - Made public (was protected)
  - [ ] Date serialization format (not explicitly tested yet)

- [ ] **1.6 Relations Definition** (Deferred to Phase 4)
  - [ ] `belongsTo()` - Key inference, custom keys
  - [ ] `hasOne()` - Key inference, custom keys
  - [ ] `hasMany()` - Key inference, custom keys
  - [ ] `belongsToMany()` - Pivot table inference, custom pivots
  - [ ] `hasOneThrough()` - Through key inference
  - [ ] `hasManyThrough()` - Through key inference
  - [ ] `morphOne()` - Morph type/id columns
  - [ ] `morphMany()` - Morph type/id columns
  - [ ] `morphTo()` - Morph type resolution
  - [ ] `morphToMany()` - Pivot with morph

- [x] **1.7 Relation Access**
  - [x] `relationLoaded()` - Check if relation is loaded
  - [x] `getRelation()` - Get loaded relation (fixed: now throws for unloaded)
  - [x] `getRelations()` - All loaded relations
  - [ ] `getWith()` - Default eager loads (not explicitly tested)

- [x] **1.8 Scopes**
  - [x] `hasNamedScope()` - Scope detection
  - [x] `callNamedScope()` - Scope invocation (via static call)

- [x] **1.9 Static Query Methods**
  - [x] `query()` - Builder creation
  - [x] `find()` - Single ID lookup
  - [x] `findOrFail()` - Single ID with exception
  - [x] `first()` - First record
  - [x] `all()` - All records
  - [x] `where()` - Basic where clause
  - [x] `with()` - Eager load specification
  - [ ] `withoutGlobalScopes()` - Scope removal (tested in GlobalScopeTest)
  - [ ] `withoutGlobalScope()` - Single scope removal (tested in GlobalScopeTest)

- [x] **1.10 Magic Methods**
  - [x] `__call()` - Method forwarding to query builder
  - [x] `__callStatic()` - Static method forwarding
  - [x] `__set()` - In-memory mutation allowed (intentional)
  - [x] `__unset()` - Removes attributes and relations

- [x] **1.11 ArrayAccess**
  - [x] `offsetExists()` - isset($model['key'])
  - [x] `offsetGet()` - $model['key']
  - [x] `offsetSet()` - In-memory mutation allowed (intentional)
  - [ ] `offsetUnset()` - Verify behavior

---

### Phase 2: ImmutableQueryBuilder

- [ ] **2.1 Basic Retrieval**
  - [ ] `get()` - Collection retrieval
  - [ ] `first()` - Single model
  - [ ] `firstOrFail()` - Exception on not found
  - [ ] `find()` - By primary key
  - [ ] `findOrFail()` - Exception on not found
  - [ ] `findMany()` - Multiple primary keys
  - [ ] `value()` - Single column value
  - [ ] `valueOrFail()` - Exception on not found
  - [ ] `sole()` - Exactly one result
  - [ ] `firstWhere()` - First with condition

- [ ] **2.2 Eager Loading**
  - [ ] `with()` - Basic eager loading
  - [ ] `with()` - Nested eager loading (dot notation)
  - [ ] `with()` - Constrained eager loading (closure)
  - [ ] `withCount()` - All relation types

- [ ] **2.3 Scopes**
  - [ ] `applyGlobalScopes()` - Automatic application
  - [ ] `withoutGlobalScopes()` - Full removal
  - [ ] `withoutGlobalScope()` - Single removal

- [ ] **2.4 Query Builder Proxy**
  - [ ] `where()` / `orWhere()`
  - [ ] `whereIn()` / `whereNotIn()`
  - [ ] `whereNull()` / `whereNotNull()`
  - [ ] `whereBetween()`
  - [ ] `whereDate()` / `whereMonth()` / `whereYear()`
  - [ ] `whereColumn()`
  - [ ] `when()` / `unless()`
  - [ ] `orderBy()` / `orderByDesc()` / `latest()` / `oldest()`
  - [ ] `limit()` / `offset()` / `take()` / `skip()`
  - [ ] `select()` / `addSelect()`
  - [ ] `distinct()`
  - [ ] `groupBy()` / `having()`
  - [ ] `join()` / `leftJoin()` / `rightJoin()`
  - [ ] Aggregates: `count()`, `sum()`, `avg()`, `min()`, `max()`
  - [ ] `exists()` / `doesntExist()`
  - [ ] `pluck()`
  - [ ] `paginate()` / `simplePaginate()` / `cursorPaginate()`
  - [ ] `chunk()` / `cursor()`

---

### Phase 3: CastManager

- [ ] **3.1 Scalar Casts**
  - [ ] `int` / `integer` - Integer conversion
  - [ ] `float` / `real` / `double` - Float conversion
  - [ ] `bool` / `boolean` - Boolean conversion (test "0", "false", "", 0, null)
  - [ ] `string` - String conversion

- [ ] **3.2 Complex Casts**
  - [ ] `array` - JSON decode (test invalid JSON)
  - [ ] `json` - JSON decode (test invalid JSON)
  - [ ] `object` - stdClass conversion
  - [ ] `collection` - Collection wrapping

- [ ] **3.3 Date Casts**
  - [ ] `date` - Date only
  - [ ] `datetime` - Full datetime
  - [ ] `timestamp` - Unix timestamp
  - [ ] `immutable_date` - CarbonImmutable date
  - [ ] `immutable_datetime` - CarbonImmutable datetime
  - [ ] Date format parameters (`datetime:Y-m-d`)

- [ ] **3.4 Decimal Cast**
  - [ ] `decimal:X` - Precision handling

- [ ] **3.5 Custom Casters**
  - [ ] Class-based casters
  - [ ] `CastsAttributes` interface
  - [ ] `CastsInboundAttributes` handling (should be no-op)

---

### Phase 4: Relations

For each relation type, audit:

- [ ] **4.1 ImmutableBelongsTo**
  - [ ] Key inference
  - [ ] Custom keys
  - [ ] Lazy loading
  - [ ] Eager loading
  - [ ] Null foreign key handling
  - [ ] Query constraints

- [ ] **4.2 ImmutableHasOne**
  - [ ] Key inference
  - [ ] Custom keys
  - [ ] Lazy loading
  - [ ] Eager loading
  - [ ] Missing relation handling
  - [ ] Query constraints

- [ ] **4.3 ImmutableHasMany**
  - [ ] Key inference
  - [ ] Custom keys
  - [ ] Lazy loading (returns collection)
  - [ ] Eager loading
  - [ ] Empty collection handling
  - [ ] Query constraints

- [ ] **4.4 ImmutableBelongsToMany**
  - [ ] Pivot table inference
  - [ ] Custom pivot table
  - [ ] Pivot columns (`withPivot`)
  - [ ] Pivot timestamps (`withTimestamps`)
  - [ ] Pivot accessor name (`as`)
  - [ ] Lazy loading
  - [ ] Eager loading
  - [ ] Query constraints

- [ ] **4.5 ImmutableHasOneThrough**
  - [ ] Key inference
  - [ ] Custom keys
  - [ ] Lazy loading
  - [ ] Eager loading
  - [ ] Soft deletes on through model
  - [ ] `withTrashedParents()`

- [ ] **4.6 ImmutableHasManyThrough**
  - [ ] Same as HasOneThrough but returns collection

- [ ] **4.7 ImmutableMorphOne**
  - [ ] Morph type/id column inference
  - [ ] Custom morph name
  - [ ] Lazy loading
  - [ ] Eager loading
  - [ ] Missing relation handling

- [ ] **4.8 ImmutableMorphMany**
  - [ ] Same as MorphOne but returns collection

- [ ] **4.9 ImmutableMorphTo**
  - [ ] Dynamic model resolution
  - [ ] Multiple morph types
  - [ ] Lazy loading
  - [ ] Eager loading (with morphWith)
  - [ ] Unknown morph type handling

- [ ] **4.10 ImmutableMorphToMany**
  - [ ] Pivot table with morph
  - [ ] Inverse morph (`morphedByMany`)
  - [ ] Pivot columns
  - [ ] Lazy/eager loading

---

### Phase 5: ImmutablePivot

- [ ] **5.1 Attribute Access**
  - [ ] `getAttribute()` - Basic access
  - [ ] `__get()` - Magic access
  - [ ] `hasAttribute()` - Existence check
  - [ ] Foreign/related key access

- [ ] **5.2 Serialization**
  - [ ] `toArray()` - Full pivot serialization
  - [ ] `jsonSerialize()` - JSON serialization

---

### Phase 6: Edge Cases

- [ ] **6.1 Null Handling**
  - [ ] Null attributes
  - [ ] Null foreign keys
  - [ ] Null cast values
  - [ ] Null relation results

- [ ] **6.2 Empty Collections**
  - [ ] Empty hasMany results
  - [ ] Empty belongsToMany results
  - [ ] Empty eager loads

- [ ] **6.3 Type Coercion**
  - [ ] String IDs vs integer IDs
  - [ ] UUID primary keys
  - [ ] Composite keys (if supported)

- [ ] **6.4 Special Characters**
  - [ ] JSON with special characters
  - [ ] Attribute names with underscores
  - [ ] Table names with prefixes

- [ ] **6.5 Date Edge Cases**
  - [ ] Null dates
  - [ ] Invalid date strings
  - [ ] Different date formats
  - [ ] Timezone handling

---

### Phase 7: Comparison Test Suite

After completing the audit, create a comprehensive comparison test suite:

- [ ] **7.1 Create `tests/Parity/MechanicalParityTest.php`**
  - [ ] Base test that runs identical operations on Eloquent and ImmutableModel
  - [ ] Uses data providers for comprehensive scenarios
  - [ ] Compares exact return values, types, and structures

- [ ] **7.2 Attribute Access Comparison Tests**
  - [ ] Test every attribute access method with identical data
  - [ ] Include edge cases (null, missing, cast, accessor)

- [ ] **7.3 Query Builder Comparison Tests**
  - [ ] Test every query method produces identical SQL
  - [ ] Test every query method returns identical results
  - [ ] Include pagination, chunking, cursor

- [ ] **7.4 Relation Comparison Tests**
  - [ ] Test every relation type produces identical SQL
  - [ ] Test every relation type returns identical structures
  - [ ] Test eager loading produces identical nested structures

- [ ] **7.5 Casting Comparison Tests**
  - [ ] Test every cast type with identical inputs
  - [ ] Include edge cases (invalid JSON, null, etc.)

- [ ] **7.6 Serialization Comparison Tests**
  - [ ] `toArray()` with various model states
  - [ ] `toJson()` output comparison
  - [ ] Nested relation serialization

- [ ] **7.7 SQL Generation Comparison**
  - [ ] Capture SQL from both implementations
  - [ ] Assert identical queries generated

- [ ] **7.8 Exception Parity Tests**
  - [ ] Same conditions trigger same exceptions
  - [ ] Exception messages match (where applicable)

---

## Documentation of Intentional Differences

As the audit proceeds, document intentional differences here:

| Feature | ImmutableModel Behavior | Eloquent Behavior | Reason |
|---------|------------------------|-------------------|--------|
| `save()`, `update()`, `delete()` | Throws `ImmutableModelViolationException` | Persists to database | Core immutability |
| `__set()` | Allows in-memory mutation | Allows mutation | Spec allows for API response building |
| `isDirty()`, `getDirty()` | Not implemented | Tracks changes | No persistence = no dirty tracking |
| Model events | Not implemented | Full event system | No persistence lifecycle |
| `fill()`, `$fillable`, `$guarded` | Not implemented | Mass assignment | Mutation protection |
| `getOriginal()` | Returns current values | Returns original from DB | No change tracking needed |

---

## Progress Tracking

| Phase | Status | Completion % | Notes |
|-------|--------|--------------|-------|
| Phase 1: ImmutableModel Core | Not Started | 0% | |
| Phase 2: ImmutableQueryBuilder | Not Started | 0% | |
| Phase 3: CastManager | Not Started | 0% | |
| Phase 4: Relations | Not Started | 0% | |
| Phase 5: ImmutablePivot | Not Started | 0% | |
| Phase 6: Edge Cases | Not Started | 0% | |
| Phase 7: Comparison Test Suite | Not Started | 0% | |

---

## Verification

After completing each phase:

1. Run full test suite: `./vendor/bin/phpunit`
2. Run parity tests specifically: `./vendor/bin/phpunit --testsuite Parity`
3. Review any behavioral differences found
4. Update this document with findings
5. Fix discrepancies or document as intentional

---

## Final Goal

When this audit is complete:
- Every read operation in ImmutableModel behaves **identically** to Eloquent
- All edge cases are documented and tested
- A comprehensive comparison test suite exists to prevent future regressions
- Any intentional differences are explicitly documented with rationale
