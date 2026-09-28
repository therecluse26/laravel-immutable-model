# Changelog

All notable changes to `brighten/immutable-model` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

### Added

- Laravel 13 support. CI runs Laravel 11, 12 and 13 on PHP 8.2 to 8.5 (Laravel 13 needs PHP 8.3+), on SQLite, MySQL 8.4 and Postgres 16.
- `ReadOnlyConnection`. Every model and pivot query runs on it, and its six SQL write methods (`insert`, `update`, `delete`, `statement`, `affectingStatement`, `unprepared`) throw `ImmutableModelViolationException`. This also blocks `toBase()->update()`, `getQuery()->delete()`, `insertOrIgnoreUsing()`, and write methods that later Laravel versions add.
- `ImmutableModelViolationException::writeAttempt()`. The message shows the blocked SQL with `?` placeholders. Binding values are never included.
- `ImmutableModelConfigurationException::mutablePivot()`. On an immutable model, a pivot class set with `using()` must extend `ImmutablePivot` or `ImmutableMorphPivot`.
- `SerializesDatesNatively`. `toArray()` and JSON output format dates with PHP's native formatter. The output is identical to Eloquent's, and `toArray()` takes about a third of the time.

### Changed

- Relations return Laravel's own classes (`HasMany`, `BelongsTo`, `BelongsToMany`, ...). `morphToMany()` and `morphedByMany()` return `ImmutableMorphToMany`, which extends `MorphToMany`.
- Queries use Laravel's own `Eloquent\Builder`.
- `fresh()`, `refresh()` and `replicate()` work like Eloquent. The replica cannot be saved.
- `associate()` and `dissociate()` work in memory. `save()` still throws.
- `$timestamps` stays `true`, so `created_at` and `updated_at` are read as dates, like Eloquent.
- `$guarded = []`, so `fill()` works in memory and a relation `create()` reaches `save()` and throws.

### Removed

- `ImmutableEloquentBuilder`
- `ImmutableModel::getRawAttribute()`. It is not an Eloquent method. Use `getAttributes()[$key]`, as in Eloquent.
- `ImmutableBelongsTo`, `ImmutableBelongsToMany`, `ImmutableHasMany`, `ImmutableHasManyThrough`, `ImmutableHasOne`, `ImmutableHasOneThrough`, `ImmutableMorphMany`, `ImmutableMorphOne`, `ImmutableMorphTo`
- Exception factories that nothing threw: `missingPrimaryKey()`, `forbiddenProperty()`, `invalidCast()`, `missingTable()`, `missingConnectionResolver()`, `relationMutation()`, `collectionMutation()`, `directInstantiation()`

### Fixed

- `$pivot[0] = 1` and `unset($pivot[0])` threw `TypeError` instead of `ImmutableModelViolationException`.
- Casts added with `withCasts()` on one query leaked into later queries.
- Query results and `fromRow()` skipped trait initializers (`initializeXxx()`), so per-instance state such as `$hidden` or `$appends` set by a trait was lost.

### Upgrading from 0.x

1. Change relation return types to Laravel's classes. For example, `ImmutableHasMany` becomes `Illuminate\Database\Eloquent\Relations\HasMany`, and `ImmutableBelongsTo` becomes `Illuminate\Database\Eloquent\Relations\BelongsTo`. `ImmutableMorphToMany` stays.
2. Change `ImmutableEloquentBuilder` type hints to `Illuminate\Database\Eloquent\Builder`.
3. If you catch exceptions from a removed factory method, catch `ImmutableModelViolationException` or `ImmutableModelConfigurationException` instead.
4. If a relation uses a custom pivot with `using()`, make the pivot class extend `ImmutablePivot` (or `ImmutableMorphPivot` for `morphToMany()`).
5. Replace `$model->getRawAttribute('name')` with `$model->getAttributes()['name']`.
6. If you relied on `fresh()`, `refresh()` or `replicate()` throwing, note that they now work.

## [0.12.2] and earlier

See the [Git tags](https://github.com/therecluse26/laravel-immutable-model/tags).

[1.0.0]: https://github.com/therecluse26/laravel-immutable-model/compare/v0.12.2...v1.0.0
[0.12.2]: https://github.com/therecluse26/laravel-immutable-model/releases/tag/v0.12.2
