<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $season_id
 * @property string $locale
 * @property string $title
 * @property string|null $description
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonTranslationModel whereTitle($value)
 *
 * @mixin \Eloquent
 */
final class SeasonTranslationModel extends Model
{
    protected $table = 'season_translations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'locale',
        'title',
        'description',
    ];
}
