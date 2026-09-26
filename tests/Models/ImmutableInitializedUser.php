<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Tests\Models\Concerns\HasInitializedState;

/**
 * Immutable user model with a trait initializer, for parity testing.
 */
class ImmutableInitializedUser extends ImmutableModel
{
    use HasInitializedState;

    protected $table = 'users';
}
