<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;

/**
 * Test model with configurable casts for casting tests.
 */
class TestCastableModel extends ImmutableModel
{
    protected $table = 'test';

    protected $primaryKey = 'id';

    /**
     * Static casts configuration for testing.
     *
     * @var array<string, string>
     */
    public static array $testCasts = [];

    /**
     * Get the casts array.
     *
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        return static::$testCasts;
    }
}
