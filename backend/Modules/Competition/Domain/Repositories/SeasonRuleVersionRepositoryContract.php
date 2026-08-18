<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

interface SeasonRuleVersionRepositoryContract
{
    /**
     * Persist an immutable rule-version snapshot row. season_rule_versions
     * has a unique (season_id, version) constraint and this contract has
     * no update/delete method — a version, once saved, is never modified,
     * only ever superseded by a later version number.
     *
     * @param  array<string, mixed>  $snapshotJson
     */
    public function save(string $seasonId, int $version, array $snapshotJson, ?string $createdByUserId): void;
}
