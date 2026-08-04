<?php

declare(strict_types=1);

namespace Modules\Media\Domain\Repositories;

use Modules\Media\Domain\Entities\MediaAsset;
use Modules\Media\Domain\ValueObjects\MediaAssetId;

interface MediaAssetRepositoryContract
{
    public function findOrFail(MediaAssetId $id): MediaAsset;

    public function find(MediaAssetId $id): ?MediaAsset;

    public function findByHash(string $hashSha256): ?MediaAsset;

    public function save(MediaAsset $mediaAsset): void;
}
