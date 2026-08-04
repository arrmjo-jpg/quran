<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $channel
 * @property string $template_key
 * @property array<array-key, mixed> $payload
 * @property string $status
 * @property Carbon|null $sent_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereTemplateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLogModel whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class NotificationLogModel extends Model
{
    protected $table = 'notification_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'channel',
        'template_key',
        'payload',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];
}
