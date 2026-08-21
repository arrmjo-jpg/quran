<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Events;

/**
 * Carries the names of the fields that actually changed, not the record.
 *
 * The same reasoning as RolePermissionsChanged and UserRolesChanged: "what
 * changed" is the question an auditor asks, and a snapshot of the new state
 * makes them diff it against a history they may not have. The values
 * themselves are deliberately absent — a contestant's fields include a
 * national identity document, and an event payload is the wrong place for it.
 */
final readonly class ContestantUpdated
{
    public const TYPE = 'contestant_updated';

    /** @param array<int, string> $changed */
    public function __construct(
        public string $contestantId,
        public array $changed,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'contestant_id' => $this->contestantId,
            'changed' => $this->changed,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
