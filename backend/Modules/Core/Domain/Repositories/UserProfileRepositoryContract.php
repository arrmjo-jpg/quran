<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\Entities\UserProfile;
use Modules\Core\Domain\ValueObjects\UserId;

interface UserProfileRepositoryContract
{
    /**
     * The account's profile, or null if it has never had one written.
     *
     * The row is created on first write rather than alongside the account, so
     * "no profile" is an ordinary state and not a repair case. ADR-016 records
     * `user_profiles` as additive: no existing account data moves into it.
     */
    public function findByUserId(UserId $userId): ?UserProfile;

    public function save(UserProfile $profile): void;
}
