<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;

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
 * $translations carries real SeasonTranslation data (title + public_name
 * per locale) that gets applied via setTranslation() before save() — this
 * replaces the previous behavior of passing title_ar/title_en straight
 * into Season::create()'s legacy $translations property, which
 * getTranslation()/SeasonRepository::save() never actually read from
 * (title_ar/title_en were silently dropped on every season ever created).
 * public_short_name/description are not collected yet — a season created
 * through this Use Case still needs those (or none, since they're
 * optional) added before it can pass assertTranslationsComplete(), same
 * as age range/participation type/tajweed level/countries/stages, which
 * remain out of scope here pending the Season Rules API.
 */
final readonly class CreateSeasonUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
    ) {}

    /**
     * @param  array<string, array{title: string, public_name: string}>  $translations  locale => translation fields
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
            );

            foreach ($translations as $locale => $fields) {
                $season->setTranslation(new SeasonTranslation(
                    locale: $locale,
                    title: $fields['title'],
                    publicName: $fields['public_name'],
                ));
            }

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }
}
