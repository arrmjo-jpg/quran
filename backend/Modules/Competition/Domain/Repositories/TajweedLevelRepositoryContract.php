<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;

interface TajweedLevelRepositoryContract
{
    public function findOrFail(string $id): ResolvedLookupOption;

    /**
     * The selectable catalog, active rows only, in display_order. Backs the
     * admin picker that supplies tajweed_level_id to the season rules
     * endpoint.
     *
     * @return array<int, ResolvedLookupOption>
     */
    public function findAllActive(): array;
}
