<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * An account was restored from a soft delete — ADR-015 PE-7, ADR-016 D5.
 *
 * Recorded because restoring access is as much a privilege change as removing
 * it, and an audit trail showing only the removals reads as though nobody was
 * ever brought back. The account returns with its roles intact — they were
 * never detached — so this single line is the whole story of the change.
 */
final readonly class UserRestored
{
    public const TYPE = 'user_restored';

    public function __construct(
        public string $userId,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
