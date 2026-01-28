<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Scopes\ImmutableModelScope;
use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Query builder wrapper for immutable models.
 *
 * Provides Eloquent-compatible query building while blocking all mutation operations.
 * The underlying Laravel query builder is never exposed directly.
 */
final class ImmutableQueryBuilder
{
    /**
     * The underlying Laravel query builder.
     */
    private BaseBuilder $query;

    /**
     * The model being queried.
     */
    private ImmutableModel $model;

    /**
     * The relations to eager load.
     *
     * @var array<string, Closure|null>
     */
    private array $eagerLoad = [];

    /**
     * Scopes that have been removed from the query.
     *
     * @var array<class-string<ImmutableModelScope>>
     */
    private array $removedScopes = [];

    /**
     * Whether to apply global scopes.
     */
    private bool $withGlobalScopes = true;

    /**
     * Create a new query builder instance.
     */
    public function __construct(ImmutableModel $model)
    {
        $this->model = $model;
        $this->query = $model->getConnection()
            ->table($model->getTable());

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
            if (in_array($scopeClass, $this->removedScopes, true)) {
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
    public function withoutGlobalScopes(): static
    {
        $this->withGlobalScopes = false;

        return $this;
    }

    /**
     * Remove a specific global scope from the query.
     *
     * @param class-string<ImmutableModelScope> $scope
     */
    public function withoutGlobalScope(string $scope): static
    {
        $this->removedScopes[] = $scope;

        return $this;
    }

    // =========================================================================
    // WHERE CLAUSES
    // =========================================================================

    /**
     * Add a basic where clause to the query.
     */
    public function where(mixed $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        $this->query->where($column, $operator, $value, $boolean);

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(mixed $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->query->whereIn($column, $values, $boolean, $not);

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        $this->query->whereNotIn($column, $values, $boolean);

        return $this;
    }

    /**
     * Add a "where between" clause to the query.
     *
     * @param array<int, mixed> $values
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->query->whereBetween($column, $values, $boolean, $not);

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->query->whereNull($column, $boolean, $not);

        return $this;
    }

    /**
     * Add a "where not null" clause to the query.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        $this->query->whereNotNull($column, $boolean);

        return $this;
    }

    /**
     * Add a "where date" clause to the query.
     */
    public function whereDate(string $column, mixed $operator, mixed $value = null, string $boolean = 'and'): static
    {
        $this->query->whereDate($column, $operator, $value, $boolean);

        return $this;
    }

    /**
     * Add a "where column" clause comparing two columns.
     */
    public function whereColumn(string $first, ?string $operator = null, ?string $second = null, ?string $boolean = 'and'): static
    {
        $this->query->whereColumn($first, $operator, $second, $boolean);

        return $this;
    }

    /**
     * Apply the callback's query changes if the given "value" is true.
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Apply the callback's query changes if the given "value" is false.
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        if (! $value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    // =========================================================================
    // SELECTION
    // =========================================================================

    /**
     * Set the columns to be selected.
     *
     * @param array<int, string>|string $columns
     */
    public function select(array|string $columns = ['*']): static
    {
        $this->query->select($columns);

        return $this;
    }

    /**
     * Add a new "raw" select expression to the query.
     */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->query->selectRaw($expression, $bindings);

        return $this;
    }

    /**
     * Add a new select column to the query.
     *
     * @param array<int, string>|string $column
     */
    public function addSelect(array|string $column): static
    {
        $this->query->addSelect($column);

        return $this;
    }

    /**
     * Force the query to only return distinct results.
     */
    public function distinct(): static
    {
        $this->query->distinct();

        return $this;
    }

    // =========================================================================
    // ORDERING & LIMITING
    // =========================================================================

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     */
    public function orderByDesc(string $column): static
    {
        $this->query->orderByDesc($column);

        return $this;
    }

    /**
     * Add an "order by" clause for a timestamp column.
     */
    public function latest(string $column = 'created_at'): static
    {
        $this->query->latest($column);

        return $this;
    }

    /**
     * Add an "order by" clause for a timestamp column in ascending order.
     */
    public function oldest(string $column = 'created_at'): static
    {
        $this->query->oldest($column);

        return $this;
    }

    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): static
    {
        $this->query->limit($value);

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): static
    {
        $this->query->offset($value);

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    // =========================================================================
    // JOINS & GROUPING
    // =========================================================================

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner', bool $where = false): static
    {
        $this->query->join($table, $first, $operator, $second, $type, $where);

        return $this;
    }

    /**
     * Add a left join to the query.
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        $this->query->leftJoin($table, $first, $operator, $second);

        return $this;
    }

    /**
     * Add a right join to the query.
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        $this->query->rightJoin($table, $first, $operator, $second);

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param array<int, string>|string $groups
     */
    public function groupBy(array|string ...$groups): static
    {
        $this->query->groupBy(...$groups);

        return $this;
    }

    /**
     * Add a "having" clause to the query.
     */
    public function having(string $column, ?string $operator = null, ?string $value = null, string $boolean = 'and'): static
    {
        $this->query->having($column, $operator, $value, $boolean);

        return $this;
    }

    // =========================================================================
    // EAGER LOADING
    // =========================================================================

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param string|array<int, string>|array<string, Closure> $relations
     */
    public function with(string|array $relations): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
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
    public function withCount(string|array $relations): static
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
     */
    public function get(): ImmutableCollection
    {
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
     */
    public function first(): ?ImmutableModel
    {
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
     * @throws ModelNotFoundException
     */
    public function firstOrFail(): ImmutableModel
    {
        $model = $this->first();

        if ($model === null) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model));
        }

        return $model;
    }

    /**
     * Find a model by its primary key.
     *
     * @throws ImmutableModelConfigurationException If model has no primary key
     */
    public function find(mixed $id): ?ImmutableModel
    {
        $keyName = $this->model->getKeyName();

        if ($keyName === null) {
            throw ImmutableModelConfigurationException::missingPrimaryKey('find');
        }

        return $this->where($keyName, '=', $id)->first();
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @throws ModelNotFoundException
     * @throws ImmutableModelConfigurationException If model has no primary key
     */
    public function findOrFail(mixed $id): ImmutableModel
    {
        $keyName = $this->model->getKeyName();

        if ($keyName === null) {
            throw ImmutableModelConfigurationException::missingPrimaryKey('findOrFail');
        }

        $model = $this->find($id);

        if ($model === null) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model), $id);
        }

