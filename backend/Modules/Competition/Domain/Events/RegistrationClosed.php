<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

final readonly class RegistrationClosed
{
    public const TYPE = 'registration_closed';

    public function __construct(
        public string $seasonId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
