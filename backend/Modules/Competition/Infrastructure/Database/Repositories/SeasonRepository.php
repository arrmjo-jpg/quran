<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

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

    public function findActiveSeason(): ?Season
    {
        $model = SeasonModel::query()->with('translations')->where('is_active', true)->first();

        return $model ? $this->toDomain($model) : null;
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
