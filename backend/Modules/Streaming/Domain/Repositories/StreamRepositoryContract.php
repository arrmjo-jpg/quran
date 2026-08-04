<?php

declare(strict_types=1);

namespace Modules\Streaming\Domain\Repositories;

use Modules\Streaming\Domain\Entities\Stream;

interface StreamRepositoryContract
{
    public function findOrFail(string $id): Stream;

    public function findActiveForStage(string $stageId): ?Stream;

    public function save(Stream $stream): void;
}
