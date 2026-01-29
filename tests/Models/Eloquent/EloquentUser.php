<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eloquent user model for parity testing.
 *
 * This model mirrors ImmutableUser exactly for comparison testing.
 */
class EloquentUser extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
        'supplier_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [];

    protected $appends = ['display_name'];

    /**
     * Get the full display name accessor.
     */
    public function getDisplayNameAttribute(): string
    {
        return strtoupper($this->name);
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(EloquentProfile::class, 'user_id', 'id');
    }

    /**
     * Get the user's posts.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(EloquentPost::class, 'user_id', 'id');
    }

    /**
     * Get the user's comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(EloquentComment::class, 'user_id', 'id');
    }

    /**
     * Get the user's supplier (for HasManyThrough testing).
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(EloquentSupplier::class, 'supplier_id', 'id');
    }

    /**
     * Get the user's orders (for deep nesting testing).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(EloquentOrder::class, 'user_id', 'id');
    }

    // =========================================================================
    // LOCAL SCOPES (for parity testing)
    // =========================================================================

    /**
     * Scope to filter verified users (email_verified_at is not null).
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
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
