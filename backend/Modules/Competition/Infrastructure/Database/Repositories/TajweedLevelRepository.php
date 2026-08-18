<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Modules\Competition\Domain\Repositories\TajweedLevelRepositoryContract;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;

final class TajweedLevelRepository implements TajweedLevelRepositoryContract
{
    public function findOrFail(string $id): ResolvedLookupOption
    {
        $model = TajweedLevelModel::query()->with('translations')->findOrFail($id);

        return new ResolvedLookupOption(
            id: $model->id,
            code: $model->code,
            name: $model->translations->pluck('name', 'locale')->toArray(),
        );
    }

    /**
     * @return array<int, ResolvedLookupOption>
     */
    public function findAllActive(): array
    {
        return TajweedLevelModel::query()
            ->with('translations')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (TajweedLevelModel $model): ResolvedLookupOption => new ResolvedLookupOption(
                id: $model->id,
                code: $model->code,
                name: $model->translations->pluck('name', 'locale')->toArray(),
                displayOrder: $model->display_order,
            ))
            ->all();
    }
}
