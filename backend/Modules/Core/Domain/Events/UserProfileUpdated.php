<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * An account's display details changed.
 *
 * Carries the previous name as well as the new one. "Renamed to X" without the
 * old value forces an auditor to reconstruct it from earlier entries, which
 * only works if every earlier change was also recorded — the same reasoning
 * that made RolePermissionsChanged carry deltas rather than snapshots
 * (ADR-015 §4.5).
 */
final readonly class UserProfileUpdated
{
    public const TYPE = 'user_profile_updated';

    public function __construct(
        public string $userId,
        public string $previousName,
        public string $name,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'previous_name' => $this->previousName,
            'name' => $this->name,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
