<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Entities;

use Modules\Applications\Domain\Events\ApplicationCreated;
use Modules\Applications\Domain\Events\ApplicationSubmitted;

/**
 * Application Aggregate Root
 *
 * Governs contestant application submissions, state machine transitions, and media references.
 */
final class Application
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        public readonly string $id,
        public readonly string $contestantId,
        public readonly string $seasonId,
        public readonly string $stageId,
        public readonly string $applicationNumber,
        private string $status = 'draft',
        public ?string $videoId = null,
        public ?string $videoMediaId = null,
        private ?string $submittedAtIso = null,
        private ?string $deletedAt = null,
        private ?string $reuploadReason = null,
    ) {}

    public static function create(
        string $id,
        string $contestantId,
        string $seasonId,
        string $stageId,
        string $applicationNumber
    ): self {
        $app = new self(
            id: $id,
            contestantId: $contestantId,
            seasonId: $seasonId,
            stageId: $stageId,
            applicationNumber: $applicationNumber,
            status: 'draft'
        );

        $app->recordEvent(new ApplicationCreated($id, $contestantId, $seasonId, $stageId, now()->toIso8601String()));

        return $app;
    }

    public static function submit(
        string $id,
        string $contestantId,
        string $seasonId,
        string $stageId,
        string $videoMediaId
    ): self {
        $appNum = 'APP-'.strtoupper(substr(md5($id), 0, 8));
        $app = new self(
            id: $id,
            contestantId: $contestantId,
            seasonId: $seasonId,
            stageId: $stageId,
            applicationNumber: $appNum,
            status: 'submitted',
            videoMediaId: $videoMediaId,
            submittedAtIso: now()->toIso8601String()
        );

        $app->recordEvent(new ApplicationSubmitted($id, $contestantId, $seasonId, $app->submittedAtIso));

        return $app;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReuploadReason(): ?string
    {
        return $this->reuploadReason;
    }

    public function requestReupload(string $reason): void
    {
        $this->status = 'reupload_requested';
        $this->reuploadReason = $reason;
    }

    public function markReadyForJudging(): void
    {
        $this->status = 'ready_for_judging';
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
