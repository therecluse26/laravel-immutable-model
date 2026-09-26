<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Pivots;

use Brighten\ImmutableModel\Relations\ImmutablePivot;

/**
 * Immutable custom pivot; allowed with using().
 */
class CustomImmutablePivot extends ImmutablePivot
{
    public function getOrderLabelAttribute(): string
    {
        return 'order-' . $this->getAttribute('order');
    }
}
