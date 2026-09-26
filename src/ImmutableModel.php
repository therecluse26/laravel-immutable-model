<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Concerns\UsesReadOnlyConnection;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Abstract base class for immutable, read-only models.
 *
 * ImmutableModel extends Eloquent\Model to provide full read compatibility
 * while disabling all persistence, events, and dirty tracking functionality.
 * This ensures that models retrieved from the database cannot be modified
 * or saved back.
 *
 * All read functionality (queries, casting, relations, serialization) works
 * exactly like Eloquent because it IS Eloquent - we just disable writes.
 */
abstract class ImmutableModel extends Model
{
    use UsesReadOnlyConnection;

    /**
     * Disable timestamps auto-updating.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Allow in-memory fill() of any attribute.
     *
     * Mass-assignment protection guards database writes, and writes always
     * throw. Without this, relation create() would throw MassAssignmentException
     * before the write reaches save() and throws ImmutableModelViolationException.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    // =========================================================================
    // DISABLE EVENT SYSTEM
    // =========================================================================

    /**
     * Boot the HasEvents trait - disabled for immutable models.
     */
    public static function bootHasEvents(): void
    {
        // No-op: Skip event trait boot entirely
    }

    /**
     * Register observers - disabled for immutable models.
     *
     * @param object|array|string $classes
     */
    public static function observe($classes): void
    {
        // No-op: Observers not supported
    }

    /**
     * Register a single observer - disabled for immutable models.
     *
     * @param object|string $class
     */
    protected function registerObserver($class): void
    {
        // No-op
    }

    /**
     * Flush event listeners - disabled for immutable models.
     */
    public static function flushEventListeners(): void
    {
        // No-op
    }

    /**
     * Fire a model event - disabled for immutable models.
     *
     * @param string $event
     * @param bool $halt
     * @return bool
     */
    protected function fireModelEvent($event, $halt = true)
    {
        return true; // Pretend success, but do nothing
    }

    /**
     * Fire a custom model event - disabled for immutable models.
     *
     * @param string $event
     * @param string $method
     * @return mixed
     */
    protected function fireCustomModelEvent($event, $method)
    {
        return null;
    }

    /**
     * Get the event dispatcher - always returns null for immutable models.
     *
     * @return Dispatcher|null
     */
    public static function getEventDispatcher()
    {
        return null;
    }

    /**
     * Set the event dispatcher - disabled for immutable models.
     *
     * @param Dispatcher $dispatcher
     */
    public static function setEventDispatcher(Dispatcher $dispatcher): void
    {
        // No-op
    }

    /**
     * Unset the event dispatcher - disabled for immutable models.
     */
    public static function unsetEventDispatcher(): void
    {
        // No-op
    }

    /**
     * Register a model event - disabled for immutable models.
     *
     * @param string $event
     * @param \Illuminate\Events\QueuedClosure|callable|array|class-string $callback
     */
    protected static function registerModelEvent($event, $callback): void
    {
        // No-op
    }

    // =========================================================================
    // DISABLE DIRTY TRACKING
    // =========================================================================

    /**
     * Sync the original attributes - no-op for immutable models.
     *
     * @return static
     */
    public function syncOriginal()
    {
        return $this;
    }

    /**
     * Sync a single original attribute - no-op for immutable models.
     *
     * @param string $attribute
     * @return static
     */
    public function syncOriginalAttribute($attribute)
    {
        return $this;
    }

    /**
     * Sync multiple original attributes - no-op for immutable models.
     *
     * @param array|string $attributes
     * @return static
     */
    public function syncOriginalAttributes($attributes)
    {
        return $this;
    }

    /**
     * Sync the changes - no-op for immutable models.
     *
     * @return static
     */
    public function syncChanges()
    {
        return $this;
    }

    /**
     * Get dirty attributes - always empty for immutable models.
     *
     * @return array
     */
    public function getDirty()
    {
        return [];
    }

    /**
     * Get dirty attributes for update - always empty for immutable models.
     *
     * @return array
     */
    protected function getDirtyForUpdate()
    {
        return [];
    }

    /**
     * Get changes - always empty for immutable models.
     *
     * @return array
     */
    public function getChanges()
    {
        return [];
    }

