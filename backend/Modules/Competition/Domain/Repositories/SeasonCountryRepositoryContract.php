<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;

interface SeasonCountryRepositoryContract
{
    /**
     * The season_countries pivot rows for a season, resolved to their
     * id/code/translated-name shape. season_countries is the real
     * registration-eligibility restriction, not decorative metadata —
     * this is what ResolvedSeasonRules::$eligibleCountries is built from.
     *
     * @return array<int, ResolvedLookupOption>
     */
    public function findEligibleCountries(string $seasonId): array;

    /**
     * Just the ids, unresolved. syncEligibleCountries() replaces the whole
     * set, so a client editing a season's rules has to send back every
     * country it wants kept — which means it must be able to read the
     * current set first. Ids alone are enough for that: the names live in
     * the countries catalog, and duplicating them here would create a
     * second source of truth for the same data.
     *
     * @return array<int, string>
     */
    public function findEligibleCountryIds(string $seasonId): array;

    /**
     * The same thing for several seasons at once, so listing seasons does
     * not fire one query per row.
     *
     * @param  array<int, string>  $seasonIds
     * @return array<string, array<int, string>> season id => country ids
     */
    public function findEligibleCountryIdsBySeasons(array $seasonIds): array;

    /**
     * Replace a season's entire eligible-country set with the given ids.
     *
     * @param  array<int, string>  $countryIds
     */
    public function syncEligibleCountries(string $seasonId, array $countryIds): void;
}
