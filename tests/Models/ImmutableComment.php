<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;

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
    protected string $table = 'comments';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'post_id' => 'int',
        'user_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the comment's post.
     */
    public function post(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutablePost::class, 'post_id', 'id');
    }

    /**
     * Get the comment's author.
     */
    public function user(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }
}
