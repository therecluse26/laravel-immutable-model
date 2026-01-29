<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;

/**
 * Test model that uses Laravel 11's method-based casts.
 */
class MethodCastModel extends ImmutableModel
{
    protected string $table = 'users';

    protected ?string $primaryKey = 'id';

    /**
     * Laravel 11 style: define casts via method instead of property.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'email_verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
