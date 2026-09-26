<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Exceptions;

use RuntimeException;

/**
 * Thrown when an immutable model is misconfigured.
 *
 * This exception indicates a configuration error such as:
 * - A relation that uses a pivot class which is not immutable
 */
class ImmutableModelConfigurationException extends RuntimeException
{
    /**
     * Create exception for a mutable custom pivot class set with using().
     */
    public static function mutablePivot(string $pivotClass, string $requiredParent): self
    {
        return new self(
            "Pivot class [{$pivotClass}] must extend [{$requiredParent}] to be used by an immutable relation."
        );
    }
}
