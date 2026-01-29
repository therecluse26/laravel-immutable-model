<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Represents a polymorphic one-to-one relationship for immutable models.
 *
 * Example: A Post morphOne Image (where Image is polymorphic via imageable_type/imageable_id)
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
 * @method EloquentCollection|\Illuminate\Support\Collection get(array $columns = ['*'])
 * @method TRelatedModel|null first(array $columns = ['*'])
 * @method int count(string $columns = '*')
 * @method bool exists()
 *
 * @template TRelatedModel of ImmutableModel|EloquentModel
 */
class ImmutableMorphOne
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
     * The morph type column name.
     */
    private string $morphType;

    /**
     * The foreign key column name (morph ID).
     */
    private string $foreignKey;

    /**
     * The local key on the parent model.
     */
    private string $localKey;

    /**
     * The parent's morph class name.
     */
    private string $morphClass;

    /**
     * The relation name.
     */
    private string $relationName;

    /**
     * Create a new morph-one relationship instance.
     *
     * @param  ImmutableModel  $parent
     * @param  class-string<TRelatedModel>  $related
     */
    public function __construct(
        ImmutableModel $parent,
        string $related,
        string $morphType,
        string $foreignKey,
        string $localKey,
        string $relationName
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->morphType = $morphType;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->morphClass = $parent->getMorphClass();
        $this->relationName = $relationName;
    }

    /**
     * Get the related model.
     *
     * @return TRelatedModel|null
     */
    public function getResults(): mixed
    {
        $localKeyValue = $this->parent->getRawAttribute($this->localKey);

        if ($localKeyValue === null) {
            return null;
        }

        return $this->getConstrainedQuery()->first();
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

        return $related::query();
    }

    /**
     * Get a constrained query builder with morph type and foreign key constraints.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function getConstrainedQuery(): mixed
    {
        $localKeyValue = $this->parent->getRawAttribute($this->localKey);

        return $this->getQuery()
            ->where($this->morphType, '=', $this->morphClass)
            ->where($this->foreignKey, '=', $localKeyValue);
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
            // Set null for all models
            foreach ($models as $model) {
                $model->setRelationInternal($name, null);
            }

            return;
        }

        // Build query with morph type constraint
        $query = $this->getQuery()
            ->where($this->morphType, '=', $this->morphClass)
            ->whereIn($this->foreignKey, $keys);

        // Apply constraints if provided
        if ($constraints !== null) {
            $constraints($query);
        }

        // Fetch related models
        $results = $query->get();

        // Build dictionary keyed by foreign key (only keep first match for MorphOne)
        $dictionary = [];
        foreach ($results as $relatedModel) {
            $foreignKeyValue = $this->getRelatedForeignKey($relatedModel);
            if (! isset($dictionary[$foreignKeyValue])) {
                $dictionary[$foreignKeyValue] = $relatedModel;
            }
        }

        // Match to parent models
        foreach ($models as $model) {
            $localKey = $model->getRawAttribute($this->localKey);
            $model->setRelationInternal($name, $dictionary[$localKey] ?? null);
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
     * Forward calls to the query builder.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Block mutation methods
        $blocked = ['create', 'save', 'update', 'delete', 'insert', 'make', 'forceCreate'];

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
     * Get the morph class name.
     */
    public function getMorphClass(): string
    {
        return $this->morphClass;
    }

    /**
     * Get the foreign key column name.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }
}
