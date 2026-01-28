<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Eloquent post model for parity testing.
 *
 * This model mirrors ImmutablePost exactly for comparison testing.
 */
class EloquentPost extends Model
{
    protected $table = 'posts';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'user_id' => 'int',
        'category_id' => 'int',
        'published' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the post's author.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(EloquentUser::class, 'user_id', 'id');
    }

    /**
     * Get the post's comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(EloquentComment::class, 'post_id', 'id');
    }

    /**
     * Get the post's tags (BelongsToMany via post_tag pivot).
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(EloquentTag::class, 'post_tag', 'post_id', 'tag_id')
            ->withPivot('order')
            ->withTimestamps();
    }

    /**
     * Get the post's tags via polymorphic relation (MorphToMany via taggables).
     */
    public function morphTags(): MorphToMany
    {
        return $this->morphToMany(EloquentTag::class, 'taggable')
            ->withTimestamps();
    }

    /**
     * Get the post's featured image (MorphOne).
     */
    public function featuredImage(): MorphOne
    {
        return $this->morphOne(EloquentImage::class, 'imageable');
    }

    /**
     * Get all of the post's images (MorphMany).
     */
    public function images(): MorphMany
    {
        return $this->morphMany(EloquentImage::class, 'imageable');
    }
}
