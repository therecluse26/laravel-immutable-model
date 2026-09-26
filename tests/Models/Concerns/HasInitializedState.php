<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Concerns;

/**
 * Test trait whose initializer changes per-instance model state.
 *
 * Laravel runs initializeXxx() methods in the model constructor. Query
 * results must get the same state as models built with `new`.
 */
trait HasInitializedState
{
    public int $initializerRuns = 0;

    public function initializeHasInitializedState(): void
    {
        $this->initializerRuns++;
        $this->hidden[] = 'email';
        $this->appends[] = 'initialized_marker';
        $this->mergeCasts(['supplier_id' => 'string']);
    }

    public function getInitializedMarkerAttribute(): string
    {
        return 'initialized';
    }
}
