<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $judge_score_system_id
 * @property string $locale
 * @property string $name
 *
 * @mixin \Eloquent
 */
final class JudgeScoreSystemTranslationModel extends Model
{
    protected $table = 'judge_score_system_translations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'judge_score_system_id',
        'locale',
        'name',
    ];
}
