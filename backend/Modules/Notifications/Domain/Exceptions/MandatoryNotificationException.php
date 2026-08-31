<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Exceptions;

use DomainException;

/**
 * Somebody tried to switch off a notification that may not be switched off
 * — ADR-020 D9.
 *
 * Today that means the account-creation invitation, and the reason is not
 * politeness: an invitation is the only way an account can be claimed
 * (ADR-016 D14 leaves accounts Pending Activation until it is accepted, and no
 * administrator ever sets another user's password). An account that could
 * decline its own invitation could never be entered again.
 *
 * A REFUSAL RATHER THAN A SILENT IGNORE. Accepting the request and quietly
 * continuing to send would leave a screen showing "off" beside a mailbox
 * receiving mail, which is the same class of untruth the rest of this epic
 * removed.
 */
final class MandatoryNotificationException extends DomainException
{
    public static function for(string $type): self
    {
        return new self("The notification '{$type}' is mandatory and cannot be turned off.");
    }
}
