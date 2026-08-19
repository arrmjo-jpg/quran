<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable UUID identifier for an invitation.
 *
 * Validated through Symfony's Uuid like UserId and RoleId, and for the reason
 * the identity epic learned the hard way: Ramsey's validator accepts a UUID
 * with a zero version nibble and Symfony's does not, so a hand-written id that
 * looks valid can throw on every request that builds this object.
 */
final readonly class InvitationId
{
    public function __construct(
        public string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid InvitationId format: {$value}");
        }
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v7());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
