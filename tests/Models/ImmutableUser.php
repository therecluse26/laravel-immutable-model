<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableHasOne;
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
    protected string $table = 'users';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
        'supplier_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected array $hidden = [];

    protected array $appends = ['display_name'];

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
    public function profile(): ImmutableHasOne
    {
        return $this->hasOne(ImmutableProfile::class, 'user_id', 'id');
    }

    /**
     * Get the user's posts.
     */
    public function posts(): ImmutableHasMany
    {
        return $this->hasMany(ImmutablePost::class, 'user_id', 'id');
    }

    /**
     * Get the user's comments.
     */
    public function comments(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableComment::class, 'user_id', 'id');
    }

    /**
     * Get the user's settings (mutable model).
     *
     * Named "mutableSettings" to avoid conflict with the "settings" JSON attribute.
     */
    public function mutableSettings(): ImmutableHasOne
    {
        return $this->hasOne(UserSettings::class, 'user_id', 'id');
    }

    /**
     * Get the user's supplier (for HasManyThrough testing).
     */
    public function supplier(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableSupplier::class, 'supplier_id', 'id');
    }

    /**
     * Get the user's orders (for deep nesting testing).
     */
    public function orders(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableOrder::class, 'user_id', 'id');
    }

    // =========================================================================
    // LOCAL SCOPES (for parity testing)
    // =========================================================================

    /**
     * Scope to filter verified users (email_verified_at is not null).
     */
    public function scopeVerified(ImmutableQueryBuilder $query): ImmutableQueryBuilder
    {
        return $query->whereNotNull('email_verified_at');
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
