<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

final readonly class SeasonCreated
{
    public const TYPE = 'season_created';

    public function __construct(
        public string $seasonId,
        public string $slug,
        public int $year,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'slug' => $this->slug,
            'year' => $this->year,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
