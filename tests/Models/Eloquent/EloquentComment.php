<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent comment model for parity testing.
 *
 * This model mirrors ImmutableComment exactly for comparison testing.
 */
class EloquentComment extends Model
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
        return $this->belongsTo(EloquentPost::class, 'post_id', 'id');
    }

    /**
     * Get the comment's author.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(EloquentUser::class, 'user_id', 'id');
    }
}
