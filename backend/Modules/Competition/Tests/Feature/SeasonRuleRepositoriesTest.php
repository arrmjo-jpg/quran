<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\JudgeScoreSystemRepositoryContract;
use Modules\Competition\Domain\Repositories\ParticipationTypeRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonCountryRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonRuleVersionRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonStageRuleRepositoryContract;
use Modules\Competition\Domain\Repositories\TajweedLevelRepositoryContract;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonRuleVersionModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Competition\Infrastructure\Database\Models\StageTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelTranslationModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Countries\Infrastructure\Database\Models\CountryTranslationModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'repositories');

function makeSeason(): SeasonModel
{
    return SeasonModel::query()->create([
        'id' => (string) Str::uuid(),
        'slug' => 'season-'.Str::random(8),
        'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00',
        'registration_end' => '2026-02-01 00:00:00',
        'start_date' => '2026-03-01 00:00:00',
        'end_date' => '2026-04-01 00:00:00',
        'status' => 'draft',
        'is_active' => false,
    ]);
}

test('ParticipationTypeRepository resolves a lookup option with its translations', function (): void {
    $type = ParticipationTypeModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'male',
        'display_order' => 1,
        'is_active' => true,
    ]);

    ParticipationTypeTranslationModel::query()->create(['id' => (string) Str::uuid(), 'participation_type_id' => $type->id, 'locale' => 'ar', 'name' => 'ذكور']);
    ParticipationTypeTranslationModel::query()->create(['id' => (string) Str::uuid(), 'participation_type_id' => $type->id, 'locale' => 'en', 'name' => 'Male']);

    $resolved = app(ParticipationTypeRepositoryContract::class)->findOrFail($type->id);

    expect($resolved->id)->toBe($type->id);
    expect($resolved->code)->toBe('male');
    expect($resolved->name)->toBe(['ar' => 'ذكور', 'en' => 'Male']);
});

test('TajweedLevelRepository resolves a lookup option with its translations', function (): void {
    $level = TajweedLevelModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'advanced',
        'display_order' => 1,
        'is_active' => true,
    ]);

    TajweedLevelTranslationModel::query()->create(['id' => (string) Str::uuid(), 'tajweed_level_id' => $level->id, 'locale' => 'ar', 'name' => 'متقدم']);
    TajweedLevelTranslationModel::query()->create(['id' => (string) Str::uuid(), 'tajweed_level_id' => $level->id, 'locale' => 'en', 'name' => 'Advanced']);

    $resolved = app(TajweedLevelRepositoryContract::class)->findOrFail($level->id);

    expect($resolved->code)->toBe('advanced');
    expect($resolved->name)->toBe(['ar' => 'متقدم', 'en' => 'Advanced']);
});

test('JudgeScoreSystemRepository resolves max_score alongside the lookup option', function (): void {
    $system = JudgeScoreSystemModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'out_of_100',
        'max_score' => 100,
        'display_order' => 1,
        'is_active' => true,
    ]);

    JudgeScoreSystemTranslationModel::query()->create(['id' => (string) Str::uuid(), 'judge_score_system_id' => $system->id, 'locale' => 'ar', 'name' => 'من 100']);
    JudgeScoreSystemTranslationModel::query()->create(['id' => (string) Str::uuid(), 'judge_score_system_id' => $system->id, 'locale' => 'en', 'name' => 'Out of 100']);

    $resolved = app(JudgeScoreSystemRepositoryContract::class)->findOrFail($system->id);

    expect($resolved->code)->toBe('out_of_100');
    expect($resolved->maxScore)->toBe(100.0);
    expect($resolved->name)->toBe(['ar' => 'من 100', 'en' => 'Out of 100']);
    expect($resolved->toLookupOption()->code)->toBe('out_of_100');
});

