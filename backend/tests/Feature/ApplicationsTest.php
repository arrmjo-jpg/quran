<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;

final class ApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private ?UserModel $sharedAdmin = null;

    private function admin(): UserModel
    {
        return $this->sharedAdmin ??= withSuperAdmin(UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'applications-admin@quran.test',
            'name' => 'Applications Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]));
    }

    /** @return array{contestant: UserModel, season_id: string, stage_id: string, media_id: string} */
    private function setUpFixture(): array
    {
        $contestantUser = UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'app-contestant@quran.test',
            'name' => 'App Contestant',
            'type' => 'contestant',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $countryRepo = app(CountryRepositoryContract::class);
        (new CountriesSeeder)->run($countryRepo);
        $country = $countryRepo->findByIso2(new CountryIso2('JO'));

        $contestantRepo = app(ContestantRepositoryContract::class);
        $contestant = Contestant::create(
            id: ContestantId::generate(),
            userId: $contestantUser->id,
            countryId: $country->id->value,
            fullName: 'App Contestant',
            dateOfBirth: new BirthDate('1998-06-15'),
            gender: new Gender('male'),
            phoneNumber: '+962790000010'
        );
        $contestantRepo->save($contestant);

        $seasonRepo = app(SeasonRepositoryContract::class);
        $season = Season::create(
            id: (string) Uuid::v7(),
            slug: 'apps-test-season',
            year: 2026,
            regStartIso: '2026-08-01 00:00:00',
            regEndIso: '2026-08-15 23:59:59',
            startDateIso: '2026-08-16 00:00:00',
            endDateIso: '2026-09-30 23:59:59'
        );
        $seasonRepo->save($season);

        $stageId = (string) Uuid::v7();
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

        $mediaId = (string) Uuid::v7();
        DB::table('media_assets')->insert([
            'id' => $mediaId,
            'uploader_id' => $contestantUser->id,
            'file_path' => 'videos/test.mp4',
            'file_name' => 'test.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'contestant' => $contestantUser,
            'season_id' => $season->id,
            'stage_id' => $stageId,
            'media_id' => $mediaId,
        ];
    }

    public function test_submitting_the_same_application_twice_returns_409_instead_of_crashing(): void
    {
        $fixture = $this->setUpFixture();

        $payload = [
            'season_id' => $fixture['season_id'],
            'stage_id' => $fixture['stage_id'],
            'video_media_asset_id' => $fixture['media_id'],
        ];

        $this->actingAs($fixture['contestant'])->postJson('/api/v1/applications', $payload)
            ->assertStatus(201);

        $this->actingAs($fixture['contestant'])->postJson('/api/v1/applications', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_APPLICATION');
    }

    public function test_submit_generates_a_real_uuid_not_a_fake_one(): void
    {
        $fixture = $this->setUpFixture();

        $response = $this->actingAs($fixture['contestant'])->postJson('/api/v1/applications', [
            'season_id' => $fixture['season_id'],
            'stage_id' => $fixture['stage_id'],
            'video_media_asset_id' => $fixture['media_id'],
        ]);

        $response->assertStatus(201);
        expect(Uuid::isValid($response->json('data.id')))->toBeTrue();
    }

    public function test_submitting_with_a_nonexistent_season_returns_422_not_a_crash(): void
    {
        $fixture = $this->setUpFixture();

        $this->actingAs($fixture['contestant'])->postJson('/api/v1/applications', [
            'season_id' => (string) Uuid::v7(),
            'stage_id' => $fixture['stage_id'],
            'video_media_asset_id' => $fixture['media_id'],
        ])->assertStatus(422);
    }

    public function test_request_reupload_persists_the_reason_and_preserves_the_video_reference(): void
    {
        $fixture = $this->setUpFixture();

        $submitResponse = $this->actingAs($fixture['contestant'])->postJson('/api/v1/applications', [
            'season_id' => $fixture['season_id'],
            'stage_id' => $fixture['stage_id'],
            'video_media_asset_id' => $fixture['media_id'],
        ]);
        $appId = $submitResponse->json('data.id');

        $reuploadResponse = $this->actingAs($this->admin())->postJson("/api/v1/admin/applications/{$appId}/request-reupload", [
            'reason' => 'Audio quality too low, please re-record.',
        ]);

        $reuploadResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'reupload_requested')
            ->assertJsonPath('data.reupload_reason', 'Audio quality too low, please re-record.')
            ->assertJsonPath('data.video_media_asset_id', $fixture['media_id']);

        $model = ApplicationModel::query()->findOrFail($appId);
        expect($model->reupload_reason)->toBe('Audio quality too low, please re-record.');
        expect($model->video_media_id)->toBe($fixture['media_id']);
    }
}
