<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Locale Value Object
 *
 * The languages the platform actually ships, and the single place that says
 * so. frontend-admin carries `locales/ar`, `locales/en` and `locales/es`, so
 * those three are the set.
 *
 * IT READ `ar, en, fr` UNTIL 2026-08-20, and the mismatch was not cosmetic.
 * CreateAdminUserRequest and UpdateUserRequest both write this column and both
 * accept `es`, so validation passed and this constructor threw with nothing
 * catching it: creating an administrator in Spanish, or setting an existing
 * one to Spanish from the users screen, answered 500. In the other direction
 * `fr` was reachable and has no translations anywhere, so an account could be
 * stored in a language the interface cannot render.
 *
 * Verified before the change: no row in `users` carried `fr`, so nothing had
 * to be migrated. RegisterUserRequest still accepts `fr` at the HTTP layer and
 * is now the one surface that can hand this object a value it refuses — a 500
 * waiting elsewhere, and its own change rather than one swept in here.
 */
final readonly class Locale
{
    private const ALLOWED_LOCALES = ['ar', 'en', 'es'];

    public function __construct(
        public string $value = 'ar',
    ) {
        $clean = strtolower(trim($value));
        if (! in_array($clean, self::ALLOWED_LOCALES, true)) {
            throw new InvalidArgumentException("Unsupported locale: {$value}. Allowed: ".implode(', ', self::ALLOWED_LOCALES));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
