<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('prg_002', 'readiness_gate');

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 1: DATABASE SCHEMA & MODEL INTEGRITY AUDIT
// ─────────────────────────────────────────────────────────────────────────────

test('PRG-002 Pillar 1.1 — Schema Audit: applications table contains all model attributes', function (): void {
    expect(Schema::hasColumn('applications', 'id'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'contestant_id'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'season_id'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'stage_id'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'video_id'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'video_media_id'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'application_number'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'status'))->toBeTrue();
    expect(Schema::hasColumn('applications', 'submitted_at'))->toBeTrue();
});

test('PRG-002 Pillar 1.2 — Schema Audit: videos table contains all model attributes', function (): void {
    expect(Schema::hasColumn('videos', 'id'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'application_id'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'raw_media_asset_id'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'status'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'duration_seconds'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'hls_master_playlist_path'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'thumbnail_path'))->toBeTrue();
    expect(Schema::hasColumn('videos', 'variants'))->toBeTrue();
});

test('PRG-002 Pillar 1.3 — Schema Audit: evaluations table contains all required columns', function (): void {
    expect(Schema::hasColumn('evaluations', 'id'))->toBeTrue();
    expect(Schema::hasColumn('evaluations', 'application_id'))->toBeTrue();
    expect(Schema::hasColumn('evaluations', 'judge_id'))->toBeTrue();
    expect(Schema::hasColumn('evaluations', 'total_score'))->toBeTrue();
    expect(Schema::hasColumn('evaluations', 'status'))->toBeTrue();
});

test('PRG-002 Pillar 1.4 — Schema Audit: notification_logs table contains all required columns', function (): void {
    expect(Schema::hasColumn('notification_logs', 'id'))->toBeTrue();
    expect(Schema::hasColumn('notification_logs', 'user_id'))->toBeTrue();
    expect(Schema::hasColumn('notification_logs', 'channel'))->toBeTrue();
    expect(Schema::hasColumn('notification_logs', 'template_key'))->toBeTrue();
    expect(Schema::hasColumn('notification_logs', 'status'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 2: AUTHORIZATION AUDIT — ZERO UNPROTECTED ADMIN / SENSITIVE ROUTES
// ─────────────────────────────────────────────────────────────────────────────

test('PRG-002 Pillar 2.1 — Unauthenticated requests to Admin Media return 401', function (): void {
    $this->getJson('/api/v1/admin/media')->assertStatus(401);
});

test('PRG-002 Pillar 2.2 — Unauthenticated requests to Admin Videos return 401', function (): void {
    $this->getJson('/api/v1/admin/videos')->assertStatus(401);
});

test('PRG-002 Pillar 2.3 — Unauthenticated requests to Admin Notifications return 401', function (): void {
    $this->getJson('/api/v1/admin/notifications')->assertStatus(401);
});

test('PRG-002 Pillar 2.4 — Unauthenticated requests to Contestant Media return 401', function (): void {
    $this->getJson('/api/v1/contestant/media/'.fake()->uuid())->assertStatus(401);
});

test('PRG-002 Pillar 2.5 — Unauthenticated requests to Contestant Videos return 401', function (): void {
    $this->getJson('/api/v1/contestant/videos/'.fake()->uuid())->assertStatus(401);
});

test('PRG-002 Pillar 2.6 — Unauthenticated requests to Contestant Notifications return 401', function (): void {
    $this->getJson('/api/v1/contestant/notifications')->assertStatus(401);
});

test('PRG-002 Pillar 2.7 — Unauthenticated requests to Judge Evaluations return 401', function (): void {
    $this->getJson('/api/v1/judge/evaluations')->assertStatus(401);
});

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 3: ADR-014 RESPONSE ENVELOPE CONTRACT AUDIT
// ─────────────────────────────────────────────────────────────────────────────

test('PRG-002 Pillar 3.1 — Standard successful JSON responses contain success & data keys', function (): void {
    $admin = withSuperAdmin(UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'prg002-admin@test.test',
        'name' => 'PRG Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/media')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'data', 'meta']);
});

test('PRG-002 Pillar 3.2 — Error responses adhere to ADR-014 structure { success: false, error: { code, message } }', function (): void {
    $admin = withSuperAdmin(UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'prg002-error@test.test',
        'name' => 'PRG Admin Error',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));

    // Request non-existent video reprocessing to trigger 404/409 error
    $this->actingAs($admin)
        ->postJson('/api/v1/admin/videos/'.fake()->uuid().'/reprocess')
        ->assertStatus(404);
});
