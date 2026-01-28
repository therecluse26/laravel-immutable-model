<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent supplier model for parity testing.
 *
 * This model mirrors ImmutableSupplier exactly for comparison testing.
 */
class EloquentSupplier extends Model
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
        return $this->belongsTo(EloquentCountry::class, 'country_id', 'id');
    }

    /**
     * Get the supplier's users.
     */
    public function users(): HasMany
    {
        return $this->hasMany(EloquentUser::class, 'supplier_id', 'id');
    }
}
