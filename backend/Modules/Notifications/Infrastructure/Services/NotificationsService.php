<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Services;

use Illuminate\Mail\Mailable;
use Modules\Notifications\Application\Jobs\SendNotificationJob;
use Modules\Notifications\Contracts\NotificationMailRegistryContract;
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
        private readonly NotificationMailRegistryContract $mail,
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

    public function queue(
        string $userId,
        string $channel,
        string $templateKey,
        array $payload,
        string $recipient,
        Mailable $mail
    ): string {
        // Recorded FIRST, then dispatched. The row exists at `queued` before
        // anything can act on it, so a job that runs immediately still finds
        // the log it is meant to update.
        $id = $this->record($userId, $channel, $templateKey, $payload);

        SendNotificationJob::dispatch($id, $recipient, $mail);

        return $id;
    }

    public function retry(string $notificationId): void
    {
        $log = $this->logs->findOrFail(new NotificationId($notificationId));

        // The guard is the aggregate's -- retry() refuses anything that is not
        // `failed`. Asking here as well would put the same rule in two places
        // and let them drift.
        $log->retry();

        // Rebuilt BEFORE the state changes. If no module can produce the
        // message, this throws and the log stays `failed` with its reason
        // intact, rather than being moved to `queued` for a delivery that was
        // never dispatched -- which would be a new version of the same lie.
        $envelope = $this->mail->rebuild($log->templateKey, $log->userId, $log->payload);

        $this->logs->save($log);

        SendNotificationJob::dispatch($notificationId, $envelope->recipient, $envelope->mail);
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
