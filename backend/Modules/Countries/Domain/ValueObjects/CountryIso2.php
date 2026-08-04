<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CountryIso2
{
    public function __construct(
        public string $value,
    ) {
        $upper = strtoupper(trim($value));
        if (! preg_match('/^[A-Z]{2}$/', $upper)) {
            throw new InvalidArgumentException("ISO2 code must be exactly 2 uppercase letters. Got: {$value}");
        }
    }

    public function equals(self $other): bool
    {
        return strtoupper($this->value) === strtoupper($other->value);
    }

    public function __toString(): string
    {
        return strtoupper($this->value);
    }
}