test('SeasonCountryRepository resolves eligible countries via the Countries module contract', function (): void {
    $season = makeSeason();

    $jordan = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $jordan->id, 'locale' => 'ar', 'name' => 'الأردن']);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $jordan->id, 'locale' => 'en', 'name' => 'Jordan']);

    $egypt = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'EG', 'iso3_code' => 'EGY', 'phone_code' => '+20', 'is_active' => true]);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $egypt->id, 'locale' => 'ar', 'name' => 'مصر']);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $egypt->id, 'locale' => 'en', 'name' => 'Egypt']);

    DB::table('season_countries')->insert([
        ['season_id' => $season->id, 'country_id' => $jordan->id],
        ['season_id' => $season->id, 'country_id' => $egypt->id],
    ]);

    $resolved = app(SeasonCountryRepositoryContract::class)->findEligibleCountries($season->id);

    expect($resolved)->toHaveCount(2);
    $codes = array_map(fn ($c) => $c->code, $resolved);
    expect($codes)->toContain('JO', 'EG');
});

test('SeasonCountryRepository returns an empty array when no countries are eligible', function (): void {
    $season = makeSeason();

    expect(app(SeasonCountryRepositoryContract::class)->findEligibleCountries($season->id))->toBe([]);
});

test('SeasonStageRuleRepository resolves stage rules ordered by stage_number with required_score derived correctly', function (): void {
    $season = makeSeason();

    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_10', 'max_score' => 10, 'display_order' => 1, 'is_active' => true]);
    JudgeScoreSystemTranslationModel::query()->create(['id' => (string) Str::uuid(), 'judge_score_system_id' => $scoreSystem->id, 'locale' => 'ar', 'name' => 'من 10']);
    JudgeScoreSystemTranslationModel::query()->create(['id' => (string) Str::uuid(), 'judge_score_system_id' => $scoreSystem->id, 'locale' => 'en', 'name' => 'Out of 10']);

    $final = StageModel::query()->create(['id' => (string) Str::uuid(), 'season_id' => $season->id, 'stage_number' => 2, 'type' => 'final', 'start_date' => '2026-05-01 00:00:00', 'end_date' => '2026-05-02 00:00:00', 'status' => 'pending']);
    StageTranslationModel::query()->create(['id' => (string) Str::uuid(), 'stage_id' => $final->id, 'locale' => 'en', 'name' => 'Final']);

    $prelim = StageModel::query()->create(['id' => (string) Str::uuid(), 'season_id' => $season->id, 'stage_number' => 1, 'type' => 'preliminary', 'start_date' => '2026-04-01 00:00:00', 'end_date' => '2026-04-02 00:00:00', 'status' => 'pending']);
    StageTranslationModel::query()->create(['id' => (string) Str::uuid(), 'stage_id' => $prelim->id, 'locale' => 'en', 'name' => 'Preliminary']);

    DB::table('season_stage_rules')->insert([
        ['id' => (string) Str::uuid(), 'season_id' => $season->id, 'stage_id' => $final->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => null, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'season_id' => $season->id, 'stage_id' => $prelim->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 70, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $rules = app(SeasonStageRuleRepositoryContract::class)->findResolvedRules($season->id);

    expect($rules)->toHaveCount(2);
    expect($rules[0]->stageNumber)->toBe(1);
    expect($rules[0]->type)->toBe('preliminary');
    expect($rules[0]->name)->toBe(['en' => 'Preliminary']);
    expect($rules[0]->qualificationPercentage)->toBe(70.0);
    expect($rules[0]->requiredScore)->toBe(7.0);

    expect($rules[1]->stageNumber)->toBe(2);
    expect($rules[1]->qualificationPercentage)->toBeNull();
    expect($rules[1]->requiredScore)->toBeNull();
});

test('SeasonRuleVersionRepository persists an immutable snapshot row', function (): void {
    $season = makeSeason();

    app(SeasonRuleVersionRepositoryContract::class)->save(
        seasonId: $season->id,
        version: 1,
        snapshotJson: ['min_age' => 10, 'max_age' => 18],
        createdByUserId: null,
    );

    $row = SeasonRuleVersionModel::query()->where('season_id', $season->id)->where('version', 1)->first();

    expect($row)->not->toBeNull();
    expect($row->snapshot_json)->toBe(['min_age' => 10, 'max_age' => 18]);
    expect($row->created_by_user_id)->toBeNull();
});

test('SeasonRuleVersionRepository rejects a duplicate (season_id, version) pair', function (): void {
    $season = makeSeason();
    $repository = app(SeasonRuleVersionRepositoryContract::class);

    $repository->save($season->id, 1, ['min_age' => 10], null);

    expect(fn () => $repository->save($season->id, 1, ['min_age' => 12], null))
        ->toThrow(QueryException::class);
});
