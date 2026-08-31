<?php

declare(strict_types=1);

namespace Modules\Notifications\Contracts;

use Illuminate\Mail\Mailable;

/**
 * Public boundary interface for the Notifications module.
 * Per ADR-002: Other modules MUST only depend on this interface,
 * never on the module's internals.
 *
 * IT WAS EMPTY UNTIL ADR-020 D7 — two comment lines and no methods, and the
 * provider did not bind it either, so resolving it would have failed. That is
 * why nothing consumed it: there was nothing to consume. Meanwhile the one
 * place that actually sends mail, Core's CreateAdminUserUseCase, called
 * `Mail::to()->send()` directly and wrote no log, so `notification_logs` held
 * zero rows.
 *
 * THREE METHODS, NOT ONE. A single `send()` would have to own delivery, and
 * delivery is moving onto the queue in the next story (D3). Splitting the
 * record from its outcome lets the caller keep sending today and lets the job
 * report the outcome tomorrow, without this interface changing shape again.
 */
interface NotificationsServiceContract
{
    /**
     * Record that a notification is about to be delivered.
     *
     * Returns the log id, which the caller keeps in order to report the
     * outcome. The row starts at `queued` — the aggregate's own starting
     * state, not a fourth word invented at the boundary (D6).
     *
     * @param  array<string, mixed>  $payload  What the template needs. It is stored
     *                                         as given: callers decide what is safe
     *                                         to keep, the same rule domain events
     *                                         follow.
     */
    public function record(string $userId, string $channel, string $templateKey, array $payload): string;

    /**
     * Record a notification AND queue its delivery — ADR-020 D3.
     *
     * The path callers should use. `record()` remains for anything that must
     * report its own outcome, but delivery belongs off the request: a
     * synchronous send makes the caller wait on SMTP, and a retry has nothing
     * to re-dispatch unless a job exists.
     *
     * Takes a Mailable rather than a template name because templating is out
     * of ADR-020's scope — the caller builds the message it already knows how
     * to build. `Mailable` is a framework type, so this boundary still names
     * no other module's concrete class.
     *
     * PREFERENCES ARE CONSULTED HERE, before the log row and before the job
     * (D4). A declined notification produces NOTHING -- no row, no dispatch --
     * because a notification that was never attempted is not an attempt that
     * failed, and D6 has no status for "suppressed". Mandatory types skip the
     * check entirely, so no stored preference can affect them (D9).
     *
     * `record()` does NOT consult preferences: it records, it does not decide.
     * This is the enforcing path, and the one every sender should use.
     *
     * @param  array<string, mixed>  $payload
     * @return string|null The log id, already at `queued` -- or null when the
     *                     account has declined this notification type.
     */
    public function queue(
        string $userId,
        string $channel,
        string $templateKey,
        array $payload,
        string $recipient,
        Mailable $mail
    ): ?string;

    /**
     * Send a failed notification again -- ADR-020 D5.
     *
     * Manual and deliberate: D10 ends an exhausted notification at `failed`
     * and announces it to nobody, so a human finds it on the screen and asks
     * for this. It returns the log to `queued` and dispatches the send job.
     *
     * IT RESPECTS PREFERENCES TOO (D4). An operator retrying a notification
     * the account declined after it failed would be overriding the account's
     * decision by hand, so this refuses. A retry pushes a job, and every path
     * that pushes a job asks the same question first.
     *
     * THE MESSAGE IS REBUILT, NOT REPLAYED. Nothing keeps the original
     * Mailable, and for the platform's only real template nothing could: an
     * invitation's accept token exists for one moment and is never stored. The
     * module that owns the template rebuilds it through
     * NotificationMailRegistryContract, and refuses if it cannot.
     *
     * @throws \\Modules\\Notifications\\Domain\\Exceptions\\NotificationNotRetryableException when the log is not `failed`
     * @throws \\Modules\\Notifications\\Domain\\Exceptions\\NotificationDeclinedException when the account has since declined this type
     * @throws \\Modules\\Notifications\\Domain\\Exceptions\\TemplateNotRetryableException when no module can rebuild the message
     */
    public function retry(string $notificationId): void;

    /**
     * What this account has decided about optional notifications -- ADR-020 D4.
     *
     * Keyed by notification type, and it lists only what MAY be declined:
     * mandatory types are absent rather than present-and-locked, because a
     * control that cannot be operated is a question the reader answers twice.
     *
     * Absence of a stored row means enabled, so a fresh account gets every
     * declinable type set to `true` without a single row existing.
     *
     * @return array<string, bool>
     */
    public function preferencesFor(string $userId): array;

    /**
     * Turn one notification type on or off for one account -- ADR-020 D4, D9.
     *
     * @throws \\Modules\\Notifications\\Domain\\Exceptions\\MandatoryNotificationException when the type may never be declined -- today the invitation
     * @throws \\Modules\\Notifications\\Domain\\Exceptions\\UnknownNotificationTypeException when no module has declared the type
     */
    public function setPreference(string $userId, string $type, bool $enabled): void;

    /** Delivery succeeded. Records when, which `save()` used to discard. */
    public function markSent(string $notificationId): void;

    /**
     * Delivery failed, with the reason.
     *
     * The reason is kept because D10 ends an exhausted notification at
     * `failed` and leaves it there: without the reason, an operator learns
     * that something broke and nothing about what.
     */
    public function markFailed(string $notificationId, string $error): void;
}
