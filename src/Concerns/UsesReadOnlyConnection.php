<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Concerns;

use Brighten\ImmutableModel\ReadOnlyConnection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Bind every query the model builds to a ReadOnlyConnection.
 *
 * Eloquent builders, relations, pivot statements, toBase() and getQuery()
 * all start from newBaseQueryBuilder(), so every SQL write from the model
 * reaches ReadOnlyConnection and throws.
 */
trait UsesReadOnlyConnection
{
    /**
     * Get a new query builder instance bound to a read-only connection.
     *
     * @return QueryBuilder
     */
    protected function newBaseQueryBuilder()
    {
        return (new ReadOnlyConnection($this->getConnection()))->query();
    }
}
