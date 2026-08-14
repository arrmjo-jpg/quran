<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('frontend_contract', 'prg_003');

function fc_admin(string $email = 'fc-admin@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Contract Admin User',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

test('PRG-003 Screen 1: Login & Profile Contract — POST /api/v1/admin/auth/login and GET /api/v1/admin/auth/me', function (): void {
    $admin = fc_admin('admin-login-screen@quran.test');

    // 1. Login
    $loginResp = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin-login-screen@quran.test',
        'password' => 'Pass123!',
    ]);

    $loginResp->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'status', 'roles'],
            ],
        ]);

    $token = $loginResp->json('data.token');

    // 2. Auth Profile Me
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/auth/me')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'admin-login-screen@quran.test');
});

test('PRG-003 Screen 2: Executive Dashboard — GET /api/v1/admin/reports/summary', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/reports/summary')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'total_contestants',
                'total_applications',
                'applications_by_status',
                'total_evaluations',
                'completed_evaluations',
            ],
        ]);
});

test('PRG-003 Screen 3: Seasons Screen — GET /api/v1/seasons and POST /api/v1/admin/seasons', function (): void {
    $admin = fc_admin();

    $season = SeasonModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'season-screen-2026',
        'year' => 2026,
        'registration_start' => now()->subDays(5),
        'registration_end' => now()->addDays(20),
        'start_date' => now()->addDays(21),
        'end_date' => now()->addMonths(2),
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/seasons')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$season->id}/open-registration")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_open');
});

test('PRG-003 Screen 4: Countries Screen — GET /api/v1/countries and POST /api/v1/admin/countries', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/countries', [
            'iso2' => 'OM',
            'iso3' => 'OMN',
            'phone_code' => '+968',
            'flag_url' => 'https://cdn.quranplatform.com/flags/om.svg',
            'name_ar' => 'عُمان',
            'name_en' => 'Oman',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.iso2', 'OM');

    $this->getJson('/api/v1/countries')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 6: Judges Screen — GET & POST /api/v1/admin/judges', function (): void {
    $admin = fc_admin();
    $judgeUser = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'judge-screen@quran.test',
        'name' => 'Judge Screen Name',
        'type' => 'judge',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/judges', [
            'user_id' => $judgeUser->id,
            'full_name' => 'Dr. Judge Mohamed',
            'title' => 'Professor',
            'specialization' => 'tajweed',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.full_name', 'Dr. Judge Mohamed');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/judges')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 7: Applications & Review Queue — GET /api/v1/admin/applications', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/applications')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta']);
});

test('PRG-003 Screen 8: Evaluations Screen — GET /api/v1/admin/evaluations', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/evaluations')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 9: Media Library Screen — GET & POST /api/v1/admin/media', function (): void {
    Storage::fake('public');
    $admin = fc_admin();
    $file = UploadedFile::fake()->image('banner.jpg');

    $uploadResp = $this->actingAs($admin)
        ->postJson('/api/v1/admin/media', [
            'file' => $file,
        ]);

    $uploadResp->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/media')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 10: Videos Screen — GET /api/v1/admin/videos', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/videos')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 11: Notifications Screen — GET /api/v1/admin/notifications', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/notifications')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 12: Streaming Screen — GET & POST /api/v1/admin/streams', function (): void {
    $admin = fc_admin();
    $season = SeasonModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'season-stream-contract',
        'year' => 2026,
        'registration_start' => now()->subDays(5),
        'registration_end' => now()->addDays(20),
        'start_date' => now()->addDays(21),
        'end_date' => now()->addMonths(2),
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/streams', [
            'season_id' => $season->id,
            'title' => 'Grand Finale Live Stream',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/streams')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 13: Content Screen — GET & POST /api/v1/admin/content/announcements', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/content/announcements', [
            'slug' => 'welcome-announcement-contract',
            'target_surface' => 'all',
            'is_published' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/content/announcements')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 14: Sponsors Screen — GET & POST /api/v1/admin/sponsors', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/sponsors', [
            'name' => 'Golden Sponsor Bank',
            'tier' => 'gold',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/sponsors')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 15: Reports & Exports Screen — GET /api/v1/admin/reports/summary', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/reports/summary')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/reports/exports')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 16: Search Screen — GET /api/v1/search and POST /api/v1/admin/search/reindex', function (): void {
    $admin = fc_admin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/search/reindex', ['index_name' => 'all'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->getJson('/api/v1/search?q=test')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('PRG-003 Screen 17: Public Settings Screen — GET /api/v1/settings/public', function (): void {
    $this->getJson('/api/v1/settings/public')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});
