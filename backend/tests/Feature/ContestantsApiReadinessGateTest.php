<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('contestants_gate', 'api');

test('Contestants API Readiness Gate: complete contestant profile creation, completeness calculation, and age eligibility check', function (): void {
    $user = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'contestant@example.com',
        'name' => 'Kareem Al-Mansoor',
        'type' => 'user',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $repository = app(CountryRepositoryContract::class);
    (new CountriesSeeder)->run($repository);
    $country = $repository->findByIso2(new CountryIso2('JO'));

    // Eligibility is evaluated against the active season's start date,
    // not a hardcoded value — a season must be active for the gate below.
    SeasonModel::query()->create([
        'id' => (string) Uuid::v7(),
        'slug' => 'gate-season',
        'year' => 2026,
        'registration_start' => '2026-01-01 00:00:00',
        'registration_end' => '2026-01-15 00:00:00',
        'start_date' => '2026-08-01 00:00:00',
        'end_date' => '2026-09-01 00:00:00',
        'status' => 'registration_open',
        'is_active' => true,
    ]);

    // 1. Create Profile
    $profileResponse = $this->actingAs($user)->postJson('/api/v1/contestant/profile', [
        'country_id' => $country->id->value,
        'full_name' => 'Kareem Al-Mansoor',
        'date_of_birth' => '2000-05-15',
        'gender' => 'male',
        'phone_number' => '+962790000000',
        'national_id' => 'NAT-100200300',
        'photo_media_asset_id' => null,
    ]);

    $profileResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.full_name', 'Kareem Al-Mansoor')
        ->assertJsonPath('data.profile_completeness.completeness_percent', 80);

    // 2. Fetch Profile GET /contestant/profile
    $getProfileResponse = $this->actingAs($user)->getJson('/api/v1/contestant/profile');
    $getProfileResponse->assertStatus(200)
        ->assertJsonPath('data.phone_number', '+962790000000');

    // 3. Check Eligibility GET /contestant/eligibility
    $eligibilityResponse = $this->actingAs($user)->getJson('/api/v1/contestant/eligibility');
    $eligibilityResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_eligible', true)
        ->assertJsonPath('data.age', 26);
});
