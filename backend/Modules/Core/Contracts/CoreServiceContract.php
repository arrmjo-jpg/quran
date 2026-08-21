<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * CoreServiceContract
 *
 * Public boundary interface for the Core module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface CoreServiceContract
{
    /**
     * Resolve a batch of account ids to the four fields ADR-016 D18 admits
     * into another module's context — id, name, derived status, type.
     *
     * A batch rather than a single id, following CountriesServiceContract:
     * the callers that need this are rendering a set of rows, and a
     * one-at-a-time method is an N+1 waiting for its first list.
     *
     * Soft-deleted accounts ARE returned, reported with status 'deleted'.
     * A contestant whose account was removed still has one, and a screen
     * that showed nothing there would say "no account" when the truth is
     * "an account that can no longer sign in" — the more useful fact and
     * the reason D18 admits `status` at all.
     *
     * Ids that match nothing are simply absent from the result; the caller
     * decides what a missing account means in its own context.
     *
     * @param  array<int, string>  $ids
     * @return array<int, ResolvedUserDTO>
     */
    public function findResolvedByIds(array $ids): array;
}
