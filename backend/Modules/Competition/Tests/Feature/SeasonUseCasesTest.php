<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\ArchiveSeasonUseCase;
use Modules\Competition\Application\UseCases\CancelSeasonUseCase;
use Modules\Competition\Application\UseCases\OpenSeasonRegistrationUseCase;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Events\SeasonArchived;
use Modules\Competition\Domain\Events\SeasonCancelled;
use Modules\Competition\Domain\Events\SeasonRegistrationOpened;
use Modules\Competition\Domain\Events\SeasonRulesFrozen;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
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
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Countries\Infrastructure\Database\Models\CountryTranslationModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'season-use-cases');

/**
 * Builds and persists a draft season with every piece of configuration
 * freeze() requires already in place: age range, participation type,
 * tajweed level, ar/en/es translations, one eligible country, and one
 * stage rule. Returns the season id.
 */
function seedFullyConfiguredSeason(): string
{
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    ParticipationTypeTranslationModel::query()->create(['id' => (string) Str::uuid(), 'participation_type_id' => $participationType->id, 'locale' => 'ar', 'name' => 'مختلط']);
    ParticipationTypeTranslationModel::query()->create(['id' => (string) Str::uuid(), 'participation_type_id' => $participationType->id, 'locale' => 'en', 'name' => 'Mixed']);

    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    TajweedLevelTranslationModel::query()->create(['id' => (string) Str::uuid(), 'tajweed_level_id' => $tajweedLevel->id, 'locale' => 'ar', 'name' => 'متقدم']);
    TajweedLevelTranslationModel::query()->create(['id' => (string) Str::uuid(), 'tajweed_level_id' => $tajweedLevel->id, 'locale' => 'en', 'name' => 'Advanced']);

    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);
    JudgeScoreSystemTranslationModel::query()->create(['id' => (string) Str::uuid(), 'judge_score_system_id' => $scoreSystem->id, 'locale' => 'ar', 'name' => 'من 100']);
    JudgeScoreSystemTranslationModel::query()->create(['id' => (string) Str::uuid(), 'judge_score_system_id' => $scoreSystem->id, 'locale' => 'en', 'name' => 'Out of 100']);

    $country = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $country->id, 'locale' => 'ar', 'name' => 'الأردن']);
    CountryTranslationModel::query()->create(['id' => (string) Str::uuid(), 'country_id' => $country->id, 'locale' => 'en', 'name' => 'Jordan']);

    $season = Season::create(
        id: (string) Str::uuid(),
        slug: 'season-'.Str::random(8),
        year: 2027,
        regStartIso: '2027-01-01T00:00:00+00:00',
        regEndIso: '2027-01-15T00:00:00+00:00',
        startDateIso: '2027-01-16T00:00:00+00:00',
        endDateIso: '2027-03-01T00:00:00+00:00',
    );
    $season->setAgeRange(10, 18);
    $season->setParticipationType($participationType->id);
    $season->setTajweedLevel($tajweedLevel->id);
    $season->setTranslation(new SeasonTranslation('ar', 'موسم 2027', 'موسم القرآن 2027'));
    $season->setTranslation(new SeasonTranslation('en', '2027 Season', 'Quran Season 2027'));
    $season->setTranslation(new SeasonTranslation('es', 'Temporada 2027', 'Temporada del Corán 2027'));

    $repository = app(SeasonRepositoryContract::class);
    $repository->save($season);

    $stage = StageModel::query()->create(['id' => (string) Str::uuid(), 'season_id' => $season->id, 'stage_number' => 1, 'type' => 'final', 'start_date' => '2027-02-01 00:00:00', 'end_date' => '2027-02-05 00:00:00', 'status' => 'pending']);
    StageTranslationModel::query()->create(['id' => (string) Str::uuid(), 'stage_id' => $stage->id, 'locale' => 'en', 'name' => 'Final']);

    DB::table('season_countries')->insert(['season_id' => $season->id, 'country_id' => $country->id]);
    DB::table('season_stage_rules')->insert([
        'id' => (string) Str::uuid(),
        'season_id' => $season->id,
        'stage_id' => $stage->id,
        'judge_score_system_id' => $scoreSystem->id,
        'qualification_percentage' => 80,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $season->id;
}

/**
 * archived_by_user_id / created_by_user_id are real FKs to users.id — a
 * fake string like 'admin-1' only ever "worked" against sqlite, which
 * doesn't enforce FK constraints in this test config. Seed a real user
 * so these use cases round-trip correctly on MySQL too.
 */
function seedTestUser(): string
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'season-test-'.Str::random(8).'@quran.test',
        'name' => 'Season Test Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ])->id;
}

