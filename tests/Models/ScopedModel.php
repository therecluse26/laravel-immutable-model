<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableEloquentBuilder;
use Brighten\ImmutableModel\ImmutableModel;

/**
 * Test model with configurable global scopes and local scopes.
 */
class ScopedModel extends ImmutableModel
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    // =========================================================================
    // LOCAL SCOPES
    // =========================================================================

    /**
     * Scope to filter verified users (email_verified_at is not null).
     */
    public function scopeVerified(ImmutableEloquentBuilder $query): ImmutableEloquentBuilder
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope to filter recent users (created in the last N days).
     */
    public function scopeRecent(ImmutableEloquentBuilder $query, int $days = 7): ImmutableEloquentBuilder
    {
        return $query->where('created_at', '>=', now()->subDays($days)->toDateTimeString());
    }

    /**
     * Scope to filter by name pattern.
     */
    public function scopeNameLike(ImmutableEloquentBuilder $query, string $pattern): ImmutableEloquentBuilder
    {
        return $query->where('name', 'like', $pattern);
    }

    /**
     * Scope to order by name.
     */
    public function scopeOrderByName(ImmutableEloquentBuilder $query, string $direction = 'asc'): ImmutableEloquentBuilder
    {
        return $query->orderBy('name', $direction);
    }
}
