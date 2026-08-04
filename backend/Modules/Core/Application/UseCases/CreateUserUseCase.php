<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Commands\CreateUserCommand;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;

final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepositoryContract $repository,
    ) {}

    public function execute(CreateUserCommand $command): User
    {
        return DB::transaction(function () use ($command): User {
            // 1. Instantiate Aggregate Root
            $user = User::create(
                id: $command->id,
                email: $command->email,
                name: $command->name,
                type: $command->type,
                passwordHash: $command->passwordHash,
                preferredLocale: $command->preferredLocale
            );

            // 2. Persist via Repository
            $this->repository->save($user);

            return $user;
        });
    }
}
