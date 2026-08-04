<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Services;

use Modules\Competition\Contracts\RuleEngineContract;

/**
 * CompetitionRuleEngine
 *
 * Deterministic Rule Engine implementation per ADR-010.
 * Tie Breaking Order:
 *   1. Final Aggregate Score (total_score)
 *   2. Highest Tajweed Score
 *   3. Highest Memorization/Hifz Score
 *   4. Highest Voice/Sawt Score
 *   5. Committee Manual Decision (manual_tie_break_flag)
 *
 * DOB/Age is EXCLUDED from tie-breaking unless mandated by official competition bylaws.
 */
final class CompetitionRuleEngine implements RuleEngineContract
{
    public function evaluateQualification(float $finalScore, array $context = []): string
    {
        $minThreshold = (float) ($context['min_score_threshold'] ?? 80.0);

        return $finalScore >= $minThreshold ? 'qualified' : 'eliminated';
    }

    public function breakTies(array $tiedApplications): array
    {
        // Sort using the deterministic 4-stage comparator
        usort($tiedApplications, function (array $a, array $b): int {
            // 1. Total score
            if ($a['total_score'] !== $b['total_score']) {
                return $b['total_score'] <=> $a['total_score'];
            }
            // 2. Tajweed score
            if ($a['tajweed_score'] !== $b['tajweed_score']) {
                return $b['tajweed_score'] <=> $a['tajweed_score'];
            }
            // 3. Memorization score
            if ($a['memorization_score'] !== $b['memorization_score']) {
                return $b['memorization_score'] <=> $a['memorization_score'];
            }

            // 4. Voice score
            return $b['voice_score'] <=> $a['voice_score'];
        });

        $ranked = [];
        $currentRank = 1;

        foreach ($tiedApplications as $index => $app) {
            $manualRequired = false;

            // If exact tie remains through stage 4, mark manual_tie_break_required
            if ($index > 0) {
                $prev = $tiedApplications[$index - 1];
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
