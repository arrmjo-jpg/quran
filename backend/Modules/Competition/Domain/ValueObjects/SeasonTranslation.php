<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * SeasonTranslation
 *
 * One locale's worth of a season's public-facing content. title and
 * public_name are the two fields required before a season may leave
 * draft (per the i18n architecture: content lives in the database,
 * never in a language file). description and public_short_name are
 * optional per PART 2's "if present" wording.
 */
final readonly class SeasonTranslation
{
    private const SUPPORTED_LOCALES = ['ar', 'en', 'es'];

    public function __construct(
        public string $locale,
        public string $title,
        public ?string $publicName = null,
        public ?string $publicShortName = null,
        public ?string $description = null,
    ) {
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new InvalidArgumentException("Unsupported locale: {$locale}");
        }
    }

    public function isComplete(): bool
    {
        return trim($this->title) !== '' && $this->publicName !== null && trim($this->publicName) !== '';
    }
}
