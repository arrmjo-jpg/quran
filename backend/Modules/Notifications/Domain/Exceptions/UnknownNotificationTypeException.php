<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Exceptions;

use DomainException;

/**
 * A preference was set for a notification type no module has declared
 * — ADR-020 D4.
 *
 * The catalogue is the list of what this platform can send. Storing a
 * preference outside it would accumulate rows nothing ever reads — settings
 * for notifications that do not exist, indistinguishable from settings for
 * notifications that were renamed.
 *
 * Unknown is deliberately NOT treated as mandatory elsewhere: an unregistered
 * type means a module forgot to declare itself, and answering "mandatory"
 * would force delivery of something nobody claimed.
 */
final class UnknownNotificationTypeException extends DomainException
{
    public static function for(string $type): self
    {
        return new self("No module has declared a notification type called '{$type}'.");
    }
}
