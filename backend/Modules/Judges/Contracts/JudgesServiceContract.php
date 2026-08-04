<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Judges\Contracts;

/**
 * JudgesServiceContract
 *
 * Public boundary interface for the Judges module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface JudgesServiceContract
{
    // Define public methods that other modules may call.
    // Keep this interface minimal — expose only what cross-module consumers need.
}
