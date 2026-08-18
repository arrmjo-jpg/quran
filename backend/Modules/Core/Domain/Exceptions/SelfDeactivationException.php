<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * An account may not deactivate itself.
 *
 * ADR-015 PE-6 — "you cannot deactivate or delete yourself". A change that
 * removes your own access should involve a second person, so nobody can
 * strand themselves and nobody can quietly remove their own trace.
 *
 * Attributed to PE-3 here at first, which was wrong: PE-3 governs roles,
 * and PE-6 already existed for exactly this. The rule was implemented
 * correctly and filed under the wrong invariant — worth correcting,
 * because the number is how a reader traces code back to the decision.
 *
 * Independent of PE-5. PE-5 asks whether the platform would still have an
 * administrator; this asks nothing about the wider system — it refuses even
 * when a dozen other super admins are active, because a live session
 * deactivating the account it is running as is a confusing state whatever
 * else is true.
 *
 * A separate exception rather than SelfRoleChangeException because the two
 * say different things to the operator, and one message covering both would
 * be vague in the place vagueness costs most.
 */
final class SelfDeactivationException extends DomainException
{
    public function __construct(public readonly string $userId)
    {
        parent::__construct('You cannot deactivate your own account.');
    }
}
