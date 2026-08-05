<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\Entities\Season;

interface SeasonRepositoryContract
{
    public function findOrFail(string $id): Season;

    public function find(string $id): ?Season;

    public function findActiveSeason(): ?Season;

    public function save(Season $season): void;

    /**
     * Deactivate every season except the given one. Only one season may
     * be is_active=true at a time — findActiveSeason() and the public
     * "current season" endpoint assume exactly one result.
     */
    public function deactivateOthers(string $exceptId): void;
}
