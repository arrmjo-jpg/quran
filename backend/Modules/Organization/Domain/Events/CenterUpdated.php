<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/**
 * A centre was renamed or moved.
 *
 * Carries the previous name as well as the current one, for the reason
 * ADR-015 §4.5 made role events carry deltas: "renamed to X" forces a reader
 * to reconstruct what it was from earlier entries, which only works if every
 * earlier change was also recorded.
 *
 * This matters more here than elsewhere. Applications freeze a centre's name
 * at submission (ADR-016 Q4), so when someone asks why a 2024 application says
 * one name and the centre now says another, this event is the answer.
 */
final readonly class CenterUpdated
{
    public const TYPE = 'center_updated';

    public function __construct(
        public string $centerId,
        public string $previousName,
        public string $name,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'center_id' => $this->centerId,
            'previous_name' => $this->previousName,
            'name' => $this->name,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
