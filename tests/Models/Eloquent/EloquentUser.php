<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

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
}
