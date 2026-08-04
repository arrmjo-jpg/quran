<?php

declare(strict_types=1);

use Modules\Videos\Domain\Entities\Video;
use Modules\Videos\Domain\ValueObjects\VideoId;

uses()->group('videos', 'unit', 'domain');

test('video aggregate transitions through HLS transcoding state machine', function (): void {
    $video = Video::create(
        id: VideoId::generate(),
        applicationId: fake()->uuid(),
        rawMediaAssetId: fake()->uuid()
    );

    expect($video->getStatus())->toBe('uploaded');

    $video->markProcessing();
    expect($video->getStatus())->toBe('processing');

    $video->markReady(
        durationSeconds: 180,
        hlsMasterPath: 'hls/recitation-101/master.m3u8',
        thumbnailPath: 'thumbnails/recitation-101.jpg',
        variants: [
            '1080p' => 'hls/recitation-101/1080p.m3u8',
            '720p' => 'hls/recitation-101/720p.m3u8',
        ]
    );

    expect($video->getStatus())->toBe('ready');
    expect($video->getHlsMasterPlaylistPath())->toBe('hls/recitation-101/master.m3u8');
});
