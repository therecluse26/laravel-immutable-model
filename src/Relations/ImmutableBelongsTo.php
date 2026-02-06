<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable BelongsTo relationship.
 *
 * Extends Eloquent's BelongsTo for full read compatibility while
 * blocking all mutation operations.
 */
class ImmutableBelongsTo extends BelongsTo
{
    /**
     * @throws ImmutableModelViolationException
     */
    public function associate($model): never
    {
        throw ImmutableModelViolationException::relationMutation('associate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function dissociate(): never
    {
        throw ImmutableModelViolationException::relationMutation('dissociate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('update');
    }
}
