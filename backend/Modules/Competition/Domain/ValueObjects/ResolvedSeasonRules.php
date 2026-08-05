<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * ResolvedSeasonRules
 *
 * Everything SeasonRuleSnapshotFactory needs to build a snapshot,
 * already fetched and resolved by the repository/application layer.
 * The pure domain layer never queries the database itself — it only
 * ever receives already-resolved bundles like this one.
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
        if ($this->eligibleCountries === []) {
            throw new InvalidArgumentException('A season cannot open registration with zero eligible countries.');
        }

        if ($this->stageRules === []) {
            throw new InvalidArgumentException('A season cannot open registration with zero stages configured.');
        }
    }
}
