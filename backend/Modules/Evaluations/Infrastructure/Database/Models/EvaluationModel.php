<?php

declare(strict_types=1);

namespace Modules\Evaluations\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $application_id
 * @property string $judge_id
 * @property float $total_score
 * @property string|null $notes
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereJudgeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereTotalScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class EvaluationModel extends Model
{
    protected $table = 'evaluations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'application_id',
        'judge_id',
        'total_score',
        'notes',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'total_score' => 'float',
        'submitted_at' => 'datetime',
    ];
}
