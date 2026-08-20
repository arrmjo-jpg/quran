<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Entities;

use Modules\Applications\Domain\Events\ApplicationCreated;
use Modules\Applications\Domain\Events\ApplicationSubmitted;
use Modules\Applications\Domain\ValueObjects\PlacementSnapshot;
use Modules\Core\Domain\Concerns\HasDomainEvents;

/**
 * Application Aggregate Root
 *
 * Governs contestant application submissions, state machine transitions, and media references.
 */
final class Application
{
    use HasDomainEvents;

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
        /**
         * Where the contestant studied when they submitted — ADR-016 D8.
         *
         * Nullable because Q4 refused to trap rows written before this epic,
         * not because a new submission may go without one: submit() requires
         * it. An application with no placement is an old row, never a fresh
         * one.
         */
        private ?PlacementSnapshot $placement = null,
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

    /**
     * The placement is REQUIRED here, not optional as on the constructor.
     *
     * The constructor also rebuilds rows written before D8 existed, which have
     * no placement and cannot be given one after the fact. A submission
     * happening now always can — G3 has already established that the
     * contestant holds an active membership, so there is always a circle and a
     * centre to record. Requiring it here is what makes "old rows may lack a
     * snapshot" a statement about history rather than a hole in the rule.
     */
    public static function submit(
        string $id,
        string $contestantId,
        string $seasonId,
        string $stageId,
        string $videoMediaId,
        PlacementSnapshot $placement
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
            submittedAtIso: now()->toIso8601String(),
            placement: $placement
        );

        $app->recordEvent(new ApplicationSubmitted($id, $contestantId, $seasonId, $app->submittedAtIso));

        return $app;
    }

    /**
     * The frozen placement, or null for an application submitted before D8.
     *
     * There is deliberately no setter. Refreshing a snapshot to match the live
     * centre would erase exactly the history it was added to keep.
     */
    public function getPlacement(): ?PlacementSnapshot
    {
        return $this->placement;
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
}
