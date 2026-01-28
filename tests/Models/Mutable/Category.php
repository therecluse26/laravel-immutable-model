<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Mutable;

use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mutable category model for testing Eloquent-to-ImmutableModel relations.
 *
 * This demonstrates that standard Laravel relationships work seamlessly
 * with ImmutableModels as the related model type.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = ['name', 'slug'];

    /**
     * Get the posts in this category.
     *
     * This uses standard Laravel hasMany() to relate to ImmutablePost.
     * The relationship works because ImmutableModel implements the
     * necessary Laravel interop methods (newInstance, newFromBuilder, etc.).
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ImmutablePost::class, 'category_id', 'id');
    }
}
