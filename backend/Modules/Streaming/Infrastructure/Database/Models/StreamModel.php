<?php

declare(strict_types=1);

namespace Modules\Streaming\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $season_id
 * @property string|null $stage_id
 * @property string $title
 * @property string $stream_key
 * @property string $rtmp_url
 * @property string|null $hls_url
 * @property string $status
 * @property Carbon|null $scheduled_start
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereActualEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereActualStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereHlsUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereRtmpUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereScheduledStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereStreamKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class StreamModel extends Model
{
    protected $table = 'streams';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'stage_id',
        'title',
        'stream_key',
        'rtmp_url',
        'hls_url',
        'status',
        'scheduled_start',
        'actual_start',
        'actual_end',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];
}
