<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

/**
 * Recorded exactly once, at draft -> registration_open. Carries the
 * fully-resolved rule snapshot; persisting it into season_rule_versions
 * atomically alongside the season's own status/frozen_at update is the
 * repository/application layer's responsibility — this event only
 * carries the data, it does not write anything itself.
 */
final readonly class SeasonFrozen
{
    public const TYPE = 'season_frozen';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public string $seasonId,
        public int $version,
        public array $snapshot,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'version' => $this->version,
            'snapshot' => $this->snapshot,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
