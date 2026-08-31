<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Services;

use Illuminate\Mail\Mailable;
use Modules\Notifications\Application\Jobs\SendNotificationJob;
use Modules\Notifications\Contracts\NotificationMailRegistryContract;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Contracts\NotificationTypeCatalogContract;
use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\Exceptions\MandatoryNotificationException;
use Modules\Notifications\Domain\Exceptions\NotificationDeclinedException;
use Modules\Notifications\Domain\Exceptions\UnknownNotificationTypeException;
use Modules\Notifications\Domain\Repositories\NotificationLogRepositoryContract;
use Modules\Notifications\Domain\Repositories\NotificationPreferenceRepositoryContract;
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
        private readonly NotificationTypeCatalogContract $types,
        private readonly NotificationPreferenceRepositoryContract $preferences,
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
    ): ?string {
        // PREFERENCES ARE CONSULTED BEFORE ANYTHING IS WRITTEN -- ADR-020 D4.
        //
        // Before the log and before the job, because a declined notification
        // is not an attempt that failed: it is an attempt that was never made.
        // Recording it at `queued` and never dispatching would be the exact
        // shape of the defect this epic removed, and D6 has no status for
        // "suppressed" -- deliberately, since inventing one means every reader
        // learns a word the domain does not use.
        //
        // The consequence, stated rather than discovered: a declined
        // notification leaves NO row, so the log cannot distinguish "declined"
        // from "never triggered". The preference is the record.
        //
        // MANDATORY TYPES SKIP THE CHECK ENTIRELY (D9). The invitation is not
        // merely enabled by default -- it is never asked about, so no row in
        // notification_preferences can affect it whatever it says.
        if (! $this->maySend($userId, $templateKey)) {
            return null;
        }

        // Recorded FIRST, then dispatched. The row exists at `queued` before
        // anything can act on it, so a job that runs immediately still finds
        // the log it is meant to update.
        $id = $this->record($userId, $channel, $templateKey, $payload);

        SendNotificationJob::dispatch($id, $recipient, $mail);

        return $id;
    }

    public function preferencesFor(string $userId): array
    {
        $declined = $this->preferences->declinedTypesFor($userId);

        // Built from the CATALOGUE, not from the stored rows. A screen driven
        // by the table would show an account nothing at all until it had
        // already changed something, and would keep showing a type long after
        // the module that sent it stopped declaring it.
        $out = [];

        foreach ($this->types->declinable() as $type) {
            $out[$type] = ! in_array($type, $declined, true);
        }

        return $out;
    }

    public function setPreference(string $userId, string $type, bool $enabled): void
    {
        if (! $this->types->isKnown($type)) {
            throw UnknownNotificationTypeException::for($type);
        }

        // Refused rather than silently ignored (D9). Accepting the request and
        // continuing to send would leave a screen reading "off" beside a
        // mailbox receiving mail.
        if ($this->types->isMandatory($type)) {
            throw MandatoryNotificationException::for($type);
        }

        $this->preferences->set($userId, $type, $enabled);
    }

    /**
     * ADR-020 D9. Mandatory first, so the invitation never reaches the
     * preference lookup at all -- the rule is code, not the absence of a row.
     *
     * An UNKNOWN type is allowed through. A module that sends without
     * declaring itself has a gap in its own registration, and the failure mode
     * of silently dropping its mail is far worse than the failure mode of
     * sending something nobody can decline yet.
     */
    private function maySend(string $userId, string $templateKey): bool
    {
        if ($this->types->isMandatory($templateKey)) {
            return true;
        }

        return ! in_array($templateKey, $this->preferences->declinedTypesFor($userId), true);
    }

    public function retry(string $notificationId): void
    {
        $log = $this->logs->findOrFail(new NotificationId($notificationId));

        // The guard is the aggregate's -- retry() refuses anything that is not
        // `failed`. Asking here as well would put the same rule in two places
        // and let them drift.
        $log->retry();

        // The account's decision outranks the operator's (D4). Asked here as
        // well as in queue() because this is the other path that pushes a job,
        // and a rule enforced on one of two paths is a rule with a hole in it.
        if (! $this->maySend($log->userId, $log->templateKey)) {
            throw NotificationDeclinedException::for($log->templateKey);
        }

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
