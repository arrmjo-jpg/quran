<?php

declare(strict_types=1);

namespace Modules\Videos\Infrastructure\Database\Repositories;

use Modules\Videos\Domain\Entities\Video;
use Modules\Videos\Domain\Repositories\VideoRepositoryContract;
use Modules\Videos\Domain\ValueObjects\VideoId;
use Modules\Videos\Infrastructure\Database\Models\VideoModel;

final class VideoRepository implements VideoRepositoryContract
{
    public function findOrFail(VideoId $id): Video
    {
        $model = VideoModel::query()->findOrFail($id->value);

        return $this->toDomain($model);
    }

    public function findByApplicationId(string $appId): ?Video
    {
        $model = VideoModel::query()->where('application_id', $appId)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Video $video): void
    {
        VideoModel::query()->updateOrCreate(
            ['id' => $video->id->value],
            [
                'application_id' => $video->applicationId,
                'raw_media_asset_id' => $video->rawMediaAssetId,
                'status' => $video->getStatus(),
                'hls_master_playlist_path' => $video->getHlsMasterPlaylistPath(),
            ]
        );
    }

    private function toDomain(VideoModel $model): Video
    {
        return new Video(
            id: new VideoId($model->id),
            applicationId: $model->application_id,
            rawMediaAssetId: $model->raw_media_asset_id,
            status: $model->status,
            durationSeconds: $model->duration_seconds,
            hlsMasterPlaylistPath: $model->hls_master_playlist_path,
            thumbnailPath: $model->thumbnail_path,
            variants: $model->variants ?? []
        );
    }
}
