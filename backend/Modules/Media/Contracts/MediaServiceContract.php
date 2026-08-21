<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Media\Contracts;

/**
 * MediaServiceContract
 *
 * Public boundary interface for the Media module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface MediaServiceContract
{
    /**
     * Resolve a batch of asset ids to what a screen can render — a url, a
     * thumbnail, and enough type information to decide how to show it.
     *
     * Keyed by asset id, so a caller holding several ids (a contestant's
     * photo, a sponsor's logo) can match them without scanning.
     *
     * A batch rather than one at a time, for the reason
     * CountriesServiceContract gives: the first consumer that renders a list
     * turns a single-id method into an N+1.
     *
     * SOFT-DELETED ASSETS DO NOT RESOLVE. A deleted asset has no file to
     * point at, so returning a url for it would hand a screen an address
     * that 404s. Ids matching nothing — deleted or never existed — are
     * simply absent from the result, and the caller decides what that means
     * in its own context: for a contestant photo it means "no picture",
     * which is not the same as "the record is broken".
     *
     * @param  array<int, string>  $ids
     * @return array<string, ResolvedMediaDTO>
     */
    public function findResolvedByIds(array $ids): array;
}
