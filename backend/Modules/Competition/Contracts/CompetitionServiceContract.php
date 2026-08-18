<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Competition\Contracts;

/**
 * CompetitionServiceContract
 *
 * Public boundary interface for the Competition module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface CompetitionServiceContract
{
    /**
     * ISO8601 start date of the currently active season, or null if no
     * season is active. Used by other modules (e.g. Contestants) to
     * evaluate age eligibility against the real competition, not a
     * hardcoded date.
     */
    public function getActiveSeasonStartDateIso(): ?string;
}
