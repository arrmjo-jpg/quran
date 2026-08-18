<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\PasswordHash;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

final class UserRepository implements UserRepositoryContract
{
    public function findOrFail(UserId $id): User
    {
        $model = UserModel::query()->findOrFail($id->value);

        return $this->toDomain($model);
    }

    public function find(UserId $id): ?User
    {
        $model = UserModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where('email', $email->value)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(User $user): void
    {
        UserModel::query()->updateOrCreate(
            ['id' => $user->id->value],
            [
                'email' => $user->getEmail()->value,
                'name' => $user->getName(),
                'type' => $user->getType()->value,
                'password_hash' => $user->getPasswordHash()?->value,
                'preferred_locale' => (string) $user->getPreferredLocale(),
                'is_active' => $user->isActive(),
            ]
        );

        $this->syncRoles($user);
    }

    public function countUsersWithRole(RoleId $roleId): int
    {
        // Counts only accounts that can still act. A soft-deleted or
        // deactivated holder must not be what keeps PE-5 satisfied, or
        // the guarantee it exists for — that someone can always
        // administer the platform — would be satisfied by someone who
        // cannot log in.
        return DB::table('role_user')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->where('role_user.role_id', $roleId->value)
            ->where('users.is_active', true)
            ->whereNull('users.deleted_at')
            ->count();
    }

    /**
     * Replaces the user's role grants with exactly what the aggregate
     * holds. Unknown role ids are rejected rather than silently dropped,
     * for the same reason RoleRepository rejects unknown permissions: a
     * grant the pivot never recorded is a capability the user believes
     * they have and the system does not.
     */
    private function syncRoles(User $user): void
    {
        $roleIds = $user->getRoleIdValues();

        DB::table('role_user')->where('user_id', $user->id->value)->delete();

        if ($roleIds === []) {
            return;
        }

        $existing = DB::table('roles')->whereIn('id', $roleIds)->pluck('id')->all();
        $unknown = array_diff($roleIds, $existing);

        if ($unknown !== []) {
            throw new \RuntimeException('Cannot assign roles that do not exist: '.implode(', ', $unknown));
        }

        DB::table('role_user')->insert(array_map(
            static fn (string $roleId): array => [
                'role_id' => $roleId,
                'user_id' => $user->id->value,
            ],
            $roleIds
        ));
    }

    public function delete(UserId $id): void
    {
        UserModel::query()->where('id', $id->value)->delete();
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: new UserId($model->id),
            email: new Email($model->email),
            name: $model->name,
            type: new UserType($model->type),
            passwordHash: $model->password_hash !== null ? new PasswordHash($model->password_hash) : null,
            preferredLocale: new Locale($model->preferred_locale),
            isActive: (bool) $model->is_active,
            deletedAt: $model->deleted_at?->toIso8601String(),
            roleIds: array_map(
                static fn (string $id): RoleId => new RoleId($id),
                DB::table('role_user')->where('user_id', $model->id)->pluck('role_id')->all()
            )
        );
    }
}
