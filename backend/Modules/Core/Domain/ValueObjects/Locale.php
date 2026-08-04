<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Locale Value Object
 *
 * Enforces BCP-47 locale standards ('ar', 'en').
 */
final readonly class Locale
{
    private const ALLOWED_LOCALES = ['ar', 'en', 'fr'];

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
