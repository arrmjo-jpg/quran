<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Services;

use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\Repositories\NotificationLogRepositoryContract;
use Modules\Notifications\Domain\ValueObjects\NotificationChannel;
use Modules\Notifications\Domain\ValueObjects\NotificationId;

/**
 * The module's boundary implementation — ADR-020 D2, D7.
 *
 * Everything here goes through the aggregate and the repository. Nothing
 * touches NotificationLogModel, which is what let the admin controller write
 * a `retrying` state no invariant allowed.
 */
final class NotificationsService implements NotificationsServiceContract
{
    public function __construct(
        private readonly NotificationLogRepositoryContract $logs,
    ) {}

    public function record(string $userId, string $channel, string $templateKey, array $payload): string
    {
        // NotificationChannel validates against email/sms/push, so an
        // unsupported channel is refused here rather than stored and
        // discovered later by whatever tries to deliver it.
        $log = NotificationLog::create(
            id: NotificationId::generate(),
            userId: $userId,
            channel: new NotificationChannel($channel),
            templateKey: $templateKey,
            payload: $payload,
        );

        $this->logs->save($log);

        return $log->id->value;
    }

    public function markSent(string $notificationId): void
    {
        $log = $this->logs->findOrFail(new NotificationId($notificationId));
        $log->markSent(now()->toIso8601String());

        $this->logs->save($log);
    }

    public function markFailed(string $notificationId, string $error): void
    {
        $log = $this->logs->findOrFail(new NotificationId($notificationId));
        $log->markFailed($error);

        $this->logs->save($log);
    }
}
