<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

final readonly class ResultsPublished
{
    public const TYPE = 'results_published';

    public function __construct(
        public string $stageId,
        public string $publisherUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'stage_id' => $this->stageId,
            'publisher_user_id' => $this->publisherUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
