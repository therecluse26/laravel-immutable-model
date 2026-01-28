<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableHasOne;
use Brighten\ImmutableModel\Scopes\ImmutableModelScope;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Query builder for immutable models.
 *
 * Extends Laravel's Eloquent Builder for full compatibility with Laravel's
 * relationship system while enforcing immutability by blocking all mutation
 * operations.
 */
class ImmutableQueryBuilder extends Builder
{
    /**
     * The immutable model being queried.
     *
     * @var ImmutableModel
     */
    protected $model;

    /**
     * The relations to eager load.
     *
     * @var array<string, Closure|null>
     */
    protected $eagerLoad = [];

    /**
     * Immutable model scopes that have been removed from the query.
     *
     * @var array<class-string<ImmutableModelScope>>
     */
    protected array $immutableRemovedScopes = [];

    /**
     * Whether to apply global scopes.
     */
    protected bool $withGlobalScopes = true;

    /**
     * Create a new query builder instance.
     */
    public function __construct(ImmutableModel $model)
    {
        $connection = $model->getConnection();
        $query = $connection->query();

        parent::__construct($query);

        $this->model = $model;
        $this->query->from($model->getTable());

        // Include default eager loads from model
        foreach ($model->getWith() as $relation) {
            $this->eagerLoad[$relation] = null;
        }
    }

    /**
     * Apply global scopes to the query.
     */
    public function applyGlobalScopes(): static
    {
        if (! $this->withGlobalScopes) {
            return $this;
        }

        foreach ($this->model::getGlobalScopes() as $scopeClass) {
            if (in_array($scopeClass, $this->immutableRemovedScopes, true)) {
                continue;
            }

            /** @var ImmutableModelScope $scope */
            $scope = new $scopeClass();
            $scope->apply($this);
        }

        return $this;
    }

    /**
     * Remove all global scopes from the query.
     */
    public function withoutGlobalScopes($scopes = null): static
    {
        if ($scopes === null) {
            $this->withGlobalScopes = false;
        }

        return $this;
    }

    /**
     * Remove a specific global scope from the query.
     *
     * @param class-string<ImmutableModelScope>|string $scope
     */
    public function withoutGlobalScope($scope): static
    {
        $this->immutableRemovedScopes[] = $scope;

        return $this;
    }

    // =========================================================================
    // EAGER LOADING
    // =========================================================================

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param string|array<int, string>|array<string, Closure> $relations
     * @param string|Closure|null $callback
     */
    public function with($relations, $callback = null): static
    {
        if (is_string($relations)) {
            $relations = func_num_args() === 2
                ? [$relations => $callback]
                : [$relations];
        }

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            // Handle nested relations
            if (str_contains($name, '.')) {
                $this->addNestedEagerLoad($name, $constraints);
            } else {
                $this->eagerLoad[$name] = $constraints;
            }
        }

