<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Tests\TestCase;

final class DeviceTrustTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->adminUser = UserModel::query()->create([
            'id' => '00000000-0000-0000-0000-000000000077',
            'email' => 'trust-admin@quran.test',
            'name' => 'Trust Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);
    }

    public function test_user_can_trust_current_device(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders([
                'X-Device-ID' => 'device-mac-book-pro-16',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)',
            ])
            ->postJson('/api/v1/admin/auth/devices/trust');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'user_id', 'trust_token', 'ip', 'user_agent', 'expires_at']]);
    }

    public function test_user_can_list_trusted_devices(): void
    {
        $this->actingAs($this->adminUser)
            ->withHeaders(['X-Device-ID' => 'device-1'])
            ->postJson('/api/v1/admin/auth/devices/trust');

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/auth/devices');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_revoke_trusted_device(): void
    {
        $trustResponse = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Device-ID' => 'device-to-revoke'])
            ->postJson('/api/v1/admin/auth/devices/trust');

        $deviceId = $trustResponse->json('data.id');

        $revokeResponse = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/auth/devices/{$deviceId}");

        $revokeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify device list is now empty
        $listResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/auth/devices');

        $listResponse->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
