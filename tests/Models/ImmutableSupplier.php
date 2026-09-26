<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Immutable supplier model for testing HasManyThrough.
 *
 * Uses SoftDeletes trait so HasManyThrough automatically filters
 * out soft-deleted intermediate models.
 *
 * @property int $id
 * @property int $country_id
 * @property string $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ImmutableSupplier extends ImmutableModel
{
    use SoftDeletes;
    protected $table = 'suppliers';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'country_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the supplier's country.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(ImmutableCountry::class, 'country_id', 'id');
    }

    /**
     * Get the supplier's users.
     */
    public function users(): HasMany
    {
        return $this->hasMany(ImmutableUser::class, 'supplier_id', 'id');
    }
}
