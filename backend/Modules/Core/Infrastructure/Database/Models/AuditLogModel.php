<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

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
