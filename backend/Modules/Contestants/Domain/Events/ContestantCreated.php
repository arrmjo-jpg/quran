<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Events;

final readonly class ContestantCreated
{
    public const TYPE = 'contestant_created';

    public function __construct(
        public string $contestantId,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'contestant_id' => $this->contestantId,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
