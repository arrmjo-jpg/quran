<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * Thrown when an operation would rename, delete, or strip permissions
 * from a role marked `is_system` — ADR-015 PE-4.
 *
 * Protection is read from the `roles.is_system` column, never from a
 * hardcoded list of names. That is the whole point: in the system this
 * design learned from, `is_system` was computed in a Resource from a
 * hardcoded array, so the UI showed a protection badge on eight roles
 * while exactly one was actually protected.
 */
final class SystemRoleImmutableException extends DomainException
{
    public function __construct(
        public readonly string $roleName,
        public readonly string $operation,
    ) {
        parent::__construct("Role '{$roleName}' is a system role and cannot be {$operation}.");
    }
}
