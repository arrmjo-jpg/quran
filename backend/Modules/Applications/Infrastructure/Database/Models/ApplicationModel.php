<?php

declare(strict_types=1);

namespace Modules\Applications\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $contestant_id
 * @property string $season_id
 * @property string $stage_id
 * @property string|null $video_id
 * @property string|null $video_media_id
 * @property string $application_number
 * @property string $status
 * @property string|null $reupload_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereApplicationNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereContestantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel whereVideoMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class ApplicationModel extends Model
{
    use SoftDeletes;

    protected $table = 'applications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contestant_id',
        'season_id',
        'stage_id',
        'video_id',
        'video_media_id',
        'application_number',
        'status',
        'reupload_reason',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];
}
