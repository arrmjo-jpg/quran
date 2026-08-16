<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Events;

/**
 * Recorded when a season's registration is reopened after being closed.
 *
 * The season row keeps no trace of this: reopening only flips `status`
 * back to registration_open, and the next close overwrites it again. This
 * event is therefore the only record that the window was ever extended,
 * which is why the reason is mandatory rather than optional — unlike
 * archiving, nothing else captures why it happened.
 */
final readonly class SeasonRegistrationReopened
{
    public const TYPE = 'season_registration_reopened';

    public function __construct(
        public string $seasonId,
        public string $reason,
        public string $occurredAt,
        public ?string $byUserId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'season_id' => $this->seasonId,
            'reason' => $this->reason,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
