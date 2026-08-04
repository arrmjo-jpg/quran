<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\UserId;
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
                'type' => $user->getType(),
                'password_hash' => $user->getPasswordHash()?->value,
                'preferred_locale' => (string) $user->getPreferredLocale(),
                'is_active' => $user->isActive(),
            ]
        );
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
            type: $model->type,
            passwordHash: null,
            preferredLocale: new Locale('ar'),
            isActive: (bool) $model->is_active,
            deletedAt: $model->deleted_at?->toIso8601String()
        );
    }
}
