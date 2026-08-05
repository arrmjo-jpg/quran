<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Exceptions;

use DomainException;

/**
 * Thrown when openRegistration() is attempted before the season's
 * required configuration is complete — missing translations (title +
 * public_name in every supported locale), missing participation type
 * or tajweed level, or missing age range. Registration must never open
 * on a half-configured season.
 */
final class IncompleteSeasonRulesException extends DomainException
{
    /**
     * @param  array<int, string>  $missing
     */
    public function __construct(
        public readonly array $missing,
    ) {
        parent::__construct('Season cannot open registration — incomplete configuration: '.implode(', ', $missing));
    }
}
