<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;

/**
 * UpdateSeasonUseCase
 *
 * Edits a season's own descriptive data: slug, year, the four schedule
 * dates, and per-locale title/public name. Until this existed a season's
 * basics were fixed at creation forever — a typo in a title could not be
 * corrected through the API at all.
 *
 * Deliberately not here: age range, participation type, tajweed level, and
 * eligible countries stay on UpdateSeasonRulesUseCase, and stage rules on
 * UpdateSeasonStageRulesUseCase. "What the season is" and "the rules
 * entrants are judged under" are edited on different screens and freeze
 * with different consequences, so they stay separate endpoints.
 *
 * Editing a frozen season is refused by the aggregate's own setters
 * (SeasonAlreadyFrozenException), not re-checked here.
 */
final readonly class UpdateSeasonUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
    ) {}

    /**
     * @param  array<string, array{title: string, public_name: string}>  $translations  locale => fields
     */
    public function execute(
        string $seasonId,
        string $slug,
        int $year,
        string $registrationStartIso,
        string $registrationEndIso,
        string $startDateIso,
        string $endDateIso,
        array $translations = [],
    ): Season {
        return DB::transaction(function () use ($seasonId, $slug, $year, $registrationStartIso, $registrationEndIso, $startDateIso, $endDateIso, $translations): Season {
            $season = $this->seasons->findOrFail($seasonId);

            $season->setSlug($slug);
            $season->setYear($year);
            $season->setSchedule($registrationStartIso, $registrationEndIso, $startDateIso, $endDateIso);

            foreach ($translations as $locale => $fields) {
                $existing = $season->getTranslation($locale);

                $season->setTranslation(new SeasonTranslation(
                    locale: $locale,
                    title: $fields['title'],
                    publicName: $fields['public_name'],
                    // Neither field is editable through this endpoint yet,
                    // so carry whatever is already stored rather than
                    // blanking it on every save.
                    publicShortName: $existing?->publicShortName,
                    description: $existing?->description,
                ));
            }

            $this->seasons->save($season);

            return $season;
        });
    }
}
