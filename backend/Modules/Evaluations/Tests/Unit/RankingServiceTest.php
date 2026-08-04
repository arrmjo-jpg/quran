<?php

declare(strict_types=1);

use Modules\Evaluations\Domain\Services\RankingService;

uses()->group('evaluations', 'unit', 'ranking');

test('ranking service calculates stage rankings and applies tie breaking rules', function (): void {
    $service = new RankingService;

    $scores = [
        [
            'application_id' => 'app-101',
            'total_score' => 92.5,
            'tajweed_score' => 29.0,
            'memorization_score' => 28.5,
            'voice_score' => 18.5,
        ],
        [
            'application_id' => 'app-102',
            'total_score' => 92.5,
            'tajweed_score' => 30.0, // Higher Tajweed
            'memorization_score' => 28.0,
            'voice_score' => 18.0,
        ],
    ];

    $results = $service->calculateStageRankings($scores, 80.0);

    expect($results)->toHaveCount(2);

    // App-102 must be ranked #1 due to higher Tajweed
    expect($results[0]['application_id'])->toBe('app-102');
    expect($results[0]['rank'])->toBe(1);
    expect($results[0]['qualification_status'])->toBe('qualified');

    // App-101 must be ranked #2
    expect($results[1]['application_id'])->toBe('app-101');
    expect($results[1]['rank'])->toBe(2);
});
