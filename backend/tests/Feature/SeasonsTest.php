<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;

final class SeasonsTest extends TestCase
{
    use RefreshDatabase;

    private ?UserModel $sharedAdmin = null;

    private function admin(): UserModel
    {
        // Memoized per test instance — callers invoke this repeatedly
        // (e.g. once per createSeason() call) and must get back the same
        // admin user, not a fresh one with a colliding unique email.
        return $this->sharedAdmin ??= UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'seasons-admin@quran.test',
            'name' => 'Seasons Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);
    }

    private function createSeason(string $slug, int $year): string
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/seasons', [
            'slug' => $slug,
            'year' => $year,
            'registration_start' => "{$year}-01-01 00:00:00",
            'registration_end' => "{$year}-01-15 23:59:59",
            'start_date' => "{$year}-01-16 00:00:00",
            'end_date' => "{$year}-02-28 23:59:59",
            'title_ar' => "موسم {$year}",
            'title_en' => "Season {$year}",
            'title_es' => "Temporada {$year}",
            'public_name_ar' => "موسم القرآن {$year}",
            'public_name_en' => "Quran Season {$year}",
            'public_name_es' => "Temporada del Corán {$year}",
        ]);

        $response->assertStatus(201);

        return $response->json('data.id');
    }

    /**
     * OpenSeasonRegistrationUseCase (the v2 replacement for the old,
     * naive openRegistration()) requires a season's rules to be fully
     * resolved before it may open registration — age range, participation
     * type, tajweed level, all three translations, at least one eligible
     * country, and at least one stage rule. There is no admin API yet to
     * set any of this (a known, separate gap — see Step 9's verification
     * report), so tests that need an activatable season must configure
     * it directly through the domain/repository, exactly like the
     * Competition module's own seedFullyConfiguredSeason() helper does.
     */
    private function configureSeasonRules(string $seasonId): void
    {
        $repository = app(SeasonRepositoryContract::class);
        $season = $repository->findOrFail($seasonId);

        $participationType = ParticipationTypeModel::query()->create(['id' => (string) Uuid::v4(), 'code' => 'mixed-'.Str::random(6), 'display_order' => 1, 'is_active' => true]);
        $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Uuid::v4(), 'code' => 'advanced-'.Str::random(6), 'display_order' => 1, 'is_active' => true]);
        $country = CountryModel::query()->create(['id' => (string) Uuid::v4(), 'iso_code' => Str::upper(Str::random(2)), 'iso3_code' => Str::upper(Str::random(3)), 'phone_code' => '+1', 'is_active' => true]);

        $season->setAgeRange(10, 18);
        $season->setParticipationType($participationType->id);
        $season->setTajweedLevel($tajweedLevel->id);
        $season->setTranslation(new SeasonTranslation('ar', 'موسم', 'موسم القرآن'));
        $season->setTranslation(new SeasonTranslation('en', 'Season', 'Quran Season'));
        $season->setTranslation(new SeasonTranslation('es', 'Temporada', 'Temporada del Corán'));
        $repository->save($season);

        DB::table('season_countries')->insert(['season_id' => $seasonId, 'country_id' => $country->id]);

        $stage = StageModel::query()->create([
            'id' => (string) Uuid::v4(), 'season_id' => $seasonId, 'stage_number' => 1, 'type' => 'final',
            'start_date' => now(), 'end_date' => now()->addDay(), 'status' => 'pending',
        ]);

        $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => (string) Uuid::v4(), 'code' => 'out_of_100-'.Str::random(6), 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);

        DB::table('season_stage_rules')->insert([
            'id' => (string) Uuid::v4(), 'season_id' => $seasonId, 'stage_id' => $stage->id,
            'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_season_id_is_a_real_uuid_not_a_fake_one(): void
    {
        $id = $this->createSeason('uuid-check', 2030);

        expect(Uuid::isValid($id))->toBeTrue();
    }

    public function test_creating_a_season_actually_persists_its_translations(): void
    {
        $id = $this->createSeason('translation-check', 2041);

        $repository = app(SeasonRepositoryContract::class);
        $season = $repository->findOrFail($id);

        $ar = $season->getTranslation('ar');
        $en = $season->getTranslation('en');
        $es = $season->getTranslation('es');

        expect($ar)->not->toBeNull();
        expect($ar->title)->toBe('موسم 2041');
        expect($ar->publicName)->toBe('موسم القرآن 2041');
        expect($en?->title)->toBe('Season 2041');
        expect($en?->publicName)->toBe('Quran Season 2041');
        expect($es?->title)->toBe('Temporada 2041');
        expect($es?->publicName)->toBe('Temporada del Corán 2041');
    }

    public function test_season_dates_beyond_the_mysql_timestamp_ceiling_are_accepted(): void
    {
        // NOTE: this suite runs on sqlite (:memory:), which never had the
        // MySQL TIMESTAMP 2038-01-19 ceiling this migration fixes — sqlite
        // wouldn't have failed either way, so this doesn't exercise the
        // actual bug. It's a regression guard for the app-level behavior
        // (year up to 2100 is accepted end-to-end); the real fix was
        // verified live against the MySQL container directly.
        $id = $this->createSeason('far-future-season', 2045);

        expect($id)->not->toBeNull();

        $model = SeasonModel::query()->findOrFail($id);
        expect($model->year)->toBe(2045);
    }

    public function test_opening_registration_deactivates_every_other_season(): void
    {
        $seasonA = $this->createSeason('season-a', 2031);
        $seasonB = $this->createSeason('season-b', 2032);
        $this->configureSeasonRules($seasonA);
        $this->configureSeasonRules($seasonB);

        $this->actingAs($this->admin())->postJson("/api/v1/admin/seasons/{$seasonA}/open-registration")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($this->admin())->postJson("/api/v1/admin/seasons/{$seasonB}/open-registration")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $activeCount = SeasonModel::query()->where('is_active', true)->count();
        expect($activeCount)->toBe(1);

        $seasonAModel = SeasonModel::query()->find($seasonA);
        expect($seasonAModel->is_active)->toBeFalse();
    }

    public function test_public_seasons_index_lists_all_seasons(): void
    {
        $this->createSeason('list-a', 2033);
        $this->createSeason('list-b', 2034);

        $response = $this->getJson('/api/v1/seasons');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_public_current_season_returns_404_when_none_is_active(): void
    {
        $this->getJson('/api/v1/seasons/current')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_ACTIVE_SEASON');
    }

    public function test_public_current_season_returns_the_active_one(): void
    {
        $seasonId = $this->createSeason('current-season', 2035);
        $this->configureSeasonRules($seasonId);
        $this->actingAs($this->admin())->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration");

        $this->getJson('/api/v1/seasons/current')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $seasonId);
    }

    public function test_public_show_returns_a_single_season(): void
    {
        $seasonId = $this->createSeason('show-season', 2036);

        $this->getJson("/api/v1/seasons/{$seasonId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $seasonId);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $this->createSeason('dup-slug', 2037);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/seasons', [
            'slug' => 'dup-slug',
            'year' => 2038,
            'registration_start' => '2038-01-01 00:00:00',
            'registration_end' => '2038-01-15 23:59:59',
            'start_date' => '2038-01-16 00:00:00',
            'end_date' => '2038-02-28 23:59:59',
            'title_ar' => 'مكرر',
            'title_en' => 'Duplicate',
            'title_es' => 'Duplicado',
            'public_name_ar' => 'مكرر',
            'public_name_en' => 'Duplicate',
            'public_name_es' => 'Duplicado',
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_create_a_season(): void
    {
        $regularUser = UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'not-admin@quran.test',
            'name' => 'Not Admin',
            'type' => 'contestant',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $this->actingAs($regularUser)->postJson('/api/v1/admin/seasons', [
            'slug' => 'blocked-season',
            'year' => 2039,
            'registration_start' => '2039-01-01 00:00:00',
            'registration_end' => '2039-01-15 23:59:59',
            'start_date' => '2039-01-16 00:00:00',
            'end_date' => '2039-02-28 23:59:59',
            'title_ar' => 'محظور',
            'title_en' => 'Blocked',
            'title_es' => 'Bloqueado',
            'public_name_ar' => 'محظور',
            'public_name_en' => 'Blocked',
            'public_name_es' => 'Bloqueado',
        ])->assertStatus(403);
    }
}
