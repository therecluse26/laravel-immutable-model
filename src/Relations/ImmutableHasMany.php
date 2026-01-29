<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;

/**
 * Represents a has-many relationship for immutable models.
 *
 * @method $this|ImmutableQueryBuilder with(string|array $relations, string|\Closure|null $callback = null)
 * @method $this|ImmutableQueryBuilder withCount(string|array $relations)
 * @method $this|ImmutableQueryBuilder where(string|\Closure $column, mixed $operator = null, mixed $value = null)
 * @method $this|ImmutableQueryBuilder whereIn(string $column, array $values)
 * @method $this|ImmutableQueryBuilder whereNull(string $column)
 * @method $this|ImmutableQueryBuilder whereNotNull(string $column)
 * @method $this|ImmutableQueryBuilder orderBy(string $column, string $direction = 'asc')
 * @method $this|ImmutableQueryBuilder orderByDesc(string $column)
 * @method $this|ImmutableQueryBuilder limit(int $value)
 * @method $this|ImmutableQueryBuilder offset(int $value)
 * @method $this|ImmutableQueryBuilder select(array|string $columns)
 * @method EloquentCollection|Collection get(array $columns = ['*'])
 * @method TRelatedModel|null first(array $columns = ['*'])
 * @method int count(string $columns = '*')
 * @method bool exists()
 *
 * @template TRelatedModel of ImmutableModel|EloquentModel
 */
class ImmutableHasMany
{
    /**
     * The parent immutable model.
     */
    private ImmutableModel $parent;

    /**
     * The related model class name.
     *
     * @var class-string<TRelatedModel>
     */
    private string $related;

    /**
     * The foreign key on the related model.
     */
    private string $foreignKey;

    /**
     * The local key on the parent model.
     */
    private string $localKey;

    /**
     * The relation name.
     */
    private string $relationName;

    /**
     * Create a new has-many relationship instance.
     *
     * @param ImmutableModel $parent
     * @param class-string<TRelatedModel> $related
     * @param string $foreignKey
     * @param string $localKey
     * @param string $relationName
     */
    public function __construct(
        ImmutableModel $parent,
        string $related,
        string $foreignKey,
        string $localKey,
        string $relationName
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->relationName = $relationName;
    }

    /**
     * Get the related models.
     *
     * @return EloquentCollection|Collection
     */
    public function getResults(): EloquentCollection|Collection
    {
        $localKeyValue = $this->parent->getRawAttribute($this->localKey);

        if ($localKeyValue === null) {
            return new EloquentCollection([]);
        }

        return $this->getQuery()
            ->where($this->foreignKey, '=', $localKeyValue)
            ->get();
    }

    /**
     * Check if the related model is an ImmutableModel.
     */
    private function isImmutableRelated(): bool
    {
        return is_subclass_of($this->related, ImmutableModel::class);
    }

    /**
     * Get a new query builder for the related model.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function getQuery(): mixed
    {
        $related = $this->related;

        if (is_subclass_of($related, ImmutableModel::class)) {
            return $related::query();
        }

        // Handle Eloquent models
        return $related::query();
    }

    /**
     * Eager load the relation on a collection of models.
     */
    public function eagerLoadOnCollection(
        EloquentCollection $models,
        string $name,
        ?Closure $constraints = null
    ): void {
        // Collect all local key values
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getRawAttribute($this->localKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $keys = array_unique($keys);

        if (empty($keys)) {
            // Set empty collection for all models
            $emptyCollection = new EloquentCollection([]);

            foreach ($models as $model) {
                $model->setRelationInternal($name, $emptyCollection);
            }

            return;
        }

        // Build query
        $query = $this->getQuery()->whereIn($this->foreignKey, $keys);

        // Apply constraints if provided
        if ($constraints !== null) {
            $constraints($query);
        }

        // Fetch related models
        $related = $query->get();

        // Build dictionary keyed by foreign key (grouping for hasMany)
        $dictionary = [];
        foreach ($related as $relatedModel) {
            $key = $this->getRelatedForeignKey($relatedModel);
            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $relatedModel;
        }

        // Match to parent models
        foreach ($models as $model) {
            $localKey = $model->getRawAttribute($this->localKey);
            $relatedModels = $dictionary[$localKey] ?? [];

            $model->setRelationInternal($name, new EloquentCollection($relatedModels));
        }
    }

    /**
     * Get the foreign key value from a related model.
     */
    private function getRelatedForeignKey(mixed $model): mixed
    {
        if ($model instanceof ImmutableModel) {
            return $model->getRawAttribute($this->foreignKey);
        }

        return $model->{$this->foreignKey};
    }

    /**
     * Get a constrained query builder for this relation.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function getConstrainedQuery(): mixed
    {
        $localKeyValue = $this->parent->getRawAttribute($this->localKey);

        return $this->getQuery()->where($this->foreignKey, '=', $localKeyValue);
    }

    /**
     * Forward calls to the query builder.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Block mutation methods
        $blocked = ['create', 'save', 'update', 'delete', 'insert', 'make', 'createMany', 'saveMany'];
        if (in_array($method, $blocked, true)) {
            throw ImmutableModelViolationException::persistenceAttempt($method);
        }

        return $this->getConstrainedQuery()->{$method}(...$parameters);
    }

    /**
     * Get the related model's table name.
     */
    public function getRelatedTable(): string
    {
        $related = $this->related;

        return (new $related())->getTable();
    }

    /**
     * Get the foreign key name.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key name.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}
