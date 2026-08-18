<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

/**
 * Recorded when a season that ran its full course (completed -> archived)
 * is archived. See SeasonCancelled for the separate, draft-only-reachable
 * "ended before it ever began" case.
 */
final readonly class SeasonArchived
{
    public const TYPE = 'season_archived';

    public function __construct(
        public string $seasonId,
        public ?string $reason,
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
