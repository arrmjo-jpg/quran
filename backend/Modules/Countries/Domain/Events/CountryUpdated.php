<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\Events;

final readonly class CountryUpdated
{
    public const TYPE = 'country_updated';

    public function __construct(
        public string $countryId,
        public string $iso2,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'country_id' => $this->countryId,
            'iso2' => $this->iso2,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
