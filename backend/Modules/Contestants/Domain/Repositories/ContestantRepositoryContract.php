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

    public function save(Contestant $contestant): void;
}
