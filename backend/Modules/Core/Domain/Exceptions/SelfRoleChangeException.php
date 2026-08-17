<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * ADR-015 PE-3 — an account may not change its own roles, whatever
 * permissions it holds. Role changes are made by a different account, so
 * that acquiring capability always leaves a trace involving two people.
 */
final class SelfRoleChangeException extends DomainException
{
    public function __construct(public readonly string $userId)
    {
        parent::__construct('You cannot change your own roles.');
    }
}
