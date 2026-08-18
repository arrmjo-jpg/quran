<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Exceptions;

use DomainException;

/**
 * Thrown when a season cannot be returned from `archived` to `draft`.
 *
 * Restoring is not the inverse of archiving. It exists only to undo an
 * archival that should never have happened — a draft season cancelled by
 * mistake, which never opened, never froze, and which nothing else has
 * come to depend on. A season that actually lived part of its life keeps
 * `archived` as a permanent record, and this exception is what says so.
 *
 * $reason carries the machine-readable code the HTTP layer maps to a 409,
 * so the operator is told which specific condition blocked the restore
 * rather than a generic refusal.
 */
final class SeasonNotRestorableException extends DomainException
{
    public const NOT_ARCHIVED = 'SEASON_NOT_ARCHIVED';

    public const FROZEN = 'CANNOT_RESTORE_FROZEN_SEASON';

    public const HAS_SNAPSHOTS = 'CANNOT_RESTORE_SEASON_WITH_SNAPSHOTS';

    public const HAS_DEPENDENTS = 'CANNOT_RESTORE_SEASON_WITH_DEPENDENTS';

    public const STILL_ACTIVE = 'CANNOT_RESTORE_ACTIVE_SEASON';

    /**
     * @param  array<int, string>  $details  what was found, for the operator
     */
    public function __construct(
        public readonly string $seasonId,
        public readonly string $reason,
        public readonly array $details = [],
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : "Season {$seasonId} cannot be restored: {$reason}.");
    }
}
