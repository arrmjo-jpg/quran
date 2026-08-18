<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\OpenSeasonRegistrationUseCase;
use Modules\Competition\Application\UseCases\UpdateSeasonRulesUseCase;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Repositories\SeasonCountryRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Countries\Infrastructure\Database\Models\CountryTranslationModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'season-rules');

function updateSeasonRulesTestAdmin(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'season-rules-admin-'.Str::random(8).'@quran.test',
        'name' => 'Season Rules Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function updateSeasonRulesTestDraftSeason(): string
{
    $season = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'rules-'.Str::random(8), 'year' => 2028,
        'registration_start' => '2028-01-01 00:00:00', 'registration_end' => '2028-01-15 00:00:00',
        'start_date' => '2028-01-16 00:00:00', 'end_date' => '2028-03-01 00:00:00',
        'status' => 'draft', 'is_active' => false,
    ]);

    return $season->id;
}

test('UpdateSeasonRulesUseCase sets age range, participation type, tajweed level, and eligible countries', function (): void {
    $seasonId = updateSeasonRulesTestDraftSeason();
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    $country = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $country->id, 'locale' => 'ar', 'name' => 'الأردن']);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $country->id, 'locale' => 'en', 'name' => 'Jordan']);

    $season = app(UpdateSeasonRulesUseCase::class)->execute(
        seasonId: $seasonId,
        minAge: 10,
        maxAge: 18,
        participationTypeId: $participationType->id,
        tajweedLevelId: $tajweedLevel->id,
        countryIds: [$country->id],
    );

    expect($season->getMinAge())->toBe(10);
    expect($season->getMaxAge())->toBe(18);
    expect($season->getParticipationTypeId())->toBe($participationType->id);
    expect($season->getTajweedLevelId())->toBe($tajweedLevel->id);

    $reloaded = app(SeasonRepositoryContract::class)->findOrFail($seasonId);
    expect($reloaded->getMinAge())->toBe(10);
    expect($reloaded->getParticipationTypeId())->toBe($participationType->id);

    $eligible = app(SeasonCountryRepositoryContract::class)->findEligibleCountries($seasonId);
    expect($eligible)->toHaveCount(1);
    expect($eligible[0]->id)->toBe($country->id);
});

test('UpdateSeasonRulesUseCase replaces the eligible-country set on a second call, not appends', function (): void {
    $seasonId = updateSeasonRulesTestDraftSeason();
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    $countryA = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);
    $countryB = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'EG', 'iso3_code' => 'EGY', 'phone_code' => '+20', 'is_active' => true]);

    $useCase = app(UpdateSeasonRulesUseCase::class);
    $useCase->execute($seasonId, 10, 18, $participationType->id, $tajweedLevel->id, [$countryA->id]);
    $useCase->execute($seasonId, 10, 18, $participationType->id, $tajweedLevel->id, [$countryB->id]);

    $eligible = app(SeasonCountryRepositoryContract::class)->findEligibleCountries($seasonId);
    expect($eligible)->toHaveCount(1);
    expect($eligible[0]->id)->toBe($countryB->id);
});

test('UpdateSeasonRulesUseCase rejects changes once the season is frozen', function (): void {
    $seasonId = updateSeasonRulesTestDraftSeason();
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    $country = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);

    app(UpdateSeasonRulesUseCase::class)->execute($seasonId, 10, 18, $participationType->id, $tajweedLevel->id, [$country->id]);

    $repository = app(SeasonRepositoryContract::class);
    $season = $repository->findOrFail($seasonId);
    $season->setTranslation(new SeasonTranslation('ar', 'موسم', 'موسم القرآن'));
    $season->setTranslation(new SeasonTranslation('en', 'Season', 'Quran Season'));
    $season->setTranslation(new SeasonTranslation('es', 'Temporada', 'Temporada del Corán'));
    $repository->save($season);

    // freeze() also requires at least one stage rule — out of this Use
    // Case's scope (no Stage management API exists yet), so seed one
    // directly just to reach a frozen season for this assertion.
    $stage = StageModel::query()->create(['id' => (string) Str::uuid(), 'season_id' => $seasonId, 'stage_number' => 1, 'type' => 'final', 'start_date' => now(), 'end_date' => now()->addDay(), 'status' => 'pending']);
    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);
    DB::table('season_stage_rules')->insert(['id' => (string) Str::uuid(), 'season_id' => $seasonId, 'stage_id' => $stage->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80, 'created_at' => now(), 'updated_at' => now()]);

    app(OpenSeasonRegistrationUseCase::class)->execute($seasonId);

    expect(fn () => app(UpdateSeasonRulesUseCase::class)->execute($seasonId, 12, 20, $participationType->id, $tajweedLevel->id, [$country->id]))
        ->toThrow(SeasonAlreadyFrozenException::class);
});

test('PATCH /admin/seasons/{id}/rules sets rules end-to-end and rejects an incomplete payload', function (): void {
    $admin = updateSeasonRulesTestAdmin();
    $seasonId = updateSeasonRulesTestDraftSeason();
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    $country = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10,
        'max_age' => 18,
        'participation_type_id' => $participationType->id,
        'tajweed_level_id' => $tajweedLevel->id,
        'country_ids' => [$country->id],
    ])->assertStatus(200)
        ->assertJsonPath('data.min_age', 10)
        ->assertJsonPath('data.max_age', 18)
        ->assertJsonPath('data.participation_type_id', $participationType->id);

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10,
        'max_age' => 18,
        'participation_type_id' => $participationType->id,
        // tajweed_level_id and country_ids missing
    ])->assertStatus(422);
});
