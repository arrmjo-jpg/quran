<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryId;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;

uses(RefreshDatabase::class)->group('applications_gate', 'api');

test('Applications API Readiness Gate: submission workflow, admin review queue, and state machine transitions', function (): void {
    $admin = withSuperAdmin(UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'admin-review@quranplatform.com',
        'name' => 'Queue Admin',
        'type' => 'admin',
        'password_hash' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));

    $contestantUser = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'contestant-app@quranplatform.com',
        'name' => 'Youssef Al-Hassan',
        'type' => 'contestant',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $countryRepo = app(CountryRepositoryContract::class);
    (new CountriesSeeder)->run($countryRepo);
    $country = $countryRepo->findByIso2(new CountryIso2('JO'));

    $countryIdStr = $country->id instanceof CountryId ? $country->id->value : (string) $country->id;

    $contestantRepo = app(ContestantRepositoryContract::class);
    $contestant = Contestant::create(
        id: ContestantId::generate(),
        userId: $contestantUser->id,
        countryId: $countryIdStr,
        fullName: 'Youssef Al-Hassan',
        dateOfBirth: new BirthDate('1998-04-12'),
        gender: new Gender('male'),
        phoneNumber: '+962791112233'
    );
    $contestantRepo->save($contestant);

    // G3 (ADR-016 Q4): submission requires an active circle membership.
    enrolInCircle($contestant->id->value);

    $seasonRepo = app(SeasonRepositoryContract::class);
    $season = Season::create(
        id: fake()->uuid(),
        slug: 'season-app-test',
        year: 2026,
        regStartIso: '2026-08-01 00:00:00',
        regEndIso: '2026-08-15 23:59:59',
        startDateIso: '2026-08-16 00:00:00',
        endDateIso: '2026-09-30 23:59:59'
    );
    $seasonRepo->save($season);

    $stageId = fake()->uuid();
    DB::table('stages')->insert([
        'id' => $stageId,
        'season_id' => $season->id,
        'stage_number' => 1,
        'type' => 'preliminary',
        'start_date' => now(),
        'end_date' => now()->addDays(10),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $videoFile = UploadedFile::fake()->create('recitation.mp4', 5000, 'video/mp4');
    $uploadResponse = $this->actingAs($contestantUser)->postJson('/api/v1/contestant/media/upload', [
        'file' => $videoFile,
    ]);
    $videoMediaId = $uploadResponse->json('data.id');

    // 1. Submit Application
    $submitResponse = $this->actingAs($contestantUser)->postJson('/api/v1/applications', [
        'season_id' => $season->id,
        'stage_id' => $stageId,
        'video_media_asset_id' => $videoMediaId,
    ]);

    $submitResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'submitted');

    $appId = $submitResponse->json('data.id');

    // 2. Fetch My Applications
    $myAppResponse = $this->actingAs($contestantUser)->getJson('/api/v1/applications/my-applications');
    $myAppResponse->assertStatus(200)
        ->assertJsonPath('data.0.id', $appId);

    // 3. Admin Review Queue GET /admin/applications
    $adminQueueResponse = $this->actingAs($admin)->getJson('/api/v1/admin/applications');
    $adminQueueResponse->assertStatus(200)
        ->assertJsonPath('data.0.id', $appId);

    // 4. Request Reupload
    $reuploadResponse = $this->actingAs($admin)->postJson("/api/v1/admin/applications/{$appId}/request-reupload", [
        'reason' => 'Audio echo detected, please re-record in quiet space.',
    ]);
    $reuploadResponse->assertStatus(200)
        ->assertJsonPath('data.status', 'reupload_requested');

    // 5. Mark Ready For Judging
    $readyResponse = $this->actingAs($admin)->postJson("/api/v1/admin/applications/{$appId}/ready-for-judging");
    $readyResponse->assertStatus(200)
        ->assertJsonPath('data.status', 'ready_for_judging');
});
