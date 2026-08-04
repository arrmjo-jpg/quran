<?php

declare(strict_types=1);

namespace Modules\Videos\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class VideoId
{
    public function __construct(
        public string $value,
    ) {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid VideoId format: {$value}");
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
