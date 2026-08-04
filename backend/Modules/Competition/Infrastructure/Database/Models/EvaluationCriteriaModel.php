<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $template_id
 * @property string $name
 * @property string $code
 * @property float $max_score
 * @property float $weight_percent
 * @property int $display_order
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereDisplayOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereMaxScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationCriteriaModel whereWeightPercent($value)
 *
 * @mixin \Eloquent
 */
final class EvaluationCriteriaModel extends Model
{
    protected $table = 'evaluation_criteria';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'template_id',
        'name',
        'code',
        'max_score',
        'weight_percent',
        'display_order',
    ];

    protected $casts = [
        'max_score' => 'float',
        'weight_percent' => 'float',
        'display_order' => 'integer',
    ];
}
