<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;

final class SeasonRepository implements SeasonRepositoryContract
{
    public function findOrFail(string $id): Season
    {
        $model = SeasonModel::query()->with('translations')->findOrFail($id);

        return $this->toDomain($model);
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
            ]
        );
    }

    public function deactivateOthers(string $exceptId): void
    {
        SeasonModel::query()->where('id', '!=', $exceptId)->update(['is_active' => false]);
    }

    private function toDomain(SeasonModel $model): Season
    {
        $translations = $model->translations->pluck('title', 'locale')->toArray();

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
            translations: $translations
        );
    }
}
