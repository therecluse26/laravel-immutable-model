<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent order item model for parity testing deep nesting.
 */
class EloquentOrderItem extends Model
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
    public function order(): BelongsTo
    {
        return $this->belongsTo(EloquentOrder::class, 'order_id', 'id');
    }

    /**
     * Get the product for this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(EloquentProduct::class, 'product_uuid', 'uuid');
    }
}
