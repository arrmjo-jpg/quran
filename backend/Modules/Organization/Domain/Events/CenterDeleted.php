<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/** A centre was closed. Soft deleted — its history is not destroyed. */
final readonly class CenterDeleted
{
    public const TYPE = 'center_deleted';

    public function __construct(
        public string $centerId,
        public string $name,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'center_id' => $this->centerId,
            'name' => $this->name,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
