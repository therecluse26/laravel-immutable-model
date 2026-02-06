<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Immutable MorphToMany relationship.
 *
 * Extends Eloquent's MorphToMany for full read compatibility while
 * blocking all mutation operations including pivot modifications.
 */
class ImmutableMorphToMany extends MorphToMany
{
    /**
     * Create a new pivot model instance.
     *
     * @param array $attributes
     * @param bool $exists
     * @return ImmutableMorphPivot
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        $using = $this->using;

        // Use ImmutableMorphPivot unless a custom pivot class is specified
        $pivotClass = $using ? $using : ImmutableMorphPivot::class;

        $pivot = $pivotClass::fromRawAttributes($this->parent, $attributes, $this->getTable(), $exists);

        $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
            ->setMorphType($this->morphType)
            ->setMorphClass($this->morphClass);

        return $pivot;
    }

    // =========================================================================
    // BLOCK PIVOT MUTATIONS
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function attach($id, array $attributes = [], $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('attach');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function detach($ids = null, $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('detach');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function sync($ids, $detaching = true): never
    {
        throw ImmutableModelViolationException::relationMutation('sync');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function syncWithoutDetaching($ids): never
    {
        throw ImmutableModelViolationException::relationMutation('syncWithoutDetaching');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function syncWithPivotValues($ids, array $values, $detaching = true): never
    {
        throw ImmutableModelViolationException::relationMutation('syncWithPivotValues');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function toggle($ids, $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('toggle');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateExistingPivot($id, array $attributes, $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('updateExistingPivot');
    }

    // =========================================================================
    // BLOCK MODEL MUTATIONS
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function save(Model $model, array $pivotAttributes = [], $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('save');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function saveQuietly(Model $model, array $pivotAttributes = [], $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('saveQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function saveMany($models, array $pivotAttributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('saveMany');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function saveManyQuietly($models, array $pivotAttributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('saveManyQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function create(array $attributes = [], array $joining = [], $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('create');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function createMany(iterable $records, array $joinings = []): never
    {
        throw ImmutableModelViolationException::relationMutation('createMany');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('update');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateOrCreate(array $attributes, array $values = [], array $joining = [], $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('updateOrCreate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function createOrFirst(array $attributes = [], array $values = [], array $joining = [], $touch = true): never
    {
        throw ImmutableModelViolationException::relationMutation('createOrFirst');
    }
}
