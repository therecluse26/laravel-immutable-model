<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent view-backed model for parity testing database views.
 */
class EloquentUserPostCount extends Model
{
    protected $table = 'user_post_counts';

    /**
     * View-backed models typically don't have a primary key.
     */
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'int',
        'post_count' => 'int',
    ];
}
