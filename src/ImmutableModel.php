<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
    /**
     * Disable timestamps auto-updating.
     *
     * @var bool
     */
    public $timestamps = false;

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
    // CUSTOM BUILDER
    // =========================================================================

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return ImmutableEloquentBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new ImmutableEloquentBuilder($query);
    }

    // =========================================================================
    // CUSTOM RELATION FACTORIES
    // =========================================================================

    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @param Builder $query
     * @param Model $child
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $relation
     * @return Relations\ImmutableBelongsTo
     */
    protected function newBelongsTo(Builder $query, Model $child, $foreignKey, $ownerKey, $relation)
    {
        return new Relations\ImmutableBelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @return Relations\ImmutableHasOne
     */
    protected function newHasOne(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        return new Relations\ImmutableHasOne($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasMany relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @return Relations\ImmutableHasMany
     */
    protected function newHasMany(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        return new Relations\ImmutableHasMany($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Instantiate a new BelongsToMany relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string|null $relationName
     * @return Relations\ImmutableBelongsToMany
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        return new Relations\ImmutableBelongsToMany(
            $query, $parent, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, $relationName
        );
    }

    /**
     * Instantiate a new HasOneThrough relationship.
     *
     * @param Builder $query
     * @param Model $farParent
     * @param Model $throughParent
     * @param string $firstKey
     * @param string $secondKey
     * @param string $localKey
     * @param string $secondLocalKey
     * @return Relations\ImmutableHasOneThrough
     */
    protected function newHasOneThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey
    ) {
        return new Relations\ImmutableHasOneThrough(
            $query, $farParent, $throughParent, $firstKey,
            $secondKey, $localKey, $secondLocalKey
        );
    }

    /**
     * Instantiate a new HasManyThrough relationship.
     *
     * @param Builder $query
     * @param Model $farParent
     * @param Model $throughParent
     * @param string $firstKey
     * @param string $secondKey
     * @param string $localKey
     * @param string $secondLocalKey
     * @return Relations\ImmutableHasManyThrough
     */
    protected function newHasManyThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey
    ) {
        return new Relations\ImmutableHasManyThrough(
            $query, $farParent, $throughParent, $firstKey,
            $secondKey, $localKey, $secondLocalKey
        );
    }

    /**
     * Instantiate a new MorphOne relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return Relations\ImmutableMorphOne
     */
    protected function newMorphOne(Builder $query, Model $parent, $type, $id, $localKey)
    {
        return new Relations\ImmutableMorphOne($query, $parent, $type, $id, $localKey);
    }

    /**
     * Instantiate a new MorphMany relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return Relations\ImmutableMorphMany
     */
    protected function newMorphMany(Builder $query, Model $parent, $type, $id, $localKey)
    {
        return new Relations\ImmutableMorphMany($query, $parent, $type, $id, $localKey);
    }

    /**
     * Instantiate a new MorphTo relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $type
     * @param string $relation
     * @return Relations\ImmutableMorphTo
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        return new Relations\ImmutableMorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    /**
     * Instantiate a new MorphToMany relationship.
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
     * Create a new model instance from the database.
     *
     * @param array $attributes
     * @param string|null $connection
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = parent::newFromBuilder($attributes, $connection);
        $model->exists = true;
        $model->wasRecentlyCreated = false;

        return $model;
    }

    /**
     * Create a model instance from a raw database row.
     *
     * This is a convenience method that wraps newFromBuilder for backwards
     * compatibility with the original ImmutableModel API.
     *
     * @param array|object $row
     * @return static
     */
    public static function fromRow(array|object $row): static
    {
        $attributes = $row instanceof \stdClass ? (array) $row : $row;

        $model = new static();
        $model->setRawAttributes($attributes, true);
        $model->exists = true;
        $model->wasRecentlyCreated = false;

        return $model;
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
