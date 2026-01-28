<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent article model for parity testing soft deletes.
 */
class EloquentArticle extends Model
{
    use SoftDeletes;

    protected $table = 'articles';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
