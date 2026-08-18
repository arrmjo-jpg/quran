<?php

declare(strict_types=1);

namespace Modules\Countries\Contracts;

/**
 * ResolvedCountryDTO
 *
 * Cross-module read shape for a single country: id, iso2 code, and its
 * per-locale translated names. Owned by Countries — consumers (e.g.
 * Competition) map this into their own domain value objects rather than
 * depending on Countries' internal entities directly.
 */
final readonly class ResolvedCountryDTO
{
    /**
     * @param  array<string, string>  $name  locale => translated name
     */
    public function __construct(
        public string $id,
        public string $iso2,
        public array $name,
    ) {}
}