        return $model;
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param string|null $key
     * @return Collection<int|string, mixed>
     */
    public function pluck(string $column, ?string $key = null): Collection
    {
        return $this->query->pluck($column, $key);
    }

    /**
     * Retrieve the "count" result of the query.
     */
    public function count(string $columns = '*'): int
    {
        return $this->query->count($columns);
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Retrieve the sum of the values of a given column.
     */
    public function sum(string $column): mixed
    {
        return $this->query->sum($column);
    }

    /**
     * Retrieve the average of the values of a given column.
     */
    public function avg(string $column): mixed
    {
        return $this->query->avg($column);
    }

    /**
     * Retrieve the minimum value of a given column.
     */
    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    /**
     * Retrieve the maximum value of a given column.
     */
    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    // =========================================================================
    // PAGINATION
    // =========================================================================

    /**
     * Paginate the given query.
     *
     * @param array<int, string> $columns
     */
    public function paginate(
        int $perPage = 15,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null
    ): LengthAwarePaginator {
        $page = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);

        $total = $this->query->getCountForPagination();

        $results = $total > 0
            ? $this->forPage($page, $perPage)->get()
            : new ImmutableCollection([]);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * @param array<int, string> $columns
     */
    public function simplePaginate(
        int $perPage = 15,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null
    ): Paginator {
        $page = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);

        $this->offset(($page - 1) * $perPage)->limit($perPage + 1);

        $results = $this->get();

        return new \Illuminate\Pagination\Paginator(
            $results->take($perPage),
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Paginate the given query using a cursor.
     *
     * @param array<int, string> $columns
     */
    public function cursorPaginate(
        int $perPage = 15,
        array $columns = ['*'],
        string $cursorName = 'cursor',
        ?\Illuminate\Pagination\Cursor $cursor = null
    ): CursorPaginator {
        $cursor = $cursor ?: \Illuminate\Pagination\CursorPaginator::resolveCurrentCursor($cursorName);

        $orders = $this->query->orders ?? [];

        if (empty($orders)) {
            // Default to ordering by primary key
            $keyName = $this->model->getKeyName() ?? 'id';
            $this->orderBy($keyName);
        }

        if ($cursor !== null) {
            $this->addCursorConditions($cursor);
        }

        $this->limit($perPage + 1);

        $results = $this->get();

        return new \Illuminate\Pagination\CursorPaginator(
            $results->take($perPage),
            $perPage,
            $cursor,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'cursorName' => $cursorName,
                'parameters' => array_column($orders, 'column'),
            ]
        );
    }

    /**
     * Add cursor conditions to the query.
     */
    private function addCursorConditions(\Illuminate\Pagination\Cursor $cursor): void
    {
        $orders = $this->query->orders ?? [];

        foreach ($orders as $order) {
            $column = $order['column'];
            $direction = strtolower($order['direction']) === 'desc' ? '<' : '>';

            if ($cursor->parameter($column) !== null) {
                $this->where($column, $direction, $cursor->parameter($column));
            }
        }
    }

    /**
     * Set the limit and offset for a given page.
     */
    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // =========================================================================
    // CHUNKING & LAZY ITERATION
    // =========================================================================

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults === 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults === $count);

        return true;
    }

    /**
     * Query lazily, by chunks of the given size.
     *
     * @return LazyCollection<int, ImmutableModel>
     */
    public function cursor(): LazyCollection
    {
        return new LazyCollection(function () {
            foreach ($this->query->cursor() as $row) {
                yield $this->hydrateModel((array) $row);
            }
        });
    }

    /**
     * Query lazily, by chunking the results of a query.
     *
     * @return LazyCollection<int, ImmutableModel>
     */
    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        return new LazyCollection(function () use ($chunkSize) {
            $page = 1;

            while (true) {
                $results = $this->forPage($page++, $chunkSize)->get();

                foreach ($results as $model) {
                    yield $model;
                }

                if ($results->count() < $chunkSize) {
                    return;
                }
            }
        });
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
        $relation->eagerLoadOnCollection($models, $name, $constraints);
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    /**
     * Get the underlying query builder (internal use only).
     *
     * @internal
     */
    public function getBaseQuery(): BaseBuilder
    {
        return $this->query;
    }

    /**
     * Get the model instance.
     *
     * @internal
     */
    public function getModel(): ImmutableModel
    {
        return $this->model;
    }

    /**
     * Clone the query builder.
     */
    public function clone(): static
    {
        $clone = clone $this;
        $clone->query = clone $this->query;

        return $clone;
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
    public function update(array $values): never
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
    public function upsert(array $values, array $uniqueBy, ?array $update = null): never
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
    public function create(array $attributes): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('create');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function save(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('save');
    }
}
