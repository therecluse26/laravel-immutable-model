<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Represents an inverse polymorphic relationship for immutable models.
 *
 * Example: A Comment morphTo commentable (which could be a Post, Video, etc.)
 * The Comment stores commentable_type and commentable_id.
 *
 * Unlike other relations, MorphTo dynamically resolves the target model class
 * based on the morph type value stored in the parent model.
 *
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder with(string|array $relations, string|\Closure|null $callback = null)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder withCount(string|array $relations)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder where(string|\Closure $column, mixed $operator = null, mixed $value = null)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder whereIn(string $column, array $values)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder whereNull(string $column)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder whereNotNull(string $column)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder orderBy(string $column, string $direction = 'asc')
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder orderByDesc(string $column)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder limit(int $value)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder offset(int $value)
 * @method $this|\Brighten\ImmutableModel\ImmutableQueryBuilder select(array|string $columns)
 * @method \Brighten\ImmutableModel\ImmutableCollection|\Illuminate\Support\Collection get(array $columns = ['*'])
 * @method \Brighten\ImmutableModel\ImmutableModel|\Illuminate\Database\Eloquent\Model|null first(array $columns = ['*'])
 * @method int count(string $columns = '*')
 * @method bool exists()
 */
class ImmutableMorphTo
{
    /**
     * The parent immutable model (the one storing the morph type/id).
     */
    private ImmutableModel $parent;

    /**
     * The foreign key column name (morph ID).
     */
    private string $foreignKey;

    /**
     * The owner key on the related model (usually 'id').
     */
    private ?string $ownerKey;

    /**
     * The morph type column name.
     */
    private string $morphType;

    /**
     * The relation name.
     */
    private string $relationName;

    /**
     * Create a new morph-to relationship instance.
     *
     * @param  ImmutableModel  $parent
     */
    public function __construct(
        ImmutableModel $parent,
        string $foreignKey,
        ?string $ownerKey,
        string $morphType,
        string $relationName
    ) {
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->morphType = $morphType;
        $this->relationName = $relationName;
    }

    /**
     * Get the related model based on the morph type.
     *
     * @return ImmutableModel|EloquentModel|null
     */
    public function getResults(): mixed
    {
        $morphType = $this->parent->getRawAttribute($this->morphType);
        $foreignKeyValue = $this->parent->getRawAttribute($this->foreignKey);

        if ($morphType === null || $morphType === '' || $foreignKeyValue === null) {
            return null;
        }

        // Resolve the actual class name from the morph type
        $class = EloquentModel::getActualClassNameForMorph($morphType);

        // Determine the owner key (usually 'id')
        $ownerKey = $this->getOwnerKeyForClass($class);

        // Query the related model
        return $class::where($ownerKey, '=', $foreignKeyValue)->first();
    }

    /**
     * Get the owner key for a given class.
     */
    private function getOwnerKeyForClass(string $class): string
    {
        if ($this->ownerKey !== null) {
            return $this->ownerKey;
        }

        // Get the key name from the model
        if (is_subclass_of($class, ImmutableModel::class)) {
            return (new $class())->getKeyName() ?? 'id';
        }

        return (new $class())->getKeyName();
    }

    /**
     * Eager load the relation on a collection of models.
     *
     * MorphTo requires special handling because different parent models
     * may point to different related model types.
     */
    public function eagerLoadOnCollection(
        ImmutableCollection $models,
        string $name,
        ?Closure $constraints = null
    ): void {
        // Build a dictionary grouped by morph type
        // [morphType => [foreignKeyValue => [parentModels]]]
        $dictionary = [];

        foreach ($models as $model) {
            $morphType = $model->getRawAttribute($this->morphType);
            $foreignKeyValue = $model->getRawAttribute($this->foreignKey);

            if ($morphType === null || $morphType === '' || $foreignKeyValue === null) {
                // Set null for this model immediately
                $model->setRelationInternal($name, null);

                continue;
            }

            if (! isset($dictionary[$morphType])) {
                $dictionary[$morphType] = [];
            }

            if (! isset($dictionary[$morphType][$foreignKeyValue])) {
                $dictionary[$morphType][$foreignKeyValue] = [];
            }

            $dictionary[$morphType][$foreignKeyValue][] = $model;
        }

        // Query each morph type separately
        foreach ($dictionary as $morphType => $keyGroups) {
            $this->matchResultsToType($morphType, $keyGroups, $name, $constraints);
        }
    }

