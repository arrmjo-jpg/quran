<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * An account was taken out of service — ADR-015 §4.5.
 *
 * Deactivation is the single most audit-sensitive act in this epic: it
 * removes someone's access to the platform entirely, and PE-7 requires
 * every RBAC change to be recorded explicitly. It went unrecorded until
 * the document was read back against the code, because the account's
 * roles do not change here and nothing else was watching.
 *
 * Carries no permission or role delta on purpose. Nothing was granted or
 * revoked — what changed is whether the account may act on what it still
 * holds, which is the distinction between resolution and authorization
 * that the whole design rests on.
 */
final readonly class UserDeactivated
{
    public const TYPE = 'user_deactivated';

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
