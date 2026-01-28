<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableHasManyThrough;
use Brighten\ImmutableModel\Relations\ImmutableHasOneThrough;

/**
 * Immutable country model for testing HasManyThrough.
 *
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ImmutableCountry extends ImmutableModel
{
    protected string $table = 'countries';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the country's suppliers.
     */
    public function suppliers(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableSupplier::class, 'country_id', 'id');
    }

    /**
     * Get all users through suppliers (HasManyThrough).
     */
    public function users(): ImmutableHasManyThrough
    {
        return $this->hasManyThrough(
            ImmutableUser::class,
            ImmutableSupplier::class,
            'country_id',  // FK on suppliers pointing to countries
            'supplier_id', // FK on users pointing to suppliers
            'id',          // Local key on countries
            'id'           // Local key on suppliers
        );
    }

    /**
     * Get the first user through suppliers (HasOneThrough).
     */
    public function firstUser(): ImmutableHasOneThrough
    {
        return $this->hasOneThrough(
            ImmutableUser::class,
            ImmutableSupplier::class,
            'country_id',
            'supplier_id',
            'id',
            'id'
        );
    }
}
