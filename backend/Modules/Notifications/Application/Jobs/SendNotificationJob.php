<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Throwable;

/**
 * Delivery, off the request — ADR-020 D3.
 *
 * THE FIRST JOB IN THIS REPOSITORY. The queue was provisioned and idle:
 * `jobs`, `failed_jobs` and `job_batches` exist, `QUEUE_CONNECTION=redis`, and
 * Horizon supervises `default`. Nothing used any of it.
 *
 * Two reasons for moving delivery here, and only the second is about
 * notifications. `Mail::send()` is synchronous, so creating an administrator
 * blocked on SMTP — a slow or unreachable mail server delayed or failed a
 * request that had already done its real work. And **a retry cannot exist
 * without this**: retrying is re-dispatching, so D5 has nothing to re-dispatch
 * until there is a job.
 *
 * IT TAKES A Mailable, NOT A TEMPLATE NAME. ADR-020 keeps templating out of
 * scope, so there is no registry mapping `template_key` to a class — the
 * caller builds the message it already knows how to build, and this carries
 * it. `Mailable` is a framework type, so the module still imports no other
 * module's concrete class (ADR-002); Core hands over an InvitationMail and
 * this never learns the name.
 *
 * CONSEQUENCE WORTH KNOWING: a queued Mailable is serialized into the job
 * payload, so the invitation's accept token now rests in Redis until the job
 * runs. That is what Laravel's own `Mail::queue()` does, and the token is
 * single-use and short-lived (IssueInvitationUseCase::TTL_DAYS) — but it is a
 * place the token did not previously sit, and it is recorded rather than
 * discovered later.
 */
final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts, then `failed()` — ADR-020 D10.
     *
     * Mail failures are usually environmental and usually brief: a refused
     * connection, a momentary DNS failure. Retrying costs nothing and fixes
     * most of them. What it must not do is retry forever, because a
     * permanently bad address would then never reach the `failed` state a
     * human can act on.
     */
    public int $tries = 3;

    public function __construct(
        public readonly string $notificationId,
        public readonly string $recipient,
        public readonly Mailable $mail,
    ) {}

    public function handle(NotificationsServiceContract $notifications): void
    {
        Mail::to($this->recipient)->send($this->mail);

        // Only after the send returns. Marking sent before would record a
        // delivery that may still throw, which is the one lie this whole epic
        // exists to remove.
        $notifications->markSent($this->notificationId);
    }

    /**
     * After the last attempt — ADR-020 D10.
     *
     * The log ends at `failed` carrying the reason, and stays there: nothing
     * is sent to announce it, because announcing a broken notification channel
     * through that same channel is the message least likely to arrive. A human
     * finds it on the screen and retries it by hand.
     *
     * `markFailed` is resolved from the container rather than injected —
     * Laravel calls failed() outside handle()'s dependency resolution.
     */
    public function failed(Throwable $exception): void
    {
        app(NotificationsServiceContract::class)
            ->markFailed($this->notificationId, $exception->getMessage());
    }
}
