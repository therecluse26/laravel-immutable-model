<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Immutable MorphPivot for polymorphic many-to-many relationships.
 *
 * Extends Eloquent's MorphPivot to block all persistence operations
 * while maintaining full read compatibility.
 */
class ImmutableMorphPivot extends MorphPivot
{
    /**
     * Disable timestamps auto-updating.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @throws ImmutableModelViolationException
     */
    public function save(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('save');
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
    public function update(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('update');
    }

    /**
     * Block attribute mutation via property assignment.
     *
     * @throws ImmutableModelViolationException
     */
    public function __set($key, $value): void
    {
        throw ImmutableModelViolationException::attributeMutation($key);
    }

    /**
     * Block attribute mutation via array access.
     *
     * @throws ImmutableModelViolationException
     */
    public function offsetSet($offset, $value): void
    {
        throw ImmutableModelViolationException::attributeMutation($offset ?? 'unknown');
    }

    /**
     * Block attribute removal via unset.
     *
     * @throws ImmutableModelViolationException
     */
    public function offsetUnset($offset): void
    {
        throw ImmutableModelViolationException::attributeMutation($offset);
    }
}
