<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use ArrayAccess;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use stdClass;
use Traversable;

/**
 * An immutable collection of ImmutableModel instances.
 *
 * Wraps Illuminate\Support\Collection while blocking all mutation operations.
 * Transformations that preserve ImmutableModel items return ImmutableCollection,
 * while transformations that may change item types return base Collection.
 *
 * @template TModel of ImmutableModel
 * @implements IteratorAggregate<int, TModel>
 * @implements ArrayAccess<int, TModel>
 */
class ImmutableCollection implements IteratorAggregate, Countable, ArrayAccess, JsonSerializable, Arrayable, Jsonable
{
    /**
     * The underlying collection.
     *
     * @var Collection<int, TModel>
     */
    private Collection $items;

    /**
     * Create a new immutable collection instance.
     *
     * @param iterable<TModel> $items
     */
    public function __construct(iterable $items = [])
    {
        $this->items = $items instanceof Collection ? $items : new Collection($items);
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
        return new Collection($this->items->all());
    }

    // =========================================================================
    // COLLECTION METHODS THAT PRESERVE IMMUTABLE MODELS
    // These return ImmutableCollection
    // =========================================================================

    /**
     * Get all items in the collection.
     *
     * @return array<int, TModel>
     */
    public function all(): array
    {
        return $this->items->all();
    }

