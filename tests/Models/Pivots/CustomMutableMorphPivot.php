<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Mutable custom morph pivot; using() it on an immutable relation must throw.
 */
class CustomMutableMorphPivot extends MorphPivot
{
    public function getOrderLabelAttribute(): string
    {
        return 'order-' . $this->getAttribute('order');
    }
}
