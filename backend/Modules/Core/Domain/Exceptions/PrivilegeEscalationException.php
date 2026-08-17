<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * ADR-015 PE-1 / PE-2 — you cannot grant, or build into a role, a
 * capability you do not hold yourself.
 *
 * Names the permissions that exceeded the actor rather than refusing
 * flatly: an administrator who hits this needs to know which capability
 * put them over the line, and a bare "forbidden" would send them
 * guessing.
 */
final class PrivilegeEscalationException extends DomainException
{
    /** @param array<int, string> $exceeding */
    public function __construct(
        public readonly string $subject,
        public readonly array $exceeding,
    ) {
        parent::__construct(
            "Refusing to grant '{$subject}': it carries permissions you do not hold — "
            .implode(', ', $exceeding)
        );
    }
}
