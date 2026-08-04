<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

final readonly class StageCreated
{
    public const TYPE = 'stage_created';

    public function __construct(
        public string $stageId,
        public string $seasonId,
        public int $stageNumber,
        public string $type,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'stage_id' => $this->stageId,
            'season_id' => $this->seasonId,
            'stage_number' => $this->stageNumber,
            'type' => $this->type,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
