<?php

declare(strict_types=1);

namespace Modules\Countries\Infrastructure\Services;

use Modules\Countries\Contracts\CountriesServiceContract;
use Modules\Countries\Contracts\ResolvedCountryDTO;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

final class CountriesService implements CountriesServiceContract
{
    public function findResolvedByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = CountryModel::query()
            ->with('translations')
            ->whereIn('id', $ids)
            ->get();

        return $models
            ->map(fn (CountryModel $model): ResolvedCountryDTO => new ResolvedCountryDTO(
                id: $model->id,
                iso2: $model->iso_code,
                name: $model->translations->pluck('name', 'locale')->toArray(),
            ))
            ->all();
    }
}
