<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable comment model for testing.
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property string $body
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutablePost $post
 * @property-read ImmutableUser $user
 */
class ImmutableComment extends ImmutableModel
{
    protected $table = 'comments';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'post_id' => 'int',
        'user_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the comment's post.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ImmutablePost::class, 'post_id', 'id');
    }

    /**
     * Get the comment's author.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }
}
