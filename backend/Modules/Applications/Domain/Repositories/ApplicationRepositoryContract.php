<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Repositories;

use Modules\Applications\Domain\Entities\Application;

interface ApplicationRepositoryContract
{
    public function findOrFail(string $id): Application;

    public function find(string $id): ?Application;

    public function findByNumber(string $appNumber): ?Application;

    public function findByContestantSeasonStage(string $contestantId, string $seasonId, string $stageId): ?Application;

    public function save(Application $application): void;
}
