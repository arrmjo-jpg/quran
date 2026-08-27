<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\ActivityLogModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

uses(RefreshDatabase::class)->group('core', 'feature', 'activity-log');

/*
|--------------------------------------------------------------------------
| GET /admin/activity-logs — Epic 5 Story 3 (ADR-017 D3)
|--------------------------------------------------------------------------
|
| The endpoint that makes `audit.view` live after it sat dormant since the
| catalogue was written.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function apiUser(string $type = UserType::ADMIN): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'activity-api-'.Str::random(8).'@quran.test',
        'name' => 'Activity Reader',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function apiAs(string $roleName): UserModel
{
    $user = apiUser();
    $role = app(RoleRepositoryContract::class)->findByName($roleName);

    DB::table('role_user')->insert(['role_id' => $role->id->value, 'user_id' => (string) $user->id]);
    app(EffectivePermissionResolver::class)->forget(new UserId((string) $user->id));

    return $user->fresh();
}

/** @param array<string, mixed> $overrides */
function activityRow(array $overrides = []): ActivityLogModel
{
    return ActivityLogModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'action' => 'contestant_updated',
        'entity_type' => 'contestant',
        'entity_id' => (string) Str::uuid(),
        'actor_id' => null,
        'actor_type' => 'system',
        'payload' => ['changed' => ['full_name']],
        'correlation_id' => null,
        'occurred_at' => now()->subMinutes(5),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Authorization — the dormant grant becomes live
|--------------------------------------------------------------------------
*/

test('the feed requires authentication', function (): void {
    $this->getJson('/api/v1/admin/activity-logs')->assertStatus(401);
});

test('super_admin may read the feed', function (): void {
    activityRow();

    $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('every other seeded role is refused', function (string $roleName): void {
    // audit.view is held by super_admin alone, and this is the first route
    // that has ever consulted it.
    $this->actingAs(apiAs($roleName))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertStatus(403);
})->with(['competition_manager', 'data_entry', 'moderator', 'judge', 'evaluator']);

test('a contestant-typed account is refused the admin surface', function (): void {
    $this->actingAs(apiUser(UserType::CONTESTANT))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| The contract — ADR-017 D3, not AuditExplorerPage's invention
|--------------------------------------------------------------------------
*/

test('a row carries exactly the fields the ADR defines', function (): void {
    activityRow();

    $response = $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertOk();

    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'id', 'action', 'entity_type', 'entity_id',
        'actor_id', 'actor_type', 'payload', 'correlation_id',
        'occurred_at', 'recorded_at',
    ]);
});

test('result and user_role are absent, deliberately', function (): void {
    activityRow();

    $row = $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertOk()
        ->json('data.0');

    // The screen invented both for an endpoint that never existed. `result`
    // belongs to HTTP; a dispatched domain event has already happened.
    // `user_role` would be a snapshot that stops being true.
    expect($row)->not->toHaveKey('result');
    expect($row)->not->toHaveKey('user_role');
});

test('occurred_at and recorded_at are both reported and differ', function (): void {
    activityRow(['occurred_at' => now()->subMonths(3)]);

    $row = $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertOk()
        ->json('data.0');

    expect($row['occurred_at'])->not->toBe($row['recorded_at']);
});

test('actor names are resolved once for the page, not per row', function (): void {
    $actor = apiUser();

    foreach (range(1, 5) as $ignored) {
        activityRow(['actor_id' => (string) $actor->id, 'actor_type' => 'user']);
    }

    $reader = apiAs('super_admin');

    // Warm the permission resolver so its own queries do not count.
    $this->actingAs($reader)->getJson('/api/v1/admin/activity-logs')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($reader)->getJson('/api/v1/admin/activity-logs')->assertOk();

    $queries = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($response->json('actors.'.$actor->id))->toBe('Activity Reader');

    // Five rows by one actor must not be five lookups. The batched contract
    // is why CoreServiceContract::findResolvedByIds takes an array.
    expect($queries)->toBeLessThan(8);
});

/*
|--------------------------------------------------------------------------
| Filters and ordering
|--------------------------------------------------------------------------
*/

test('the feed is newest first by when things happened', function (): void {
    $old = activityRow(['occurred_at' => now()->subDays(10), 'action' => 'contestant_created']);
    $new = activityRow(['occurred_at' => now()->subHour(), 'action' => 'contestant_deleted']);

    $response = $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs')
        ->assertOk();

    expect($response->json('data.0.id'))->toBe((string) $new->id);
    expect($response->json('data.1.id'))->toBe((string) $old->id);
});

test('one entity history can be read', function (): void {
    $subject = (string) Str::uuid();
    activityRow(['entity_id' => $subject]);
    activityRow();

    $response = $this->actingAs(apiAs('super_admin'))
        ->getJson("/api/v1/admin/activity-logs?entity_type=contestant&entity_id={$subject}")
        ->assertOk();

    expect($response->json('meta.pagination.total'))->toBe(1);
    expect($response->json('data.0.entity_id'))->toBe($subject);
});

test('an entity id without its type is refused', function (): void {
    // The two travel together: an id alone could collide across tables.
    $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs?entity_id='.Str::uuid())
        ->assertStatus(422);
});

test('the window filters on when things happened, not when they were written', function (): void {
    // A row recorded now but describing something from months ago belongs in
    // the old window, not today's.
    activityRow(['occurred_at' => now()->subMonths(6)]);
    activityRow(['occurred_at' => now()->subHour()]);

    $response = $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs?from='.now()->subDay()->toDateString())
        ->assertOk();

    expect($response->json('meta.pagination.total'))->toBe(1);
});

test('the feed filters by actor, action and correlation id', function (string $query, int $expected): void {
    $actor = apiUser();
    $correlation = (string) Str::uuid();

    activityRow(['actor_id' => (string) $actor->id, 'actor_type' => 'user']);
    activityRow(['action' => 'contestant_deleted']);
    activityRow(['correlation_id' => $correlation]);

    $query = str_replace(['ACTOR', 'CORRELATION'], [(string) $actor->id, $correlation], $query);

    $this->actingAs(apiAs('super_admin'))
        ->getJson("/api/v1/admin/activity-logs?{$query}")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', $expected);
})->with([
    'by actor' => ['actor_id=ACTOR', 1],
    'by action' => ['action=contestant_deleted', 1],
    'by correlation' => ['correlation_id=CORRELATION', 1],
]);

test('the feed paginates and caps per_page', function (): void {
    foreach (range(1, 12) as $n) {
        activityRow(['occurred_at' => now()->subMinutes($n)]);
    }

    $response = $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs?per_page=5')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.pagination.total'))->toBe(12);
    expect($response->json('meta.pagination.last_page'))->toBe(3);

    $this->actingAs(apiAs('super_admin'))
        ->getJson('/api/v1/admin/activity-logs?per_page=500')
        ->assertStatus(422);
});

test('there is no way to write to the log through the API', function (): void {
    $admin = apiAs('super_admin');

    // Rows come from the listener. A log that can be posted to is not a
    // record of what happened.
    $this->actingAs($admin)->postJson('/api/v1/admin/activity-logs', [])->assertStatus(405);
    $this->actingAs($admin)->deleteJson('/api/v1/admin/activity-logs')->assertStatus(405);
});
