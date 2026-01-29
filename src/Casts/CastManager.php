<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Casts;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;

/**
 * Manages attribute casting for immutable models.
 *
 * Supports Eloquent-compatible casting including:
 * - Scalar types (int, float, bool, string)
 * - Date/time types (datetime, date, timestamp, immutable_datetime)
 * - Complex types (array, json, collection)
 * - Custom cast classes implementing CastsAttributes
 */
class CastManager
{
    /**
     * Cache of instantiated custom casters.
     *
     * @var array<string, CastsAttributes>
     */
    private array $casterCache = [];

    /**
     * Cast a value according to the specified cast type.
     *
     * @param string $key The attribute key
     * @param mixed $value The raw value to cast
     * @param string $castType The cast type or class
     */
    public function cast(string $key, mixed $value, string $castType): mixed
    {
        if ($value === null) {
            return null;
        }

        // Check for cast type with parameters (e.g., "datetime:Y-m-d")
        $parameters = [];
        if (str_contains($castType, ':')) {
            [$castType, $paramString] = explode(':', $castType, 2);
            $parameters = explode(',', $paramString);
        }

        // Handle built-in cast types
        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => $this->castToBoolean($value),
            'object' => $this->castToObject($value),
            'array', 'json' => $this->castToArray($value),
            'collection' => $this->castToCollection($value),
            'date' => $this->castToDate($value),
            'datetime', 'custom_datetime' => $this->castToDatetime($value, $parameters[0] ?? null),
            'immutable_date' => $this->castToImmutableDate($value),
            'immutable_datetime' => $this->castToImmutableDatetime($value, $parameters[0] ?? null),
            'timestamp' => $this->castToTimestamp($value),
            'decimal' => $this->castToDecimal($value, $parameters),
            default => $this->castWithCustomCaster($key, $value, $castType),
        };
    }

    /**
     * Cast a value to boolean.
     */
    private function castToBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Cast a value to an object.
     *
     * @throws \JsonException If the string value is not valid JSON
     */
    private function castToObject(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
            return (object) $decoded;
        }

        return (object) $value;
    }

    /**
     * Cast a value to an array.
     *
     * @throws \JsonException If the string value is not valid JSON
     */
    private function castToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        return (array) $value;
    }

    /**
     * Cast a value to a Collection.
     */
    private function castToCollection(mixed $value): Collection
    {
        return new Collection($this->castToArray($value));
    }

    /**
     * Cast a value to a date (Carbon at midnight).
     */
    private function castToDate(mixed $value): Carbon
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Cast a value to a datetime (Carbon).
     */
    private function castToDatetime(mixed $value, ?string $format = null): Carbon
    {
        return $this->asDateTime($value);
    }

    /**
     * Cast a value to an immutable date (CarbonImmutable at midnight).
     */
    private function castToImmutableDate(mixed $value): CarbonImmutable
    {
        return $this->asImmutableDateTime($value)->startOfDay();
    }

    /**
     * Cast a value to an immutable datetime (CarbonImmutable).
     */
    private function castToImmutableDatetime(mixed $value, ?string $format = null): CarbonImmutable
    {
        return $this->asImmutableDateTime($value);
    }

    /**
     * Cast a value to a Unix timestamp.
     */
    private function castToTimestamp(mixed $value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * Cast a value to a decimal string with the specified precision.
     *
     * @param array<int, string> $parameters
     */
    private function castToDecimal(mixed $value, array $parameters): string
    {
        $decimals = isset($parameters[0]) ? (int) $parameters[0] : 2;

        return number_format((float) $value, $decimals, '.', '');
    }

    /**
     * Convert a value to a Carbon instance.
     */
    private function asDateTime(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse($value);
    }

    /**
     * Convert a value to a CarbonImmutable instance.
     */
    private function asImmutableDateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp($value);
        }

        return CarbonImmutable::parse($value);
    }

    /**
     * Cast a value using a custom caster class.
     *
     * @param class-string $casterClass
     */
    private function castWithCustomCaster(string $key, mixed $value, string $casterClass): mixed
    {
        // Check if the class exists
        if (! class_exists($casterClass)) {
            throw ImmutableModelConfigurationException::invalidCast(
                $key,
                "Cast class [{$casterClass}] does not exist."
            );
        }

        // Get or create the caster instance
        $caster = $this->getCaster($casterClass);

        // Verify it implements the correct interface
        if (! $caster instanceof CastsAttributes) {
            throw ImmutableModelConfigurationException::invalidCast(
                $key,
                "Cast class [{$casterClass}] must implement CastsAttributes."
            );
        }

        // Call only the get method (never set)
        return $caster->get(
            new class {}, // Dummy model - we don't pass the actual model to avoid mutation paths
            $key,
            $value,
            []
        );
    }

    /**
     * Get a caster instance, caching for reuse.
     *
     * @param class-string $casterClass
     */
    private function getCaster(string $casterClass): CastsAttributes
    {
        if (! isset($this->casterCache[$casterClass])) {
            $this->casterCache[$casterClass] = new $casterClass();
        }

        return $this->casterCache[$casterClass];
    }
}
