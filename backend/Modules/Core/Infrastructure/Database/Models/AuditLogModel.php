<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the audit_logs table.
 *
 * Written by AuditLoggingMiddleware as a side effect of every api/* request,
 * and read by LoginHistoryRepository -- ADR-018 D2 makes this table the login
 * history rather than adding a second store beside it.
 *
 * The annotations below are not decoration. Without them static analysis
 * reports every read in that repository as access to an undefined property,
 * which is the same reason ActivityLogModel carries them.
 *
 * @property string $id
 * @property string|null $correlation_id
 * @property int $execution_duration_ms
 * @property string|null $actor_id
 * @property string|null $actor_type
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $method
 * @property string $path
 * @property string|null $route_name
 * @property int $response_status
 * @property string|null $device_id
 * @property int|null $request_size
 * @property int|null $response_size
 * @property Carbon|null $created_at
 */
final class AuditLogModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'correlation_id',
        'execution_duration_ms',
        'actor_id',
        'actor_type',
        'ip',
        'user_agent',
        'method',
        'path',
        'route_name',
        'response_status',
        'device_id',
        'request_size',
        'response_size',
    ];

    protected $casts = [
        'execution_duration_ms' => 'integer',
        'response_status' => 'integer',
        'request_size' => 'integer',
        'response_size' => 'integer',
    ];
}
