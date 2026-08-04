<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Streaming\Infrastructure\Database\Models\StreamModel;

uses(RefreshDatabase::class)->group('streaming', 'phase_16_11');

function stream_user(string $email, string $type = 'admin'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Stream Admin',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function stream_season(): SeasonModel
{
    return SeasonModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'season-stream-2026',
        'year' => 2026,
        'registration_start' => now()->subDays(5),
        'registration_end' => now()->addDays(20),
        'start_date' => now(),
        'end_date' => now()->addMonths(2),
        'status' => 'active',
        'is_active' => true,
    ]);
}

test('Streaming 16.11.1 — Admin can list streams with pagination', function (): void {
    $admin = stream_user('admin-streams-list@test.test');
    $season = stream_season();

    StreamModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'title' => 'Final Recitation Stream',
        'stream_key' => 'live_key_123',
        'rtmp_url' => 'rtmp://live.quran.test/live/live_key_123',
        'hls_url' => 'http://live.quran.test/hls/live_key_123.m3u8',
        'status' => 'idle',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/streams')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta']);
});

test('Streaming 16.11.2 — Admin can create a live stream (201)', function (): void {
    $admin = stream_user('admin-streams-create@test.test');
    $season = stream_season();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/streams', [
            'season_id' => $season->id,
            'title' => 'Semi-Finals Live',
            'scheduled_start' => now()->addHour()->toIso8601String(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Semi-Finals Live')
        ->assertJsonPath('data.status', 'idle')
        ->assertJsonStructure(['data' => ['stream_key', 'rtmp_url']]); // Admin sees RTMP key
});

test('Streaming 16.11.3 — Admin can transition stream to live (200)', function (): void {
    $admin = stream_user('admin-stream-start@test.test');
    $season = stream_season();

    $stream = StreamModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'title' => 'Stage 1 Live',
        'stream_key' => 'live_key_start',
        'rtmp_url' => 'rtmp://live.quran.test/live/live_key_start',
        'hls_url' => 'http://live.quran.test/hls/live_key_start.m3u8',
        'status' => 'idle',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/streams/{$stream->id}/start")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'live');

    $this->assertDatabaseHas('streams', ['id' => $stream->id, 'status' => 'live']);
});

test('Streaming 16.11.4 — Admin cannot start an already live stream (409)', function (): void {
    $admin = stream_user('admin-stream-start-409@test.test');
    $season = stream_season();

    $stream = StreamModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'title' => 'Already Live Stream',
        'stream_key' => 'live_key_409',
        'rtmp_url' => 'rtmp://live.quran.test/live/live_key_409',
        'status' => 'live',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/streams/{$stream->id}/start")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_LIVE');
});

test('Streaming 16.11.5 — Admin can stop a live stream (200)', function (): void {
    $admin = stream_user('admin-stream-stop@test.test');
    $season = stream_season();

    $stream = StreamModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'title' => 'Ending Live Stream',
        'stream_key' => 'live_key_stop',
        'rtmp_url' => 'rtmp://live.quran.test/live/live_key_stop',
        'status' => 'live',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/streams/{$stream->id}/stop")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ended');

    $this->assertDatabaseHas('streams', ['id' => $stream->id, 'status' => 'ended']);
});

test('Streaming 16.11.6 — Public user can list active live streams (200)', function (): void {
    $season = stream_season();

    StreamModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'title' => 'Active Live Stream',
        'stream_key' => 'live_key_public',
        'rtmp_url' => 'rtmp://live.quran.test/live/live_key_public',
        'hls_url' => 'http://live.quran.test/hls/live_key_public.m3u8',
        'status' => 'live',
    ]);

    $response = $this->getJson('/api/v1/streams/live');
    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    // Public context does NOT expose stream_key or rtmp_url
    $data = $response->json('data.0');
    expect($data)->not->toHaveKey('stream_key');
    expect($data)->not->toHaveKey('rtmp_url');
    expect($data['hls_url'])->toBe('http://live.quran.test/hls/live_key_public.m3u8');
});
