<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\Services\SeasonEditingPolicy;
use Modules\Competition\Domain\ValueObjects\StageTranslation;

/**
 * CreateStageUseCase
 *
 * Appends a stage to a season, with its per-locale content, and releases
 * the StageCreated event the aggregate records.
 *
 * stage_number is assigned here from the repository, never accepted from
 * the caller: a client-supplied number would race
 * uk_stages_season_number and force every create to handle a collision.
 * Appending is the only way to add a stage; changing where a stage sits
 * in the running order is ReorderStagesUseCase's job.
 *
 * Editing a frozen season's stages is refused for the same reason the
 * season's own rules and translations are (SeasonAlreadyFrozenException):
 * the configuration entrants signed up under must not move underneath
 * them.
 */
final readonly class CreateStageUseCase
{
    public function __construct(
        private StageRepositoryContract $stages,
        private SeasonRepositoryContract $seasons,
        private SeasonEditingPolicy $editing,
    ) {}

    /**
     * @param  array<string, array{name: string, public_name?: string|null, description?: string|null}>  $translations  locale => fields
     */
    public function execute(
        string $id,
        string $seasonId,
        string $type,
        string $startDateIso,
        string $endDateIso,
        ?string $evaluationTemplateId = null,
        array $translations = [],
    ): Stage {
        return DB::transaction(function () use ($id, $seasonId, $type, $startDateIso, $endDateIso, $evaluationTemplateId, $translations): Stage {
            $this->editing->assertEditable($this->seasons->findOrFail($seasonId), 'stages');

            $stage = Stage::create(
                id: $id,
                seasonId: $seasonId,
                stageNumber: $this->stages->nextStageNumber($seasonId),
                type: $type,
                startDateIso: $startDateIso,
                endDateIso: $endDateIso,
                evaluationTemplateId: $evaluationTemplateId,
            );

            foreach ($translations as $locale => $fields) {
                $stage->setTranslation(new StageTranslation(
                    locale: $locale,
                    name: $fields['name'],
                    publicName: $fields['public_name'] ?? null,
                    description: $fields['description'] ?? null,
                ));
            }

            $this->stages->save($stage);

            foreach ($stage->releaseEvents() as $event) {
                event($event);
            }

            return $stage;
        });
    }
}
