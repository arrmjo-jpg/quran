<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\ValueObjects\StageTranslation;

/**
 * UpdateStageUseCase
 *
 * Edits a stage's type, schedule, evaluation template, and per-locale
 * content. Deliberately cannot move a stage's stage_number — that is
 * ReorderStagesUseCase's sole responsibility, which keeps every write
 * that contends uk_stages_season_number in one place.
 *
 * Translations are merged, not replaced: a payload carrying only 'ar'
 * leaves the existing 'en'/'es' rows untouched.
 */
final readonly class UpdateStageUseCase
{
    public function __construct(
        private StageRepositoryContract $stages,
        private SeasonRepositoryContract $seasons,
    ) {}

    /**
     * @param  array<string, array{name: string, public_name?: string|null, description?: string|null}>  $translations  locale => fields
     */
    public function execute(
        string $stageId,
        string $type,
        string $startDateIso,
        string $endDateIso,
        ?string $evaluationTemplateId = null,
        array $translations = [],
    ): Stage {
        return DB::transaction(function () use ($stageId, $type, $startDateIso, $endDateIso, $evaluationTemplateId, $translations): Stage {
            $stage = $this->stages->findOrFail($stageId);

            $this->seasons->findOrFail($stage->seasonId)->assertMutable('stages');

            $stage->setType($type);
            $stage->setSchedule($startDateIso, $endDateIso);
            $stage->setEvaluationTemplate($evaluationTemplateId);

            foreach ($translations as $locale => $fields) {
                $stage->setTranslation(new StageTranslation(
                    locale: $locale,
                    name: $fields['name'],
                    publicName: $fields['public_name'] ?? null,
                    description: $fields['description'] ?? null,
                ));
            }

            $this->stages->save($stage);

            return $stage;
        });
    }
}
