<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\Entities\Season;

interface SeasonRepositoryContract
{
    public function findOrFail(string $id): Season;

    /**
     * Same as findOrFail(), but first takes a row lock (SELECT ... FOR
     * UPDATE) across every season row. Per ADR-005 Decision 16
     * ("seasons.is_current: Activating a season — Pessimistic Locking,
     * lock all candidate rows"), this is what serializes concurrent
     * "open registration" attempts against the single-active-season
     * invariant, so a second caller blocks until the first transaction
     * commits or rolls back instead of racing it and surfacing a raw
     * uk_seasons_single_active constraint violation. Callers MUST invoke
     * this inside an existing DB transaction — the lock is held only for
     * that transaction's lifetime.
     */
    public function findOrFailForActivation(string $id): Season;

    public function find(string $id): ?Season;

    /**
     * @return array<int, Season>
     */
    public function findAll(): array;

    public function findActiveSeason(): ?Season;

    public function save(Season $season): void;

    /**
     * Deactivate every season except the given one. Only one season may
     * be is_active=true at a time — findActiveSeason() and the public
     * "current season" endpoint assume exactly one result.
     */
    public function deactivateOthers(string $exceptId): void;
}
