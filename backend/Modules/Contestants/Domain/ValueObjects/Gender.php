<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Gender
{
    private const ALLOWED = ['male', 'female'];

    public function __construct(
        public string $value,
    ) {
        $clean = strtolower(trim($value));
        if (! in_array($clean, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Invalid gender: {$value}. Allowed: male, female");
        }
    }

    public function __toString(): string
    {
        return strtolower($this->value);
    }
}