    /**
     * Get the first item from the collection.
     *
     * @return TModel|null
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->items->first($callback, $default);
    }

    /**
     * Get the last item from the collection.
     *
     * @return TModel|null
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->items->last($callback, $default);
    }

    /**
     * Get an item by key.
     *
     * @return TModel|null
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->items->get($key, $default);
    }

    /**
     * Run a filter over each of the items.
     *
     * @return self<TModel>
     */
    public function filter(?callable $callback = null): self
    {
        return new self($this->items->filter($callback)->values());
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @return self<TModel>
     */
    public function reject(callable $callback): self
    {
        return new self($this->items->reject($callback)->values());
    }

    /**
     * Filter items by the given key value pair.
     *
     * @return self<TModel>
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): self
    {
        return new self($this->items->where($key, $operator, $value)->values());
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @return self<TModel>
     */
    public function whereStrict(string $key, mixed $value): self
    {
        return new self($this->items->whereStrict($key, $value)->values());
    }

    /**
     * Filter items such that the value of the given key is in the array.
     *
     * @param array<int, mixed> $values
     * @return self<TModel>
     */
    public function whereIn(string $key, array $values, bool $strict = false): self
    {
        return new self($this->items->whereIn($key, $values, $strict)->values());
    }

    /**
     * Filter items such that the value of the given key is not in the array.
     *
     * @param array<int, mixed> $values
     * @return self<TModel>
     */
    public function whereNotIn(string $key, array $values, bool $strict = false): self
    {
        return new self($this->items->whereNotIn($key, $values, $strict)->values());
    }

    /**
     * Filter items where the given key is null.
     *
     * @return self<TModel>
     */
    public function whereNull(?string $key = null): self
    {
        return new self($this->items->whereNull($key)->values());
    }

    /**
     * Filter items where the given key is not null.
     *
     * @return self<TModel>
     */
    public function whereNotNull(?string $key = null): self
    {
        return new self($this->items->whereNotNull($key)->values());
    }

    /**
     * Take the first or last n items.
     *
     * @return self<TModel>
     */
    public function take(int $limit): self
    {
        return new self($this->items->take($limit));
    }

    /**
     * Skip the first n items.
     *
     * @return self<TModel>
     */
    public function skip(int $count): self
    {
        return new self($this->items->skip($count)->values());
    }

    /**
     * Slice the underlying collection array.
     *
     * @return self<TModel>
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return new self($this->items->slice($offset, $length)->values());
    }

    /**
     * Return only unique items from the collection.
     *
     * @return self<TModel>
     */
    public function unique(string|callable|null $key = null, bool $strict = false): self
    {
        return new self($this->items->unique($key, $strict)->values());
    }

    /**
     * Sort through each item with a callback.
     *
     * @return self<TModel>
     */
    public function sort(?callable $callback = null): self
    {
        return new self($this->items->sort($callback)->values());
    }

    /**
     * Sort the collection using the given callback.
     *
     * @return self<TModel>
     */
    public function sortBy(string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): self
    {
        return new self($this->items->sortBy($callback, $options, $descending)->values());
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @return self<TModel>
     */
    public function sortByDesc(string|callable $callback, int $options = SORT_REGULAR): self
    {
        return new self($this->items->sortByDesc($callback, $options)->values());
    }

    /**
     * Reverse the collection.
     *
     * @return self<TModel>
     */
    public function reverse(): self
    {
        return new self($this->items->reverse()->values());
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return self<TModel>
     */
    public function values(): self
    {
        return new self($this->items->values());
    }

    // =========================================================================
    // COLLECTION METHODS THAT MAY CHANGE ITEM TYPES
    // These return base Collection
    // =========================================================================

    /**
     * Run a map over each of the items.
     *
     * @return Collection<int, mixed>
     */
    public function map(callable $callback): Collection
    {
        return $this->items->map($callback);
    }

    /**
     * Get the values of a given key.
     *
     * @return Collection<int|string, mixed>
     */
    public function pluck(string $value, ?string $key = null): Collection
    {
        return $this->items->pluck($value, $key);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return Collection<int, int|string>
     */
    public function keys(): Collection
    {
        return $this->items->keys();
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @return Collection<int, mixed>
     */
    public function flatMap(callable $callback): Collection
    {
        return $this->items->flatMap($callback);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @return Collection<string|int, Collection>
     */
    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): Collection
    {
        return $this->items->groupBy($groupBy, $preserveKeys);
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @return Collection<string|int, TModel>
     */
    public function keyBy(string|callable $keyBy): Collection
    {
        return $this->items->keyBy($keyBy);
    }

    // =========================================================================
    // AGGREGATE & QUERY METHODS
    // =========================================================================

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Determine if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    /**
     * Determine if an item exists in the collection.
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return $this->items->contains($key, $operator, $value);
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     */
    public function search(mixed $value, bool $strict = false): int|string|false
    {
        return $this->items->search($value, $strict);
    }

    /**
     * Get the sum of the given values.
     */
    public function sum(string|callable|null $callback = null): mixed
    {
        return $this->items->sum($callback);
    }

    /**
     * Get the average value of a given key.
     */
    public function avg(string|callable|null $callback = null): mixed
    {
        return $this->items->avg($callback);
    }

    /**
     * Get the min value of a given key.
     */
    public function min(string|callable|null $callback = null): mixed
    {
        return $this->items->min($callback);
    }

    /**
     * Get the max value of a given key.
     */
    public function max(string|callable|null $callback = null): mixed
    {
        return $this->items->max($callback);
    }

    /**
     * Execute a callback over each item.
     *
     * @return $this
     */
    public function each(callable $callback): self
    {
        $this->items->each($callback);

        return $this;
    }

    /**
     * Reduce the collection to a single value.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return $this->items->reduce($callback, $initial);
    }

    /**
     * Determine if any item passes the given truth test.
     */
    public function some(string|callable $key, mixed $operator = null, mixed $value = null): bool
    {
        return $this->contains($key, $operator, $value);
    }

    /**
     * Determine if every item passes the given truth test.
     */
    public function every(string|callable $key, mixed $operator = null, mixed $value = null): bool
    {
        return $this->items->every($key, $operator, $value);
    }

    // =========================================================================
    // ITERATION & ACCESS
    // =========================================================================

    /**
     * Get an iterator for the items.
     *
     * @return Traversable<int, TModel>
     */
    public function getIterator(): Traversable
    {
        return $this->items->getIterator();
    }

    /**
     * Determine if an item exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->items->offsetExists($offset);
    }

    /**
     * Get an item at a given offset.
     *
     * @return TModel|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items->offsetGet($offset);
    }

    /**
     * Set the item at a given offset (throws).
     *
     * @throws ImmutableModelViolationException
     */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw ImmutableModelViolationException::collectionMutation('offsetSet');
    }

    /**
     * Unset the item at a given offset (throws).
     *
     * @throws ImmutableModelViolationException
     */
    public function offsetUnset(mixed $offset): never
    {
        throw ImmutableModelViolationException::collectionMutation('offsetUnset');
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
        return $this->items->map(fn($item) => $item->toArray())->all();
    }

    /**
     * Convert the collection to its JSON representation.
     *
     * @param int $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // =========================================================================
    // FORBIDDEN METHODS (Mutation Attempts)
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function push(mixed ...$values): never
    {
        throw ImmutableModelViolationException::collectionMutation('push');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function put(mixed $key, mixed $value): never
    {
        throw ImmutableModelViolationException::collectionMutation('put');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forget(mixed $keys): never
    {
        throw ImmutableModelViolationException::collectionMutation('forget');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function pop(int $count = 1): never
    {
        throw ImmutableModelViolationException::collectionMutation('pop');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function shift(int $count = 1): never
    {
        throw ImmutableModelViolationException::collectionMutation('shift');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function pull(mixed $key, mixed $default = null): never
    {
        throw ImmutableModelViolationException::collectionMutation('pull');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function prepend(mixed $value, mixed $key = null): never
    {
        throw ImmutableModelViolationException::collectionMutation('prepend');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function add(mixed $item): never
    {
        throw ImmutableModelViolationException::collectionMutation('add');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function splice(int $offset, ?int $length = null, mixed $replacement = []): never
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
}
