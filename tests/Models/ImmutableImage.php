<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableMorphTo;

/**
 * Immutable image model for testing MorphTo.
 *
 * @property int $id
 * @property string $imageable_type
 * @property int $imageable_id
 * @property string $path
 * @property bool $is_featured
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ImmutableImage extends ImmutableModel
{
    protected string $table = 'images';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'is_featured' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent imageable model (Post, User, etc.).
     */
    public function imageable(): ImmutableMorphTo
    {
        return $this->morphTo();
    }
}
