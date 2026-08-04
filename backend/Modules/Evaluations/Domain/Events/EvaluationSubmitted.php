<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Events;

final readonly class EvaluationSubmitted
{
    public const TYPE = 'evaluation_submitted';

    public function __construct(
        public string $evaluationId,
        public string $applicationId,
        public string $judgeId,
        public float $totalScore,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'application_id' => $this->applicationId,
            'judge_id' => $this->judgeId,
            'total_score' => $this->totalScore,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
