<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\ValueObjects\StageTranslation;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Competition\Infrastructure\Database\Models\StageTranslationModel;

final class StageRepository implements StageRepositoryContract
{
    /**
     * Every table with a foreign key onto stages.id, minus
     * stage_translations — those are the stage's own children and get
     * deleted with it, so they are not "usage". Queried by table name
     * rather than through each owning module's Eloquent model on purpose:
     * ADR-002 forbids Competition from importing concrete classes out of
     * Judges/Streaming/Applications/Evaluations, and the architecture test
     * enforces it.
     */
    private const REFERENCING_TABLES = [
        'season_stage_rules',
        'judge_assignments',
        'streams',
        'applications',
        'stage_results',
    ];

    public function findOrFail(string $id): Stage
    {
        return $this->toDomain(StageModel::query()->with('translations')->findOrFail($id));
    }

    public function find(string $id): ?Stage
    {
        $model = StageModel::query()->with('translations')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<int, Stage>
     */
    public function findBySeason(string $seasonId): array
    {
        return StageModel::query()
            ->with('translations')
            ->where('season_id', $seasonId)
            ->orderBy('stage_number')
            ->get()
            ->map(fn (StageModel $model): Stage => $this->toDomain($model))
            ->all();
    }

    public function nextStageNumber(string $seasonId): int
    {
        $highest = StageModel::query()->where('season_id', $seasonId)->max('stage_number');

        return $highest === null ? 1 : ((int) $highest) + 1;
    }

    public function save(Stage $stage): void
    {
        StageModel::query()->updateOrCreate(
            ['id' => $stage->id],
            [
                'season_id' => $stage->seasonId,
                'stage_number' => $stage->getStageNumber(),
                'type' => $stage->getType(),
                'start_date' => $stage->getStartDateIso(),
                'end_date' => $stage->getEndDateIso(),
                'evaluation_template_id' => $stage->getEvaluationTemplateId(),
                'status' => $stage->getStatus(),
            ]
        );

        $this->saveTranslations($stage);
    }

    public function delete(string $id): void
    {
        // stage_translations cascades on the FK, but deleting it here
        // keeps the behaviour identical on any driver rather than
        // depending on FK enforcement being switched on.
        StageTranslationModel::query()->where('stage_id', $id)->delete();
        StageModel::query()->where('id', $id)->delete();
    }

    public function isReferenced(string $stageId): bool
    {
        foreach (self::REFERENCING_TABLES as $table) {
            if (DB::table($table)->where('stage_id', $stageId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $orderedStageIds
     */
    public function applyOrder(string $seasonId, array $orderedStageIds): void
    {
        // Two passes, because uk_stages_season_number is checked per
        // statement and MySQL cannot defer it: assigning final numbers
        // directly would collide the moment two stages swap places.
        // Parking every row above the season's current maximum first
        // guarantees the temporary numbers cannot clash with the
        // originals, and the second pass then lands on 1..N in a table
        // where nothing occupies that range any more.
        $offset = (int) (StageModel::query()->where('season_id', $seasonId)->max('stage_number') ?? 0);

        foreach ($orderedStageIds as $index => $stageId) {
            StageModel::query()
                ->where('id', $stageId)
                ->where('season_id', $seasonId)
                ->update(['stage_number' => $offset + $index + 1]);
        }

        foreach ($orderedStageIds as $index => $stageId) {
            StageModel::query()
                ->where('id', $stageId)
                ->where('season_id', $seasonId)
                ->update(['stage_number' => $index + 1]);
        }
    }

    private function saveTranslations(Stage $stage): void
    {
        foreach (['ar', 'en', 'es'] as $locale) {
            $translation = $stage->getTranslation($locale);

            if ($translation === null) {
                continue;
            }

            $attributes = [
                'name' => $translation->name,
                'public_name' => $translation->publicName,
                'description' => $translation->description,
            ];

            $existing = StageTranslationModel::query()
                ->where('stage_id', $stage->id)
                ->where('locale', $locale)
                ->first();

            if ($existing) {
                $existing->update($attributes);
            } else {
                StageTranslationModel::query()->create([
                    'id' => (string) Str::uuid(),
                    'stage_id' => $stage->id,
                    'locale' => $locale,
                    ...$attributes,
                ]);
            }
        }
    }

    private function toDomain(StageModel $model): Stage
    {
        $translationsByLocale = [];

        foreach ($model->translations as $translationModel) {
            $translationsByLocale[$translationModel->locale] = new StageTranslation(
                locale: $translationModel->locale,
                name: $translationModel->name,
                publicName: $translationModel->public_name,
                description: $translationModel->description,
            );
        }

        return new Stage(
            id: $model->id,
            seasonId: $model->season_id,
            stageNumber: (int) $model->stage_number,
            type: $model->type,
            startDateIso: $model->start_date?->toIso8601String() ?? now()->toIso8601String(),
            endDateIso: $model->end_date?->toIso8601String() ?? now()->addDay()->toIso8601String(),
            evaluationTemplateId: $model->evaluation_template_id,
            status: $model->status,
            translationsByLocale: $translationsByLocale,
        );
    }
}
