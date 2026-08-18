<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Services;

use Modules\Competition\Contracts\CompetitionServiceContract;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;

final class CompetitionService implements CompetitionServiceContract
{
    public function __construct(
        private readonly SeasonRepositoryContract $seasonRepository,
    ) {}

    public function getActiveSeasonStartDateIso(): ?string
    {
        return $this->seasonRepository->findActiveSeason()?->getStartDateIso();
    }
}