        return $this;
    }

    /**
     * Add nested eager load constraints.
     */
    private function addNestedEagerLoad(string $name, ?Closure $constraints): void
    {
        $parts = explode('.', $name);
        $current = array_shift($parts);

        // Ensure the parent relation is loaded
        if (! isset($this->eagerLoad[$current])) {
            $this->eagerLoad[$current] = null;
        }

        // For now, store nested relations with their full path
        // The hydrator will handle this
        $this->eagerLoad[$name] = $constraints;
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param string|array<int, string> $relations
     */
    public function withCount($relations): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        foreach ($relations as $relation) {
            // withCount adds a {relation}_count column via subquery
            // This requires knowledge of the relation to build the subquery
            // For now, mark it for processing during hydration
            $this->eagerLoad['__count_' . $relation] = true;
        }

        return $this;
    }

    // =========================================================================
    // TERMINAL METHODS (Execute Query)
    // =========================================================================

    /**
     * Execute the query and get all results.
     *
     * @param array<int, string>|string $columns
     * @return ImmutableCollection<int, ImmutableModel>
     */
    public function get($columns = ['*']): ImmutableCollection
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        $results = $this->query->get();

        $models = [];
        foreach ($results as $row) {
            $models[] = $this->hydrateModel((array) $row);
        }

        $collection = new ImmutableCollection($models);

        // Apply eager loading
        if (! empty($this->eagerLoad)) {
            $this->loadRelations($collection);
        }

        return $collection;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array<int, string>|string $columns
     */
    public function first($columns = ['*']): ?ImmutableModel
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        $row = $this->query->first();

        if ($row === null) {
            return null;
        }

        $model = $this->hydrateModel((array) $row);

        // Apply eager loading for single model
        if (! empty($this->eagerLoad)) {
            $collection = new ImmutableCollection([$model]);
            $this->loadRelations($collection);
            $model = $collection->first();
        }

        return $model;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param array<int, string>|string $columns
     * @throws ModelNotFoundException
     */
    public function firstOrFail($columns = ['*']): ImmutableModel
    {
        $model = $this->first($columns);

        if ($model === null) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model));
        }

        return $model;
    }

    /**
     * Find a model by its primary key.
     *
     * @param mixed $id
     * @param array<int, string>|string $columns
     * @throws ImmutableModelConfigurationException If model has no primary key
     */
    public function find($id, $columns = ['*']): ?ImmutableModel
    {
        $keyName = $this->model->getKeyName();

        if ($keyName === null) {
            throw ImmutableModelConfigurationException::missingPrimaryKey('find');
        }

        return $this->where($keyName, '=', $id)->first($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param mixed $id
     * @param array<int, string>|string $columns
     * @throws ModelNotFoundException
     * @throws ImmutableModelConfigurationException If model has no primary key
     */
    public function findOrFail($id, $columns = ['*']): ImmutableModel
    {
        $keyName = $this->model->getKeyName();

        if ($keyName === null) {
            throw ImmutableModelConfigurationException::missingPrimaryKey('findOrFail');
        }

        $model = $this->find($id, $columns);

        if ($model === null) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model), $id);
        }

        return $model;
    }

    // =========================================================================
    // HYDRATION
    // =========================================================================

    /**
     * Hydrate a model from row data.
     *
     * @param array<string, mixed> $row
     */
    private function hydrateModel(array $row): ImmutableModel
    {
        $class = get_class($this->model);

        return $class::fromRow($row);
    }

    /**
     * Load the given relations on the collection.
     */
    private function loadRelations(ImmutableCollection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        foreach ($this->eagerLoad as $name => $constraints) {
            // Skip count relations for now
            if (str_starts_with($name, '__count_')) {
                continue;
            }

            // Skip nested relations (they're handled by their parent)
            if (str_contains($name, '.')) {
                continue;
            }

            $this->loadRelation($models, $name, $constraints);
        }
    }

    /**
     * Load a single relation onto the models.
     *
     * @param Closure|null $constraints
     */
    private function loadRelation(ImmutableCollection $models, string $name, ?Closure $constraints): void
    {
        // Get the first model to determine relation type
        $firstModel = $models->first();

        if (! method_exists($firstModel, $name)) {
            return;
        }

        $relation = $firstModel->{$name}();

        // Match results back to models
        if ($relation instanceof ImmutableBelongsTo ||
            $relation instanceof ImmutableHasOne ||
            $relation instanceof ImmutableHasMany) {
            $relation->eagerLoadOnCollection($models, $name, $constraints);
        }
    }

    // =========================================================================
    // MODEL ACCESS
    // =========================================================================

    /**
     * Get the model instance.
     *
     * @return ImmutableModel
     */
    public function getModel(): ImmutableModel
    {
        return $this->model;
    }

    /**
     * Get the underlying query builder (for internal use).
     *
     * @internal
     */
    public function getBaseQuery(): \Illuminate\Database\Query\Builder
    {
        return $this->query;
    }

    // =========================================================================
    // FORBIDDEN METHODS (Throw on attempt)
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function insert(array $values): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('insert');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $values = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('update');
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
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('upsert');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function truncate(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('truncate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forceDelete(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceDelete');
    }
}
