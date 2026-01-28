<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableHasOne;

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
 */
class ImmutableUser extends ImmutableModel
{
    protected string $table = 'users';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
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
}
