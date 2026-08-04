<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * PasswordHash Value Object
 *
 * Encapsulates secure bcrypt/argon2 password hash string.
 */
final readonly class PasswordHash
{
    public function __construct(
        public string $value,
    ) {
        if (strlen($value) < 10) {
            throw new InvalidArgumentException('PasswordHash value must be a valid hashed string.');
        }
    }

    public static function fromPlainPassword(string $plainPassword): self
    {
        if (strlen($plainPassword) < 8) {
            throw new InvalidArgumentException('Plain password must be at least 8 characters.');
        }

        return new self(password_hash($plainPassword, PASSWORD_BCRYPT));
    }

    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->value);
    }
}
