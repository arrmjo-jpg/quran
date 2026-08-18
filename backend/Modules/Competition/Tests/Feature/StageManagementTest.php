<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\CreateStageUseCase;
use Modules\Competition\Application\UseCases\DeleteStageUseCase;
use Modules\Competition\Application\UseCases\OpenSeasonRegistrationUseCase;
use Modules\Competition\Application\UseCases\ReorderStagesUseCase;
use Modules\Competition\Application\UseCases\UpdateStageUseCase;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Events\StageCreated;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Exceptions\StageInUseException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\StageTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'stages');

function stageTestAdmin(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'stage-admin-'.Str::random(8).'@quran.test',
        'name' => 'Stage Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function stageTestDraftSeason(): string
{
    return SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'stages-'.Str::random(8), 'year' => 2029,
        'registration_start' => '2029-01-01 00:00:00', 'registration_end' => '2029-01-15 00:00:00',
        'start_date' => '2029-01-16 00:00:00', 'end_date' => '2029-03-01 00:00:00',
        'status' => 'draft', 'is_active' => false,
    ])->id;
}

/** @return array<string, array{name: string, public_name: string, description: string}> */
function stageTestTranslations(string $name = 'Stage'): array
{
    return [
        'ar' => ['name' => $name.' AR', 'public_name' => $name.' علني', 'description' => $name.' وصف'],
        'en' => ['name' => $name, 'public_name' => $name.' Public', 'description' => $name.' description'],
        'es' => ['name' => $name.' ES', 'public_name' => $name.' Público', 'description' => $name.' descripción'],
    ];
}

function stageTestCreate(string $seasonId, string $name = 'Stage', string $type = 'preliminary'): Stage
{
    return app(CreateStageUseCase::class)->execute(
        id: (string) Str::uuid(),
        seasonId: $seasonId,
        type: $type,
        startDateIso: '2029-02-01T00:00:00+00:00',
        endDateIso: '2029-02-10T00:00:00+00:00',
        translations: stageTestTranslations($name),
    );
}

