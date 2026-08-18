<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $season_id
 * @property string $stage_id
 * @property string $judge_score_system_id
 * @property string|null $qualification_percentage
 * @property-read StageModel $stage
 * @property-read JudgeScoreSystemModel $judgeScoreSystem
 *
 * @mixin \Eloquent
 */
final class SeasonStageRuleModel extends Model
{
    protected $table = 'season_stage_rules';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'stage_id',
        'judge_score_system_id',
        'qualification_percentage',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(StageModel::class, 'stage_id', 'id');
    }

    public function judgeScoreSystem(): BelongsTo
    {
        return $this->belongsTo(JudgeScoreSystemModel::class, 'judge_score_system_id', 'id');
    }
}
