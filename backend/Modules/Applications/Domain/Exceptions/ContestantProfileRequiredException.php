<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Exceptions;

use DomainException;

/**
 * The signed-in user has no contestant profile, so there is nobody to apply as.
 *
 * A named exception rather than a bare DomainException because the submission
 * path has two refusals that map to different status codes — this one to 422
 * and a duplicate application to 409 — and a controller cannot tell them apart
 * by class if both are the same class.
 */
final class ContestantProfileRequiredException extends DomainException
{
    public static function forUser(string $userId): self
    {
        return new self("User {$userId} has no contestant profile.");
    }
}
