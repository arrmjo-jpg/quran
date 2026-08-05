<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Repositories;

use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\ValueObjects\ContestantId;

interface ContestantRepositoryContract
{
    public function findOrFail(ContestantId $id): Contestant;

    public function find(ContestantId $id): ?Contestant;

    public function findByUserId(string $userId): ?Contestant;

    /**
     * Search contestants by full name, phone number, or national ID.
     * An empty/null query returns all contestants.
     *
     * @return array<int, Contestant>
     */
    public function search(?string $query): array;

    public function save(Contestant $contestant): void;
}
