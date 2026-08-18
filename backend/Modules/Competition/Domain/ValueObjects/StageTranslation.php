<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * StageTranslation
 *
 * One locale's worth of a stage's content. Mirrors SeasonTranslation:
 * name and public_name are the two fields a stage needs before its
 * season may leave draft, description stays optional. Stage content
 * lives in the database, never in a language file.
 */
final readonly class StageTranslation
{
    private const SUPPORTED_LOCALES = ['ar', 'en', 'es'];

    public function __construct(
        public string $locale,
        public string $name,
        public ?string $publicName = null,
        public ?string $description = null,
    ) {
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new InvalidArgumentException("Unsupported locale: {$locale}");
        }
    }

    public function isComplete(): bool
    {
        return trim($this->name) !== '' && $this->publicName !== null && trim($this->publicName) !== '';
    }
}
