<?php

declare(strict_types=1);

use Modules\Competition\Domain\Entities\EvaluationTemplate;
use Modules\Competition\Domain\Services\CompetitionRuleEngine;

uses()->group('competition', 'unit', 'rules');

test('competition rule engine breaks ties deterministically: Tajweed -> Memorization -> Voice', function (): void {
    $engine = new CompetitionRuleEngine;

    $tiedApplications = [
        [
            'application_id' => 'app-1',
            'total_score' => 95.0,
            'tajweed_score' => 28.0,
            'memorization_score' => 29.0,
            'voice_score' => 19.0,
        ],
        [
            'application_id' => 'app-2',
            'total_score' => 95.0,
            'tajweed_score' => 30.0, // Higher Tajweed score
            'memorization_score' => 27.0,
            'voice_score' => 18.0,
        ],
        [
            'application_id' => 'app-3',
            'total_score' => 95.0,
            'tajweed_score' => 28.0,
            'memorization_score' => 30.0, // Same Tajweed as app-1, higher Memorization
            'voice_score' => 19.0,
        ],
    ];

    $ranked = $engine->breakTies($tiedApplications);

    // App-2 must be rank 1 (Highest Tajweed)
    expect($ranked[0]['application_id'])->toBe('app-2');
    expect($ranked[0]['rank'])->toBe(1);

    // App-3 must be rank 2 (Equal Tajweed to App-1, but higher Memorization)
    expect($ranked[1]['application_id'])->toBe('app-3');
    expect($ranked[1]['rank'])->toBe(2);

    // App-1 must be rank 3
    expect($ranked[2]['application_id'])->toBe('app-1');
    expect($ranked[2]['rank'])->toBe(3);
});

test('evaluation template aggregate enforces 100% total weight invariant', function (): void {
    new EvaluationTemplate(
        id: fake()->uuid(),
        seasonId: fake()->uuid(),
        name: 'Invalid Template',
        criteria: [
            ['id' => 'c1', 'code' => 'tajweed', 'name' => 'Tajweed', 'max_score' => 30.0, 'weight_percent' => 50.0],
            ['id' => 'c2', 'code' => 'memorization', 'name' => 'Hifz', 'max_score' => 30.0, 'weight_percent' => 30.0],
            // Total weight sum is 80% (invalid)
        ]
    );
})->throws(InvalidArgumentException::class);
