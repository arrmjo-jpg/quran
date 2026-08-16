<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonTranslationModel;

final class SeasonRepository implements SeasonRepositoryContract
{
    public function findOrFail(string $id): Season
    {
        $model = SeasonModel::query()->with('translations')->findOrFail($id);

        return $this->toDomain($model);
    }

    public function findOrFailForActivation(string $id): Season
    {
        // Lock every season row before reading any of them. "Candidate
        // rows" (ADR-005 D16) means every season, not just the target: a
        // concurrent activation of a *different* draft season also
        // contends for the single-active-season slot, so it must be
        // serialized against this one too.
        SeasonModel::query()->lockForUpdate()->get(['id']);

        return $this->findOrFail($id);
    }

    public function find(string $id): ?Season
    {
        $model = SeasonModel::query()->with('translations')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<int, Season>
     */
    public function findAll(): array
    {
        return SeasonModel::query()->with('translations')->orderBy('year', 'desc')->get()
            ->map(fn (SeasonModel $model): Season => $this->toDomain($model))
            ->all();
    }

    public function findActiveSeason(): ?Season
    {
        $model = SeasonModel::query()->with('translations')->where('is_active', true)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Deliberately excludes stages, season_stage_rules and season_countries:
     * those are the season's own configuration, they belong to it, and a
     * restored draft is expected to still have them. What blocks a restore
     * is evidence the season was *used* — a frozen rule snapshot, or rows
     * in other modules that were created on the assumption it was running.
     *
     * Queried by table name rather than through each owning module's
     * models, per ADR-002.
     *
     * @return array<string, int>
     */
    public function findRestoreBlockers(string $seasonId): array
    {
        $blockers = [];

        // Directly keyed on the season.
        foreach (['season_rule_versions', 'applications', 'streams'] as $table) {
            $count = DB::table($table)->where('season_id', $seasonId)->count();

            if ($count > 0) {
                $blockers[$table] = $count;
            }
        }

        // Reached through the season's stages: these tables know a stage,
        // not a season, so a season with no stages can never have them.
        $stageIds = DB::table('stages')->where('season_id', $seasonId)->pluck('id')->all();

        if ($stageIds !== []) {
            foreach (['stage_results', 'judge_assignments'] as $table) {
                $count = DB::table($table)->whereIn('stage_id', $stageIds)->count();

                if ($count > 0) {
                    $blockers[$table] = $count;
                }
            }
        }

        return $blockers;
    }

    public function save(Season $season): void
    {
        SeasonModel::query()->updateOrCreate(
            ['id' => $season->id],
            [
                'slug' => $season->getSlug(),
                'year' => $season->getYear(),
                'status' => $season->getStatus(),
                'is_active' => $season->isActive(),
                'registration_start' => $season->getRegistrationStartIso(),
                'registration_end' => $season->getRegistrationEndIso(),
                'start_date' => $season->getStartDateIso(),
                'end_date' => $season->getEndDateIso(),
                'min_age' => $season->getMinAge(),
                'max_age' => $season->getMaxAge(),
                'participation_type_id' => $season->getParticipationTypeId(),
                'tajweed_level_id' => $season->getTajweedLevelId(),
                'frozen_at' => $season->getFrozenAtIso(),
                'archived_at' => $season->getArchivedAtIso(),
                'archived_by_user_id' => $season->getArchivedByUserId(),
                'archive_reason' => $season->getArchiveReason(),
            ]
        );

        $this->saveTranslations($season);
    }

    public function deactivateOthers(string $exceptId): void
    {
        SeasonModel::query()->where('id', '!=', $exceptId)->update(['is_active' => false]);
    }

    private function saveTranslations(Season $season): void
    {
        foreach (['ar', 'en', 'es'] as $locale) {
            $translation = $season->getTranslation($locale);

            if ($translation === null) {
                continue;
            }

            $attributes = [
                'title' => $translation->title,
                'public_name' => $translation->publicName,
                'public_short_name' => $translation->publicShortName,
                'description' => $translation->description,
            ];

            $existing = SeasonTranslationModel::query()
                ->where('season_id', $season->id)
                ->where('locale', $locale)
                ->first();

            if ($existing) {
                $existing->update($attributes);
            } else {
                SeasonTranslationModel::query()->create([
                    'id' => (string) Str::uuid(),
                    'season_id' => $season->id,
                    'locale' => $locale,
                    ...$attributes,
                ]);
            }
        }
    }

    private function toDomain(SeasonModel $model): Season
    {
        $translationsByLocale = [];

        foreach ($model->translations as $translationModel) {
            $translationsByLocale[$translationModel->locale] = new SeasonTranslation(
                locale: $translationModel->locale,
                title: $translationModel->title,
                publicName: $translationModel->public_name,
                publicShortName: $translationModel->public_short_name,
                description: $translationModel->description,
            );
        }

        return new Season(
            id: $model->id,
            slug: $model->slug,
            year: (int) $model->year,
            registrationStartIso: $model->registration_start?->toIso8601String() ?? now()->toIso8601String(),
            registrationEndIso: $model->registration_end?->toIso8601String() ?? now()->toIso8601String(),
            startDateIso: $model->start_date?->toIso8601String() ?? now()->toIso8601String(),
            endDateIso: $model->end_date?->toIso8601String() ?? now()->toIso8601String(),
            status: $model->status,
            isActive: (bool) $model->is_active,
            minAge: $model->min_age,
            maxAge: $model->max_age,
            participationTypeId: $model->participation_type_id,
            tajweedLevelId: $model->tajweed_level_id,
            frozenAtIso: $model->frozen_at?->toIso8601String(),
            archivedAtIso: $model->archived_at?->toIso8601String(),
            archivedByUserId: $model->archived_by_user_id,
            archiveReason: $model->archive_reason,
            translationsByLocale: $translationsByLocale,
        );
    }
}
