<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;

/**
 * Immutable product model for testing UUID primary keys.
 *
 * @property string $uuid
 * @property string $name
 * @property float $price
 * @property string $sku
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ImmutableProduct extends ImmutableModel
{
    protected string $table = 'products';

    protected ?string $primaryKey = 'uuid';

    protected string $keyType = 'string';

    protected bool $incrementing = false;

    protected array $casts = [
        'price' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
