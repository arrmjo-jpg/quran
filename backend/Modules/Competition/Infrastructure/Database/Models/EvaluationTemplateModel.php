<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $season_id
 * @property string $name
 * @property float $max_total_score
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereMaxTotalScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvaluationTemplateModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class EvaluationTemplateModel extends Model
{
    protected $table = 'evaluation_templates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'name',
        'max_total_score',
        'is_active',
    ];

    protected $casts = [
        'max_total_score' => 'float',
        'is_active' => 'boolean',
    ];
}
