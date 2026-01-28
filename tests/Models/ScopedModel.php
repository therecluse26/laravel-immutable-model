<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;

/**
 * Test model with configurable global scopes.
 */
class ScopedModel extends ImmutableModel
{
    protected string $table = 'users';

    protected ?string $primaryKey = 'id';

    /**
     * Static global scopes for testing.
     *
     * @var array<class-string>
     */
    public static array $globalScopes = [];

    /**
     * Get the global scopes for this model.
     *
     * @return array<class-string>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes;
    }
}
