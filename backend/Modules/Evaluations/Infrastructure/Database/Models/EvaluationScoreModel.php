<?php

declare(strict_types=1);

namespace Modules\Evaluations\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $evaluation_id
 * @property string $criterion_id
 * @property float $score
 * @property string|null $notes
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel whereCriterionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel whereEvaluationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationScoreModel whereScore($value)
 *
 * @mixin \Eloquent
 */
final class EvaluationScoreModel extends Model
{
    public $timestamps = false;

    protected $table = 'evaluation_scores';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'evaluation_id',
        'criterion_id',
        'score',
        'notes',
    ];

    protected $casts = [
        'score' => 'float',
    ];
}