test('OpenSeasonRegistrationUseCase freezes a fully configured season, persists version 1, and dispatches events', function (): void {
    Event::fake();
    $seasonId = seedFullyConfiguredSeason();
    $adminId = seedTestUser();

    $season = app(OpenSeasonRegistrationUseCase::class)->execute($seasonId, $adminId);

    expect($season->getStatus())->toBe('registration_open');
    expect($season->isFrozen())->toBeTrue();
    expect($season->isActive())->toBeTrue();

    $model = SeasonModel::query()->findOrFail($seasonId);
    expect($model->status)->toBe('registration_open');
    expect($model->frozen_at)->not->toBeNull();
    expect((bool) $model->is_active)->toBeTrue();

    $version = SeasonRuleVersionModel::query()->where('season_id', $seasonId)->where('version', 1)->first();
    expect($version)->not->toBeNull();
    expect($version->created_by_user_id)->toBe($adminId);
    expect($version->snapshot_json['min_age'])->toBe(10);
    expect($version->snapshot_json['max_age'])->toBe(18);
    expect($version->snapshot_json['participation_type']['code'])->toBe('mixed');
    expect($version->snapshot_json['eligible_countries'])->toHaveCount(1);
    expect($version->snapshot_json['stages'])->toHaveCount(1);
    expect((float) $version->snapshot_json['stages'][0]['required_score'])->toBe(80.0);

    Event::assertDispatched(SeasonRegistrationOpened::class);
    Event::assertDispatched(SeasonRulesFrozen::class);
});

test('a season reloaded from the repository after freezing still rejects configuration edits', function (): void {
    $seasonId = seedFullyConfiguredSeason();
    app(OpenSeasonRegistrationUseCase::class)->execute($seasonId);

    // Simulate a later, unrelated request: fetch the season fresh from the
    // repository (not the same in-memory instance the use case mutated)
    // and confirm frozen_at round-tripped correctly enough that the guard
    // still fires — proving the lock survives a real persist+reload, not
    // just the in-memory object graph within one request.
    $reloaded = app(SeasonRepositoryContract::class)->findOrFail($seasonId);

    expect($reloaded->isFrozen())->toBeTrue();
    expect(fn () => $reloaded->setAgeRange(5, 10))->toThrow(SeasonAlreadyFrozenException::class);
    expect(fn () => $reloaded->setParticipationType('some-other-id'))->toThrow(SeasonAlreadyFrozenException::class);
    expect(fn () => $reloaded->setTranslation(new SeasonTranslation('ar', 'x', 'y')))->toThrow(SeasonAlreadyFrozenException::class);
});

test('OpenSeasonRegistrationUseCase deactivates the previously active season atomically', function (): void {
    $previouslyActive = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'old-'.Str::random(6), 'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00', 'registration_end' => '2026-01-15 00:00:00',
        'start_date' => '2026-01-16 00:00:00', 'end_date' => '2026-03-01 00:00:00',
        'status' => 'registration_open', 'is_active' => true,
    ]);

    $seasonId = seedFullyConfiguredSeason();
    app(OpenSeasonRegistrationUseCase::class)->execute($seasonId);

    expect((bool) $previouslyActive->refresh()->is_active)->toBeFalse();
    expect((bool) SeasonModel::query()->findOrFail($seasonId)->is_active)->toBeTrue();
});

