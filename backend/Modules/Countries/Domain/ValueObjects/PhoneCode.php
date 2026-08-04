<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class PhoneCode
{
    public function __construct(
        public string $value,
    ) {
        $clean = trim($value);
        if (! preg_match('/^\+?[0-9]{1,5}$/', $clean)) {
            throw new InvalidArgumentException("Invalid phone country code: {$value}");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
