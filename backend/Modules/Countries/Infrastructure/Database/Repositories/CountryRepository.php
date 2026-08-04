<?php

declare(strict_types=1);

namespace Modules\Countries\Infrastructure\Database\Repositories;

use Illuminate\Support\Str;
use Modules\Countries\Domain\Entities\Country;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryId;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Domain\ValueObjects\CountryIso3;
use Modules\Countries\Domain\ValueObjects\PhoneCode;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Countries\Infrastructure\Database\Models\CountryTranslationModel;

final class CountryRepository implements CountryRepositoryContract
{
    public function findOrFail(CountryId $id): Country
    {
        $model = CountryModel::query()->with('translations')->findOrFail($id->value);

        return $this->toDomain($model);
    }

    public function find(CountryId $id): ?Country
    {
        $model = CountryModel::query()->with('translations')->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIso2(CountryIso2 $iso2): ?Country
    {
        $model = CountryModel::query()->with('translations')->where('iso_code', (string) $iso2)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIso3(CountryIso3 $iso3): ?Country
    {
        $model = CountryModel::query()->with('translations')->where('iso3_code', (string) $iso3)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function getAllActive(): array
    {
        $models = CountryModel::query()->with('translations')->where('is_active', true)->get();

        return $models->map(fn (CountryModel $m): Country => $this->toDomain($m))->toArray();
    }

    public function save(Country $country): void
    {
        $model = null;
        CountryModel::withoutSyncingToSearch(function () use ($country, &$model): void {
            $model = CountryModel::query()->updateOrCreate(
                ['id' => $country->id->value],
                [
                    'iso_code' => (string) $country->getIso2(),
                    'iso3_code' => (string) $country->getIso3(),
                    'phone_code' => (string) $country->getPhoneCode(),
                    'flag_url' => $country->getFlagUrl(),
                    'is_active' => $country->isActive(),
                ]
            );
        });

        foreach ($country->getTranslations() as $locale => $name) {
            $existing = CountryTranslationModel::query()
                ->where('country_id', $country->id->value)
                ->where('locale', $locale)
                ->first();

            if ($existing) {
                $existing->update(['name' => $name]);
            } else {
                CountryTranslationModel::query()->create([
                    'id' => (string) Str::uuid(),
                    'country_id' => $country->id->value,
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }
        }
    }

    private function toDomain(CountryModel $model): Country
    {
        $translations = $model->translations->pluck('name', 'locale')->toArray();

        return new Country(
            id: new CountryId($model->id),
            iso2: new CountryIso2($model->iso_code),
            iso3: new CountryIso3($model->iso3_code),
            phoneCode: new PhoneCode($model->phone_code),
            flagUrl: $model->flag_url,
            isActive: (bool) $model->is_active,
            translations: $translations
        );
    }
}
