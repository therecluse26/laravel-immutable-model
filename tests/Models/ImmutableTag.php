<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsToMany;
use Brighten\ImmutableModel\Relations\ImmutableMorphToMany;

/**
 * Immutable tag model for testing BelongsToMany and MorphToMany.
 *
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ImmutableTag extends ImmutableModel
{
    protected string $table = 'tags';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the posts that have this tag (BelongsToMany).
     */
    public function posts(): ImmutableBelongsToMany
    {
        return $this->belongsToMany(ImmutablePost::class, 'post_tag', 'tag_id', 'post_id');
    }

    /**
     * Get all taggable models (morphedByMany - posts via taggables table).
     */
    public function taggablePosts(): ImmutableMorphToMany
    {
        return $this->morphedByMany(ImmutablePost::class, 'taggable')
            ->withTimestamps();
    }

    /**
     * Get all taggable users (morphedByMany - users via taggables table).
     */
    public function taggableUsers(): ImmutableMorphToMany
    {
        return $this->morphedByMany(ImmutableUser::class, 'taggable');
    }
}
