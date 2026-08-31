<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $notification_type
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel whereNotificationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationPreferenceModel whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class NotificationPreferenceModel extends Model
{
    protected $table = 'notification_preferences';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'notification_type',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
