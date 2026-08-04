<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CountryIso3
{
    public function __construct(
        public string $value,
    ) {
        $upper = strtoupper(trim($value));
        if (! preg_match('/^[A-Z]{3}$/', $upper)) {
            throw new InvalidArgumentException("ISO3 code must be exactly 3 uppercase letters. Got: {$value}");
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
