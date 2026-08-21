<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-011

declare(strict_types=1);

namespace Modules\Organization\Contracts;

/**
 * OrganizationServiceContract
 *
 * Public boundary interface for the Organization module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on concrete implementations inside this module.
 */
interface OrganizationServiceContract
{
    /**
     * Every period a contestant has belonged to a circle, newest first,
     * with each circle and its centre already resolved.
     *
     * The whole history rather than the open membership alone. Q4 made this
     * table historical on purpose — a contestant who transfers twice leaves
     * three rows — and a caller shown only the current circle would be
     * looking at the one view that loses every transfer, which is exactly
     * what Q4 rejected `contestants.circle_id` for.
     *
     * Unpaginated, and deliberately so: G1 permits one open membership at a
     * time, so the length of this list is the number of transfers a person
     * has made. That is a handful, not a page. If it ever is not, the
     * consumer's screen is the wrong shape rather than this method.
     *
     * @return array<int, ResolvedMembershipDTO>
     */
    public function findMembershipsForContestant(string $contestantId): array;
}
