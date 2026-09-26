<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
    protected $table = 'countries';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the country's suppliers.
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(ImmutableSupplier::class, 'country_id', 'id');
    }

    /**
     * Get all users through suppliers (HasManyThrough).
     */
    public function users(): HasManyThrough
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
    public function firstUser(): HasOneThrough
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
