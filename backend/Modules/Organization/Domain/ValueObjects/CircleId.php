<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable UUID identifier for a circle.
 *
 * Validated through Symfony's Uuid for the same reason CenterId is: Ramsey's
 * validator accepts a UUID whose version nibble is zero and Symfony's does
 * not, so a hand-written id can look valid everywhere until the first value
 * object is built from it.
 */
final readonly class CircleId
{
    public function __construct(
        public string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid CircleId format: {$value}");
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
