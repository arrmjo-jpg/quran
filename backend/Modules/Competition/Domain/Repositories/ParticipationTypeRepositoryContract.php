<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;

interface ParticipationTypeRepositoryContract
{
    public function findOrFail(string $id): ResolvedLookupOption;

    /**
     * The selectable catalog, active rows only, in display_order. Backs the
     * admin picker that supplies participation_type_id to the season rules
     * endpoint — inactive rows are retired options that existing seasons
     * may still reference but nothing new should be able to choose.
     *
     * @return array<int, ResolvedLookupOption>
     */
    public function findAllActive(): array;
}
