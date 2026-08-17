<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;

final class ContestantsTest extends TestCase
{
    use RefreshDatabase;

    private ?UserModel $sharedAdmin = null;

    private function admin(): UserModel
    {
        return $this->sharedAdmin ??= withSuperAdmin(UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'contestants-admin@quran.test',
            'name' => 'Contestants Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]));
    }

    private function countryId(): string
    {
        $repository = app(CountryRepositoryContract::class);
        (new CountriesSeeder)->run($repository);

        return $repository->findByIso2(new CountryIso2('JO'))->id->value;
    }

    private function createContestant(string $fullName, string $phoneNumber): string
    {
        $user = UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => strtolower(str_replace(' ', '.', $fullName)).'@example.com',
            'name' => $fullName,
            'type' => 'contestant',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $repository = app(ContestantRepositoryContract::class);
        $contestant = Contestant::create(
            id: ContestantId::generate(),
            userId: $user->id,
            countryId: $this->countryId(),
            fullName: $fullName,
            dateOfBirth: new BirthDate('2000-05-15'),
            gender: new Gender('male'),
            phoneNumber: $phoneNumber,
            nationalId: 'NAT-'.fake()->numerify('######')
        );
        $repository->save($contestant);

        return $contestant->id->value;
    }

    public function test_admin_can_search_contestants_by_name(): void
    {
        $this->createContestant('Ahmad Al-Mansoor', '+962790000001');
        $this->createContestant('Yousef Al-Amin', '+962790000002');

        $response = $this->actingAs($this->admin())->getJson('/api/v1/admin/contestants?q=Ahmad');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Ahmad Al-Mansoor');
    }

    public function test_admin_search_with_no_query_returns_all_contestants(): void
    {
        $this->createContestant('Contestant One', '+962790000003');
        $this->createContestant('Contestant Two', '+962790000004');

        $this->actingAs($this->admin())->getJson('/api/v1/admin/contestants')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_fetch_a_single_contestant_by_id(): void
    {
        $id = $this->createContestant('Solo Contestant', '+962790000005');

        $response = $this->actingAs($this->admin())->getJson("/api/v1/admin/contestants/{$id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.full_name', 'Solo Contestant');

        expect($response->json('data.national_id'))->not->toBeNull();
    }

    public function test_admin_fetching_unknown_contestant_id_returns_404(): void
    {
        $this->actingAs($this->admin())->getJson('/api/v1/admin/contestants/'.((string) Uuid::v7()))
            ->assertStatus(404);
    }

    public function test_non_admin_cannot_access_admin_contestants_endpoints(): void
    {
        $regularUser = UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'blocked@quran.test',
            'name' => 'Blocked',
            'type' => 'contestant',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $this->actingAs($regularUser)->getJson('/api/v1/admin/contestants')
            ->assertStatus(403);
    }

    public function test_eligibility_check_returns_no_active_season_error_when_none_is_open(): void
    {
        $user = UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'no-season@quran.test',
            'name' => 'No Season',
            'type' => 'contestant',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $repository = app(ContestantRepositoryContract::class);
        $contestant = Contestant::create(
            id: ContestantId::generate(),
            userId: $user->id,
            countryId: $this->countryId(),
            fullName: 'No Season Contestant',
            dateOfBirth: new BirthDate('2000-05-15'),
            gender: new Gender('male'),
            phoneNumber: '+962790000006'
        );
        $repository->save($contestant);

        $this->actingAs($user)->getJson('/api/v1/contestant/eligibility')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ACTIVE_SEASON');
    }

    public function test_contestant_model_is_findable_via_query(): void
    {
        $id = $this->createContestant('Query Check', '+962790000007');

        expect(ContestantModel::query()->find($id))->not->toBeNull();
    }
}
