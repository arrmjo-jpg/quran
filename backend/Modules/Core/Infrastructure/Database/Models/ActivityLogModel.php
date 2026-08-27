<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the activity_logs table — ADR-017 D3.
 *
 * NO SoftDeletes AND NO updated_at. A log entry is a statement that something
 * happened; it is never edited and never partially withdrawn. `created_at` is
 * written once by the database default and nothing touches the row again.
 *
 * @property string $id
 * @property string $action
 * @property string $entity_type
 * @property string $entity_id
 * @property string|null $actor_id
 * @property string $actor_type
 * @property array<string, mixed> $payload
 * @property string|null $correlation_id
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 */
final class ActivityLogModel extends Model
{
    protected $table = 'activity_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The row is written once, by the database default, and `occurred_at`
     * comes from the event rather than from the clock.
     */
    public $timestamps = false;

    protected $fillable = [
        'id',
        'action',
        'entity_type',
        'entity_id',
        'actor_id',
        'actor_type',
        'payload',
        'correlation_id',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
