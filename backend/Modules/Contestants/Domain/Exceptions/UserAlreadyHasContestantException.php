<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Exceptions;

use DomainException;

/**
 * `contestants.user_id` is UNIQUE, and the index does not exclude
 * soft-deleted rows — so an account whose contestant was deleted still holds
 * it. Raised before the insert so an administrator is told the person is
 * already registered, rather than shown a constraint violation.
 */
final class UserAlreadyHasContestantException extends DomainException
{
    public function __construct(public readonly string $userId)
    {
        parent::__construct('This account already has a contestant record.');
    }
}
