<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }

    /**
     * Get the post's comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ImmutableComment::class, 'post_id', 'id');
    }

    /**
     * Get the post's category (mutable model).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * Get the post's meta entries (mutable models).
     */
    public function meta(): HasMany
    {
        return $this->hasMany(PostMeta::class, 'post_id', 'id');
    }

    /**
     * Get the post's tags (BelongsToMany via post_tag pivot).
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ImmutableTag::class, 'post_tag', 'post_id', 'tag_id')
            ->withPivot('order')
            ->withTimestamps();
    }

    /**
     * Get the post's tags via polymorphic relation (MorphToMany via taggables).
     */
    /**
     * Tags through a custom immutable pivot class.
     */
    public function tagsWithCustomPivot(): BelongsToMany
    {
        return $this->belongsToMany(ImmutableTag::class, 'post_tag', 'post_id', 'tag_id')
            ->using(Pivots\CustomImmutablePivot::class)
            ->withPivot('order');
    }

    /**
     * Tags through a mutable pivot class. Loading this relation must throw.
     */
    public function tagsWithMutablePivot(): BelongsToMany
    {
        return $this->belongsToMany(ImmutableTag::class, 'post_tag', 'post_id', 'tag_id')
            ->using(Pivots\CustomMutablePivot::class)
            ->withPivot('order');
    }

    /**
     * Morph tags through a custom immutable morph pivot class.
     */
    public function morphTagsWithCustomPivot(): ImmutableMorphToMany
    {
        return $this->morphToMany(ImmutableTag::class, 'taggable', 'taggables', null, 'tag_id')
            ->using(Pivots\CustomImmutableMorphPivot::class);
    }

    /**
     * Morph tags through a mutable morph pivot class. Loading this relation must throw.
     */
    public function morphTagsWithMutablePivot(): ImmutableMorphToMany
    {
        return $this->morphToMany(ImmutableTag::class, 'taggable', 'taggables', null, 'tag_id')
            ->using(Pivots\CustomMutableMorphPivot::class);
    }

    public function morphTags(): ImmutableMorphToMany
    {
        // Explicitly specify 'tag_id' since Eloquent would derive 'immutable_tag_id' from class name
        return $this->morphToMany(ImmutableTag::class, 'taggable', 'taggables', null, 'tag_id')
            ->withTimestamps();
    }

    /**
     * Get the post's featured image (MorphOne).
     */
    public function featuredImage(): MorphOne
    {
        return $this->morphOne(ImmutableImage::class, 'imageable');
    }

    /**
     * Get all of the post's images (MorphMany).
     */
    public function images(): MorphMany
    {
        return $this->morphMany(ImmutableImage::class, 'imageable');
    }
}