test('OpenSeasonRegistrationUseCase rolls back entirely when translations are incomplete', function (): void {
    $seasonId = seedFullyConfiguredSeason();

    // seedFullyConfiguredSeason() sets ar/en/es translations — strip
    // the season back down to zero translations so freeze()'s own
    // assertTranslationsComplete() is what rejects it, not the earlier
    // ResolvedSeasonRules "zero countries/stages" guard.
    DB::table('season_translations')->where('season_id', $seasonId)->delete();

    expect(fn () => app(OpenSeasonRegistrationUseCase::class)->execute($seasonId))
        ->toThrow(IncompleteSeasonRulesException::class);

    $reloaded = SeasonModel::query()->findOrFail($seasonId);
    expect($reloaded->status)->toBe('draft');
    expect($reloaded->frozen_at)->toBeNull();
    expect(SeasonRuleVersionModel::query()->where('season_id', $seasonId)->exists())->toBeFalse();
});

test('OpenSeasonRegistrationUseCase raises a domain exception before touching the repository when rules are unconfigured', function (): void {
    $season = Season::create(
        id: (string) Str::uuid(), slug: 'season-'.Str::random(8), year: 2027,
        regStartIso: '2027-01-01T00:00:00+00:00', regEndIso: '2027-01-15T00:00:00+00:00',
        startDateIso: '2027-01-16T00:00:00+00:00', endDateIso: '2027-03-01T00:00:00+00:00',
    );

    $repository = app(SeasonRepositoryContract::class);
    $repository->save($season);

    expect(fn () => app(OpenSeasonRegistrationUseCase::class)->execute($season->id))
        ->toThrow(IncompleteSeasonRulesException::class);
});

test('ArchiveSeasonUseCase archives a completed season and dispatches SeasonArchived', function (): void {
    Event::fake();

    $season = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'season-'.Str::random(8), 'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00', 'registration_end' => '2026-01-15 00:00:00',
        'start_date' => '2026-01-16 00:00:00', 'end_date' => '2026-03-01 00:00:00',
        'status' => 'completed', 'is_active' => false,
    ]);
    $adminId = seedTestUser();

    $result = app(ArchiveSeasonUseCase::class)->execute($season->id, 'season concluded', $adminId);

    expect($result->getStatus())->toBe('archived');

    $reloaded = SeasonModel::query()->findOrFail($season->id);
    expect($reloaded->status)->toBe('archived');
    expect($reloaded->archive_reason)->toBe('season concluded');
    expect($reloaded->archived_by_user_id)->toBe($adminId);

    Event::assertDispatched(SeasonArchived::class);
});

test('ArchiveSeasonUseCase rolls back when the season is not completed', function (): void {
    $season = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'season-'.Str::random(8), 'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00', 'registration_end' => '2026-01-15 00:00:00',
        'start_date' => '2026-01-16 00:00:00', 'end_date' => '2026-03-01 00:00:00',
        'status' => 'draft', 'is_active' => false,
    ]);

    expect(fn () => app(ArchiveSeasonUseCase::class)->execute($season->id, 'too early', null))
        ->toThrow(InvalidSeasonTransitionException::class);

    expect(SeasonModel::query()->findOrFail($season->id)->status)->toBe('draft');
});

test('CancelSeasonUseCase cancels a draft season and dispatches SeasonCancelled', function (): void {
    Event::fake();

    $season = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'season-'.Str::random(8), 'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00', 'registration_end' => '2026-01-15 00:00:00',
        'start_date' => '2026-01-16 00:00:00', 'end_date' => '2026-03-01 00:00:00',
        'status' => 'draft', 'is_active' => false,
    ]);
    $adminId = seedTestUser();

    $result = app(CancelSeasonUseCase::class)->execute($season->id, 'insufficient interest', $adminId);

    expect($result->getStatus())->toBe('archived');
    expect(SeasonModel::query()->findOrFail($season->id)->archive_reason)->toBe('insufficient interest');

    Event::assertDispatched(SeasonCancelled::class);
});

test('CancelSeasonUseCase rolls back when the season already left draft', function (): void {
    $season = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'season-'.Str::random(8), 'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00', 'registration_end' => '2026-01-15 00:00:00',
        'start_date' => '2026-01-16 00:00:00', 'end_date' => '2026-03-01 00:00:00',
        'status' => 'registration_open', 'is_active' => true,
    ]);

    expect(fn () => app(CancelSeasonUseCase::class)->execute($season->id, 'too late', null))
        ->toThrow(InvalidSeasonTransitionException::class);

    expect(SeasonModel::query()->findOrFail($season->id)->status)->toBe('registration_open');
});
