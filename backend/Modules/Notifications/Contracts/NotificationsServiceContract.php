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
     * @param  array<string, mixed>  $payload
     * @return string The log id, already at `queued`.
     */
    public function queue(
        string $userId,
        string $channel,
        string $templateKey,
        array $payload,
        string $recipient,
        Mailable $mail
    ): string;

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
