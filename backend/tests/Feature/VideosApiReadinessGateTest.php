<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;
use Modules\Videos\Infrastructure\Database\Models\VideoModel;

uses(RefreshDatabase::class)->group('videos', 'phase_16_9');

beforeEach(function (): void {
    Storage::fake('public');
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function video_user(string $email, string $type = 'contestant'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Video Test',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function video_media_asset(string $uploaderId, string $mimeType = 'video/mp4'): MediaAssetModel
{
    return MediaAssetModel::query()->create([
        'id' => fake()->uuid(),
        'uploader_id' => $uploaderId,
        'disk' => 'public',
        'file_path' => 'contestants/'.$uploaderId.'/2026/01/'.fake()->uuid().'.mp4',
        'file_name' => 'recitation.mp4',
        'mime_type' => $mimeType,
        'size_bytes' => 1024 * 1024 * 50, // 50MB
        'hash_sha256' => fake()->sha256(),
        'collection' => 'contestants',
    ]);
}

function video_record(
    string $applicationId,
    string $rawMediaAssetId,
    string $status = 'uploaded'
): VideoModel {
    return VideoModel::query()->create([
        'id' => fake()->uuid(),
        'application_id' => $applicationId,
        'raw_media_asset_id' => $rawMediaAssetId,
        'status' => $status,
        'duration_seconds' => null,
        'hls_master_playlist_path' => null,
        'thumbnail_path' => null,
        'variants' => [],
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — LIST
// ─────────────────────────────────────────────────────────────────────────────

test('Videos 16.9.1 — Admin can list all videos with pagination', function (): void {
    $admin = video_user('admin-list-videos@test.test', 'admin');
    $asset = video_media_asset($admin->id);
    video_record(fake()->uuid(), $asset->id, 'ready');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/videos')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success', 'data', 'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);
});

test('Videos 16.9.2 — Admin can filter videos by status=ready', function (): void {
    $admin = video_user('admin-filter-videos@test.test', 'admin');
    $asset1 = video_media_asset($admin->id);
    $asset2 = video_media_asset($admin->id);
    video_record(fake()->uuid(), $asset1->id, 'ready');
    video_record(fake()->uuid(), $asset2->id, 'failed');

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/videos?status=ready');
    $response->assertStatus(200);

    $statuses = collect($response->json('data'))->pluck('status');
    expect($statuses->every(fn ($s) => $s === 'ready'))->toBeTrue();
});

test('Videos 16.9.3 — Admin can filter videos by application_id', function (): void {
    $admin = video_user('admin-filter-app@test.test', 'admin');
    $appId = fake()->uuid();
    $asset = video_media_asset($admin->id);
    video_record($appId, $asset->id, 'uploaded');

    $response = $this->actingAs($admin)->getJson("/api/v1/admin/videos?application_id={$appId}");
    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('application_id');
    expect($ids)->toContain($appId);
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — SHOW
// ─────────────────────────────────────────────────────────────────────────────

test('Videos 16.9.4 — Admin can view a single video with full details', function (): void {
    $admin = video_user('admin-show-video@test.test', 'admin');
    $asset = video_media_asset($admin->id);
    $video = video_record(fake()->uuid(), $asset->id, 'ready');

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/videos/{$video->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $video->id)
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonStructure(['data' => ['id', 'status', 'hls_url', 'thumbnail_url', 'variants']]);
});

test('Videos 16.9.5 — Admin gets 404 for non-existent video', function (): void {
    $admin = video_user('admin-404-video@test.test', 'admin');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/videos/'.fake()->uuid())
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — REPROCESS
// ─────────────────────────────────────────────────────────────────────────────

test('Videos 16.9.6 — Admin can reprocess a failed video', function (): void {
    $admin = video_user('admin-reprocess-video@test.test', 'admin');
    $asset = video_media_asset($admin->id);
    $video = video_record(fake()->uuid(), $asset->id, 'failed');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/videos/{$video->id}/reprocess")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'processing');

    $this->assertDatabaseHas('videos', ['id' => $video->id, 'status' => 'processing']);
});

test('Videos 16.9.7 — Admin cannot reprocess a video already processing (409)', function (): void {
    $admin = video_user('admin-409-video@test.test', 'admin');
    $asset = video_media_asset($admin->id);
    $video = video_record(fake()->uuid(), $asset->id, 'processing');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/videos/{$video->id}/reprocess")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_PROCESSING');
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — DELETE
// ─────────────────────────────────────────────────────────────────────────────

test('Videos 16.9.8 — Admin can soft-delete a video', function (): void {
    $admin = video_user('admin-delete-video@test.test', 'admin');
    $asset = video_media_asset($admin->id);
    $video = video_record(fake()->uuid(), $asset->id, 'ready');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/videos/{$video->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('videos', ['id' => $video->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// CONTESTANT — REGISTER VIDEO
// ─────────────────────────────────────────────────────────────────────────────

test('Videos 16.9.9 — Contestant can register a video from their own media asset (201)', function (): void {
    $user = video_user('contestant-reg-video@test.test');
    $asset = video_media_asset($user->id, 'video/mp4');
    $appId = fake()->uuid();

    $this->actingAs($user)
        ->postJson('/api/v1/contestant/videos', [
            'raw_media_asset_id' => $asset->id,
            'application_id' => $appId,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'uploaded')
        ->assertJsonPath('data.application_id', $appId);

    $this->assertDatabaseHas('videos', [
        'raw_media_asset_id' => $asset->id,
        'application_id' => $appId,
        'status' => 'uploaded',
    ]);
});

test('Videos 16.9.10 — Contestant cannot use another contestant\'s media asset (404)', function (): void {
    $user1 = video_user('contestant-asset-owner@test.test');
    $user2 = video_user('contestant-asset-thief@test.test');
    $asset = video_media_asset($user1->id, 'video/mp4'); // belongs to user1

    $this->actingAs($user2)
        ->postJson('/api/v1/contestant/videos', [
            'raw_media_asset_id' => $asset->id,
            'application_id' => fake()->uuid(),
        ])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'ASSET_NOT_FOUND');
});

test('Videos 16.9.11 — Contestant cannot register an image as video (422)', function (): void {
    $user = video_user('contestant-image-video@test.test');
    $asset = video_media_asset($user->id, 'image/jpeg'); // wrong MIME

    $this->actingAs($user)
        ->postJson('/api/v1/contestant/videos', [
            'raw_media_asset_id' => $asset->id,
            'application_id' => fake()->uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'NOT_A_VIDEO');
});

test('Videos 16.9.12 — Registering duplicate returns existing record (200)', function (): void {
    $user = video_user('contestant-dup-video@test.test');
    $asset = video_media_asset($user->id, 'video/mp4');
    $appId = fake()->uuid();

    // Pre-existing video record
    video_record($appId, $asset->id, 'processing');

    $this->actingAs($user)
        ->postJson('/api/v1/contestant/videos', [
            'raw_media_asset_id' => $asset->id,
            'application_id' => $appId,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

// ─────────────────────────────────────────────────────────────────────────────
// CONTESTANT — SHOW & STATUS
// ─────────────────────────────────────────────────────────────────────────────

test('Videos 16.9.13 — Contestant can view their own video (200)', function (): void {
    $user = video_user('contestant-own-video@test.test');
    $asset = video_media_asset($user->id, 'video/mp4');
    $video = video_record(fake()->uuid(), $asset->id, 'ready');

    $this->actingAs($user)
        ->getJson("/api/v1/contestant/videos/{$video->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $video->id);
});

test('Videos 16.9.14 — Contestant cannot view another contestant\'s video (403)', function (): void {
    $user1 = video_user('contestant-vid-owner@test.test');
    $user2 = video_user('contestant-vid-spy@test.test');
    $asset = video_media_asset($user1->id, 'video/mp4');
    $video = video_record(fake()->uuid(), $asset->id, 'ready');

    $this->actingAs($user2)
        ->getJson("/api/v1/contestant/videos/{$video->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

test('Videos 16.9.15 — Contestant can poll processing status (lightweight)', function (): void {
    $user = video_user('contestant-poll-video@test.test');
    $asset = video_media_asset($user->id, 'video/mp4');
    $video = video_record(fake()->uuid(), $asset->id, 'processing');

    $this->actingAs($user)
        ->getJson("/api/v1/contestant/videos/{$video->id}/status")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonStructure(['data' => ['id', 'status', 'duration_seconds', 'hls_url']]);
});
