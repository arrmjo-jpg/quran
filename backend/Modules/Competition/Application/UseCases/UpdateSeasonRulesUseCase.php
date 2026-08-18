<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonCountryRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;

/**
 * UpdateSeasonRulesUseCase
 *
 * Sets a draft season's age range, participation type, tajweed level, and
 * eligible countries — the configuration OpenSeasonRegistrationUseCase's
 * assertRulesSelected()/assertTranslationsComplete() require before a
 * season may open registration, previously only reachable by manipulating
 * the domain/repository directly (there was no admin endpoint at all).
 *
 * Stage rules (season_stage_rules) are deliberately NOT part of this Use
 * Case: they reference a real stage_id, and no Stage management API
 * exists yet to create one — out of scope until that exists.
 *
 * Setting these fields on an already-frozen season is rejected by the
 * aggregate itself (assertMutableSettings() -> SeasonAlreadyFrozenException),
 * not re-validated here.
 */
final readonly class UpdateSeasonRulesUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
        private SeasonCountryRepositoryContract $seasonCountries,
    ) {}

    /**
     * @param  array<int, string>  $countryIds
     */
    public function execute(
        string $seasonId,
        int $minAge,
        int $maxAge,
        string $participationTypeId,
        string $tajweedLevelId,
        array $countryIds,
    ): Season {
        return DB::transaction(function () use ($seasonId, $minAge, $maxAge, $participationTypeId, $tajweedLevelId, $countryIds): Season {
            $season = $this->seasons->findOrFail($seasonId);

            $season->setAgeRange($minAge, $maxAge);
            $season->setParticipationType($participationTypeId);
            $season->setTajweedLevel($tajweedLevelId);

            $this->seasons->save($season);
            $this->seasonCountries->syncEligibleCountries($seasonId, $countryIds);

            return $season;
        });
    }
}
