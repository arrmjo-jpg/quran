<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\CreateStageUseCase;
use Modules\Competition\Application\UseCases\UpdateSeasonStageRulesUseCase;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Repositories\SeasonStageRuleRepositoryContract;
use Modules\Competition\Domain\ValueObjects\StageRuleAssignment;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'stage-rules');

function stageRulesTestAdmin(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'stage-rules-admin-'.Str::random(8).'@quran.test',
        'name' => 'Stage Rules Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function stageRulesTestSeason(): string
{
    return SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'rules-'.Str::random(8), 'year' => 2030,
        'registration_start' => '2030-01-01 00:00:00', 'registration_end' => '2030-01-15 00:00:00',
        'start_date' => '2030-01-16 00:00:00', 'end_date' => '2030-03-01 00:00:00',
        'status' => 'draft', 'is_active' => false,
    ])->id;
}

function stageRulesTestScoreSystem(string $code = 'out_of_100', int $maxScore = 100): JudgeScoreSystemModel
{
    $system = JudgeScoreSystemModel::query()->create([
        'id' => (string) Str::uuid(), 'code' => $code, 'max_score' => $maxScore,
        'display_order' => 1, 'is_active' => true,
    ]);

    JudgeScoreSystemTranslationModel::query()->create([
        'id' => (string) Str::uuid(), 'judge_score_system_id' => $system->id,
        'locale' => 'en', 'name' => 'Out of '.$maxScore,
    ]);

    return $system;
}

function stageRulesTestStage(string $seasonId, string $name, string $type = 'preliminary'): Stage
{
    return app(CreateStageUseCase::class)->execute(
        id: (string) Str::uuid(),
        seasonId: $seasonId,
        type: $type,
        startDateIso: '2030-02-01T00:00:00+00:00',
        endDateIso: '2030-02-10T00:00:00+00:00',
        translations: [
            'ar' => ['name' => $name.' AR', 'public_name' => $name.' علني'],
            'en' => ['name' => $name, 'public_name' => $name.' Public'],
            'es' => ['name' => $name.' ES', 'public_name' => $name.' Público'],
        ],
    );
}

