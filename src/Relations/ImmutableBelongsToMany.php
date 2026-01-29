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
 * Represents a many-to-many relationship for immutable models.
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
class ImmutableBelongsToMany
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
     * The intermediate pivot table name.
     */
    private string $table;

    /**
     * The foreign key of the parent model on the pivot table.
     */
    private string $foreignPivotKey;

    /**
     * The foreign key of the related model on the pivot table.
     */
    private string $relatedPivotKey;

    /**
     * The local key on the parent model.
     */
    private string $parentKey;

    /**
     * The local key on the related model.
     */
    private string $relatedKey;

    /**
     * The relation name.
     */
    private string $relationName;

    /**
     * Additional pivot columns to retrieve.
     *
     * @var array<int, string>
     */
    private array $pivotColumns = [];

    /**
     * Whether to include timestamps in pivot data.
     */
    private bool $withTimestamps = false;

    /**
     * The custom name for the created_at timestamp.
     */
    private ?string $pivotCreatedAt = null;

    /**
     * The custom name for the updated_at timestamp.
     */
    private ?string $pivotUpdatedAt = null;

    /**
     * The name of the accessor for the pivot data.
     */
    private string $accessor = 'pivot';

    /**
     * Create a new belongs-to-many relationship instance.
     *
     * @param  ImmutableModel  $parent
     * @param  class-string<TRelatedModel>  $related
     */
    public function __construct(
        ImmutableModel $parent,
        string $related,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        string $relationName
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;
    }

    /**
     * Specify additional columns to retrieve from the pivot table.
     *
     * @param  string|array<int, string>  ...$columns
     */
    public function withPivot(string|array ...$columns): static
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns,
            is_array($columns[0] ?? null) ? $columns[0] : $columns
        );

        return $this;
    }

    /**
     * Specify that the pivot table has timestamps.
     */
    public function withTimestamps(?string $createdAt = null, ?string $updatedAt = null): static
    {
        $this->withTimestamps = true;
        $this->pivotCreatedAt = $createdAt;
        $this->pivotUpdatedAt = $updatedAt;

        return $this;
    }

    /**
     * Specify a custom accessor name for the pivot data.
     */
    public function as(string $accessor): static
    {
        $this->accessor = $accessor;

        return $this;
    }

    /**
     * Get the related models with pivot data.
     *
     * @return EloquentCollection|Collection
     */
    public function getResults(): EloquentCollection|Collection
    {
        $parentKeyValue = $this->parent->getRawAttribute($this->parentKey);

        if ($parentKeyValue === null) {
            return new EloquentCollection([]);
        }

        $query = $this->buildSelectQuery();
        $query->where($this->qualifyPivotColumn($this->foreignPivotKey), '=', $parentKeyValue);

        $results = $query->get();

        return $this->hydratePivotRelation($results);
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
     * Build a query that joins the pivot table and selects the appropriate columns.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    private function buildSelectQuery(): mixed
    {
        $query = $this->getQuery();

        // Get the related table name
        $relatedTable = $this->getRelatedTableName();

        // Join pivot table to related table
        $query->join(
            $this->table,
            $relatedTable . '.' . $this->relatedKey,
            '=',
            $this->table . '.' . $this->relatedPivotKey
        );

        // Select related model columns
        $query->select($relatedTable . '.*');

        // Select pivot columns with prefixed aliases
        foreach ($this->getPivotColumns() as $column) {
            $query->addSelect($this->table . '.' . $column . ' as pivot_' . $column);
        }

        return $query;
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
     * Get all pivot columns to select.
     *
     * @return array<int, string>
     */
    private function getPivotColumns(): array
    {
        $columns = array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            $this->pivotColumns
        );

        if ($this->withTimestamps) {
            $columns[] = $this->pivotCreatedAt ?? 'created_at';
            $columns[] = $this->pivotUpdatedAt ?? 'updated_at';
        }

        return array_unique($columns);
    }

    /**
     * Qualify a pivot column with the table name.
     */
    private function qualifyPivotColumn(string $column): string
    {
        return $this->table . '.' . $column;
    }

    /**
     * Hydrate the pivot relation on the given models.
     *
     * @param  EloquentCollection|Collection  $models
     * @return EloquentCollection|Collection
     */
    private function hydratePivotRelation(EloquentCollection|Collection $models): EloquentCollection|Collection
    {
        foreach ($models as $model) {
            $this->attachPivotToModel($model);
        }

        return $models;
    }

    /**
     * Attach pivot data to a model.
     */
    private function attachPivotToModel(mixed $model): void
    {
        $pivotAttributes = $this->extractPivotAttributes($model);
        $pivot = new ImmutablePivot(
            $pivotAttributes,
            $this->table,
            $this->foreignPivotKey,
            $this->relatedPivotKey
        );

        if ($model instanceof ImmutableModel) {
            // Use internal method to set the pivot
            $model->setRelationInternal($this->accessor, $pivot);
        } else {
            // For Eloquent models, set as a relation
            $model->setRelation($this->accessor, $pivot);
        }
    }

    /**
     * Extract pivot attributes from a model and remove them from the model.
     *
     * @return array<string, mixed>
     */
    private function extractPivotAttributes(mixed $model): array
    {
        $pivotAttributes = [];

        foreach ($this->getPivotColumns() as $column) {
            $pivotKey = 'pivot_' . $column;

            if ($model instanceof ImmutableModel) {
                $value = $model->getRawAttribute($pivotKey);
            } else {
                $value = $model->getAttribute($pivotKey);
                // Remove the pivot_ prefixed attribute from Eloquent model
                unset($model->$pivotKey);
            }

            $pivotAttributes[$column] = $value;
        }

        return $pivotAttributes;
    }

    /**
     * Eager load the relation on a collection of models.
     */
    public function eagerLoadOnCollection(
        EloquentCollection $models,
        string $name,
        ?Closure $constraints = null
    ): void {
        // Collect all parent key values
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getRawAttribute($this->parentKey);
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

        // Build query with pivot join
        $query = $this->buildSelectQuery();
        $query->whereIn($this->qualifyPivotColumn($this->foreignPivotKey), $keys);

        // Apply constraints if provided
        if ($constraints !== null) {
            $constraints($query);
        }

        // Fetch related models
        $results = $query->get();

        // Hydrate pivot data
        $this->hydratePivotRelation($results);

        // Build dictionary keyed by foreign pivot key (parent's key value)
        $dictionary = [];
        foreach ($results as $relatedModel) {
            $foreignKeyValue = $this->getPivotForeignKeyValue($relatedModel);
            if (! isset($dictionary[$foreignKeyValue])) {
                $dictionary[$foreignKeyValue] = [];
            }
            $dictionary[$foreignKeyValue][] = $relatedModel;
        }

        // Match to parent models
        foreach ($models as $model) {
            $parentKey = $model->getRawAttribute($this->parentKey);
            $relatedModels = $dictionary[$parentKey] ?? [];

            $model->setRelationInternal($name, new EloquentCollection($relatedModels));
        }
    }

    /**
     * Get the foreign pivot key value from a related model's pivot.
     */
    private function getPivotForeignKeyValue(mixed $model): mixed
    {
        if ($model instanceof ImmutableModel) {
            $pivot = $model->getRelation($this->accessor);
        } else {
            $pivot = $model->{$this->accessor};
        }

        if ($pivot instanceof ImmutablePivot) {
            return $pivot->getAttribute($this->foreignPivotKey);
        }

        return $pivot->{$this->foreignPivotKey} ?? null;
    }

    /**
     * Get a constrained query builder for this relation.
     *
     * @return ImmutableQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function getConstrainedQuery(): mixed
    {
        $parentKeyValue = $this->parent->getRawAttribute($this->parentKey);
        $query = $this->buildSelectQuery();

        return $query->where($this->qualifyPivotColumn($this->foreignPivotKey), '=', $parentKeyValue);
    }

    /**
     * Forward calls to the query builder.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Block mutation methods
        $blocked = [
            'attach', 'detach', 'sync', 'syncWithoutDetaching', 'toggle',
            'updateExistingPivot', 'create', 'save', 'saveMany', 'update',
            'delete', 'insert', 'touch', 'firstOrCreate', 'updateOrCreate',
        ];

        if (in_array($method, $blocked, true)) {
            throw ImmutableModelViolationException::persistenceAttempt($method);
        }

        return $this->getConstrainedQuery()->{$method}(...$parameters);
    }

    /**
     * Get the pivot table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the foreign pivot key name.
     */
    public function getForeignPivotKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    /**
     * Get the related pivot key name.
     */
    public function getRelatedPivotKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the parent key name.
     */
    public function getParentKeyName(): string
    {
        return $this->parentKey;
    }

    /**
     * Get the related key name.
     */
    public function getRelatedKeyName(): string
    {
        return $this->relatedKey;
    }

    /**
     * Get the pivot accessor name.
     */
    public function getPivotAccessor(): string
    {
        return $this->accessor;
    }
}
