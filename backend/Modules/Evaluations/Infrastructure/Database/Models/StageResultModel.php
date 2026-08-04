<?php

declare(strict_types=1);

namespace Modules\Evaluations\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * StageResultModel — maps to `stage_results` table.
 *
 * Schema: id, stage_id, status (draft|published), published_at, published_by_user_id, timestamps
 * One record per stage; acts as the "publication header" for per-application Results.
 *
 * @property string $id
 * @property string $stage_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property string|null $published_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel wherePublishedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel whereStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageResultModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class StageResultModel extends Model
{
    protected $table = 'stage_results';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'stage_id',
        'status',
        'published_at',
        'published_by_user_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
