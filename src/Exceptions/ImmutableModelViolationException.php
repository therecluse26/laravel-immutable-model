<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Exceptions;

use LogicException;

/**
 * Thrown when an attempt is made to mutate an immutable model.
 *
 * This exception indicates a programming error where code attempted to:
 * - Set an attribute on an immutable model
 * - Modify a relation on an immutable model
 * - Call a persistence method (save, update, delete, etc.)
 * - Mutate an immutable collection
 */
class ImmutableModelViolationException extends LogicException
{
    /**
     * Create exception for attribute mutation attempt.
     */
    public static function attributeMutation(string $key): self
    {
        return new self("Cannot set attribute [{$key}] on an immutable model.");
    }

    /**
     * Create exception for relation mutation attempt.
     */
    public static function relationMutation(string $relation): self
    {
        return new self("Cannot set relation [{$relation}] on an immutable model.");
    }

    /**
     * Create exception for persistence method call.
     */
    public static function persistenceAttempt(string $method): self
    {
        return new self("Cannot call [{$method}] on an immutable model. Immutable models are read-only.");
    }

    /**
     * Create exception for collection mutation attempt.
     */
    public static function collectionMutation(string $method): self
    {
        return new self("Cannot call [{$method}] on an immutable collection.");
    }

    /**
     * Create exception for direct instantiation attempt.
     */
    public static function directInstantiation(): self
    {
        return new self("Immutable models cannot be instantiated directly. Use query methods or fromRow().");
    }
}
