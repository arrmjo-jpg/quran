<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Exceptions;

use DomainException;

/**
 * Thrown when an admin action attempts to change a season's judging
 * rules (age range, participation type, tajweed level, stage rules,
 * eligible countries) after registration has opened and the season's
 * configuration has been frozen. Competition rules must not change
 * while entries are being accepted.
 */
final class SeasonAlreadyFrozenException extends DomainException
{
    public function __construct(string $seasonId, string $field)
    {
        parent::__construct("Season {$seasonId} is frozen and '{$field}' can no longer be changed.");
    }
}
