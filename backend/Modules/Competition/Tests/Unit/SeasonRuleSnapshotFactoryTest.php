<?php

declare(strict_types=1);

use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Services\SeasonRuleSnapshotFactory;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;
use Modules\Competition\Domain\ValueObjects\ResolvedSeasonRules;
use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;

uses()->group('competition', 'unit', 'season-snapshot');

function makeResolvedRules(?array $stageRules = null): ResolvedSeasonRules
{
    $scale100 = new ResolvedLookupOption('score-100', 'scale_100', ['ar' => 'من 100', 'en' => 'Out of 100', 'es' => 'Sobre 100']);

    return new ResolvedSeasonRules(
        participationType: new ResolvedLookupOption('pt-1', 'mixed', ['ar' => 'مختلط', 'en' => 'Mixed', 'es' => 'Mixto']),
        tajweedLevel: new ResolvedLookupOption('tl-1', 'advanced', ['ar' => 'متقدم', 'en' => 'Advanced', 'es' => 'Avanzado']),
        eligibleCountries: [
            new ResolvedLookupOption('jo', 'JO', ['ar' => 'الأردن', 'en' => 'Jordan', 'es' => 'Jordania']),
        ],
        stageRules: $stageRules ?? [
            new ResolvedStageRule(
                stageId: 'stage-1',
                stageNumber: 1,
                type: 'preliminary',
                name: ['ar' => 'التصفيات', 'en' => 'Preliminaries', 'es' => 'Preliminares'],
                judgeScoreSystem: $scale100,
                judgeScoreSystemMaxScore: 100.0,
                qualificationPercentage: 70.0,
            ),
        ],
    );
}

test('snapshot includes every required top-level section', function (): void {
    $factory = new SeasonRuleSnapshotFactory;

    $snapshot = $factory->build(
        version: 1,
        frozenAtIso: '2027-03-01T09:00:00+00:00',
        minAge: 6,
        maxAge: 40,
        rules: makeResolvedRules(),
    );

    expect($snapshot)->toHaveKeys([
        'version', 'frozen_at', 'min_age', 'max_age',
        'participation_type', 'tajweed_level', 'eligible_countries', 'stages',
    ]);
    expect($snapshot['version'])->toBe(1);
    expect($snapshot['min_age'])->toBe(6);
    expect($snapshot['max_age'])->toBe(40);
    expect($snapshot['participation_type']['code'])->toBe('mixed');
    expect($snapshot['tajweed_level']['code'])->toBe('advanced');
    expect($snapshot['eligible_countries'])->toHaveCount(1);
    expect($snapshot['stages'])->toHaveCount(1);
});

test('required_score is derived from max_score times qualification_percentage', function (): void {
    $rule = new ResolvedStageRule(
        stageId: 'stage-1',
        stageNumber: 1,
        type: 'preliminary',
        name: ['ar' => 'أ', 'en' => 'a', 'es' => 'a'],
        judgeScoreSystem: new ResolvedLookupOption('s', 'scale_100', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        judgeScoreSystemMaxScore: 100.0,
        qualificationPercentage: 80.0,
    );

    expect($rule->requiredScore)->toBe(80.0);
});

test('required_score scales correctly on a non-100 scale', function (): void {
    $rule = new ResolvedStageRule(
        stageId: 'stage-1',
        stageNumber: 1,
        type: 'semi_final',
        name: ['ar' => 'أ', 'en' => 'a', 'es' => 'a'],
        judgeScoreSystem: new ResolvedLookupOption('s', 'scale_10', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        judgeScoreSystemMaxScore: 10.0,
        qualificationPercentage: 80.0,
    );

    // 10 * 0.80 = 8.0 — proves the fix for the hardcoded "80 points on a
    // 100-point scale" bug: the same 80% now means 8.0, not a broken 80.
    expect($rule->requiredScore)->toBe(8.0);
});

test('a null qualification_percentage means no threshold — required_score is also null', function (): void {
    $rule = new ResolvedStageRule(
        stageId: 'stage-final',
        stageNumber: 3,
        type: 'final',
        name: ['ar' => 'النهائي', 'en' => 'Final', 'es' => 'Final'],
        judgeScoreSystem: new ResolvedLookupOption('s', 'scale_100', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        judgeScoreSystemMaxScore: 100.0,
        qualificationPercentage: null,
    );

    expect($rule->qualificationPercentage)->toBeNull();
    expect($rule->requiredScore)->toBeNull();
});

test('qualification_percentage outside 0-100 is rejected', function (float $bad): void {
    new ResolvedStageRule(
        stageId: 'stage-1',
        stageNumber: 1,
        type: 'preliminary',
        name: ['ar' => 'أ', 'en' => 'a', 'es' => 'a'],
        judgeScoreSystem: new ResolvedLookupOption('s', 'scale_100', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        judgeScoreSystemMaxScore: 100.0,
        qualificationPercentage: $bad,
    );
})->with([-1.0, 101.0])->throws(InvalidArgumentException::class);

test('a season cannot open registration with zero eligible countries', function (): void {
    new ResolvedSeasonRules(
        participationType: new ResolvedLookupOption('pt', 'mixed', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        tajweedLevel: new ResolvedLookupOption('tl', 'advanced', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        eligibleCountries: [],
        stageRules: [
            new ResolvedStageRule(
                stageId: 's',
                stageNumber: 1,
                type: 'preliminary',
                name: ['ar' => 'أ', 'en' => 'a', 'es' => 'a'],
                judgeScoreSystem: new ResolvedLookupOption('s', 'scale_100', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
                judgeScoreSystemMaxScore: 100.0,
                qualificationPercentage: 80.0,
            ),
        ],
    );
})->throws(IncompleteSeasonRulesException::class);

test('a season cannot open registration with zero stages configured', function (): void {
    new ResolvedSeasonRules(
        participationType: new ResolvedLookupOption('pt', 'mixed', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        tajweedLevel: new ResolvedLookupOption('tl', 'advanced', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        eligibleCountries: [
            new ResolvedLookupOption('jo', 'JO', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
        ],
        stageRules: [],
    );
})->throws(IncompleteSeasonRulesException::class);
