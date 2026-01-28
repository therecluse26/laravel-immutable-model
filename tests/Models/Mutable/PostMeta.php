<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Mutable;

use Illuminate\Database\Eloquent\Model;

/**
 * Mutable post meta model for testing immutable-to-mutable hasMany relations.
 *
 * @property int $id
 * @property int $post_id
 * @property string $key
 * @property string|null $value
 */
class PostMeta extends Model
{
    protected $table = 'post_meta';

    public $timestamps = false;

    protected $fillable = ['post_id', 'key', 'value'];

    protected $casts = [
        'post_id' => 'int',
    ];
}
