<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Entities;

use Modules\Evaluations\Domain\Events\EvaluationApproved;
use Modules\Evaluations\Domain\Events\EvaluationSubmitted;
use Modules\Evaluations\Domain\Services\EvaluationStateMachine;
use Modules\Evaluations\Domain\ValueObjects\EvaluationId;

/**
 * Evaluation Aggregate Root
 *
 * Represents a single judge evaluation session per application per stage.
 * Strictly forbidden from calculating stage rankings or qualification statuses!
 */
final class Evaluation
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    /**
     * @param  array<string, float>  $criteriaScores  Map of criterion_id => score
     */
    public function __construct(
        public readonly EvaluationId $id,
        public readonly string $applicationId,
        public readonly string $judgeId,
        private string $status = 'pending',
        private float $totalScore = 0.0,
        private ?string $notes = null,
        private array $criteriaScores = [],
    ) {}

    public static function start(
        EvaluationId $id,
        string $applicationId,
        string $judgeId
    ): self {
        return new self(
            id: $id,
            applicationId: $applicationId,
            judgeId: $judgeId,
            status: 'pending',
            totalScore: 0.0
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotalScore(): float
    {
        return $this->totalScore;
    }

    /** @return array<string, float> */
    public function getCriteriaScores(): array
    {
        return $this->criteriaScores;
    }

    public function submitScores(array $criteriaScores, ?string $notes, EvaluationStateMachine $stateMachine): void
    {
        $this->criteriaScores = $criteriaScores;
        $this->notes = $notes;
        $this->totalScore = array_sum($criteriaScores);

        $this->status = $stateMachine->transition($this->status, 'in_progress');
        $this->status = $stateMachine->transition($this->status, 'submitted');

        $this->recordEvent(new EvaluationSubmitted($this->id->value, $this->applicationId, $this->judgeId, $this->totalScore, now()->toIso8601String()));
    }

    public function approve(EvaluationStateMachine $stateMachine): void
    {
        $this->status = $stateMachine->transition($this->status, 'locked');
        $this->status = $stateMachine->transition($this->status, 'approved');

        $this->recordEvent(new EvaluationApproved($this->id->value, $this->applicationId, now()->toIso8601String()));
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
