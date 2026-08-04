<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Database\Repositories;

use Modules\Media\Domain\Entities\MediaAsset;
use Modules\Media\Domain\Repositories\MediaAssetRepositoryContract;
use Modules\Media\Domain\ValueObjects\MediaAssetId;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;

final class MediaAssetRepository implements MediaAssetRepositoryContract
{
    public function findOrFail(MediaAssetId $id): MediaAsset
    {
        $model = MediaAssetModel::query()->findOrFail($id->value);

        return $this->toDomain($model);
    }

    public function find(MediaAssetId $id): ?MediaAsset
    {
        $model = MediaAssetModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByHash(string $hashSha256): ?MediaAsset
    {
        $model = MediaAssetModel::query()->where('hash_sha256', $hashSha256)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(MediaAsset $mediaAsset): void
    {
        MediaAssetModel::query()->updateOrCreate(
            ['id' => $mediaAsset->id->value],
            [
                'uploader_id' => $mediaAsset->uploaderId,
                'disk' => $mediaAsset->disk,
                'file_path' => $mediaAsset->filePath,
                'file_name' => $mediaAsset->fileName,
                'mime_type' => $mediaAsset->mimeType,
                'size_bytes' => $mediaAsset->sizeBytes,
                'hash_sha256' => $mediaAsset->hashSha256,
                'collection' => $mediaAsset->collection,
                'custom_properties' => $mediaAsset->customProperties,
            ]
        );
    }

    private function toDomain(MediaAssetModel $model): MediaAsset
    {
        return new MediaAsset(
            id: new MediaAssetId($model->id),
            uploaderId: $model->uploader_id,
            disk: $model->disk,
            filePath: $model->file_path,
            fileName: $model->file_name,
            mimeType: $model->mime_type,
            sizeBytes: (int) $model->size_bytes,
            hashSha256: $model->hash_sha256,
            collection: $model->collection,
            customProperties: $model->custom_properties ?? [],
            deletedAt: $model->deleted_at?->toIso8601String()
        );
    }
}
