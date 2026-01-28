<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use stdClass;

/**
 * An immutable collection of ImmutableModel instances.
 *
 * Extends Illuminate\Database\Eloquent\Collection for full Laravel compatibility
 * while blocking all mutation operations. This allows ImmutableCollections to work
 * seamlessly with Laravel's relationship system (eager loading, matching, etc.).
 *
 * @template TModel of ImmutableModel
 * @extends EloquentCollection<int, TModel>
 */
class ImmutableCollection extends EloquentCollection
{
    /**
     * Create a new immutable collection instance.
     *
     * @param iterable<TModel> $items
     */
    public function __construct($items = [])
    {
        parent::__construct($items);
    }

    /**
     * Create an ImmutableCollection from raw database rows.
     *
     * @template TTarget of ImmutableModel
     * @param iterable<array<string, mixed>|stdClass> $rows
     * @param class-string<TTarget> $modelClass
     * @return self<TTarget>
     */
    public static function fromRows(iterable $rows, string $modelClass): self
    {
        if (! is_subclass_of($modelClass, ImmutableModel::class)) {
            throw new \InvalidArgumentException(
                "Class [{$modelClass}] must extend ImmutableModel."
            );
        }

        $models = [];
        foreach ($rows as $row) {
            $models[] = $modelClass::fromRow($row);
        }

        return new self($models);
    }

    /**
     * Get the underlying base collection (mutable).
     *
     * This is the only way to obtain a mutable collection from an ImmutableCollection.
     *
     * @return Collection<int, TModel>
     */
    public function toBase(): Collection
    {
        return new Collection($this->items);
    }

    // =========================================================================
    // COLLECTION METHODS THAT PRESERVE IMMUTABLE MODELS
    // These return ImmutableCollection
    // =========================================================================

    /**
     * Run a filter over each of the items.
     *
     * @return self<TModel>
     */
    public function filter(?callable $callback = null): self
    {
        return new self(parent::filter($callback)->values());
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @return self<TModel>
     */
    public function reject($callback = true): self
    {
        return new self(parent::reject($callback)->values());
    }

    /**
     * Filter items by the given key value pair.
     *
     * @return self<TModel>
     */
    public function where($key, $operator = null, $value = null): self
    {
        return new self(parent::where(...func_get_args())->values());
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @return self<TModel>
     */
    public function whereStrict($key, $value): self
    {
        return new self(parent::whereStrict($key, $value)->values());
    }

    /**
     * Filter items such that the value of the given key is in the array.
     *
     * @param array<int, mixed> $values
     * @return self<TModel>
     */
    public function whereIn($key, $values, $strict = false): self
    {
        return new self(parent::whereIn($key, $values, $strict)->values());
    }

    /**
     * Filter items such that the value of the given key is not in the array.
     *
     * @param array<int, mixed> $values
     * @return self<TModel>
     */
    public function whereNotIn($key, $values, $strict = false): self
    {
        return new self(parent::whereNotIn($key, $values, $strict)->values());
    }

    /**
     * Filter items where the given key is null.
     *
     * @return self<TModel>
     */
    public function whereNull($key = null): self
    {
        return new self(parent::whereNull($key)->values());
    }

    /**
     * Filter items where the given key is not null.
     *
     * @return self<TModel>
     */
    public function whereNotNull($key = null): self
    {
        return new self(parent::whereNotNull($key)->values());
    }

    /**
     * Take the first or last n items.
     *
     * @return self<TModel>
     */
    public function take($limit): self
    {
        return new self(parent::take($limit));
    }

    /**
     * Skip the first n items.
     *
     * @return self<TModel>
     */
    public function skip($count): self
    {
        return new self(parent::skip($count)->values());
    }

    /**
     * Slice the underlying collection array.
     *
     * @return self<TModel>
     */
    public function slice($offset, $length = null): self
    {
        return new self(parent::slice($offset, $length)->values());
    }

    /**
     * Return only unique items from the collection.
     *
     * @return self<TModel>
     */
    public function unique($key = null, $strict = false): self
    {
        return new self(parent::unique($key, $strict)->values());
    }

    /**
     * Sort through each item with a callback.
     *
     * @return self<TModel>
     */
    public function sort($callback = null): self
    {
        return new self(parent::sort($callback)->values());
    }

    /**
     * Sort the collection using the given callback.
     *
     * @return self<TModel>
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false): self
    {
        return new self(parent::sortBy($callback, $options, $descending)->values());
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @return self<TModel>
     */
    public function sortByDesc($callback, $options = SORT_REGULAR): self
    {
        return new self(parent::sortByDesc($callback, $options)->values());
    }

    /**
     * Reverse the collection.
     *
     * @return self<TModel>
     */
    public function reverse(): self
    {
        return new self(parent::reverse()->values());
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return self<TModel>
     */
    public function values(): self
    {
        return new self(parent::values());
    }

    // =========================================================================
    // METHODS THAT RETURN BASE COLLECTION
    // These may transform or restructure data in ways incompatible with ImmutableCollection
    // =========================================================================

    /**
     * Group an associative array by a field or using a callback.
     *
     * Returns a base Collection since grouping creates nested collections
     * that need to be modifiable during construction.
     *
     * @return Collection<string|int, Collection<int, TModel>>
     */
    public function groupBy($groupBy, $preserveKeys = false): Collection
    {
        // Get raw items and use a base collection to perform the groupBy
        $base = new Collection($this->items);

        return $base->groupBy($groupBy, $preserveKeys);
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @return Collection<string|int, TModel>
     */
    public function keyBy($keyBy): Collection
    {
        $base = new Collection($this->items);

        return $base->keyBy($keyBy);
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    /**
     * Convert the collection to its array representation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(fn($item) => $item->toArray())->all();
    }

    // =========================================================================
    // FORBIDDEN METHODS (Mutation Attempts)
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function push(...$values): never
    {
        throw ImmutableModelViolationException::collectionMutation('push');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function put($key, $value): never
    {
        throw ImmutableModelViolationException::collectionMutation('put');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forget($keys): never
    {
        throw ImmutableModelViolationException::collectionMutation('forget');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function pop($count = 1): never
    {
        throw ImmutableModelViolationException::collectionMutation('pop');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function shift($count = 1): never
    {
        throw ImmutableModelViolationException::collectionMutation('shift');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function pull($key, $default = null): never
    {
        throw ImmutableModelViolationException::collectionMutation('pull');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function prepend($value, $key = null): never
    {
        throw ImmutableModelViolationException::collectionMutation('prepend');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function add($item): never
    {
        throw ImmutableModelViolationException::collectionMutation('add');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function splice($offset, $length = null, $replacement = []): never
    {
        throw ImmutableModelViolationException::collectionMutation('splice');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function transform(callable $callback): never
    {
        throw ImmutableModelViolationException::collectionMutation('transform');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function offsetSet($key, $value): never
    {
        throw ImmutableModelViolationException::collectionMutation('offsetSet');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function offsetUnset($key): never
    {
        throw ImmutableModelViolationException::collectionMutation('offsetUnset');
    }
}
