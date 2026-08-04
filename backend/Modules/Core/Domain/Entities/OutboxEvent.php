<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

final class OutboxEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $id,
        public readonly string $aggregateType,
        public readonly string $aggregateId,
        public readonly string $eventType,
        public readonly array $payload,
        private string $status = 'pending',
        private int $attempts = 0,
        private ?string $errorMessage = null,
        private ?string $processedAt = null,
    ) {}

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markProcessed(string $processedAtIso): void
    {
        $this->status = 'processed';
        $this->processedAt = $processedAtIso;
    }

    public function markFailed(string $error): void
    {
        $this->attempts++;
        $this->errorMessage = $error;
        if ($this->attempts >= 5) {
            $this->status = 'failed';
        }
    }
}
