<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $max_score
 * @property int $display_order
 * @property bool $is_active
 * @property-read Collection<int, JudgeScoreSystemTranslationModel> $translations
 *
 * @mixin \Eloquent
 */
final class JudgeScoreSystemModel extends Model
{
    protected $table = 'judge_score_systems';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'max_score',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(JudgeScoreSystemTranslationModel::class, 'judge_score_system_id', 'id');
    }
}
