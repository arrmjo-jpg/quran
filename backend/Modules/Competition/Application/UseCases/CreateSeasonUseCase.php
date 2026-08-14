<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;

/**
 * CreateSeasonUseCase
 *
 * Creates a new draft season, persists it, and releases + dispatches the
 * SeasonCreated event the aggregate records — the Controller previously
 * called Season::create() + repository->save() directly and never
 * touched releaseEvents(), so SeasonCreated was silently dropped on
 * every season ever created via the API. Same shape as
 * ArchiveSeasonUseCase/CancelSeasonUseCase.
 *
 * NOTE: $translations is passed straight through to Season::create()
 * unchanged from the Controller's previous behavior — it populates the
 * legacy translations property, not the translationsByLocale map
 * setTranslation()/assertTranslationsComplete() read from, so title_ar/
 * title_en are not yet actually persisted as SeasonTranslation rows.
 * That gap (and the missing title_es/public_name_* fields it would also
 * need) is pre-existing, not introduced here, and is explicitly out of
 * scope for this Use Case — see Step 9 verification report.
 */
final readonly class CreateSeasonUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
    ) {}

    /**
     * @param  array<string, string>  $translations
     */
    public function execute(
        string $id,
        string $slug,
        int $year,
        string $registrationStartIso,
        string $registrationEndIso,
        string $startDateIso,
        string $endDateIso,
        array $translations = [],
    ): Season {
        return DB::transaction(function () use ($id, $slug, $year, $registrationStartIso, $registrationEndIso, $startDateIso, $endDateIso, $translations): Season {
            $season = Season::create(
                id: $id,
                slug: $slug,
                year: $year,
                regStartIso: $registrationStartIso,
                regEndIso: $registrationEndIso,
                startDateIso: $startDateIso,
                endDateIso: $endDateIso,
                translations: $translations,
            );

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }
}
