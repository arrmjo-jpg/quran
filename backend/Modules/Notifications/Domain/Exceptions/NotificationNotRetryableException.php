<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Exceptions;

use DomainException;

/**
 * A retry was asked for on a notification that is not failed — ADR-020 D5.
 *
 * Retry is manual and exists for one situation: delivery was attempted, the
 * attempts ran out, and a human decided to try again. Re-sending something
 * already `sent` would deliver it twice, and re-sending something still
 * `queued` would race the job that is about to run.
 *
 * Thrown from the domain rather than checked in the controller so that every
 * caller inherits the rule — the previous version enforced it in one HTTP
 * handler, which is a rule that holds only as long as nothing else calls it.
 */
final class NotificationNotRetryableException extends DomainException
{
    public static function because(string $status): self
    {
        return new self("Only failed notifications can be retried. Current status: {$status}");
    }
}
