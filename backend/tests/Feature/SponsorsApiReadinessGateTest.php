<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Sponsors\Infrastructure\Database\Models\SponsorModel;

uses(RefreshDatabase::class)->group('sponsors', 'phase_16_13');

function sponsor_user(string $email, string $type = 'admin'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Sponsor Admin',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

test('Sponsors 16.13.1 — Admin can list sponsors with pagination', function (): void {
    $admin = sponsor_user('admin-sponsors-list@test.test');
    SponsorModel::query()->create([
        'id' => fake()->uuid(),
        'name' => 'Global Quran Foundation',
        'tier' => 'headline',
        'website_url' => 'https://example.org',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/sponsors')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta']);
});

test('Sponsors 16.13.2 — Admin can create a sponsor (201)', function (): void {
    $admin = sponsor_user('admin-sponsors-create@test.test');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/sponsors', [
            'name' => 'Islamic Bank',
            'tier' => 'gold',
            'website_url' => 'https://bank.example.org',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Islamic Bank')
        ->assertJsonPath('data.tier', 'gold');

    $this->assertDatabaseHas('sponsors', ['name' => 'Islamic Bank', 'tier' => 'gold']);
});

test('Sponsors 16.13.3 — Public user can list active sponsors (200)', function (): void {
    SponsorModel::query()->create([
        'id' => fake()->uuid(),
        'name' => 'Partner Media',
        'tier' => 'partner',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/sponsors')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Partner Media');
});
