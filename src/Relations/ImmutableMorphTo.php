<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable MorphTo relationship.
 *
 * Extends Eloquent's MorphTo for full read compatibility while
 * blocking all mutation operations.
 */
class ImmutableMorphTo extends MorphTo
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
