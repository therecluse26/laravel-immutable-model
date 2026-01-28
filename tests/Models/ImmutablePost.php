<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;

/**
 * Immutable post model for testing.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property bool $published
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ImmutableUser $user
 * @property-read ImmutableCollection<ImmutableComment> $comments
 */
class ImmutablePost extends ImmutableModel
{
    protected string $table = 'posts';

    protected ?string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected array $casts = [
        'user_id' => 'int',
        'published' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the post's author.
     */
    public function user(): ImmutableBelongsTo
    {
        return $this->belongsTo(ImmutableUser::class, 'user_id', 'id');
    }

    /**
     * Get the post's comments.
     */
    public function comments(): ImmutableHasMany
    {
        return $this->hasMany(ImmutableComment::class, 'post_id', 'id');
    }
}
