<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Countries\Contracts;

/**
 * CountriesServiceContract
 *
 * Public boundary interface for the Countries module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface CountriesServiceContract
{
    /**
     * Resolve a batch of country ids to their id/iso2/translated-name
     * shape in one call, for consumers (e.g. Competition's season
     * eligible-countries snapshot) that need several at once.
     *
     * @param  array<int, string>  $ids
     * @return array<int, ResolvedCountryDTO>
     */
    public function findResolvedByIds(array $ids): array;
}
