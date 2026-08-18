<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Services;

use Modules\Competition\Domain\ValueObjects\ResolvedSeasonRules;

/**
 * SeasonRuleSnapshotFactory
 *
 * Pure domain service — zero DB/repository reads, exactly like
 * RankingService. Builds the season_rule_versions.snapshot_json
 * payload from already-resolved data handed to it by the caller. Never
 * invoked from a Controller: the season's aggregate root calls this
 * during openRegistration() and records the result on a SeasonFrozen
 * domain event; persisting it is the repository/application layer's
 * job.
 */
final readonly class SeasonRuleSnapshotFactory
{
    /**
     * @return array<string, mixed>
     */
    public function build(
        int $version,
        string $frozenAtIso,
        ?int $minAge,
        ?int $maxAge,
        ResolvedSeasonRules $rules,
    ): array {
        return [
            'version' => $version,
            'frozen_at' => $frozenAtIso,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'participation_type' => $rules->participationType->toArray(),
            'tajweed_level' => $rules->tajweedLevel->toArray(),
            'eligible_countries' => array_map(
                static fn ($country) => $country->toArray(),
                $rules->eligibleCountries
            ),
            'stages' => array_map(
                static fn ($stageRule) => $stageRule->toArray(),
                $rules->stageRules
            ),
        ];
    }
}
