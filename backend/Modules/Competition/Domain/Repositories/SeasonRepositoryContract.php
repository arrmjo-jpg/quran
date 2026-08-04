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
}
