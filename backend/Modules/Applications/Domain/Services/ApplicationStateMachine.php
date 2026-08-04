<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Services;

use InvalidArgumentException;

/**
 * ApplicationStateMachine
 *
 * Dedicated State Machine for application status transitions per architectural directives.
 */
final class ApplicationStateMachine
{
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review', 'needs_data', 'video_reupload_requested'],
        'needs_data' => ['submitted'],
        'video_reupload_requested' => ['submitted'],
        'under_review' => ['ready_for_judging', 'disqualified'],
        'ready_for_judging' => ['under_judging'],
        'under_judging' => ['qualified', 'waitlisted', 'eliminated'],
        'qualified' => ['published'],
        'waitlisted' => ['published'],
        'eliminated' => ['published'],
        'disqualified' => ['published'],
        'published' => [],
    ];

    public function transition(string $currentStatus, string $targetStatus): string
    {
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid application status transition from '{$currentStatus}' to '{$targetStatus}'. Allowed: ".implode(', ', $allowed));
        }

        return $targetStatus;
    }

    public function canTransition(string $currentStatus, string $targetStatus): bool
    {
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        return in_array($targetStatus, $allowed, true);
    }
}
