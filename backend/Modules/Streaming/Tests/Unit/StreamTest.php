<?php

declare(strict_types=1);

use Modules\Streaming\Domain\Entities\Stream;

uses()->group('streaming', 'unit', 'domain');

test('stream aggregate executes failover to backup RTMP/HLS source url', function (): void {
    $stream = Stream::create(
        id: fake()->uuid(),
        stageId: fake()->uuid(),
        title: 'Final Competition Live Stream',
        primarySourceUrl: 'https://live-primary.quranplatform.com/hls/master.m3u8',
        backupSourceUrl: 'https://live-backup.quranplatform.com/hls/master.m3u8'
    );

    expect($stream->getActiveStreamUrl())->toBe('https://live-primary.quranplatform.com/hls/master.m3u8');

    $stream->triggerFailover();
    expect($stream->getActiveStreamUrl())->toBe('https://live-backup.quranplatform.com/hls/master.m3u8');
});
