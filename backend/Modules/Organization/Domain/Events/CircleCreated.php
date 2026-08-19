<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/** A circle was opened at a centre. */
final readonly class CircleCreated
{
    public const TYPE = 'circle_created';

    public function __construct(
        public string $circleId,
        public string $name,
        public string $centerId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'circle_id' => $this->circleId,
            'name' => $this->name,
            'center_id' => $this->centerId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
