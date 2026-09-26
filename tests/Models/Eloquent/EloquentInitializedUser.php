<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Brighten\ImmutableModel\Tests\Models\Concerns\HasInitializedState;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent user model with a trait initializer, for parity testing.
 */
class EloquentInitializedUser extends Model
{
    use HasInitializedState;

    protected $table = 'users';
}
