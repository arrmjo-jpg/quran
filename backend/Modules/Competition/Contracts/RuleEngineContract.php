<?php

declare(strict_types=1);

namespace Modules\Competition\Contracts;

/**
 * RuleEngineContract
 *
 * Rule Engine Interface per ADR-010.
 * Decouples Use Cases from concrete competition rule evaluation.
 */
interface RuleEngineContract
{
    /**
     * Evaluate qualification status for a given aggregate score and stage parameters.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluateQualification(float $finalScore, array $context = []): string;

    /**
     * Break ties deterministically: Tajweed -> Memorization -> Voice -> Committee Manual.
     *
     * @param  array<int, array{application_id: string, total_score: float, tajweed_score: float, memorization_score: float, voice_score: float}>  $tiedApplications
     * @return array<int, array{application_id: string, rank: int, manual_tie_break_required: bool}>
     */
    public function breakTies(array $tiedApplications): array;
}
