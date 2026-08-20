<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Applications\Domain\Events\ApplicationSubmitted;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('applications', 'feature', 'golden-master');

/*
|--------------------------------------------------------------------------
| Golden master for POST /api/v1/applications
|--------------------------------------------------------------------------
|
| WRITTEN BEFORE ANY REFACTORING, AND DESCRIBING WHAT THE CODE DOES RATHER
| THAN WHAT IT SHOULD DO. Story 4 extracts SubmitApplicationUseCase out of a
| 139-line controller, and `Modules/Applications/Tests/Feature` was empty —
| there was no way to tell whether an extraction preserved behaviour, because
| nothing described the behaviour.
|
| Three of these tests pin things that are arguably wrong. They are pinned
| anyway, and each says so, because a characterisation suite that quietly
| corrects as it describes cannot answer the only question it exists for:
| did the refactoring change anything? Fixing them is a later, deliberate step
| that will show up as an edit to this file — which is exactly the visibility
| a behavioural change should have.
*/

function goldenContestantUser(string $email = 'applicant@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Applicant',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function goldenCountry(): string
{
    $existing = DB::table('countries')->value('id');

    if ($existing !== null) {
        return (string) $existing;
    }

    $id = (string) Str::uuid();

    DB::table('countries')->insert([
        'id' => $id,
        'iso_code' => 'JO',
        'iso3_code' => 'JOR',
        'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * A centre and a circle, inserted directly rather than through Organization's
 * models: this file belongs to Applications and has no business importing
 * concrete classes from another module. The ids are all it needs.
 */
function goldenCircle(): string
{
    $centerId = (string) Str::uuid();

    DB::table('centers')->insert([
        'id' => $centerId,
        'name' => 'Golden Centre',
        'country_id' => goldenCountry(),
        'city' => 'Amman',
        'address' => '1 Test Street',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $circleId = (string) Str::uuid();

    DB::table('circles')->insert([
        'id' => $circleId,
        'center_id' => $centerId,
        'name' => 'Golden Circle '.Str::random(6),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $circleId;
}

/**
 * A contestant who may apply.
 *
 * G3 made an active membership a precondition for submission, so the shared
 * fixture enrols them. Every test body below is unchanged by that rule — only
 * the fixture grew, which is what adding a precondition means. The one test
 * that needs an unenrolled contestant asks for one explicitly.
 */
function goldenContestant(UserModel $user): string
{
    $id = goldenContestantWithoutMembership($user);

    DB::table('contestant_memberships')->insert([
        'id' => (string) Str::uuid(),
        'contestant_id' => $id,
        'circle_id' => goldenCircle(),
        'joined_at' => now()->subMonths(6),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function goldenContestantWithoutMembership(UserModel $user): string
{
    $id = (string) Str::uuid();

    DB::table('contestants')->insert([
        'id' => $id,
        'user_id' => (string) $user->id,
        'country_id' => goldenCountry(),
        'full_name' => 'Applicant',
        'date_of_birth' => '2008-05-01',
        'gender' => 'male',
        'phone_number' => '+962790000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/** A season and a stage to apply into. Neither is what this file tests. */
function goldenSeasonAndStage(): array
{
    $seasonId = (string) Str::uuid();

    DB::table('seasons')->insert([
        'id' => $seasonId,
        'slug' => 'season-'.Str::lower(Str::random(6)),
        'year' => 2026,
        'registration_start' => now()->subMonth(),
        'registration_end' => now()->addMonth(),
        'start_date' => now()->addMonths(2),
        'end_date' => now()->addMonths(3),
        'status' => 'registration_open',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stageId = (string) Str::uuid();

    DB::table('stages')->insert([
        'id' => $stageId,
        'season_id' => $seasonId,
        'stage_number' => 1,
        'type' => 'qualifying',
        'start_date' => now()->addMonths(2),
        'end_date' => now()->addMonths(3),
        'status' => 'scheduled',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$seasonId, $stageId];
}

function goldenMediaAsset(): string
{
    $id = (string) Str::uuid();

    DB::table('media_assets')->insert([
        'id' => $id,
        'disk' => 'local',
        'file_path' => 'videos/entry.mp4',
        'file_name' => 'entry.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 1024,
        'collection' => 'application_videos',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

test('a contestant submits an application', function (): void {
    $user = goldenContestantUser();
    $contestant = goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/applications', [
            'season_id' => $season,
            'stage_id' => $stage,
            'video_media_asset_id' => goldenMediaAsset(),
        ])
        ->assertCreated();

    expect($response->json('data.contestant_id'))->toBe($contestant)
        ->and($response->json('data.season_id'))->toBe($season)
        ->and($response->json('data.stage_id'))->toBe($stage)
        ->and($response->json('data.status'))->toBe('submitted');
});

test('the stored row carries a derived application number', function (): void {
    // The number is derived from the id: 'APP-' . first 8 hex of md5(id),
    // uppercased. Not sequential and not human-chosen. Pinned because the
    // extraction must not change it — every stored application already has
    // one, and a different scheme would make old and new rows inconsistent.
    $user = goldenContestantUser();
    goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    $id = $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])->json('data.id');

    $row = ApplicationModel::query()->findOrFail($id);

    expect($row->status)->toBe('submitted')
        ->and($row->application_number)->toBe('APP-'.strtoupper(substr(md5($id), 0, 8)));
});

test('PINNED: submitted_at is computed and then dropped at the repository', function (): void {
    // Application::submit() sets submittedAtIso on the aggregate and records it
    // into ApplicationSubmitted. ApplicationRepository::save() writes eight
    // columns and submitted_at is not one of them, so every row in the table
    // carries NULL however submitted it is. The value exists for exactly as
    // long as the request that made it.
    //
    // Pinned rather than fixed: persisting it is a behavioural change, and it
    // belongs in the step that changes behaviour, not the one that moves code.
    $user = goldenContestantUser();
    goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    $id = $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])->assertCreated()->json('data.id');

    expect(ApplicationModel::query()->findOrFail($id)->submitted_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The refusals that exist today
|--------------------------------------------------------------------------
*/

test('a user with no contestant profile is refused', function (): void {
    $user = goldenContestantUser();
    [$season, $stage] = goldenSeasonAndStage();

    $this->actingAs($user)
        ->postJson('/api/v1/applications', [
            'season_id' => $season,
            'stage_id' => $stage,
            'video_media_asset_id' => goldenMediaAsset(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'PROFILE_REQUIRED');
});

test('a second application for the same season and stage is refused', function (): void {
    $user = goldenContestantUser();
    goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    $payload = [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ];

    $this->actingAs($user)->postJson('/api/v1/applications', $payload)->assertCreated();

    $this->actingAs($user)->postJson('/api/v1/applications', $payload)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'DUPLICATE_APPLICATION');
});

test('the submission endpoint requires authentication', function (): void {
    [$season, $stage] = goldenSeasonAndStage();

    $this->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])->assertUnauthorized();
});

test('season, stage and video are each validated for existence', function (): void {
    $user = goldenContestantUser();
    goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => (string) Str::uuid(),
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['season_id']]]);

    $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => (string) Str::uuid(),
        'video_media_asset_id' => goldenMediaAsset(),
    ])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['stage_id']]]);

    $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => (string) Str::uuid(),
    ])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['video_media_asset_id']]]);
});

/*
|--------------------------------------------------------------------------
| Behaviour that is pinned but questionable
|--------------------------------------------------------------------------
|
| These describe what happens, not what should. Each is a candidate for a
| later, deliberate change — and pinning them now is what will make that
| change visible as an edit here rather than an invisible side effect of the
| extraction.
*/

test('PINNED: `notes` is accepted by validation and then discarded', function (): void {
    // SubmitApplicationRequest validates `notes` as a nullable string up to
    // 1000 characters. Nothing reads it: Application::submit() takes no notes
    // parameter, and the applications table has no notes column. The API
    // therefore advertises a field it silently drops.
    $user = goldenContestantUser();
    goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    $id = $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
        'notes' => 'Please consider my recitation of Surah Al-Baqarah.',
    ])->assertCreated()->json('data.id');

    expect(Schema::hasColumn('applications', 'notes'))->toBeFalse();
    expect(ApplicationModel::query()->findOrFail($id)->getAttributes())
        ->not->toHaveKey('notes');
});

test('PINNED: no domain event reaches a listener', function (): void {
    // Application::submit() records ApplicationSubmitted on the aggregate, but
    // nothing calls releaseEvents() anywhere in the codebase and nothing
    // listens for it. Every other module's use case ends with a dispatch loop;
    // this path has no use case, so the event is dead code.
    //
    // This is the strongest argument for the extraction — and the reason the
    // extraction must NOT add the dispatch loop in the same step. Starting to
    // dispatch is a behavioural change, and this test is what will make it
    // visible when it happens.
    $user = goldenContestantUser();
    goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    Event::fake([ApplicationSubmitted::class]);

    $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])->assertCreated();

    Event::assertNotDispatched(ApplicationSubmitted::class);
});

