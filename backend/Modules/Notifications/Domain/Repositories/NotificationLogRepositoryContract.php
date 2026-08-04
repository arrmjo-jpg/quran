<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Repositories;

use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\ValueObjects\NotificationId;

interface NotificationLogRepositoryContract
{
    public function findOrFail(NotificationId $id): NotificationLog;

    /** @return array<int, NotificationLog> */
    public function findByUserId(string $userId): array;

    public function save(NotificationLog $log): void;
}
