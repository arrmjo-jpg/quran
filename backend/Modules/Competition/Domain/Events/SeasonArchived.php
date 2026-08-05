<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

final readonly class SeasonArchived
{
    public const TYPE = 'season_archived';

    public function __construct(
        public string $seasonId,
        public bool $wasCancelled,
        public ?string $reason,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'was_cancelled' => $this->wasCancelled,
            'reason' => $this->reason,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
