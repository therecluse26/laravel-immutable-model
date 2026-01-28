<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent category model for parity testing self-referential relationships.
 */
class EloquentCategory extends Model
{
    protected $table = 'immutable_categories';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'parent_id' => 'int',
        'depth' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(EloquentCategory::class, 'parent_id', 'id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(EloquentCategory::class, 'parent_id', 'id');
    }
}
