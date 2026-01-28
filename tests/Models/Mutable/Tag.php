<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Mutable;

use Illuminate\Database\Eloquent\Model;

/**
 * Mutable tag model for testing immutable-to-mutable relations.
 *
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = ['name'];
}
