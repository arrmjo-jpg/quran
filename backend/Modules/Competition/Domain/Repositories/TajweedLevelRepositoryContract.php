<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;

interface TajweedLevelRepositoryContract
{
    public function findOrFail(string $id): ResolvedLookupOption;
}
