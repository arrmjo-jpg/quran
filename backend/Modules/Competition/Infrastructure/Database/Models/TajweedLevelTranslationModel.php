<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tajweed_level_id
 * @property string $locale
 * @property string $name
 *
 * @mixin \Eloquent
 */
final class TajweedLevelTranslationModel extends Model
{
    protected $table = 'tajweed_level_translations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tajweed_level_id',
        'locale',
        'name',
    ];
}
