<?php

declare(strict_types=1);

namespace Modules\Contestants\Infrastructure\Services;

use Modules\Contestants\Contracts\ContestantsServiceContract;
use Modules\Contestants\Contracts\ResolvedContestantDTO;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;

final class ContestantsService implements ContestantsServiceContract
{
    public function findResolvedByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return ContestantModel::withTrashed()
            ->whereIn('user_id', $userIds)
            // Named columns. The three D22 admits, plus user_id to key the
            // result and deleted_at to derive isDeleted from — so a column
            // added to `contestants` later cannot arrive here by accident,
            // and national_id is never even fetched.
            ->get(['id', 'user_id', 'full_name', 'deleted_at'])
            ->mapWithKeys(fn (ContestantModel $contestant): array => [
                (string) $contestant->user_id => new ResolvedContestantDTO(
                    id: (string) $contestant->id,
                    fullName: $contestant->full_name,
                    isDeleted: $contestant->deleted_at !== null,
                ),
            ])
            ->all();
    }
}
