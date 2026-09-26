<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Illuminate\Database\Eloquent\Builder;
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
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope to filter recent users (created in the last N days).
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days)->toDateTimeString());
    }

    /**
     * Scope to filter by name pattern.
     */
    public function scopeNameLike(Builder $query, string $pattern): Builder
    {
        return $query->where('name', 'like', $pattern);
    }

    /**
     * Scope to order by name.
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('name', $direction);
    }
}