    /**
     * Match results for a specific morph type to parent models.
     *
     * @param  array<mixed, array<ImmutableModel>>  $keyGroups
     */
    private function matchResultsToType(
        string $morphType,
        array $keyGroups,
        string $name,
        ?Closure $constraints
    ): void {
        // Resolve the actual class name
        $class = EloquentModel::getActualClassNameForMorph($morphType);
        $ownerKey = $this->getOwnerKeyForClass($class);

        // Get all foreign key values for this type
        $keys = array_keys($keyGroups);

        // Query related models
        $query = $class::query()->whereIn($ownerKey, $keys);

        if ($constraints !== null) {
            $constraints($query);
        }

        $results = $query->get();

        // Build a lookup by owner key
        $lookup = [];
        foreach ($results as $relatedModel) {
            $key = $this->getOwnerKeyValue($relatedModel, $ownerKey);
            $lookup[$key] = $relatedModel;
        }

        // Match to parent models
        foreach ($keyGroups as $foreignKeyValue => $parentModels) {
            $relatedModel = $lookup[$foreignKeyValue] ?? null;

            foreach ($parentModels as $parentModel) {
                $parentModel->setRelationInternal($name, $relatedModel);
            }
        }
    }

    /**
     * Get the owner key value from a model.
     */
    private function getOwnerKeyValue(mixed $model, string $ownerKey): mixed
    {
        if ($model instanceof ImmutableModel) {
            return $model->getRawAttribute($ownerKey);
        }

        return $model->{$ownerKey};
    }

    /**
     * Get the query builder for the related model.
     *
     * Note: For MorphTo, we need to know the morph type first.
     */
    public function getQuery(): mixed
    {
        $morphType = $this->parent->getRawAttribute($this->morphType);

        if ($morphType === null || $morphType === '') {
            // Return a dummy query that returns nothing
            // This matches Eloquent's behavior
            return $this->parent::query()->whereRaw('0 = 1');
        }

        $class = EloquentModel::getActualClassNameForMorph($morphType);

        return $class::query();
    }

    /**
     * Get a constrained query builder for this relation.
     */
    public function getConstrainedQuery(): mixed
    {
        $morphType = $this->parent->getRawAttribute($this->morphType);
        $foreignKeyValue = $this->parent->getRawAttribute($this->foreignKey);

        if ($morphType === null || $morphType === '' || $foreignKeyValue === null) {
            return $this->parent::query()->whereRaw('0 = 1');
        }

        $class = EloquentModel::getActualClassNameForMorph($morphType);
        $ownerKey = $this->getOwnerKeyForClass($class);

        return $class::where($ownerKey, '=', $foreignKeyValue);
    }

    /**
     * Forward calls to the query builder.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Block mutation methods
        $blocked = ['associate', 'dissociate', 'update', 'delete', 'save'];

        if (in_array($method, $blocked, true)) {
            throw ImmutableModelViolationException::persistenceAttempt($method);
        }

        return $this->getConstrainedQuery()->{$method}(...$parameters);
    }

    /**
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the foreign key column name.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the currently associated morph type value.
     */
    public function getCurrentMorphType(): ?string
    {
        $value = $this->parent->getRawAttribute($this->morphType);

        return $value !== '' ? $value : null;
    }

    /**
     * Get the currently associated foreign key value.
     */
    public function getCurrentForeignKeyValue(): mixed
    {
        return $this->parent->getRawAttribute($this->foreignKey);
    }
}
