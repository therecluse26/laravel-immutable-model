<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Eloquent country model for parity testing.
 *
 * This model mirrors ImmutableCountry exactly for comparison testing.
 */
class EloquentCountry extends Model
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
        return $this->hasMany(EloquentSupplier::class, 'country_id', 'id');
    }

    /**
     * Get all users through suppliers (HasManyThrough).
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            EloquentUser::class,
            EloquentSupplier::class,
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
            EloquentUser::class,
            EloquentSupplier::class,
            'country_id',
            'supplier_id',
            'id',
            'id'
        );
    }
}
