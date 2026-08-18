<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

/**
 * Recorded when an archived season is returned to draft.
 *
 * Only ever reachable for a season that was cancelled from draft and never
 * froze — see Season::restore().
 *
 * This event carries the archival it undoes, not just the fact that it was
 * undone. Restoring clears archived_at, archived_by_user_id and
 * archive_reason from the row, so once it has run this event is the only
 * remaining record that the season was ever archived, by whom, and why.
 * Field names follow SeasonArchived/SeasonCancelled ($byUserId,
 * $occurredAt) so every lifecycle event in this module reads the same way.
 */
final readonly class SeasonRestored
{
    public const TYPE = 'season_restored';

    public function __construct(
        public string $seasonId,
        public string $occurredAt,
        public ?string $byUserId = null,
        /** What archived_at held before this restore cleared it. */
        public ?string $previousArchivedAt = null,
        /** Who archived it, before this restore cleared that too. */
        public ?string $previousArchivedByUserId = null,
        /** The reason given when it was archived or cancelled. */
        public ?string $previousArchiveReason = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
            'previous_archived_at' => $this->previousArchivedAt,
            'previous_archived_by_user_id' => $this->previousArchivedByUserId,
            'previous_archive_reason' => $this->previousArchiveReason,
        ];
    }
}
