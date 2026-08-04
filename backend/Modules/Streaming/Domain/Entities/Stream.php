<?php

declare(strict_types=1);

namespace Modules\Streaming\Domain\Entities;

/**
 * Stream Aggregate Root
 *
 * Governs active live competition streams, primary/backup failover sources, and recordings.
 */
final class Stream
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        public readonly string $id,
        public readonly string $stageId,
        public readonly string $title,
        private string $primarySourceUrl,
        private ?string $backupSourceUrl = null,
        private string $status = 'offline', // 'offline', 'live', 'ended'
        private bool $isFailoverActive = false,
    ) {}

    public static function create(
        string $id,
        string $stageId,
        string $title,
        string $primarySourceUrl,
        ?string $backupSourceUrl = null
    ): self {
        return new self(
            id: $id,
            stageId: $stageId,
            title: $title,
            primarySourceUrl: $primarySourceUrl,
            backupSourceUrl: $backupSourceUrl,
            status: 'offline',
            isFailoverActive: false
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getActiveStreamUrl(): string
    {
        if ($this->isFailoverActive && $this->backupSourceUrl !== null) {
            return $this->backupSourceUrl;
        }

        return $this->primarySourceUrl;
    }

    public function startLive(): void
    {
        $this->status = 'live';
    }

    public function triggerFailover(): void
    {
        if ($this->backupSourceUrl !== null) {
            $this->isFailoverActive = true;
        }
    }

    public function endLive(): void
    {
        $this->status = 'ended';
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
