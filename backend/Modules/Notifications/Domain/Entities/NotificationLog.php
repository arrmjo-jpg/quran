<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Entities;

use Modules\Notifications\Domain\Exceptions\NotificationNotRetryableException;
use Modules\Notifications\Domain\ValueObjects\NotificationChannel;
use Modules\Notifications\Domain\ValueObjects\NotificationId;

/**
 * NotificationLog Aggregate Root
 *
 * Governs asynchronous multi-channel notification dispatch logs and status tracking.
 */
final class NotificationLog
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly NotificationId $id,
        public readonly string $userId,
        public readonly NotificationChannel $channel,
        public readonly string $templateKey,
        public readonly array $payload,
        private string $status = 'queued', // 'queued', 'sent', 'failed'
        private ?string $sentAtIso = null,
        private ?string $errorMessage = null,
    ) {}

    public static function create(
        NotificationId $id,
        string $userId,
        NotificationChannel $channel,
        string $templateKey,
        array $payload = []
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            channel: $channel,
            templateKey: $templateKey,
            payload: $payload,
            status: 'queued'
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * ADR-020 D2/D10. These existed on the aggregate and had no readers, so
     * the repository could not persist them: `save()` wrote status and
     * dropped both, meaning markSent()'s timestamp and markFailed()'s reason
     * never reached the database. D10 requires the reason to survive, since
     * `failed` without it tells an operator that something broke and nothing
     * about what.
     */
    public function getSentAtIso(): ?string
    {
        return $this->sentAtIso;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function markSent(string $sentAtIso): void
    {
        $this->status = 'sent';
        $this->sentAtIso = $sentAtIso;
    }

    public function markFailed(string $error): void
    {
        $this->status = 'failed';
        $this->errorMessage = $error;
    }

    /**
     * Back to the start of the line — ADR-020 D5, D6.
     *
     * A retry is not a fourth state. It returns the log to `queued`, which is
     * what a retry IS: a delivery waiting to be attempted. The controller used
     * to write `retrying` by reaching past this aggregate into Eloquent, which
     * is the only reason it could produce a word no invariant here permits.
     *
     * The guard lives on the aggregate rather than in the HTTP handler so that
     * every caller inherits it. Re-sending something already `sent` would
     * deliver it twice; re-sending something still `queued` would race the job
     * about to run.
     *
     * The error is cleared because it described the attempt now superseded,
     * and the timestamp with it: a row carrying both `queued` and a sent_at
     * would claim a delivery that has not happened yet.
     */
    public function retry(): void
    {
        if ($this->status !== 'failed') {
            throw NotificationNotRetryableException::because($this->status);
        }

        $this->status = 'queued';
        $this->errorMessage = null;
        $this->sentAtIso = null;
    }

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
