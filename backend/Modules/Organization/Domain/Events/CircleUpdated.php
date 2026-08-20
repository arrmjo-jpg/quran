<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/**
 * A circle was renamed, or its supervisor changed.
 *
 * Carries both facts separately rather than a single "changed" flag: who
 * supervises a circle is an access-control-adjacent fact under D9, and a trail
 * that cannot distinguish a rename from a change of supervisor answers neither
 * question later.
 */
final readonly class CircleUpdated
{
    public const TYPE = 'circle_updated';

    public function __construct(
        public string $circleId,
        public string $previousName,
        public string $name,
        public ?string $previousSupervisorUserId,
        public ?string $supervisorUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'circle_id' => $this->circleId,
            'previous_name' => $this->previousName,
            'name' => $this->name,
            'previous_supervisor_user_id' => $this->previousSupervisorUserId,
            'supervisor_user_id' => $this->supervisorUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
