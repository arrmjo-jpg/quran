<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

uses(RefreshDatabase::class)->group('search', 'phase_16_15');

function search_user(string $email, string $type = 'admin'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Search Admin',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

test('Search 16.15.1 — Admin can trigger reindex job (200)', function (): void {
    $admin = search_user('admin-search-reindex@test.test');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/search/reindex', [
            'index_name' => 'all',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.index_name', 'all');

    $this->assertDatabaseHas('indexing_logs', [
        'index_name' => 'all',
        'action' => 'flush_and_import',
    ]);
});

test('Search 16.15.2 — Admin can list indexing logs', function (): void {
    $admin = search_user('admin-search-logs@test.test');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/search/indexing-logs')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta']);
});

test('Search 16.15.3 — Public multi-entity search endpoint (200)', function (): void {
    $country = CountryModel::query()->create([
        'id' => fake()->uuid(),
        'iso_code' => 'EG',
        'iso3_code' => 'EGY',
        'phone_code' => '+20',
        'is_active' => true,
        'display_order' => 1,
    ]);

    $cUser = search_user('contestant-search@test.test', 'user');

    ContestantModel::query()->create([
        'id' => fake()->uuid(),
        'user_id' => $cUser->id,
        'country_id' => $country->id,
        'full_name' => 'Mahmoud Al-Banna',
        'date_of_birth' => '1995-01-01',
        'gender' => 'male',
        'national_id' => '1234567890',
        'phone_number' => '+201000000000',
    ]);

    $this->getJson('/api/v1/search?q=Mahmoud')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.query', 'Mahmoud')
        ->assertJsonCount(1, 'data.contestants');
});