/** Drives a draft season all the way to frozen, so the frozen-season guards can be exercised. */
function stageTestFreezeSeason(string $seasonId): void
{
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    $country = CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true]);
    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);

    $seasons = app(SeasonRepositoryContract::class);
    $season = $seasons->findOrFail($seasonId);
    $season->setAgeRange(10, 18);
    $season->setParticipationType($participationType->id);
    $season->setTajweedLevel($tajweedLevel->id);
    $season->setTranslation(new SeasonTranslation('ar', 'موسم', 'موسم القرآن'));
    $season->setTranslation(new SeasonTranslation('en', 'Season', 'Quran Season'));
    $season->setTranslation(new SeasonTranslation('es', 'Temporada', 'Temporada del Corán'));
    $seasons->save($season);

    // Pure pivot — composite PK, no surrogate id column.
    DB::table('season_countries')->insert(['season_id' => $seasonId, 'country_id' => $country->id]);

    $stage = stageTestCreate($seasonId, 'Freeze Stage', 'final');
    DB::table('season_stage_rules')->insert([
        'id' => (string) Str::uuid(), 'season_id' => $seasonId, 'stage_id' => $stage->id,
        'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(OpenSeasonRegistrationUseCase::class)->execute($seasonId);
}

test('CreateStageUseCase appends stages, assigning stage_number server-side', function (): void {
    $seasonId = stageTestDraftSeason();

    $first = stageTestCreate($seasonId, 'Preliminary');
    $second = stageTestCreate($seasonId, 'Final', 'final');

    expect($first->getStageNumber())->toBe(1);
    expect($second->getStageNumber())->toBe(2);

    $stages = app(StageRepositoryContract::class)->findBySeason($seasonId);
    expect($stages)->toHaveCount(2);
    expect($stages[0]->id)->toBe($first->id);
    expect($stages[1]->id)->toBe($second->id);
});

test('CreateStageUseCase persists every locale, public_name included', function (): void {
    $seasonId = stageTestDraftSeason();
    $stage = stageTestCreate($seasonId, 'Preliminary');

    $reloaded = app(StageRepositoryContract::class)->findOrFail($stage->id);

    expect($reloaded->getTranslation('ar')?->name)->toBe('Preliminary AR');
    expect($reloaded->getTranslation('en')?->publicName)->toBe('Preliminary Public');
    expect($reloaded->getTranslation('es')?->name)->toBe('Preliminary ES');
    expect($reloaded->getTranslation('en')?->description)->toBe('Preliminary description');

    expect(StageTranslationModel::query()->where('stage_id', $stage->id)->count())->toBe(3);
});

test('Stage rejects an end_date that is not after its start_date', function (): void {
    $seasonId = stageTestDraftSeason();

    expect(fn () => app(CreateStageUseCase::class)->execute(
        id: (string) Str::uuid(),
        seasonId: $seasonId,
        type: 'final',
        startDateIso: '2029-02-10T00:00:00+00:00',
        endDateIso: '2029-02-01T00:00:00+00:00',
        translations: stageTestTranslations(),
    ))->toThrow(InvalidArgumentException::class);
});

test('CreateStageUseCase dispatches StageCreated', function (): void {
    Event::fake([StageCreated::class]);

    stageTestCreate(stageTestDraftSeason());

    Event::assertDispatched(StageCreated::class);
});

test('UpdateStageUseCase edits schedule and merges translations without touching stage_number', function (): void {
    $seasonId = stageTestDraftSeason();
    stageTestCreate($seasonId, 'First');
    $target = stageTestCreate($seasonId, 'Second', 'final');

    $updated = app(UpdateStageUseCase::class)->execute(
        stageId: $target->id,
        type: 'semi_final',
        startDateIso: '2029-02-15T00:00:00+00:00',
        endDateIso: '2029-02-20T00:00:00+00:00',
        translations: ['en' => ['name' => 'Renamed', 'public_name' => 'Renamed Public']],
    );

    expect($updated->getType())->toBe('semi_final');
    expect($updated->getStageNumber())->toBe(2);

    $reloaded = app(StageRepositoryContract::class)->findOrFail($target->id);
    expect($reloaded->getTranslation('en')?->name)->toBe('Renamed');
    // ar was not in the payload, so it must have survived untouched.
    expect($reloaded->getTranslation('ar')?->name)->toBe('Second AR');
});

test('ReorderStagesUseCase renumbers to 1..N without tripping the unique constraint', function (): void {
    $seasonId = stageTestDraftSeason();
    $a = stageTestCreate($seasonId, 'A');
    $b = stageTestCreate($seasonId, 'B');
    $c = stageTestCreate($seasonId, 'C', 'final');

    $reordered = app(ReorderStagesUseCase::class)->execute($seasonId, [$c->id, $a->id, $b->id]);

    expect(array_map(fn (Stage $s): string => $s->id, $reordered))->toBe([$c->id, $a->id, $b->id]);
    expect(array_map(fn (Stage $s): int => $s->getStageNumber(), $reordered))->toBe([1, 2, 3]);
});

test('ReorderStagesUseCase swaps two adjacent stages, the case a naive write would collide on', function (): void {
    $seasonId = stageTestDraftSeason();
    $a = stageTestCreate($seasonId, 'A');
    $b = stageTestCreate($seasonId, 'B', 'final');

    app(ReorderStagesUseCase::class)->execute($seasonId, [$b->id, $a->id]);

    $stages = app(StageRepositoryContract::class)->findBySeason($seasonId);
    expect($stages[0]->id)->toBe($b->id);
    expect($stages[0]->getStageNumber())->toBe(1);
    expect($stages[1]->id)->toBe($a->id);
    expect($stages[1]->getStageNumber())->toBe(2);
});

test('ReorderStagesUseCase rejects a partial or duplicated ordering', function (): void {
    $seasonId = stageTestDraftSeason();
    $a = stageTestCreate($seasonId, 'A');
    stageTestCreate($seasonId, 'B', 'final');

    $useCase = app(ReorderStagesUseCase::class);

    expect(fn () => $useCase->execute($seasonId, [$a->id]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $useCase->execute($seasonId, [$a->id, $a->id]))->toThrow(InvalidArgumentException::class);
});

test('DeleteStageUseCase removes an unused stage and its translations', function (): void {
    $seasonId = stageTestDraftSeason();
    $stage = stageTestCreate($seasonId);

    app(DeleteStageUseCase::class)->execute($stage->id);

    expect(app(StageRepositoryContract::class)->find($stage->id))->toBeNull();
    expect(StageTranslationModel::query()->where('stage_id', $stage->id)->count())->toBe(0);
});

test('DeleteStageUseCase refuses a stage a season stage rule points at', function (): void {
    $seasonId = stageTestDraftSeason();
    $stage = stageTestCreate($seasonId);
    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);

    DB::table('season_stage_rules')->insert([
        'id' => (string) Str::uuid(), 'season_id' => $seasonId, 'stage_id' => $stage->id,
        'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 70,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(DeleteStageUseCase::class)->execute($stage->id))->toThrow(StageInUseException::class);
    expect(app(StageRepositoryContract::class)->find($stage->id))->not->toBeNull();
});

test('a frozen season refuses stage create, update, delete, and reorder alike', function (): void {
    $seasonId = stageTestDraftSeason();
    stageTestFreezeSeason($seasonId);

    $existing = app(StageRepositoryContract::class)->findBySeason($seasonId)[0];

    expect(fn () => stageTestCreate($seasonId, 'Late'))->toThrow(SeasonAlreadyFrozenException::class);

    expect(fn () => app(UpdateStageUseCase::class)->execute(
        stageId: $existing->id,
        type: 'final',
        startDateIso: '2029-02-15T00:00:00+00:00',
        endDateIso: '2029-02-20T00:00:00+00:00',
    ))->toThrow(SeasonAlreadyFrozenException::class);

    expect(fn () => app(DeleteStageUseCase::class)->execute($existing->id))->toThrow(SeasonAlreadyFrozenException::class);

    expect(fn () => app(ReorderStagesUseCase::class)->execute($seasonId, [$existing->id]))->toThrow(SeasonAlreadyFrozenException::class);
});

test('admin stage endpoints cover create, list, update, reorder, and delete', function (): void {
    $admin = stageTestAdmin();
    $seasonId = stageTestDraftSeason();

    $created = $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/stages", [
        'type' => 'preliminary',
        'start_date' => '2029-02-01T00:00:00+00:00',
        'end_date' => '2029-02-10T00:00:00+00:00',
        'translations' => stageTestTranslations('Preliminary'),
    ])->assertStatus(201)
        ->assertJsonPath('data.stage_number', 1)
        ->assertJsonPath('data.translations.en.public_name', 'Preliminary Public');

    $firstId = $created->json('data.id');

    $secondId = $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/stages", [
        'type' => 'final',
        'start_date' => '2029-02-11T00:00:00+00:00',
        'end_date' => '2029-02-20T00:00:00+00:00',
        'translations' => stageTestTranslations('Final'),
    ])->assertStatus(201)->json('data.id');

    $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}/stages")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin)->patchJson("/api/v1/admin/stages/{$firstId}", [
        'type' => 'semi_final',
        'start_date' => '2029-02-02T00:00:00+00:00',
        'end_date' => '2029-02-09T00:00:00+00:00',
    ])->assertStatus(200)->assertJsonPath('data.type', 'semi_final');

    $this->actingAs($admin)->putJson("/api/v1/admin/seasons/{$seasonId}/stages/order", [
        'stage_ids' => [$secondId, $firstId],
    ])->assertStatus(200)
        ->assertJsonPath('data.0.id', $secondId)
        ->assertJsonPath('data.0.stage_number', 1);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/stages/{$firstId}")->assertStatus(200);

    $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}/stages")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('POST stages rejects an unknown type and an end_date before start_date', function (): void {
    $admin = stageTestAdmin();
    $seasonId = stageTestDraftSeason();

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/stages", [
        'type' => 'quarter_final',
        'start_date' => '2029-02-01T00:00:00+00:00',
        'end_date' => '2029-02-10T00:00:00+00:00',
        'translations' => stageTestTranslations(),
    ])->assertStatus(422);

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/stages", [
        'type' => 'final',
        'start_date' => '2029-02-10T00:00:00+00:00',
        'end_date' => '2029-02-01T00:00:00+00:00',
        'translations' => stageTestTranslations(),
    ])->assertStatus(422);
});

test('DELETE returns 409 STAGE_IN_USE for a referenced stage', function (): void {
    $admin = stageTestAdmin();
    $seasonId = stageTestDraftSeason();
    $stage = stageTestCreate($seasonId);
    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);

    DB::table('season_stage_rules')->insert([
        'id' => (string) Str::uuid(), 'season_id' => $seasonId, 'stage_id' => $stage->id,
        'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 70,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/stages/{$stage->id}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'STAGE_IN_USE');
});