test('a contestant in no circle is refused — G3', function (): void {
    // This test was pinned the other way round one commit ago: it asserted
    // that an unenrolled contestant COULD apply, because ADR-016 Q4's rule had
    // nowhere to live. The flip is the whole behavioural change of this commit,
    // and it is deliberately the only assertion in this file that reverses.
    $user = goldenContestantUser();
    goldenContestantWithoutMembership($user);
    [$season, $stage] = goldenSeasonAndStage();

    $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'MEMBERSHIP_REQUIRED');
});

test('a membership that has ended does not let a contestant apply — G3', function (): void {
    // `left_at IS NULL` is the definition of active everywhere else in the
    // platform, and it has to mean the same thing here: a contestant who left
    // their circle last term is not enrolled now.
    $user = goldenContestantUser();
    $contestant = goldenContestant($user);
    [$season, $stage] = goldenSeasonAndStage();

    DB::table('contestant_memberships')
        ->where('contestant_id', $contestant)
        ->update(['left_at' => now()->subDay(), 'reason' => 'Left the programme.']);

    $this->actingAs($user)->postJson('/api/v1/applications', [
        'season_id' => $season,
        'stage_id' => $stage,
        'video_media_asset_id' => goldenMediaAsset(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'MEMBERSHIP_REQUIRED');
});

test('PINNED: center_id and circle_id are not frozen onto the application', function (): void {
    // D8 requires an application to freeze center_id, circle_id, center_name
    // and circle_name at submission. The columns do not exist yet; Story 4
    // adds them and the freeze together. Pinned as the "before" half of that
    // change.
    expect(Schema::hasColumn('applications', 'circle_id'))->toBeFalse()
        ->and(Schema::hasColumn('applications', 'center_id'))->toBeFalse()
        ->and(Schema::hasColumn('applications', 'circle_name'))->toBeFalse()
        ->and(Schema::hasColumn('applications', 'center_name'))->toBeFalse();
});
