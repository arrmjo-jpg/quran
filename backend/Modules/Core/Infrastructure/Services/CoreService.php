<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Services;

use Modules\Core\Contracts\CoreServiceContract;
use Modules\Core\Contracts\ResolvedUserDTO;
use Modules\Core\Domain\ValueObjects\UserStatus;
use Modules\Core\Infrastructure\Database\Models\UserModel;

final class CoreService implements CoreServiceContract
{
    public function findResolvedByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return UserModel::withTrashed()
            ->whereIn('id', $ids)
            // Named columns, not all of them. The four D18 admits plus the
            // two `status` is also derived from — so a column added to `users`
            // later cannot arrive here by accident.
            //
            // THE PASSWORD HASH IS NEVER SELECTED. `status` needs to know only
            // whether one exists, which the database can answer as a boolean,
            // so the hash stays in MySQL instead of being loaded into a read
            // path that has nothing to do with authentication. It never
            // reached the DTO either way — ResolvedUserDTO has four fields and
            // no room for a fifth — but a value that is never fetched cannot
            // surface in a var_dump, an exception trace or a log context that
            // happens to capture the model.
            ->selectRaw('id, name, type, deleted_at, is_active, (password_hash IS NOT NULL) AS has_password')
            ->get()
            ->map(fn (UserModel $user): ResolvedUserDTO => new ResolvedUserDTO(
                id: (string) $user->id,
                name: $user->name,
                status: (string) UserStatus::derive(
                    isDeleted: $user->deleted_at !== null,
                    // Cast because the column is computed: MySQL and SQLite
                    // both answer 1/0 rather than a PHP boolean.
                    hasPassword: (bool) $user->getAttribute('has_password'),
                    isActive: (bool) $user->is_active,
                ),
                type: $user->type,
            ))
            ->all();
    }
}
