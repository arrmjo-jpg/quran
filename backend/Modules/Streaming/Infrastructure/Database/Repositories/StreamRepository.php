<?php

declare(strict_types=1);

namespace Modules\Streaming\Infrastructure\Database\Repositories;

use Modules\Streaming\Domain\Entities\Stream;
use Modules\Streaming\Domain\Repositories\StreamRepositoryContract;
use Modules\Streaming\Infrastructure\Database\Models\StreamModel;

final class StreamRepository implements StreamRepositoryContract
{
    public function findOrFail(string $id): Stream
    {
        $model = StreamModel::query()->findOrFail($id);

        return $this->toDomain($model);
    }

    public function findActiveForStage(string $stageId): ?Stream
    {
        $model = StreamModel::query()->where('stage_id', $stageId)->where('status', 'live')->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Stream $stream): void
    {
        StreamModel::query()->updateOrCreate(
            ['id' => $stream->id],
            [
                'stage_id' => $stream->stageId,
                'title' => $stream->title,
                'hls_playback_url' => $stream->getActiveStreamUrl(),
                'status' => $stream->getStatus(),
            ]
        );
    }

    private function toDomain(StreamModel $model): Stream
    {
        return new Stream(
            id: $model->id,
            stageId: $model->stage_id,
            title: $model->title,
            primarySourceUrl: $model->hls_playback_url ?? '',
            status: $model->status
        );
    }
}
