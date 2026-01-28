<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Test model with configurable casts for casting parity tests.
 */
class EloquentTestCastableModel extends Model
{
    protected $table = 'test';

    protected $primaryKey = 'id';

    public $timestamps = false;

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
