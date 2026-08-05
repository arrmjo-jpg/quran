<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Services;

use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\ValueObjects\SeasonStatus;

/**
 * SeasonStateMachine
 *
 * Strictly linear, no branching, no backward transitions — a season has
 * exactly one forward path from draft to archived via completed, with
 * a single early exit (draft -> archived, i.e. cancellation before
 * registration ever opens; there is no cancellation path after that).
 */
final class SeasonStateMachine
{
    private const TRANSITIONS = [
        SeasonStatus::DRAFT => [SeasonStatus::REGISTRATION_OPEN, SeasonStatus::ARCHIVED],
        SeasonStatus::REGISTRATION_OPEN => [SeasonStatus::REGISTRATION_CLOSED],
        SeasonStatus::REGISTRATION_CLOSED => [SeasonStatus::COMPETITION_RUNNING],
        SeasonStatus::COMPETITION_RUNNING => [SeasonStatus::JUDGING],
        SeasonStatus::JUDGING => [SeasonStatus::COMPLETED],
        SeasonStatus::COMPLETED => [SeasonStatus::ARCHIVED],
        SeasonStatus::ARCHIVED => [],
    ];

    public function transition(string $from, string $to): string
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidSeasonTransitionException($from, $to, self::TRANSITIONS[$from] ?? []);
        }

        return $to;
    }

    public function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /** @return array<int, string> */
    public function allowedFrom(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }
}
