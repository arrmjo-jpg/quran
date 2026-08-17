<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Services\SeasonStateMachine;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'season-repository');

test('save() then findOrFail() round-trips the full lifecycle field set', function (): void {
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);

    $season = Season::create(
        id: fake()->uuid(),
        slug: 'season-'.fake()->unique()->numerify('####'),
        year: 2027,
        regStartIso: '2027-01-01T00:00:00+00:00',
        regEndIso: '2027-01-15T00:00:00+00:00',
        startDateIso: '2027-01-16T00:00:00+00:00',
        endDateIso: '2027-03-01T00:00:00+00:00',
    );

    $season->setAgeRange(10, 18);
    $season->setParticipationType($participationType->id);
    $season->setTajweedLevel($tajweedLevel->id);
    $season->setTranslation(new SeasonTranslation(
        locale: 'ar',
        title: 'موسم 2027',
        publicName: 'موسم القرآن 2027',
        publicShortName: 'موسم 2027',
        description: 'وصف الموسم',
    ));
    $season->setTranslation(new SeasonTranslation(locale: 'en', title: '2027 Season', publicName: 'Quran Season 2027'));

    $repository = app(SeasonRepositoryContract::class);
    $repository->save($season);

    $reloaded = $repository->findOrFail($season->id);

    expect($reloaded->getMinAge())->toBe(10);
    expect($reloaded->getMaxAge())->toBe(18);
    expect($reloaded->getParticipationTypeId())->toBe($participationType->id);
    expect($reloaded->getTajweedLevelId())->toBe($tajweedLevel->id);
    expect($reloaded->isFrozen())->toBeFalse();

    $ar = $reloaded->getTranslation('ar');
    expect($ar)->not->toBeNull();
    expect($ar->title)->toBe('موسم 2027');
    expect($ar->publicName)->toBe('موسم القرآن 2027');
    expect($ar->publicShortName)->toBe('موسم 2027');
    expect($ar->description)->toBe('وصف الموسم');

    $en = $reloaded->getTranslation('en');
    expect($en->title)->toBe('2027 Season');
    expect($en->publicName)->toBe('Quran Season 2027');

    expect($reloaded->getTranslation('es'))->toBeNull();
});

test('save() persists archive metadata and findOrFail() rehydrates it', function (): void {
    $season = Season::create(
        id: fake()->uuid(),
        slug: 'season-'.fake()->unique()->numerify('####'),
        year: 2027,
        regStartIso: '2027-01-01T00:00:00+00:00',
        regEndIso: '2027-01-15T00:00:00+00:00',
        startDateIso: '2027-01-16T00:00:00+00:00',
        endDateIso: '2027-03-01T00:00:00+00:00',
    );

    $repository = app(SeasonRepositoryContract::class);
    $repository->save($season);

    $userId = withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'season-repo-test-'.Str::random(8).'@quran.test',
        'name' => 'Season Repository Test User',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]))->id;

    $season->cancel(new SeasonStateMachine, 'no interest', $userId);
    $repository->save($season);

    $reloaded = $repository->findOrFail($season->id);

    expect($reloaded->getStatus())->toBe('archived');
    expect($reloaded->getArchiveReason())->toBe('no interest');
    expect($reloaded->getArchivedByUserId())->toBe($userId);
    expect($reloaded->getArchivedAtIso())->not->toBeNull();
});
