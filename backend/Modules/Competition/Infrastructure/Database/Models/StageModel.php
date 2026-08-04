<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $season_id
 * @property string|null $evaluation_template_id
 * @property int $stage_number
 * @property string $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereEvaluationTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereStageNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class StageModel extends Model
{
    protected $table = 'stages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'evaluation_template_id',
        'stage_number',
        'type',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'stage_number' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];
}
