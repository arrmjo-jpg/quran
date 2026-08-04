<?php

declare(strict_types=1);

namespace Modules\Countries\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $country_id
 * @property string $locale
 * @property string $name
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryTranslationModel whereName($value)
 *
 * @mixin \Eloquent
 */
final class CountryTranslationModel extends Model
{
    protected $table = 'country_translations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'country_id',
        'locale',
        'name',
    ];
}
