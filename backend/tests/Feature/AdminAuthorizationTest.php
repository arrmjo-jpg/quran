<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;

final class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $type, bool $isActive = true): UserModel
    {
        return withSuperAdmin(UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => "authz-{$type}@quran.test",
            'name' => ucfirst($type),
            'type' => $type,
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => $isActive,
        ]));
    }

    public function test_non_admin_authenticated_user_is_rejected_from_admin_routes(): void
    {
        $regularUser = $this->makeUser('contestant');

        $endpoints = [
            ['method' => 'getJson', 'uri' => '/api/v1/admin/auth/me'],
            ['method' => 'getJson', 'uri' => '/api/v1/admin/auth/sessions'],
            ['method' => 'postJson', 'uri' => '/api/v1/admin/auth/mfa/setup'],
            ['method' => 'getJson', 'uri' => '/api/v1/admin/auth/devices'],
        ];

        foreach ($endpoints as $e) {
            $method = $e['method'];
            $response = $this->actingAs($regularUser)->$method($e['uri']);
            $response->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    // NOTE: a second test here previously created a 'contestant'-typed user
    // and asserted one admin endpoint returned 403, to cover a non-admin
    // type other than the then-current 'user'. After the ADR-015 rename
    // both tests construct the identical user and the shorter one became a
    // strict subset of the four-endpoint test above, so it was merged into
    // it rather than left as a duplicate.

    public function test_admin_type_user_can_access_admin_routes(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/auth/me')
            ->assertStatus(200);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $this->makeUser('contestant', isActive: false);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'authz-contestant@quran.test',
            'password' => 'Pass123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DEACTIVATED');
    }

    public function test_active_user_can_still_log_in(): void
    {
        $this->makeUser('contestant');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'authz-contestant@quran.test',
            'password' => 'Pass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_register_response_reflects_the_real_type_and_permissions_not_a_fake_admin_grant(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Contestant',
            'email' => 'new-contestant@quran.test',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'contestant')
            ->assertJsonPath('data.roles', ['contestant'])
            ->assertJsonPath('data.permissions', []);
    }
}
