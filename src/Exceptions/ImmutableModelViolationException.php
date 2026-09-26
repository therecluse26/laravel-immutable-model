<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Exceptions;

use LogicException;

/**
 * Thrown when an attempt is made to mutate an immutable model.
 *
 * This exception indicates a programming error where code attempted to:
 * - Set an attribute on an immutable pivot model
 * - Call a persistence method (save, update, delete, etc.)
 * - Run a SQL write through ReadOnlyConnection
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
     * Create exception for persistence method call.
     */
    public static function persistenceAttempt(string $method): self
    {
        return new self("Cannot call [{$method}] on an immutable model. Immutable models are read-only.");
    }

    /**
     * Create exception for a SQL write blocked at the read-only connection.
     *
     * The SQL keeps its "?" placeholders. Binding values are never included,
     * because they can hold user data and exception messages reach logs.
     */
    public static function writeAttempt(string $operation, string $sql): self
    {
        return new self("Cannot run [{$operation}] on an immutable model. Immutable models are read-only. SQL: {$sql}");
    }
}
