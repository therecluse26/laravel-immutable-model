<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Represents a belongs-to relationship for immutable models.
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
 * @method ImmutableCollection|\Illuminate\Support\Collection get(array $columns = ['*'])
 * @method TRelatedModel|null first(array $columns = ['*'])
 * @method int count(string $columns = '*')
 * @method bool exists()
 *
 * @template TRelatedModel of ImmutableModel|EloquentModel
 */
class ImmutableBelongsTo
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
     * The foreign key on the parent model.
     */
    private string $foreignKey;

    /**
     * The owner key on the related model.
     */
    private string $ownerKey;

    /**
     * The relation name.
     */
    private string $relationName;

    /**
     * Create a new belongs-to relationship instance.
     *
     * @param ImmutableModel $parent
     * @param class-string<TRelatedModel> $related
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $relationName
     */
    public function __construct(
        ImmutableModel $parent,
        string $related,
        string $foreignKey,
        string $ownerKey,
        string $relationName
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;
    }

    /**
     * Get the related model instance.
     *
     * @return TRelatedModel|null
     */
    public function getResults(): mixed
    {
        $foreignKeyValue = $this->parent->getRawAttribute($this->foreignKey);

        if ($foreignKeyValue === null) {
            return null;
        }

        return $this->getQuery()
            ->where($this->ownerKey, '=', $foreignKeyValue)
            ->first();
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
        ImmutableCollection $models,
        string $name,
        ?Closure $constraints = null
    ): void {
        // Collect all foreign key values
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getRawAttribute($this->foreignKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $keys = array_unique($keys);

        if (empty($keys)) {
            // Set null for all models
            foreach ($models as $model) {
                $model->setRelationInternal($name, null);
            }

            return;
        }

        // Build query
        $query = $this->getQuery()->whereIn($this->ownerKey, $keys);

        // Apply constraints if provided
        if ($constraints !== null) {
            $constraints($query);
        }

        // Fetch related models
        $related = $query->get();

        // Build dictionary keyed by owner key
        $dictionary = [];
        foreach ($related as $relatedModel) {
            $key = $this->getRelatedKey($relatedModel);
            $dictionary[$key] = $relatedModel;
        }

        // Match to parent models
        foreach ($models as $model) {
            $foreignKey = $model->getRawAttribute($this->foreignKey);
            $model->setRelationInternal($name, $dictionary[$foreignKey] ?? null);
        }
    }

    /**
     * Get the key from a related model.
     */
    private function getRelatedKey(mixed $model): mixed
    {
        if ($model instanceof ImmutableModel) {
            return $model->getRawAttribute($this->ownerKey);
        }

        return $model->{$this->ownerKey};
    }

    /**
     * Get a constrained query builder for this relation.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function getConstrainedQuery(): mixed
    {
        $foreignKeyValue = $this->parent->getRawAttribute($this->foreignKey);

        return $this->getQuery()->where($this->ownerKey, '=', $foreignKeyValue);
    }

    /**
     * Forward calls to the query builder.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Block mutation methods
        $blocked = ['create', 'save', 'update', 'delete', 'insert', 'associate', 'dissociate'];
        if (in_array($method, $blocked, true)) {
            throw ImmutableModelViolationException::persistenceAttempt($method);
        }

        return $this->getConstrainedQuery()->{$method}(...$parameters);
    }
}
