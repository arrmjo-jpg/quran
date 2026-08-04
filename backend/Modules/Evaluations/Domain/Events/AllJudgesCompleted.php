<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Events;

final readonly class AllJudgesCompleted
{
    public const TYPE = 'all_judges_completed';

    public function __construct(
        public string $applicationId,
        public string $stageId,
        public int $judgeCount,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'application_id' => $this->applicationId,
            'stage_id' => $this->stageId,
            'judge_count' => $this->judgeCount,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
