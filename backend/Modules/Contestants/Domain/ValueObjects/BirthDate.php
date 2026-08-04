<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class BirthDate
{
    public function __construct(
        public string $value, // 'YYYY-MM-DD'
    ) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException("BirthDate must be YYYY-MM-DD format: {$value}");
        }
    }

    public function calculateAgeAt(string $targetDateIso): int
    {
        $birth = new \DateTimeImmutable($this->value);
        $target = new \DateTimeImmutable($targetDateIso);

        return $birth->diff($target)->y;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
