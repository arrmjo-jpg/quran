<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use Modules\Core\Domain\Exceptions\InvalidUserEmailException;

/**
 * Email Value Object
 *
 * Enforces email validation invariant at domain layer.
 */
final readonly class Email
{
    public function __construct(
        public string $value,
    ) {
        $normalized = strtolower(trim($value));
        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidUserEmailException("Invalid email format: {$value}");
        }
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
