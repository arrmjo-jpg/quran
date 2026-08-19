<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * An account was soft deleted — ADR-015 PE-7, ADR-016 D5.
 *
 * Removing someone's access is the act an auditor most wants a name against,
 * and deletion removes it more completely than deactivation does. Recorded for
 * the same reason UserDeactivated is, and discovered the same way: by reading
 * what the documents promise against what the code emits.
 */
final readonly class UserDeleted
{
    public const TYPE = 'user_deleted';

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
