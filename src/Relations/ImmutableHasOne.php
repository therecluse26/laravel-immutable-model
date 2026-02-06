<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Immutable HasOne relationship.
 *
 * Extends Eloquent's HasOne for full read compatibility while
 * blocking all mutation operations.
 */
class ImmutableHasOne extends HasOne
{
    /**
     * @throws ImmutableModelViolationException
     */
    public function save(Model $model): never
    {
        throw ImmutableModelViolationException::relationMutation('save');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function saveQuietly(Model $model): never
    {
        throw ImmutableModelViolationException::relationMutation('saveQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function create(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('create');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function createQuietly(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('createQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forceCreate(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('forceCreate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forceCreateQuietly(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('forceCreateQuietly');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $attributes = []): never
    {
        throw ImmutableModelViolationException::relationMutation('update');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function updateOrCreate(array $attributes, array $values = []): never
    {
        throw ImmutableModelViolationException::relationMutation('updateOrCreate');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function createOrFirst(array $attributes = [], array $values = []): never
    {
        throw ImmutableModelViolationException::relationMutation('createOrFirst');
    }
}
