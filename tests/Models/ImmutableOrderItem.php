<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;

/**
 * Immutable order item model for testing deep nesting.
 *
 * Part of chain: Country -> Supplier -> User -> Order -> OrderItem
 *
 * @property int $id
 * @property int $order_id
 * @property string|null $product_uuid
 * @property int $quantity
 * @property float $price
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutableOrder $order
 * @property-read ImmutableProduct|null $product
 */
class ImmutableOrderItem extends ImmutableModel
{
    protected $table = 'order_items';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'order_id' => 'int',
        'quantity' => 'int',
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order this item belongs to.
     */
    public function order(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableOrder::class, 'order_id', 'id');
    }

    /**
     * Get the product for this item.
     */
    public function product(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableProduct::class, 'product_uuid', 'uuid');
    }
}
