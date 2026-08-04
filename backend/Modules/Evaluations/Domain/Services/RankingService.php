<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Services;

use Modules\Competition\Domain\Strategies\QualificationStrategy;
use Modules\Competition\Domain\Strategies\TieBreakerStrategy;

/**
 * RankingService
 *
 * Pure Domain Service responsible for aggregating judge score arrays,
 * executing TieBreakerStrategy & QualificationStrategy, and returning StageResult snapshots.
 *
 * Zero DB / Repository reads inside this class per architectural directives.
 */
final readonly class RankingService
{
    public function __construct(
        private TieBreakerStrategy $tieBreakerStrategy = new TieBreakerStrategy,
        private QualificationStrategy $qualificationStrategy = new QualificationStrategy,
    ) {}

    /**
     * @param  array<int, array{application_id: string, total_score: float, tajweed_score: float, memorization_score: float, voice_score: float}>  $applicationScores
     * @return array<int, array{application_id: string, final_score: float, rank: int, qualification_status: string, manual_tie_break_flag: bool}>
     */
    public function calculateStageRankings(array $applicationScores, float $minQualificationThreshold = 80.0): array
    {
        // 1. Execute TieBreakerStrategy
        $ranked = $this->tieBreakerStrategy->execute($applicationScores);

        $results = [];

        foreach ($ranked as $item) {
            $appId = $item['application_id'];
            $original = array_values(array_filter($applicationScores, fn ($a) => $a['application_id'] === $appId))[0];

            // 2. Execute QualificationStrategy
            $status = $this->qualificationStrategy->execute($original['total_score'], $minQualificationThreshold);

            $results[] = [
                'application_id' => $appId,
                'final_score' => $original['total_score'],
                'rank' => $item['rank'],
                'qualification_status' => $status,
                'manual_tie_break_flag' => $item['manual_tie_break_required'],
            ];
        }

        return $results;
    }
}
