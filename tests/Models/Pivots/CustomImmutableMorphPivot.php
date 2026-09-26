<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Pivots;

use Brighten\ImmutableModel\Relations\ImmutableMorphPivot;

/**
 * Immutable custom morph pivot; allowed with using().
 */
class CustomImmutableMorphPivot extends ImmutableMorphPivot
{
    public function getOrderLabelAttribute(): string
    {
        return 'order-' . $this->getAttribute('order');
    }
}
