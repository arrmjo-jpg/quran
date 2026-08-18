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
     * Replace a season's entire eligible-country set with the given ids.
     *
     * @param  array<int, string>  $countryIds
     */
    public function syncEligibleCountries(string $seasonId, array $countryIds): void;
}
