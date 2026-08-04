<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Contestants\Contracts;

/**
 * ContestantsServiceContract
 *
 * Public boundary interface for the Contestants module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface ContestantsServiceContract
{
    // Define public methods that other modules may call.
    // Keep this interface minimal — expose only what cross-module consumers need.
}
