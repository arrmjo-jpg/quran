<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * UserType Value Object
 *
 * The authentication surface an account may use — and nothing else.
 * Per ADR-015 §1 this answers only "which door may this account enter",
 * never "what may it do once inside": that is the job of roles and
 * permissions, which are a separate concern this class knows nothing
 * about.
 *
 * Contestants authenticate via social providers on the public surface;
 * admins via email/password + MFA on the admin surface (ADR-003).
 *
 * NOTE ON THE VALUE 'contestant': the column stored 'user' until the
 * ADR-015 rename. "user" as a value on the users table was ambiguous to
 * the point of being misleading once admin accounts became first-class,
 * so no code may use it to mean a contestant. This class is the single
 * definition of the two permitted values, and the architecture test
 * asserts nothing else reaches the column.
 */
final readonly class UserType
{
    public const CONTESTANT = 'contestant';

    public const ADMIN = 'admin';

    public const ALLOWED = [self::CONTESTANT, self::ADMIN];

    public function __construct(
        public string $value,
    ) {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                "Unsupported user type: {$value}. Allowed: ".implode(', ', self::ALLOWED)
            );
        }
    }

    public static function contestant(): self
    {
        return new self(self::CONTESTANT);
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function isContestant(): bool
    {
        return $this->value === self::CONTESTANT;
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
