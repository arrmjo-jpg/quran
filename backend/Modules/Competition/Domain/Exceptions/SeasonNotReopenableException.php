<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Exceptions;

use DomainException;

/**
 * Thrown when a season's registration cannot be reopened.
 *
 * Reopening is an administrative correction, not a lifecycle step — the
 * competition's own path runs one way. It exists for the case where
 * registration was closed and the organisers decide entrants should still
 * be able to apply, and it is legal only from `registration_closed`.
 *
 * $reason carries the machine-readable code the HTTP layer maps to a 409,
 * so an operator learns which condition blocked the reopen rather than
 * being told no.
 */
final class SeasonNotReopenableException extends DomainException
{
    public const NOT_CLOSED = 'SEASON_NOT_REGISTRATION_CLOSED';

    public const ANOTHER_SEASON_ACTIVE = 'ANOTHER_SEASON_IS_ACTIVE';

    /**
     * When the blocker is another season holding the active slot, the
     * caller is told which one by id, slug and year — not the id alone.
     * The admin has to go and close that season, and a bare UUID means
     * hunting for it in the list first.
     */
    public function __construct(
        public readonly string $seasonId,
        public readonly string $reason,
        public readonly ?string $activeSeasonId = null,
        public readonly ?string $activeSeasonSlug = null,
        public readonly ?int $activeSeasonYear = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : "Season {$seasonId} cannot reopen registration: {$reason}.");
    }
}
