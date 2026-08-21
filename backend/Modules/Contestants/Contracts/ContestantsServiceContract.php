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
    /**
     * Resolve accounts to the contestant records behind them — the three
     * fields ADR-016 D22 admits into an account's context.
     *
     * Keyed by ACCOUNT id, not by contestant id: the caller starts from
     * accounts and needs to know which one each row answers for, and putting
     * that on the DTO would make it four fields wide and blur what D22
     * admitted. `contestants.user_id` is UNIQUE, so at most one row per
     * account and the key cannot collide.
     *
     * Soft-deleted contestants ARE returned, flagged `isDeleted`. An account
     * whose contestant record was removed still has one, and reporting
     * nothing would say "never competed" when the truth is "no longer
     * competing" — the distinction D22 admits `isDeleted` for.
     *
     * Accounts with no contestant are simply absent from the result.
     *
     * @param  array<int, string>  $userIds
     * @return array<string, ResolvedContestantDTO> account id => contestant
     */
    public function findResolvedByUserIds(array $userIds): array;
}
