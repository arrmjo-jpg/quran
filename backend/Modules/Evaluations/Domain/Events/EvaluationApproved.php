<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Events;

final readonly class EvaluationApproved
{
    public const TYPE = 'evaluation_approved';

    public function __construct(
        public string $evaluationId,
        public string $applicationId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'application_id' => $this->applicationId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
