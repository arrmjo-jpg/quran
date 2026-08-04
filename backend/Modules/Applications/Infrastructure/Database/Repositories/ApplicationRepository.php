<?php

declare(strict_types=1);

namespace Modules\Applications\Infrastructure\Database\Repositories;

use Modules\Applications\Domain\Entities\Application;
use Modules\Applications\Domain\Repositories\ApplicationRepositoryContract;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;

final class ApplicationRepository implements ApplicationRepositoryContract
{
    public function findOrFail(string $id): Application
    {
        $model = ApplicationModel::query()->findOrFail($id);

        return $this->toDomain($model);
    }

    public function find(string $id): ?Application
    {
        $model = ApplicationModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByNumber(string $appNumber): ?Application
    {
        $model = ApplicationModel::query()->where('application_number', $appNumber)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Application $application): void
    {
        ApplicationModel::query()->updateOrCreate(
            ['id' => $application->id],
            [
                'contestant_id' => $application->contestantId,
                'season_id' => $application->seasonId,
                'stage_id' => $application->stageId,
                'video_id' => $application->videoId,
                'video_media_id' => $application->videoMediaId,
                'application_number' => $application->applicationNumber,
                'status' => $application->getStatus(),
            ]
        );
    }

    private function toDomain(ApplicationModel $model): Application
    {
        return new Application(
            id: $model->id,
            contestantId: $model->contestant_id,
            seasonId: $model->season_id,
            stageId: $model->stage_id,
            applicationNumber: $model->application_number,
            status: $model->status,
            videoId: $model->video_id,
            submittedAtIso: $model->submitted_at?->toIso8601String(),
            deletedAt: $model->deleted_at?->toIso8601String()
        );
    }
}
