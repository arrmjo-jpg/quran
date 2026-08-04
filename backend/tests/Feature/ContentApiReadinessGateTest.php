<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Infrastructure\Database\Models\AnnouncementModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('content', 'phase_16_12');

function content_user(string $email, string $type = 'admin'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Content Admin',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

test('Content 16.12.1 — Admin can list announcements with pagination', function (): void {
    $admin = content_user('admin-ann-list@test.test');
    AnnouncementModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'welcome-season-2026',
        'target_surface' => 'all',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/content/announcements')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta']);
});

test('Content 16.12.2 — Admin can create an announcement (201)', function (): void {
    $admin = content_user('admin-ann-create@test.test');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/content/announcements', [
            'slug' => 'registration-deadline-extension',
            'target_surface' => 'contestants',
            'is_published' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.slug', 'registration-deadline-extension')
        ->assertJsonPath('data.is_published', true);

    $this->assertDatabaseHas('announcements', [
        'slug' => 'registration-deadline-extension',
        'target_surface' => 'contestants',
    ]);
});

test('Content 16.12.3 — Admin can toggle announcement publish status', function (): void {
    $admin = content_user('admin-ann-publish@test.test');
    $ann = AnnouncementModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'draft-announcement',
        'target_surface' => 'all',
        'is_published' => false,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/content/announcements/{$ann->id}/publish")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_published', true);

    $this->assertDatabaseHas('announcements', ['id' => $ann->id, 'is_published' => true]);
});

test('Content 16.12.4 — Public user can list published announcements (200)', function (): void {
    AnnouncementModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'public-news',
        'target_surface' => 'all',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->getJson('/api/v1/content/announcements')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.slug', 'public-news');
});
