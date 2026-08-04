<?php

declare(strict_types=1);

use Modules\Media\Domain\Entities\MediaAsset;
use Modules\Media\Domain\ValueObjects\MediaAssetId;

uses()->group('media', 'unit', 'domain');

test('media asset aggregate distinguishes between private and public Cloudflare R2 disks', function (): void {
    $privateAsset = MediaAsset::create(
        id: MediaAssetId::generate(),
        uploaderId: fake()->uuid(),
        disk: 'r2_private',
        filePath: 'recitations/2026/video-101.mp4',
        fileName: 'video-101.mp4',
        mimeType: 'video/mp4',
        sizeBytes: 104857600
    );

    $publicAsset = MediaAsset::create(
        id: MediaAssetId::generate(),
        uploaderId: fake()->uuid(),
        disk: 'r2_public',
        filePath: 'avatars/contestant-101.png',
        fileName: 'contestant-101.png',
        mimeType: 'image/png',
        sizeBytes: 524288
    );

    expect($privateAsset->isPrivate())->toBeTrue();
    expect($publicAsset->isPrivate())->toBeFalse();
});
