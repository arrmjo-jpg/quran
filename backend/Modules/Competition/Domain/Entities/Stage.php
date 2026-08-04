<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Entities;

use Modules\Competition\Domain\Events\StageCreated;

/**
 * Stage Aggregate Root
 *
 * Governs competition stage ordering, schedules, and active evaluation template linkage.
 */
final class Stage
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        public readonly string $id,
        public readonly string $seasonId,
        public readonly int $stageNumber,
        public readonly string $type, // 'preliminary', 'semi_final', 'final'
        private ?string $evaluationTemplateId = null,
        private string $status = 'pending', // 'pending', 'active', 'completed'
    ) {}

    public static function create(
        string $id,
        string $seasonId,
        int $stageNumber,
        string $type,
        ?string $evaluationTemplateId = null
    ): self {
        $stage = new self(
            id: $id,
            seasonId: $seasonId,
            stageNumber: $stageNumber,
            type: $type,
            evaluationTemplateId: $evaluationTemplateId,
            status: 'pending'
        );

        $stage->recordEvent(new StageCreated($id, $seasonId, $stageNumber, $type, now()->toIso8601String()));

        return $stage;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getEvaluationTemplateId(): ?string
    {
        return $this->evaluationTemplateId;
    }

    public function activate(): void
    {
        $this->status = 'active';
    }

    public function complete(): void
    {
        $this->status = 'completed';
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
