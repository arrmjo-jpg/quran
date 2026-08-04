<?php

declare(strict_types=1);

namespace Modules\Judges\Domain\Repositories;

use Modules\Judges\Domain\Entities\Judge;

interface JudgeRepositoryContract
{
    public function findOrFail(string $id): Judge;

    public function find(string $id): ?Judge;

    public function findByUserId(string $userId): ?Judge;

    /** @return array<int, Judge> */
    public function getAllActive(): array;

    public function save(Judge $judge): void;
}
