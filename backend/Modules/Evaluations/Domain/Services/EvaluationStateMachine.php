<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Services;

use InvalidArgumentException;

final class EvaluationStateMachine
{
    private const TRANSITIONS = [
        'pending' => ['in_progress'],
        'in_progress' => ['submitted'],
        'submitted' => ['locked'],
        'locked' => ['returned_for_revision', 'approved'],
        'returned_for_revision' => ['submitted'],
        'approved' => [],
    ];

    public function transition(string $currentStatus, string $targetStatus): string
    {
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid evaluation transition from '{$currentStatus}' to '{$targetStatus}'. Allowed: ".implode(', ', $allowed));
        }

        return $targetStatus;
    }
}
