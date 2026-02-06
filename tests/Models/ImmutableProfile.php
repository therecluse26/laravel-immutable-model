<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;

/**
 * Immutable profile model for testing.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $bio
 * @property \Carbon\Carbon|null $birthday
 * @property-read ImmutableUser $user
 */
class ImmutableProfile extends ImmutableModel
{
    protected $table = 'profiles';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'user_id' => 'int',
        'birthday' => 'date',
    ];

    /**
     * Get the profile's user.
     */
    public function user(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }
}
