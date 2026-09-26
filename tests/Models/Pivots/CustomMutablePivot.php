<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Mutable custom pivot; using() it on an immutable relation must throw.
 */
class CustomMutablePivot extends Pivot
{
    public function getOrderLabelAttribute(): string
    {
        return 'order-' . $this->getAttribute('order');
    }
}
