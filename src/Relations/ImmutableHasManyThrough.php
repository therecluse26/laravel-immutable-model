<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Represents a has-many-through relationship for immutable models.
 *
 * Example: Country -> Supplier -> User
 * A country has many users through its suppliers.
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
 * @template TThroughModel of ImmutableModel|EloquentModel
 */
class ImmutableHasManyThrough
{
    /**
     * The far parent model (the one declaring the relationship).
     */
    private ImmutableModel $farParent;

    /**
     * The intermediate (through) model class name.
     *
     * @var class-string<TThroughModel>
     */
    private string $throughParent;

    /**
     * The related model class name.
     *
     * @var class-string<TRelatedModel>
     */
    private string $related;

    /**
     * The foreign key on the intermediate model that references the far parent.
     */
    private string $firstKey;

    /**
     * The foreign key on the related model that references the intermediate model.
     */
    private string $secondKey;

    /**
     * The local key on the far parent model.
     */
    private string $localKey;

    /**
     * The local key on the intermediate model.
     */
    private string $secondLocalKey;

    /**
     * The relation name.
     */
    private string $relationName;

    /**
     * Whether to include soft-deleted intermediate models.
     */
    private bool $withTrashedParents = false;

    /**
     * Create a new has-many-through relationship instance.
     *
     * @param  ImmutableModel  $farParent
     * @param  class-string<TThroughModel>  $throughParent
     * @param  class-string<TRelatedModel>  $related
     */
    public function __construct(
        ImmutableModel $farParent,
        string $throughParent,
        string $related,
        string $firstKey,
        string $secondKey,
        string $localKey,
        string $secondLocalKey,
        string $relationName
    ) {
        $this->farParent = $farParent;
        $this->throughParent = $throughParent;
        $this->related = $related;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->localKey = $localKey;
        $this->secondLocalKey = $secondLocalKey;
        $this->relationName = $relationName;
    }

    /**
     * Include soft-deleted intermediate models in the result.
     */
    public function withTrashedParents(): static
    {
        $this->withTrashedParents = true;

        return $this;
    }

    /**
     * Get the related models through the intermediate model.
     *
     * @return EloquentCollection|Collection
     */
    public function getResults(): EloquentCollection|Collection
    {
        $localKeyValue = $this->farParent->getRawAttribute($this->localKey);

        if ($localKeyValue === null) {
            return new EloquentCollection([]);
        }

        return $this->buildThroughQuery()
            ->where($this->getQualifiedFirstKeyName(), '=', $localKeyValue)
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

        return $related::query();
    }

    /**
     * Build a query that joins through the intermediate model.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    private function buildThroughQuery(): mixed
    {
        $query = $this->getQuery();

        $relatedTable = $this->getRelatedTableName();
        $throughTable = $this->getThroughTableName();

        // Join through table to related table
        // related.second_key = through.second_local_key
        $query->join(
            $throughTable,
            $relatedTable . '.' . $this->secondKey,
            '=',
            $throughTable . '.' . $this->secondLocalKey
        );

        // Handle soft deletes on intermediate model
        if ($this->throughParentSoftDeletes() && ! $this->withTrashedParents) {
            $query->whereNull($throughTable . '.deleted_at');
        }

        // Select only related model columns
        $query->select($relatedTable . '.*');

        return $query;
    }

    /**
     * Check if the through parent model uses soft deletes.
     */
    private function throughParentSoftDeletes(): bool
    {
        // Check if Eloquent model uses SoftDeletes trait
        if (in_array(SoftDeletes::class, class_uses_recursive($this->throughParent), true)) {
            return true;
        }

        // For ImmutableModel, check if deleted_at is in casts
        if (is_subclass_of($this->throughParent, ImmutableModel::class)) {
            $through = $this->throughParent;
            $instance = new $through();
            $casts = $instance->getCasts();

            return array_key_exists('deleted_at', $casts);
        }

        return false;
    }

    /**
     * Get the related model's table name.
     */
    private function getRelatedTableName(): string
    {
        $related = $this->related;

        if (is_subclass_of($related, ImmutableModel::class)) {
            return (new $related())->getTable();
        }

        return (new $related())->getTable();
    }

    /**
     * Get the through model's table name.
     */
    private function getThroughTableName(): string
    {
        $through = $this->throughParent;

        if (is_subclass_of($through, ImmutableModel::class)) {
            return (new $through())->getTable();
        }

        return (new $through())->getTable();
    }

    /**
     * Get the qualified first key name (through table + first key).
     */
    private function getQualifiedFirstKeyName(): string
    {
        return $this->getThroughTableName() . '.' . $this->firstKey;
    }

    /**
     * Eager load the relation on a collection of models.
     */
    public function eagerLoadOnCollection(
        EloquentCollection $models,
        string $name,
        ?Closure $constraints = null
    ): void {
        // Collect all local key values from far parents
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

        // Build query with through join
        $query = $this->buildThroughQuery();

        // Add the first key to the select for matching
        $throughTable = $this->getThroughTableName();
        $query->addSelect($throughTable . '.' . $this->firstKey . ' as laravel_through_key');

        $query->whereIn($this->getQualifiedFirstKeyName(), $keys);

        // Apply constraints if provided
        if ($constraints !== null) {
            $constraints($query);
        }

        // Fetch related models
        $results = $query->get();

        // Build dictionary keyed by through key, grouping into arrays
        $dictionary = [];
        foreach ($results as $relatedModel) {
            $throughKey = $this->getThroughKeyValue($relatedModel);
            if (! isset($dictionary[$throughKey])) {
                $dictionary[$throughKey] = [];
            }
            $dictionary[$throughKey][] = $relatedModel;
        }

        // Match to parent models
        foreach ($models as $model) {
            $localKey = $model->getRawAttribute($this->localKey);
            $relatedModels = $dictionary[$localKey] ?? [];

            $model->setRelationInternal($name, new EloquentCollection($relatedModels));
        }
    }

    /**
     * Get the through key value from a related model.
     */
    private function getThroughKeyValue(mixed $model): mixed
    {
        if ($model instanceof ImmutableModel) {
            return $model->getRawAttribute('laravel_through_key');
        }

        $value = $model->getAttribute('laravel_through_key');
        // Remove the temporary attribute
        unset($model->laravel_through_key);

        return $value;
    }

    /**
     * Get a constrained query builder for this relation.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function getConstrainedQuery(): mixed
    {
        $localKeyValue = $this->farParent->getRawAttribute($this->localKey);

        return $this->buildThroughQuery()
            ->where($this->getQualifiedFirstKeyName(), '=', $localKeyValue);
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
        return (new $this->related())->getTable();
    }

    /**
     * Get the intermediate (through) model's table name.
     */
    public function getThroughTable(): string
    {
        return (new $this->throughParent())->getTable();
    }

    /**
     * Get the first key name (on the intermediate model).
     */
    public function getFirstKeyName(): string
    {
        return $this->firstKey;
    }

    /**
     * Get the second key name (on the related model).
     */
    public function getSecondKeyName(): string
    {
        return $this->secondKey;
    }

    /**
     * Get the local key name (on the far parent).
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Get the second local key name (on the intermediate model).
     */
    public function getSecondLocalKeyName(): string
    {
        return $this->secondLocalKey;
    }
}
