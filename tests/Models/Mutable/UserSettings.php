<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Models\Mutable;

use Illuminate\Database\Eloquent\Model;

/**
 * Mutable user settings model for testing immutable-to-mutable hasOne relations.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $theme
 * @property bool $notifications_enabled
 */
class UserSettings extends Model
{
    protected $table = 'user_settings';

    public $timestamps = false;

    protected $fillable = ['user_id', 'theme', 'notifications_enabled'];

    protected $casts = [
        'user_id' => 'int',
        'notifications_enabled' => 'boolean',
    ];
}
