<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Entities;

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
