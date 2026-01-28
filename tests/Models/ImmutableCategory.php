<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;

/**
 * Immutable category model for testing self-referential relationships.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property int $depth
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutableCategory|null $parent
 * @property-read ImmutableCollection<ImmutableCategory> $children
 */
class ImmutableCategory extends ImmutableModel
{
    protected string $table = 'immutable_categories';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'parent_id' => 'int',
        'depth' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableCategory::class, 'parent_id', 'id');
    }

    /**
     * Get the child categories.
     */
    public function children(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableCategory::class, 'parent_id', 'id');
    }
}
