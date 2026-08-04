<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Videos\Contracts;

/**
 * VideosServiceContract
 *
 * Public boundary interface for the Videos module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface VideosServiceContract
{
    // Define public methods that other modules may call.
    // Keep this interface minimal — expose only what cross-module consumers need.
}
