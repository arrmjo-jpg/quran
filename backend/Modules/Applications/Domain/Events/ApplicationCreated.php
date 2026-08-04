<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Events;

final readonly class ApplicationCreated
{
    public const TYPE = 'application_created';

    public function __construct(
        public string $applicationId,
        public string $contestantId,
        public string $seasonId,
        public string $stageId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'application_id' => $this->applicationId,
            'contestant_id' => $this->contestantId,
            'season_id' => $this->seasonId,
            'stage_id' => $this->stageId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
