<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * ADR-015 PE-5 — the last active account holding a system role cannot
 * lose it.
 *
 * Expressed in terms of `roles.is_system` rather than a role name, for
 * the same reason PE-4 is: the moment this rule names super_admin, that
 * name has to be right in one more place, and a second system role added
 * later would silently fall outside the protection. The column already
 * marks which roles are protected; this rule reads it.
 *
 * Deliberately its own exception rather than a generic refusal — an
 * operator who hits this is about to make the platform unadministrable,
 * which is worth saying plainly rather than as "forbidden".
 */
final class LastSystemRoleHolderException extends DomainException
{
    public function __construct(public readonly string $roleName)
    {
        parent::__construct(
            "Refusing to remove the last active account holding '{$roleName}' — "
            .'the platform would become unadministrable.'
        );
    }
}
