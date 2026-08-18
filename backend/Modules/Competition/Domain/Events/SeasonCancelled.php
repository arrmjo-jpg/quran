<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

/**
 * Recorded when a season is cancelled before registration ever opened
 * (draft -> archived). A distinct event type from SeasonArchived so
 * consumers (audit trail, notifications) can tell "never launched" apart
 * from "ran its full course" without inspecting a flag.
 */
final readonly class SeasonCancelled
{
    public const TYPE = 'season_cancelled';

    public function __construct(
        public string $seasonId,
        public string $reason,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'reason' => $this->reason,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
