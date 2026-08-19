<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/** A centre was opened. */
final readonly class CenterCreated
{
    public const TYPE = 'center_created';

    public function __construct(
        public string $centerId,
        public string $name,
        public string $countryId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'center_id' => $this->centerId,
            'name' => $this->name,
            'country_id' => $this->countryId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
