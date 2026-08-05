<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
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
        ]);

        $response->assertStatus(201);

        return $response->json('data.id');
    }

    public function test_season_id_is_a_real_uuid_not_a_fake_one(): void
    {
        $id = $this->createSeason('uuid-check', 2030);

        expect(Uuid::isValid($id))->toBeTrue();
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
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_create_a_season(): void
    {
        $regularUser = UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'not-admin@quran.test',
            'name' => 'Not Admin',
            'type' => 'user',
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
        ])->assertStatus(403);
    }
}
