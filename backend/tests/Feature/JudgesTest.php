<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;

final class JudgesTest extends TestCase
{
    use RefreshDatabase;

    private ?UserModel $sharedAdmin = null;

    private function admin(): UserModel
    {
        return $this->sharedAdmin ??= UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => 'judges-admin@quran.test',
            'name' => 'Judges Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);
    }

    private function judgeUser(string $email = 'judge-candidate@quran.test'): UserModel
    {
        return UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => $email,
            'name' => 'Judge Candidate',
            'type' => 'user',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);
    }

    public function test_creating_a_judge_generates_a_real_uuid_not_a_fake_one(): void
    {
        $user = $this->judgeUser();

        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/judges', [
            'user_id' => $user->id,
            'full_name' => 'Sheikh Test Judge',
            'specialization' => 'tajweed',
        ]);

        $response->assertStatus(201);
        expect(Uuid::isValid($response->json('data.id')))->toBeTrue();
    }

    public function test_creating_a_judge_with_a_nonexistent_user_id_returns_422_not_a_crash(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/judges', [
            'user_id' => (string) Uuid::v7(),
            'full_name' => 'Ghost Judge',
            'specialization' => 'hifz',
        ])->assertStatus(422);
    }

    public function test_creating_a_second_judge_profile_for_the_same_user_is_rejected(): void
    {
        $user = $this->judgeUser();

        $this->actingAs($this->admin())->postJson('/api/v1/admin/judges', [
            'user_id' => $user->id,
            'full_name' => 'First Profile',
            'specialization' => 'tajweed',
        ])->assertStatus(201);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/judges', [
            'user_id' => $user->id,
            'full_name' => 'Duplicate Profile',
            'specialization' => 'hifz',
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_create_a_judge(): void
    {
        $regularUser = $this->judgeUser('blocked@quran.test');
        $target = $this->judgeUser('target@quran.test');

        $this->actingAs($regularUser)->postJson('/api/v1/admin/judges', [
            'user_id' => $target->id,
            'full_name' => 'Blocked Attempt',
            'specialization' => 'sawt',
        ])->assertStatus(403);
    }

    public function test_a_user_with_no_judge_profile_gets_404_from_judge_profile_endpoint(): void
    {
        $user = $this->judgeUser();

        $this->actingAs($user)->getJson('/api/v1/judge/profile')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_A_JUDGE');
    }
}
