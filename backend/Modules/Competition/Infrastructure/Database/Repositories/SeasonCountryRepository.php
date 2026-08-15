<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Repositories\SeasonCountryRepositoryContract;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;
use Modules\Countries\Contracts\CountriesServiceContract;
use Modules\Countries\Contracts\ResolvedCountryDTO;

final class SeasonCountryRepository implements SeasonCountryRepositoryContract
{
    public function __construct(
        private readonly CountriesServiceContract $countries,
    ) {}

    public function findEligibleCountries(string $seasonId): array
    {
        $countryIds = DB::table('season_countries')
            ->where('season_id', $seasonId)
            ->pluck('country_id')
            ->all();

        if ($countryIds === []) {
            return [];
        }

        return array_map(
            static fn (ResolvedCountryDTO $country): ResolvedLookupOption => new ResolvedLookupOption(
                id: $country->id,
                code: $country->iso2,
                name: $country->name,
            ),
            $this->countries->findResolvedByIds($countryIds)
        );
    }

    /**
     * @return array<int, string>
     */
    public function findEligibleCountryIds(string $seasonId): array
    {
        return DB::table('season_countries')
            ->where('season_id', $seasonId)
            ->pluck('country_id')
            ->all();
    }

    /**
     * @param  array<int, string>  $seasonIds
     * @return array<string, array<int, string>>
     */
    public function findEligibleCountryIdsBySeasons(array $seasonIds): array
    {
        if ($seasonIds === []) {
            return [];
        }

        return DB::table('season_countries')
            ->whereIn('season_id', $seasonIds)
            ->get(['season_id', 'country_id'])
            ->groupBy('season_id')
            ->map(fn ($rows): array => $rows->pluck('country_id')->all())
            ->all();
    }

    public function syncEligibleCountries(string $seasonId, array $countryIds): void
    {
        DB::table('season_countries')->where('season_id', $seasonId)->delete();

        if ($countryIds === []) {
            return;
        }

        DB::table('season_countries')->insert(array_map(
            static fn (string $countryId): array => ['season_id' => $seasonId, 'country_id' => $countryId],
            $countryIds
        ));
    }
}
