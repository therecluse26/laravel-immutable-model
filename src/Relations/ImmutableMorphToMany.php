<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;

/**
 * Represents a polymorphic many-to-many relationship for immutable models.
 *
 * Example: A Post morphToMany Tags via a taggables pivot table
 * The taggables table has: tag_id, taggable_id, taggable_type
 *
 * When $inverse=true, this becomes a morphedByMany relationship.
 *
 * @template TRelatedModel of ImmutableModel|EloquentModel
 */
class ImmutableMorphToMany
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
     * The morph name (base for type column, e.g., 'taggable').
     */
    private string $name;

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
     * Whether this is an inverse relationship (morphedByMany).
     */
    private bool $inverse;

    /**
     * The morph type column name.
     */
    private string $morphType;

    /**
     * The morph class to filter by.
     */
    private string $morphClass;

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
     * Create a new morph-to-many relationship instance.
     *
     * @param  ImmutableModel  $parent
     * @param  class-string<TRelatedModel>  $related
     */
    public function __construct(
        ImmutableModel $parent,
        string $related,
        string $name,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        string $relationName,
        bool $inverse = false
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->name = $name;
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;
        $this->inverse = $inverse;

        // The morph type column is {name}_type
        $this->morphType = $name . '_type';

        // Determine the morph class based on inverse flag
        if ($inverse) {
            // morphedByMany: filter by the related model's morph class
            $this->morphClass = $this->getRelatedMorphClass();
        } else {
            // morphToMany: filter by the parent model's morph class
            $this->morphClass = $parent->getMorphClass();
        }
    }

    /**
     * Get the related model's morph class.
     */
    private function getRelatedMorphClass(): string
    {
        $related = $this->related;

        if (is_subclass_of($related, ImmutableModel::class)) {
            return (new $related())->getMorphClass();
        }

        // For Eloquent models
        return (new $related())->getMorphClass();
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
     * @return ImmutableCollection|Collection
     */
    public function getResults(): ImmutableCollection|Collection
    {
        $parentKeyValue = $this->parent->getRawAttribute($this->parentKey);

        if ($parentKeyValue === null) {
            return $this->isImmutableRelated()
                ? new ImmutableCollection([])
                : new Collection([]);
        }

        $query = $this->buildSelectQuery();
        $query->where($this->qualifyPivotColumn($this->foreignPivotKey), '=', $parentKeyValue);
        $query->where($this->qualifyPivotColumn($this->morphType), '=', $this->morphClass);

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
            [$this->foreignPivotKey, $this->relatedPivotKey, $this->morphType],
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
     * @param  ImmutableCollection|Collection  $models
     * @return ImmutableCollection|Collection
     */
    private function hydratePivotRelation(ImmutableCollection|Collection $models): ImmutableCollection|Collection
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
            $model->setRelationInternal($this->accessor, $pivot);
        } else {
            $model->setRelation($this->accessor, $pivot);
        }
    }

    /**
     * Extract pivot attributes from a model.
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
        ImmutableCollection $models,
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
            $emptyCollection = $this->isImmutableRelated()
                ? new ImmutableCollection([])
                : new Collection([]);

            foreach ($models as $model) {
                $model->setRelationInternal($name, $emptyCollection);
            }

            return;
        }

        // Build query with pivot join and morph type constraint
        $query = $this->buildSelectQuery();
        $query->whereIn($this->qualifyPivotColumn($this->foreignPivotKey), $keys);
        $query->where($this->qualifyPivotColumn($this->morphType), '=', $this->morphClass);

        // Apply constraints if provided
        if ($constraints !== null) {
            $constraints($query);
        }

        // Fetch related models
        $results = $query->get();

        // Hydrate pivot data
        $this->hydratePivotRelation($results);

        // Build dictionary keyed by foreign pivot key
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

            $collection = $this->isImmutableRelated()
                ? new ImmutableCollection($relatedModels)
                : new Collection($relatedModels);

            $model->setRelationInternal($name, $collection);
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

        return $query
            ->where($this->qualifyPivotColumn($this->foreignPivotKey), '=', $parentKeyValue)
            ->where($this->qualifyPivotColumn($this->morphType), '=', $this->morphClass);
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
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the morph class being filtered.
     */
    public function getMorphClass(): string
    {
        return $this->morphClass;
    }

    /**
     * Determine if this is an inverse relationship.
     */
    public function isInverse(): bool
    {
        return $this->inverse;
    }

    /**
     * Get the pivot accessor name.
     */
    public function getPivotAccessor(): string
    {
        return $this->accessor;
    }
}
