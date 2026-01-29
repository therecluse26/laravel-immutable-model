<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Exceptions;

use RuntimeException;

/**
 * Thrown when an immutable model is misconfigured.
 *
 * This exception indicates a configuration error such as:
 * - Using identity operations on a model without a primary key
 * - Using forbidden model properties
 * - Invalid cast configuration
 */
class ImmutableModelConfigurationException extends RuntimeException
{
    /**
     * Create exception for missing primary key.
     */
    public static function missingPrimaryKey(string $operation): self
    {
        return new self("Cannot perform [{$operation}] on a model without a primary key.");
    }

    /**
     * Create exception for forbidden property usage.
     */
    public static function forbiddenProperty(string $property): self
    {
        return new self("Property [{$property}] is forbidden on immutable models.");
    }

    /**
     * Create exception for invalid cast configuration.
     */
    public static function invalidCast(string $key, string $reason): self
    {
        return new self("Invalid cast configuration for [{$key}]: {$reason}");
    }

    /**
     * Create exception for missing table configuration.
     */
    public static function missingTable(string $class): self
    {
        return new self("Immutable model [{$class}] must define a \$table property.");
    }

    /**
     * Create exception for missing connection resolver.
     */
    public static function missingConnectionResolver(): self
    {
        return new self(
            'No database connection resolver has been configured. '
            . 'Ensure Laravel has booted or call ImmutableModel::setConnectionResolver().'
        );
    }
}
