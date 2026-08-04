<?php

declare(strict_types=1);

namespace Modules\Countries\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * CountryModel
 *
 * Infrastructure Eloquent Model for countries table. Integrated with Meilisearch via Scout.
 *
 * @property string $id
 * @property string $iso_code
 * @property string $iso3_code
 * @property string $phone_code
 * @property string|null $flag_url
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CountryTranslationModel> $translations
 * @property-read int|null $translations_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereFlagUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereIso3Code($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereIsoCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel wherePhoneCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class CountryModel extends Model
{
    use Searchable;

    protected $table = 'countries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'iso_code',
        'iso3_code',
        'phone_code',
        'flag_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslationModel::class, 'country_id', 'id');
    }

    /**
     * Get the indexable data array for Meilisearch.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $translations = $this->translations->pluck('name', 'locale')->toArray();

        return [
            'id' => $this->id,
            'iso_code' => $this->iso_code,
            'iso3_code' => $this->iso3_code,
            'phone_code' => $this->phone_code,
            'is_active' => $this->is_active,
            'name_ar' => $translations['ar'] ?? '',
            'name_en' => $translations['en'] ?? '',
        ];
    }
}
