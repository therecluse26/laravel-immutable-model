<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Immutable HasManyThrough relationship.
 *
 * Extends Eloquent's HasManyThrough for full read compatibility while
 * blocking all mutation operations.
 */
class ImmutableHasManyThrough extends HasManyThrough
{
    /**
     * @throws ImmutableModelViolationException
     */
    public function createOrFirst(array $attributes = [], array $values = []): never
    {
        throw ImmutableModelViolationException::relationMutation('createOrFirst');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateOrCreate(array $attributes, array $values = []): never
    {
        throw ImmutableModelViolationException::relationMutation('updateOrCreate');
    }
}
