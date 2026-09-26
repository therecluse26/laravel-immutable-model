<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Concerns\SerializesDatesNatively;
use Brighten\ImmutableModel\Concerns\UsesReadOnlyConnection;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Immutable Pivot model.
 *
 * Extends Eloquent's Pivot for full read compatibility while
 * blocking all mutation operations.
 */
class ImmutablePivot extends Pivot
{
    use SerializesDatesNatively;
    use UsesReadOnlyConnection;

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
