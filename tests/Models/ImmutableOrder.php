<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;

/**
 * Immutable order model for testing deep nesting.
 *
 * Part of chain: Country -> Supplier -> User -> Order -> OrderItem
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property float $total
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutableUser $user
 * @property-read ImmutableCollection<ImmutableOrderItem> $items
 */
class ImmutableOrder extends ImmutableModel
{
    protected $table = 'orders';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'user_id' => 'int',
        'total' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who placed the order.
     */
    public function user(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }

    /**
     * Get the order items.
     */
    public function items(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableOrderItem::class, 'order_id', 'id');
    }
}
