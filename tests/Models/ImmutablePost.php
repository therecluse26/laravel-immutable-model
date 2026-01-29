<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableBelongsToMany;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableMorphMany;
use Brighten\ImmutableModel\Relations\ImmutableMorphOne;
use Brighten\ImmutableModel\Relations\ImmutableMorphToMany;
use Brighten\ImmutableModel\Tests\Models\Mutable\Category;
use Brighten\ImmutableModel\Tests\Models\Mutable\PostMeta;
use Illuminate\Support\Collection;

/**
 * Immutable post model for testing.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property string $title
 * @property string $body
 * @property bool $published
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutableUser $user
 * @property-read ImmutableCollection<ImmutableComment> $comments
 * @property-read Category|null $category
 * @property-read Collection<PostMeta> $meta
 */
class ImmutablePost extends ImmutableModel
{
    protected string $table = 'posts';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'user_id' => 'int',
        'category_id' => 'int',
        'published' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the post's author.
     */
    public function user(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }

    /**
     * Get the post's comments.
     */
    public function comments(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableComment::class, 'post_id', 'id');
    }

    /**
     * Get the post's category (mutable model).
     */
    public function category(): ImmutableBelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * Get the post's meta entries (mutable models).
     */
    public function meta(): ImmutableHasMany
    {
        return $this->hasMany(PostMeta::class, 'post_id', 'id');
    }

    /**
     * Get the post's tags (BelongsToMany via post_tag pivot).
     */
    public function tags(): ImmutableBelongsToMany
    {
        return $this->belongsToMany(ImmutableTag::class, 'post_tag', 'post_id', 'tag_id')
            ->withPivot('order')
            ->withTimestamps();
    }

    /**
     * Get the post's tags via polymorphic relation (MorphToMany via taggables).
     */
    public function morphTags(): ImmutableMorphToMany
    {
        return $this->morphToMany(ImmutableTag::class, 'taggable')
            ->withTimestamps();
    }

    /**
     * Get the post's featured image (MorphOne).
     */
    public function featuredImage(): ImmutableMorphOne
    {
        return $this->morphOne(ImmutableImage::class, 'imageable');
    }

    /**
     * Get all of the post's images (MorphMany).
     */
    public function images(): ImmutableMorphMany
    {
        return $this->morphMany(ImmutableImage::class, 'imageable');
    }
}
