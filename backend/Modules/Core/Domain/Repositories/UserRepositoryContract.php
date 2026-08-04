<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * UserRepositoryContract
 *
 * Domain persistence interface for User aggregate root.
 */
interface UserRepositoryContract
{
    public function findOrFail(UserId $id): User;

    public function find(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function save(User $user): void;

    public function delete(UserId $id): void;
}
