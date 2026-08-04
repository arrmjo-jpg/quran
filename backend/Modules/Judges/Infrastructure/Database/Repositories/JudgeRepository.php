<?php

declare(strict_types=1);

namespace Modules\Judges\Infrastructure\Database\Repositories;

use Modules\Judges\Domain\Entities\Judge;
use Modules\Judges\Domain\Repositories\JudgeRepositoryContract;
use Modules\Judges\Infrastructure\Database\Models\JudgeModel;

final class JudgeRepository implements JudgeRepositoryContract
{
    public function findOrFail(string $id): Judge
    {
        $model = JudgeModel::query()->findOrFail($id);

        return $this->toDomain($model);
    }

    public function find(string $id): ?Judge
    {
        $model = JudgeModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByUserId(string $userId): ?Judge
    {
        $model = JudgeModel::query()->where('user_id', $userId)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function getAllActive(): array
    {
        $models = JudgeModel::query()->where('is_active', true)->get();

        return $models->map(fn (JudgeModel $m): Judge => $this->toDomain($m))->toArray();
    }

    public function save(Judge $judge): void
    {
        JudgeModel::query()->updateOrCreate(
            ['id' => $judge->id],
            [
                'user_id' => $judge->userId,
                'full_name' => $judge->getFullName(),
                'specialization' => $judge->getSpecialization(),
                'is_active' => $judge->isActive(),
            ]
        );
    }

    private function toDomain(JudgeModel $model): Judge
    {
        return new Judge(
            id: $model->id,
            userId: $model->user_id,
            fullName: $model->full_name,
            specialization: $model->specialization,
            title: $model->title,
            bio: $model->bio,
            photoMediaId: $model->photo_media_id,
            isActive: (bool) $model->is_active,
            deletedAt: $model->deleted_at?->toIso8601String()
        );
    }
}
