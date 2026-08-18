<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;

/**
 * ResolvedSeasonRules
 *
 * Everything SeasonRuleSnapshotFactory needs to build a snapshot,
 * already fetched and resolved by the repository/application layer.
 * The pure domain layer never queries the database itself — it only
 * ever receives already-resolved bundles like this one.
 *
 * The completeness guards below raise IncompleteSeasonRulesException
 * rather than a plain InvalidArgumentException: these fire on exactly the
 * same "this season is not configured yet" condition that
 * Season::assertRulesSelected() reports, and an admin who has not finished
 * configuring a season must get a 422 naming what is missing, not a 500.
 */
final readonly class ResolvedSeasonRules
{
    /**
     * @param  array<int, ResolvedLookupOption>  $eligibleCountries
     * @param  array<int, ResolvedStageRule>  $stageRules
     */
    public function __construct(
        public ResolvedLookupOption $participationType,
        public ResolvedLookupOption $tajweedLevel,
        public array $eligibleCountries,
        public array $stageRules,
    ) {
        $missing = [];

        if ($this->eligibleCountries === []) {
            $missing[] = 'eligible_countries';
        }

        if ($this->stageRules === []) {
            $missing[] = 'stage_rules';
        }

        if ($missing !== []) {
            throw new IncompleteSeasonRulesException($missing);
        }
    }
}
