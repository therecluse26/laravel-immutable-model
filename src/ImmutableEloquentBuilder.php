<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder for immutable models.
 *
 * Extends Laravel's Eloquent Builder for full compatibility while
 * blocking all mutation operations (insert, update, delete, etc.).
 */
class ImmutableEloquentBuilder extends Builder
{
    // =========================================================================
    // BLOCK BULK MUTATIONS
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function insert(array $values): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('insert');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function insertOrIgnore(array $values): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('insertOrIgnore');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function insertGetId(array $values, $sequence = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('insertGetId');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function insertUsing(array $columns, $query): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('insertUsing');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $values = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('update');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateOrInsert(array $attributes, array $values = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('updateOrInsert');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('upsert');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function delete(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('delete');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forceDelete(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceDelete');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function truncate(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('truncate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('increment');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('decrement');
    }

    // =========================================================================
    // BLOCK MODEL CREATION VIA BUILDER
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function create(array $attributes = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('create');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forceCreate(array $attributes): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceCreate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function firstOrCreate(array $attributes = [], array $values = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('firstOrCreate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateOrCreate(array $attributes, array $values = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('updateOrCreate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function firstOrNew(array $attributes = [], array $values = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('firstOrNew');
    }
}
