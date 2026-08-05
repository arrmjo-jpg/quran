<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;

interface SeasonStageRuleRepositoryContract
{
    /**
     * A season's per-stage judging rules (score system + qualification
     * percentage), resolved and ordered by stage_number. Backs
     * ResolvedSeasonRules::$stageRules.
     *
     * @return array<int, ResolvedStageRule>
     */
    public function findResolvedRules(string $seasonId): array;
}
