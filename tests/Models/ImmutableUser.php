<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Brighten\ImmutableModel\Tests\Models\Mutable\UserSettings;

/**
 * Immutable user model for testing.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property array|null $settings
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutableProfile|null $profile
 * @property-read ImmutableCollection<ImmutablePost> $posts
 * @property-read ImmutableCollection<ImmutableComment> $comments
 * @property-read UserSettings|null $mutableSettings
 */
class ImmutableUser extends ImmutableModel
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
        return $this->hasOne(ImmutableProfile::class, 'user_id', 'id');
    }

    /**
     * Get the user's posts.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ImmutablePost::class, 'user_id', 'id');
    }

    /**
     * Get the user's comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ImmutableComment::class, 'user_id', 'id');
    }

    /**
     * Get the user's settings (mutable model).
     *
     * Named "mutableSettings" to avoid conflict with the "settings" JSON attribute.
     */
    public function mutableSettings(): HasOne
    {
        return $this->hasOne(UserSettings::class, 'user_id', 'id');
    }

    /**
     * Get the user's supplier (for HasManyThrough testing).
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ImmutableSupplier::class, 'supplier_id', 'id');
    }

    /**
     * Get the user's orders (for deep nesting testing).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ImmutableOrder::class, 'user_id', 'id');
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
