<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use ArrayAccess;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A read-only pivot value object for BelongsToMany and MorphToMany relationships.
 *
 * Unlike Laravel's Pivot class (which extends Model), this is a lightweight
 * container that holds pivot table attributes without database connectivity
 * or mutation capabilities.
 *
 * @template-implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class ImmutablePivot implements ArrayAccess, JsonSerializable, Arrayable
{
    /**
     * The pivot table attributes.
     *
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * The pivot table name.
     */
    private string $table;

    /**
     * The name of the foreign key column for the parent model.
     */
    private ?string $foreignKey;

    /**
     * The name of the foreign key column for the related model.
     */
    private ?string $relatedKey;

    /**
     * Create a new immutable pivot instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        string $table,
        ?string $foreignKey = null,
        ?string $relatedKey = null
    ) {
        $this->attributes = $attributes;
        $this->table = $table;
        $this->foreignKey = $foreignKey;
        $this->relatedKey = $relatedKey;
    }

    /**
     * Get an attribute from the pivot.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Block setting attributes.
     *
     * @throws ImmutableModelViolationException
     */
    public function __set(string $key, mixed $value): never
    {
        throw ImmutableModelViolationException::attributeMutation($key);
    }

    /**
     * Determine if an attribute exists.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Get an attribute from the pivot.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get all pivot attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the pivot table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the foreign key column name.
     */
    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    /**
     * Get the related key column name.
     */
    public function getRelatedKey(): ?string
    {
        return $this->relatedKey;
    }

    /**
     * Determine if the pivot has a given attribute.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get the pivot attributes as an array.
     *
     * Timestamps are serialized to ISO 8601 format to match Eloquent's behavior.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->attributes as $key => $value) {
            // Serialize timestamp columns to ISO 8601 format
            if (in_array($key, ['created_at', 'updated_at', 'deleted_at'], true) && $value !== null) {
                if ($value instanceof \DateTimeInterface) {
                    $result[$key] = $value->format('Y-m-d\TH:i:s.u\Z');
                } elseif (is_string($value)) {
                    // Parse string timestamps and reformat
                    try {
                        $date = new \DateTimeImmutable($value);
                        $result[$key] = $date->format('Y-m-d\TH:i:s.u\Z');
                    } catch (\Exception) {
                        $result[$key] = $value;
                    }
                } else {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ArrayAccess implementation (read-only)

    /**
     * Determine if the given offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get the value at the given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Block setting values at offsets.
     *
     * @throws ImmutableModelViolationException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw ImmutableModelViolationException::attributeMutation($offset ?? 'unknown');
    }

    /**
     * Block unsetting values at offsets.
     *
     * @throws ImmutableModelViolationException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw ImmutableModelViolationException::attributeMutation($offset);
    }
}
