<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Mutable;

use Brighten\ImmutableModel\Relations\ImmutableMorphPivot;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Mutable post model for testing pivots between a mutable parent and an
 * immutable related model.
 *
 * @property int $id
 * @property string $title
 */
class Post extends Model
{
    protected $table = 'posts';

    /**
     * BelongsToMany asks the related model for the pivot, so this pivot is
     * an ImmutablePivot without any extra setup.
     */
    public function immutableTags(): BelongsToMany
    {
        return $this->belongsToMany(ImmutableTag::class, 'post_tag', 'post_id', 'tag_id');
    }

    /**
     * MorphToMany builds the pivot from the parent, so a mutable parent gets
     * a plain MorphPivot. using() makes the pivot immutable.
     */
    public function immutableMorphTags(): MorphToMany
    {
        return $this->morphToMany(ImmutableTag::class, 'taggable', 'taggables', null, 'tag_id')
            ->using(ImmutableMorphPivot::class);
    }
}
