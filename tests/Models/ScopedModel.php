<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;

/**
 * Test model with configurable global scopes and local scopes.
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

    // =========================================================================
    // LOCAL SCOPES
    // =========================================================================

    /**
     * Scope to filter verified users (email_verified_at is not null).
     */
    public function scopeVerified(ImmutableQueryBuilder $query): ImmutableQueryBuilder
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope to filter recent users (created in the last N days).
     */
    public function scopeRecent(ImmutableQueryBuilder $query, int $days = 7): ImmutableQueryBuilder
    {
        return $query->where('created_at', '>=', now()->subDays($days)->toDateTimeString());
    }

    /**
     * Scope to filter by name pattern.
     */
    public function scopeNameLike(ImmutableQueryBuilder $query, string $pattern): ImmutableQueryBuilder
    {
        return $query->where('name', 'like', $pattern);
    }

    /**
     * Scope to order by name.
     */
    public function scopeOrderByName(ImmutableQueryBuilder $query, string $direction = 'asc'): ImmutableQueryBuilder
    {
        return $query->orderBy('name', $direction);
    }
}
