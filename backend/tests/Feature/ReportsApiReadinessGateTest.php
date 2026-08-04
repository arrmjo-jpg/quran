<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('reports', 'phase_16_14');

function reports_user(string $email, string $type = 'admin'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Reports Admin',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

test('Reports 16.14.1 — Admin can view executive summary metrics', function (): void {
    $admin = reports_user('admin-reports-summary@test.test');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/reports/summary')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'total_contestants',
                'total_applications',
                'applications_by_status',
                'total_evaluations',
                'completed_evaluations',
            ],
        ]);
});

test('Reports 16.14.2 — Admin can create an export job (201)', function (): void {
    $admin = reports_user('admin-reports-export@test.test');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/reports/exports', [
            'type' => 'contestants_csv',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.type', 'contestants_csv')
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('exports', [
        'user_id' => $admin->id,
        'type' => 'contestants_csv',
    ]);
});
