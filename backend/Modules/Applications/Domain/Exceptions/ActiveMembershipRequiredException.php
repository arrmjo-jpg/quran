<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Exceptions;

use DomainException;

/**
 * The contestant belongs to no circle, so there is nothing to apply from.
 *
 * ADR-016 Q4: the columns an application freezes are nullable so historical
 * rows and future migrations are not trapped, and the rule lives here instead.
 * A NOT NULL column would have made every past row a migration problem; a rule
 * with no enforcement would have been decoration.
 *
 * Named rather than a bare DomainException for the reason the other two
 * refusals on this path are named: they map to different status codes, and a
 * controller cannot tell them apart by class if they share one.
 */
final class ActiveMembershipRequiredException extends DomainException
{
    public static function forContestant(string $contestantId): self
    {
        return new self("Contestant {$contestantId} has no active circle membership.");
    }
}
