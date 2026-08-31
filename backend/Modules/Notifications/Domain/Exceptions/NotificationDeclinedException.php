<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Exceptions;

use DomainException;

/**
 * A retry was asked for on a notification the account has since declined
 * — ADR-020 D4.
 *
 * The ordinary send path returns quietly when a type is declined, because
 * nothing was asked for: an event fired and the account had opted out. A retry
 * is different — a person clicked a button and is owed an answer, and the
 * answer is that the account's decision outranks the operator's.
 *
 * The row it refuses is not moved. It stays `failed` with the reason it
 * already carried, the same as every other refusal on that path.
 */
final class NotificationDeclinedException extends DomainException
{
    public static function for(string $type): self
    {
        return new self(
            "This account has turned off '{$type}' notifications, so it cannot be sent again."
        );
    }
}
