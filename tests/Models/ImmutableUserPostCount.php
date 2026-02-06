<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models;

use Brighten\ImmutableModel\ImmutableModel;

/**
 * Immutable view-backed model for testing database views.
 *
 * This model reads from the user_post_counts view which aggregates
 * user data with their post counts.
 *
 * @property int $user_id
 * @property string $name
 * @property int $post_count
 */
class ImmutableUserPostCount extends ImmutableModel
{
    protected $table = 'user_post_counts';

    /**
     * View-backed models typically don't have a primary key.
     */
    protected $primaryKey = null;

    protected $casts = [
        'user_id' => 'int',
        'post_count' => 'int',
    ];
}
