<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * An account was put back into service — ADR-015 §4.5.
 *
 * Recorded for the same reason as its opposite, and it is the half more
 * easily forgotten: restoring access is as much a privilege change as
 * removing it, and an audit trail that shows only the removals reads as
 * though nobody was ever let back in.
 */
final readonly class UserReactivated
{
    public const TYPE = 'user_reactivated';

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