    /**
     * Check if dirty - always false for immutable models.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        return false;
    }

    /**
     * Check if clean - always true for immutable models.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isClean($attributes = null)
    {
        return true;
    }

    /**
     * Discard changes - no-op for immutable models.
     *
     * @return static
     */
    public function discardChanges()
    {
        return $this;
    }

    /**
     * Check if changed - always false for immutable models.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function wasChanged($attributes = null)
    {
        return false;
    }

    /**
     * Check if original is equivalent - always true for immutable models.
     *
     * @param string $key
     * @return bool
     */
    public function originalIsEquivalent($key)
    {
        return true;
    }

    /**
     * Get original attribute(s) - returns current values for immutable models.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function getOriginal($key = null, $default = null)
    {
        // For immutable models, "original" is same as current
        return $key ? $this->getAttribute($key) ?? $default : $this->getAttributes();
    }

    /**
     * Get raw original attribute(s) - returns current raw values for immutable models.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function getRawOriginal($key = null, $default = null)
    {
        return $key ? ($this->attributes[$key] ?? $default) : $this->attributes;
    }

    /**
     * Get the raw value of an attribute without casting or mutators.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getRawAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    // =========================================================================
    // PERSISTENCE METHODS - ALL THROW
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function save(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('save');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function saveQuietly(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('saveQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function saveOrFail(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('saveOrFail');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('update');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateQuietly(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('updateQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateOrFail(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('updateOrFail');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function delete(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('delete');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function deleteQuietly(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('deleteQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function deleteOrFail(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('deleteOrFail');
    }

    /**
     * Force delete - not allowed on immutable models.
     *
     * Note: Return type omitted for compatibility with SoftDeletes trait.
     *
     * @throws ImmutableModelViolationException
     */
    public function forceDelete()
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceDelete');
    }

    /**
     * Force delete quietly - not allowed on immutable models.
     *
     * Note: Return type omitted for compatibility with SoftDeletes trait.
     *
     * @throws ImmutableModelViolationException
     */
    public function forceDeleteQuietly()
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceDeleteQuietly');
    }

    /**
     * Restore - not allowed on immutable models.
     *
     * Note: Return type omitted for compatibility with SoftDeletes trait.
     *
     * @throws ImmutableModelViolationException
     */
    public function restore()
    {
        throw ImmutableModelViolationException::persistenceAttempt('restore');
    }

    /**
     * Restore quietly - not allowed on immutable models.
     *
     * Note: Return type omitted for compatibility with SoftDeletes trait.
     *
     * @throws ImmutableModelViolationException
     */
    public function restoreQuietly()
    {
        throw ImmutableModelViolationException::persistenceAttempt('restoreQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public static function create(array $attributes = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('create');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public static function forceCreate(array $attributes): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceCreate');
    }


    /**
     * @throws ImmutableModelViolationException
     */
    public function push(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('push');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function pushQuietly(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('pushQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function touch($attribute = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('touch');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function touchQuietly($attribute = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('touchQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('increment');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('decrement');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function incrementQuietly($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('incrementQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function decrementQuietly($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('decrementQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function fresh($with = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('fresh');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function refresh(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('refresh');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function replicate(?array $except = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('replicate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function replicateQuietly(?array $except = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('replicateQuietly');
    }

    // =========================================================================
    // PIVOT MODELS
    // =========================================================================

    /**
     * Create a new pivot model instance.
     *
     * BelongsToMany asks the related model for its pivot, so returning
     * ImmutablePivot here keeps belongsToMany() pivots immutable.
     *
     * @param Model $parent
     * @param array $attributes
     * @param string $table
     * @param bool $exists
     * @param string|null $using
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function newPivot(Model $parent, array $attributes, $table, $exists, $using = null)
    {
        return $using
            ? $using::fromRawAttributes($parent, $attributes, $table, $exists)
            : Relations\ImmutablePivot::fromAttributes($parent, $attributes, $table, $exists);
    }

    /**
     * Instantiate a new MorphToMany relationship.
     *
     * MorphToMany builds its pivot itself (it does not ask the related model),
     * so ImmutableMorphToMany overrides newPivot() to return ImmutableMorphPivot.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string|null $relationName
     * @param bool $inverse
     * @return Relations\ImmutableMorphToMany
     */
    protected function newMorphToMany(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false
    ) {
        return new Relations\ImmutableMorphToMany(
            $query, $parent, $name, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, $relationName, $inverse
        );
    }

    // =========================================================================
    // FOREIGN KEY NAMING
    // =========================================================================

    /**
     * Get the default foreign key name for the model.
     *
     * Strips the "Immutable" prefix from the class name so that ImmutableUser
     * produces 'user_id' instead of 'immutable_user_id'. This allows
     * ImmutableModel to work with existing database schemas.
     *
     * @return string
     */
    public function getForeignKey()
    {
        $className = class_basename($this);

        // Strip "Immutable" prefix if present
        if (str_starts_with($className, 'Immutable')) {
            $className = substr($className, 9); // Remove "Immutable" (9 chars)
        }

        return \Illuminate\Support\Str::snake($className) . '_' . $this->getKeyName();
    }

    // =========================================================================
    // HYDRATION OVERRIDE
    // =========================================================================

    /**
     * Cached ReflectionClass instances for fast hydration.
     *
     * @var array<class-string, ReflectionClass>
     */
    private static array $reflectionCache = [];

    /**
     * Cached merged casts arrays per class.
     *
     * This caches the result of merging $casts property with casts() method,
     * avoiding the per-instance array_merge overhead.
     *
     * @var array<class-string, array>
     */
    private static array $castsCache = [];

    /**
     * Create a new model instance from the database.
     *
     * This method bypasses most of Eloquent's constructor ceremony for maximum
     * hydration performance. We use ReflectionClass::newInstanceWithoutConstructor()
     * to avoid the overhead of syncOriginal(), fill(), and per-instance trait
     * initialization that happen in the normal constructor path.
     *
     * Optimizations:
     * - bootIfNotBooted(): Called but cached per class, not per instance
     * - casts merging: Cached per class, avoiding array_merge per instance
     * - syncOriginal(): Skipped - immutable models don't track dirty state
     * - fill(): Skipped - we set attributes directly
     * - initializeTraits(): Skipped - we cache the casts merge result
     *
     * @param array $attributes
     * @param string|null $connection
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $class = static::class;

        // Get cached reflection or create and cache it
        if (!isset(self::$reflectionCache[$class])) {
            self::$reflectionCache[$class] = new ReflectionClass($class);
        }

        // Create instance without constructor (skip syncOriginal, fill overhead)
        $model = self::$reflectionCache[$class]->newInstanceWithoutConstructor();

        // Ensure the class is booted (cached per class, not per instance)
        $model->bootIfNotBooted();

        // Apply cached merged casts (instead of calling initializeTraits per instance)
        if (!isset(self::$castsCache[$class])) {
            // First time: merge casts property with casts() method and cache
            self::$castsCache[$class] = $this->ensureCastsAreStringValues(
                array_merge($this->casts, $this->casts())
            );
        }
        $model->casts = self::$castsCache[$class];

        // Direct attribute assignment - bypass setRawAttributes overhead
        $model->attributes = (array) $attributes;

        // Set required state
        $model->exists = true;
        $model->wasRecentlyCreated = false;

        // Set connection - inherit from calling model if not explicitly provided
        $model->setConnection($connection ?: $this->getConnectionName());

        return $model;
    }

    /**
     * Create a model instance from a raw database row.
     *
     * This is a convenience method that uses the optimized hydration path.
     *
     * @param array|object $row
     * @return static
     */
    public static function fromRow(array|object $row): static
    {
        $attributes = $row instanceof \stdClass ? (array) $row : $row;

        // Use the same optimized hydration path
        $instance = new static();
        return $instance->newFromBuilder($attributes);
    }

    /**
     * Create a collection of model instances from raw database rows.
     *
     * @param array $rows
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function fromRows(array $rows): \Illuminate\Database\Eloquent\Collection
    {
        $models = array_map(fn($row) => static::fromRow($row), $rows);

        return new \Illuminate\Database\Eloquent\Collection($models);
    }
}