test('the whole rule set is written at once and derives required_score', function (): void {
    $seasonId = stageRulesTestSeason();
    $first = stageRulesTestStage($seasonId, 'Preliminary');
    $final = stageRulesTestStage($seasonId, 'Final', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    $rules = app(UpdateSeasonStageRulesUseCase::class)->execute($seasonId, [
        new StageRuleAssignment($first->id, $scoreSystem->id, 80.0),
        // null percentage: a final stage may rank without eliminating.
        new StageRuleAssignment($final->id, $scoreSystem->id, null),
    ]);

    expect($rules)->toHaveCount(2);
    expect($rules[0]->stageNumber)->toBe(1);
    expect($rules[0]->qualificationPercentage)->toBe(80.0);
    expect($rules[0]->requiredScore)->toBe(80.0);
    expect($rules[1]->qualificationPercentage)->toBeNull();
    expect($rules[1]->requiredScore)->toBeNull();
});

test('a second call replaces the previous rule set rather than appending', function (): void {
    $seasonId = stageRulesTestSeason();
    $stage = stageRulesTestStage($seasonId, 'Only', 'final');
    $hundred = stageRulesTestScoreSystem('out_of_100', 100);
    $ten = stageRulesTestScoreSystem('out_of_10', 10);

    $useCase = app(UpdateSeasonStageRulesUseCase::class);
    $useCase->execute($seasonId, [new StageRuleAssignment($stage->id, $hundred->id, 50.0)]);
    $useCase->execute($seasonId, [new StageRuleAssignment($stage->id, $ten->id, 90.0)]);

    $rules = app(SeasonStageRuleRepositoryContract::class)->findResolvedRules($seasonId);

    expect($rules)->toHaveCount(1);
    expect($rules[0]->judgeScoreSystem->code)->toBe('out_of_10');
    expect($rules[0]->qualificationPercentage)->toBe(90.0);
    expect($rules[0]->requiredScore)->toBe(9.0);
});

test('a rule set that misses a stage is rejected', function (): void {
    $seasonId = stageRulesTestSeason();
    $first = stageRulesTestStage($seasonId, 'Preliminary');
    stageRulesTestStage($seasonId, 'Final', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    expect(fn () => app(UpdateSeasonStageRulesUseCase::class)->execute($seasonId, [
        new StageRuleAssignment($first->id, $scoreSystem->id, 80.0),
    ]))->toThrow(IncompleteSeasonRulesException::class);

    // The rejected call must not have half-applied: nothing stored.
    expect(app(SeasonStageRuleRepositoryContract::class)->findResolvedRules($seasonId))->toHaveCount(0);
});

test('a rule naming the same stage twice is rejected', function (): void {
    $seasonId = stageRulesTestSeason();
    $stage = stageRulesTestStage($seasonId, 'Only', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    expect(fn () => app(UpdateSeasonStageRulesUseCase::class)->execute($seasonId, [
        new StageRuleAssignment($stage->id, $scoreSystem->id, 80.0),
        new StageRuleAssignment($stage->id, $scoreSystem->id, 60.0),
    ]))->toThrow(IncompleteSeasonRulesException::class);
});

test('a rule naming a stage from another season is rejected', function (): void {
    $seasonId = stageRulesTestSeason();
    $otherSeasonId = stageRulesTestSeason();
    $mine = stageRulesTestStage($seasonId, 'Mine', 'final');
    $theirs = stageRulesTestStage($otherSeasonId, 'Theirs', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    expect(fn () => app(UpdateSeasonStageRulesUseCase::class)->execute($seasonId, [
        new StageRuleAssignment($mine->id, $scoreSystem->id, 80.0),
        new StageRuleAssignment($theirs->id, $scoreSystem->id, 80.0),
    ]))->toThrow(IncompleteSeasonRulesException::class);
});

test('a season with no stages at all is rejected', function (): void {
    $seasonId = stageRulesTestSeason();
    $scoreSystem = stageRulesTestScoreSystem();

    expect(fn () => app(UpdateSeasonStageRulesUseCase::class)->execute($seasonId, [
        new StageRuleAssignment((string) Str::uuid(), $scoreSystem->id, 80.0),
    ]))->toThrow(IncompleteSeasonRulesException::class);
});

test('StageRuleAssignment refuses a percentage outside 0..100', function (): void {
    expect(fn () => new StageRuleAssignment((string) Str::uuid(), (string) Str::uuid(), 120.0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new StageRuleAssignment((string) Str::uuid(), (string) Str::uuid(), -1.0))
        ->toThrow(InvalidArgumentException::class);
});

test('PATCH /admin/seasons/{id}/stage-rules writes the set end-to-end', function (): void {
    $admin = stageRulesTestAdmin();
    $seasonId = stageRulesTestSeason();
    $first = stageRulesTestStage($seasonId, 'Preliminary');
    $final = stageRulesTestStage($seasonId, 'Final', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $first->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80],
            ['stage_id' => $final->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => null],
        ],
    ])->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.stage_number', 1)
        ->assertJsonPath('data.0.required_score', 80)
        ->assertJsonPath('data.1.qualification_percentage', null);
});

test('PATCH stage-rules returns 422 INCOMPLETE_STAGE_RULES when a stage is uncovered', function (): void {
    $admin = stageRulesTestAdmin();
    $seasonId = stageRulesTestSeason();
    $first = stageRulesTestStage($seasonId, 'Preliminary');
    stageRulesTestStage($seasonId, 'Final', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $first->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'INCOMPLETE_STAGE_RULES');
});

test('PATCH stage-rules rejects an out-of-range percentage at validation', function (): void {
    $admin = stageRulesTestAdmin();
    $seasonId = stageRulesTestSeason();
    $stage = stageRulesTestStage($seasonId, 'Only', 'final');
    $scoreSystem = stageRulesTestScoreSystem();

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $stage->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 150],
        ],
    ])->assertStatus(422);
});
