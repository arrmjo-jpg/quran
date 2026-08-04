<?php

declare(strict_types=1);

namespace Modules\Videos\Domain\Entities;

use Modules\Videos\Domain\ValueObjects\VideoId;

/**
 * Video Aggregate Root
 *
 * Governs recitation video transcoding lifecycle, HLS master playlists, and resolution variants per ADR-006.
 */
final class Video
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    /**
     * @param  array<string, string>  $variants  Map of resolution => hls_playlist_path (e.g. '1080p' => 'path/master_1080p.m3u8')
     */
    public function __construct(
        public readonly VideoId $id,
        public readonly string $applicationId,
        public readonly string $rawMediaAssetId,
        private string $status = 'uploaded', // 'uploaded', 'processing', 'ready', 'failed'
        private ?int $durationSeconds = null,
        private ?string $hlsMasterPlaylistPath = null,
        private ?string $thumbnailPath = null,
        private array $variants = [],
    ) {}

    public static function create(
        VideoId $id,
        string $applicationId,
        string $rawMediaAssetId
    ): self {
        return new self(
            id: $id,
            applicationId: $applicationId,
            rawMediaAssetId: $rawMediaAssetId,
            status: 'uploaded'
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHlsMasterPlaylistPath(): ?string
    {
        return $this->hlsMasterPlaylistPath;
    }

    public function markProcessing(): void
    {
        $this->status = 'processing';
    }

    public function markReady(int $durationSeconds, string $hlsMasterPath, string $thumbnailPath, array $variants = []): void
    {
        $this->status = 'ready';
        $this->durationSeconds = $durationSeconds;
        $this->hlsMasterPlaylistPath = $hlsMasterPath;
        $this->thumbnailPath = $thumbnailPath;
        $this->variants = $variants;
    }

    public function markFailed(): void
    {
        $this->status = 'failed';
    }

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
