<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Eloquent tag model for parity testing.
 *
 * This model mirrors ImmutableTag exactly for comparison testing.
 */
class EloquentTag extends Model
{
    protected $table = 'tags';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the posts that have this tag (BelongsToMany).
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(EloquentPost::class, 'post_tag', 'tag_id', 'post_id');
    }

    /**
     * Get all taggable models (morphedByMany - posts via taggables table).
     */
    public function taggablePosts(): MorphToMany
    {
        return $this->morphedByMany(EloquentPost::class, 'taggable')
            ->withTimestamps();
    }

    /**
     * Get all taggable users (morphedByMany - users via taggables table).
     */
    public function taggableUsers(): MorphToMany
    {
        return $this->morphedByMany(EloquentUser::class, 'taggable');
    }
}
