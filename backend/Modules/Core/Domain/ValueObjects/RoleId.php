<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * RoleId Value Object — UUID, per ADR-005 Decision 2.
 */
final readonly class RoleId
{
    public function __construct(
        public string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid role id: {$value}");
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
