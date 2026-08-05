<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Modules\Competition\Domain\Repositories\ParticipationTypeRepositoryContract;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;

final class ParticipationTypeRepository implements ParticipationTypeRepositoryContract
{
    public function findOrFail(string $id): ResolvedLookupOption
    {
        $model = ParticipationTypeModel::query()->with('translations')->findOrFail($id);

        return new ResolvedLookupOption(
            id: $model->id,
            code: $model->code,
            name: $model->translations->pluck('name', 'locale')->toArray(),
        );
    }
}
