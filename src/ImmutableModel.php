<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Concerns\SerializesDatesNatively;
use Brighten\ImmutableModel\Concerns\UsesReadOnlyConnection;
use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
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
 *
 * @phpstan-consistent-constructor
 */
abstract class ImmutableModel extends Model
{
    use SerializesDatesNatively;
    use UsesReadOnlyConnection;

    /**
     * Allow in-memory fill() of any attribute.
     *
     * Mass-assignment protection guards database writes, and writes always
     * throw. Without this, relation create() would throw MassAssignmentException
     * before the write reaches save() and throws ImmutableModelViolationException.
     *
     * @var array<string>
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
     * @param object|array<int, object|string>|string $classes
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
     * @param \Illuminate\Events\QueuedClosure|callable|array<int, mixed>|class-string $callback
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
     * @param array<int, string>|string $attributes
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
     * @return array<string, mixed>
     */
    public function getDirty()
    {
        return [];
    }

    /**
     * Get dirty attributes for update - always empty for immutable models.
     *
     * @return array<string, mixed>
     */
    protected function getDirtyForUpdate()
    {
        return [];
    }

    /**
     * Get changes - always empty for immutable models.
     *
     * @return array<string, mixed>
     */
    public function getChanges()
    {
        return [];
    }

    /**
     * Check if dirty - always false for immutable models.
     *
     * @param array<int, string>|string|null $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        return false;
    }

    /**
     * Check if clean - always true for immutable models.
     *
     * @param array<int, string>|string|null $attributes
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
     * @param array<int, string>|string|null $attributes
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

    // =========================================================================
    // PERSISTENCE METHODS - ALL THROW
    // =========================================================================

    /**
     * @param array<string, mixed> $options
     * @throws ImmutableModelViolationException
     */
    public function save(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('save');
    }

    /**
     * @param array<string, mixed> $options
     * @throws ImmutableModelViolationException
     */
    public function saveQuietly(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('saveQuietly');
    }

    /**
     * @param array<string, mixed> $options
     * @throws ImmutableModelViolationException
     */
    public function saveOrFail(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('saveOrFail');
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $options
     * @throws ImmutableModelViolationException
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('update');
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $options
     * @throws ImmutableModelViolationException
     */
    public function updateQuietly(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('updateQuietly');
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $options
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
     * @return never
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
     * @return never
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
     * @return never
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
     * @return never
     * @throws ImmutableModelViolationException
     */
    public function restoreQuietly()
    {
        throw ImmutableModelViolationException::persistenceAttempt('restoreQuietly');
    }

    /**
     * @param array<string, mixed> $attributes
     * @throws ImmutableModelViolationException
     */
    public static function create(array $attributes = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('create');
    }

    /**
     * @param array<string, mixed> $attributes
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
     * @param array<int, string>|string|null $attribute
     * @throws ImmutableModelViolationException
     */
    public function touch($attribute = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('touch');
    }

    /**
     * @param array<int, string>|string|null $attribute
     * @throws ImmutableModelViolationException
     */
    public function touchQuietly($attribute = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('touchQuietly');
    }

    /**
     * @param array<string, mixed> $extra
     * @throws ImmutableModelViolationException
     */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('increment');
    }

    /**
     * @param array<string, mixed> $extra
     * @throws ImmutableModelViolationException
     */
    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('decrement');
    }

    /**
     * @param array<string, mixed> $extra
     * @throws ImmutableModelViolationException
     */
    public function incrementQuietly($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('incrementQuietly');
    }

    /**
     * @param array<string, mixed> $extra
     * @throws ImmutableModelViolationException
     */
    public function decrementQuietly($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('decrementQuietly');
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
     * A custom pivot set with using() must extend ImmutablePivot. A pivot
     * model has its own connection, so any other class could write.
     *
     * @param Model $parent
     * @param array<string, mixed> $attributes
     * @param string $table
     * @param bool $exists
     * @param string|null $using
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     *
     * @throws ImmutableModelConfigurationException
     */
    public function newPivot(Model $parent, array $attributes, $table, $exists, $using = null)
    {
        if ($using && ! is_a($using, Relations\ImmutablePivot::class, true)) {
            throw ImmutableModelConfigurationException::mutablePivot($using, Relations\ImmutablePivot::class);
        }

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
     * @param Builder<Model> $query
     * @param Model $parent
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string|null $relationName
     * @param bool $inverse
     * @return Relations\ImmutableMorphToMany<Model, Model>
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
     * @var array<class-string, ReflectionClass<ImmutableModel>>
     */
    private static array $reflectionCache = [];

    /**
     * Trait initializers to run per hydrated instance, cached per class.
     *
     * This is Laravel's initializer list without initializeHasAttributes(),
     * because newFromBuilder() copies the already-merged casts instead.
     *
     * @var array<class-string, array<int, string>>
     */
    private static array $initializerCache = [];

    /**
     * Create a new model instance from the database.
     *
     * This method bypasses most of Eloquent's constructor ceremony for
     * hydration performance. It uses ReflectionClass::newInstanceWithoutConstructor()
     * and then sets the same state that Eloquent's newFromBuilder() sets.
     *
     * Kept from Eloquent:
     * - Trait initializers (initializeXxx()) run once per instance
     * - Casts, table and connection are copied from the query's model, so
     *   withCasts() and setTable() behave as in Eloquent
     *
     * Skipped:
     * - initializeHasAttributes(): its result is already in $this->casts
     * - syncOriginal(): immutable models don't track dirty state
     * - fill(): attributes are set directly
     * - the "retrieved" event: events are disabled
     *
     * @param array<string, mixed>|object $attributes
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

        // The cache is keyed by static::class, so this always holds.
        if (! $model instanceof static) {
            throw new \LogicException("Reflection cache for [{$class}] built the wrong class.");
        }

        // Ensure the class is booted (cached per class, not per instance)
        $model->bootIfNotBooted();

        // Run trait initializers, as the constructor would
        if (!isset(self::$initializerCache[$class])) {
            self::$initializerCache[$class] = array_values(array_filter(
                static::$traitInitializers[$class] ?? [],
                fn (string $method) => $method !== 'initializeHasAttributes'
            ));
        }
        foreach (self::$initializerCache[$class] as $method) {
            $model->{$method}();
        }

        // Copy state from the query's model, as Eloquent's newInstance() does.
        // $this->casts already holds the merged casts (including withCasts()).
        $model->casts = $this->casts;
        $model->table = $this->table;

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
     * @param array<string, mixed>|object $row
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
     * @param array<int, array<string, mixed>|object> $rows
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function fromRows(array $rows): \Illuminate\Database\Eloquent\Collection
    {
        $models = array_map(fn($row) => static::fromRow($row), $rows);

        return new \Illuminate\Database\Eloquent\Collection($models);
    }
}
