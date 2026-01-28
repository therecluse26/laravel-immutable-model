<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;

/**
 * Test model without a primary key.
 */
class NoPrimaryKeyModel extends ImmutableModel
{
    protected string $table = 'users';

    protected ?string $primaryKey = null;
}
