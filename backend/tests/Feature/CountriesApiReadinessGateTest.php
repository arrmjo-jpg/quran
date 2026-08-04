<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;

uses(RefreshDatabase::class)->group('countries_gate', 'api');

test('Countries API Readiness Gate: public GET /api/v1/countries returns paginated localized responses and supports ETag 304', function (): void {
    $repository = app(CountryRepositoryContract::class);
    (new CountriesSeeder)->run($repository);

    // 1. Arabic Request
    $arResponse = $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/countries?per_page=5');
    $arResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.pagination.per_page', 5)
        ->assertJsonPath('data.0.name', 'الأردن');

    $etag = $arResponse->headers->get('ETag');
    expect($etag)->not->toBeNull();

    // 2. ETag 304 Not Modified check
    $etagResponse = $this->withHeader('If-None-Match', $etag)->getJson('/api/v1/countries?per_page=5');
    $etagResponse->assertStatus(304);

    // 3. English Request
    $enResponse = $this->withHeaders([
        'Accept-Language' => 'en',
        'If-None-Match' => '',
    ])->getJson('/api/v1/countries?per_page=5');
    $enResponse->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Jordan');
});

test('Countries API Readiness Gate: admin store and deactivate policy endpoints work correctly', function (): void {
    $admin = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'admin@quranplatform.com',
        'name' => 'System Admin',
        'type' => 'admin',
        'password_hash' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    // 1. Admin Create Country
    $createResponse = $this->actingAs($admin)->postJson('/api/v1/admin/countries', [
        'iso2' => 'QA',
        'iso3' => 'QAT',
        'phone_code' => '+974',
        'flag_url' => 'https://cdn.quranplatform.com/flags/qa.svg',
        'name_ar' => 'قطر',
        'name_en' => 'Qatar',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.iso2', 'QA');

    $countryId = $createResponse->json('data.id');

    // 2. Deactivate Country per ADR-005
    $deactivateResponse = $this->actingAs($admin)->patchJson("/api/v1/admin/countries/{$countryId}/deactivate");
    $deactivateResponse->assertStatus(200)
        ->assertJsonPath('data.is_active', false);
});
