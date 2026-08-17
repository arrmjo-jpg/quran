<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * Thrown when a permission name does not exist in the catalogue.
 *
 * PermissionCatalog is the single source of truth (ADR-015 §4.3): a role
 * may only be granted a permission the catalogue defines, so a typo or a
 * name left behind by a rename fails loudly instead of persisting a grant
 * that nothing will ever check.
 */
final class UnknownPermissionException extends DomainException
{
    /** @param array<int, string> $names */
    public function __construct(public readonly array $names)
    {
        parent::__construct('Unknown permission(s): '.implode(', ', $names));
    }
}
