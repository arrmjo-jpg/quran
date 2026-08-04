<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Strategies;

/**
 * TieBreakerStrategy
 *
 * Dedicated strategy encapsulating 4-stage deterministic tie-breaking logic per ADR-010:
 *   1. Total Aggregate Score
 *   2. Highest Tajweed Sub-score
 *   3. Highest Memorization Sub-score
 *   4. Highest Voice Sub-score
 *   5. Flag Manual Committee Decision
 */
final class TieBreakerStrategy
{
    /**
     * @param  array<int, array{application_id: string, total_score: float, tajweed_score: float, memorization_score: float, voice_score: float}>  $scores
     * @return array<int, array{application_id: string, rank: int, manual_tie_break_required: bool}>
     */
    public function execute(array $scores): array
    {
        usort($scores, function (array $a, array $b): int {
            if ($a['total_score'] !== $b['total_score']) {
                return $b['total_score'] <=> $a['total_score'];
            }
            if ($a['tajweed_score'] !== $b['tajweed_score']) {
                return $b['tajweed_score'] <=> $a['tajweed_score'];
            }
            if ($a['memorization_score'] !== $b['memorization_score']) {
                return $b['memorization_score'] <=> $a['memorization_score'];
            }

            return $b['voice_score'] <=> $a['voice_score'];
        });

        $ranked = [];
        $currentRank = 1;

        foreach ($scores as $index => $app) {
            $manualRequired = false;

            if ($index > 0) {
                $prev = $scores[$index - 1];
                if (
                    $app['total_score'] === $prev['total_score'] &&
                    $app['tajweed_score'] === $prev['tajweed_score'] &&
                    $app['memorization_score'] === $prev['memorization_score'] &&
                    $app['voice_score'] === $prev['voice_score']
                ) {
                    $manualRequired = true;
                }
            }

            $ranked[] = [
                'application_id' => $app['application_id'],
                'rank' => $currentRank++,
                'manual_tie_break_required' => $manualRequired,
            ];
        }

        return $ranked;
    }
}
