<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent profile model for parity testing.
 *
 * This model mirrors ImmutableProfile exactly for comparison testing.
 */
class EloquentProfile extends Model
{
    protected $table = 'profiles';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'int',
        'birthday' => 'date',
    ];

    /**
     * Get the profile's user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(EloquentUser::class, 'user_id', 'id');
    }
}
