<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonRuleVersionRepositoryContract;
use Modules\Competition\Infrastructure\Database\Models\SeasonRuleVersionModel;

final class SeasonRuleVersionRepository implements SeasonRuleVersionRepositoryContract
{
    public function save(string $seasonId, int $version, array $snapshotJson, ?string $createdByUserId): void
    {
        SeasonRuleVersionModel::query()->create([
            'id' => (string) Str::uuid(),
            'season_id' => $seasonId,
            'version' => $version,
            'snapshot_json' => $snapshotJson,
            'created_by_user_id' => $createdByUserId,
        ]);
    }
}
