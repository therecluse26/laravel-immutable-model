<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent image model for parity testing.
 *
 * This model mirrors ImmutableImage exactly for comparison testing.
 */
class EloquentImage extends Model
{
    protected $table = 'images';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'is_featured' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent imageable model (Post, User, etc.).
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
