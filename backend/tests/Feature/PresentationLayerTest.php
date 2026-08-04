<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;

uses(RefreshDatabase::class)->group('presentation', 'api');

test('public endpoint GET /api/v1/countries returns seeded ISO reference countries', function (): void {
    $repository = app(CountryRepositoryContract::class);
    (new CountriesSeeder)->run($repository);

    $response = $this->getJson('/api/v1/countries');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'iso2', 'iso3', 'phone_code', 'flag_url', 'is_active'],
            ],
        ]);
});

test('public endpoint POST /api/v1/auth/register creates user and returns UserResource', function (): void {
    $payload = [
        'name' => 'Salem Al-Ahmad',
        'email' => 'salem@example.com',
        'password' => 'SecurePass123!',
        'locale' => 'ar',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.email', 'salem@example.com')
        ->assertJsonPath('data.name', 'Salem Al-Ahmad');

    $this->assertDatabaseHas('users', [
        'email' => 'salem@example.com',
    ]);
});
