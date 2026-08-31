<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;

uses(RefreshDatabase::class)->group('notifications', 'phase_16_10');

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function notif_user(string $email, string $type = 'contestant'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Notif Test',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function notif_log(
    string $userId,
    string $channel = 'email',
    string $status = 'sent',
    string $templateKey = 'application.approved'
): NotificationLogModel {
    return NotificationLogModel::query()->create([
        'id' => fake()->uuid(),
        'user_id' => $userId,
        'channel' => $channel,
        'template_key' => $templateKey,
        'payload' => ['subject' => 'Test notification', 'to' => 'test@example.com'],
        'status' => $status,
        'sent_at' => $status === 'sent' ? now() : null,
        'error' => $status === 'failed' ? 'SMTP connection refused' : null,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — LIST
// ─────────────────────────────────────────────────────────────────────────────

test('Notifications 16.10.1 — Admin can list all notification logs with pagination', function (): void {
    $admin = notif_user('admin-list-notif@test.test', 'admin');
    $user = notif_user('contestant-notif1@test.test');

    notif_log($user->id, 'email', 'sent');
    notif_log($user->id, 'sms', 'failed');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/notifications')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success', 'data', 'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);
});

test('Notifications 16.10.2 — Admin can filter logs by channel=email', function (): void {
    $admin = notif_user('admin-filter-email@test.test', 'admin');
    $user = notif_user('contestant-notif2@test.test');

    notif_log($user->id, 'email', 'sent');
    notif_log($user->id, 'sms', 'sent');
    notif_log($user->id, 'push', 'sent');

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/notifications?channel=email');
    $response->assertStatus(200);

    $channels = collect($response->json('data'))->pluck('channel');
    expect($channels->every(fn ($c) => $c === 'email'))->toBeTrue();
});

test('Notifications 16.10.3 — Admin can filter logs by status=failed', function (): void {
    $admin = notif_user('admin-filter-failed@test.test', 'admin');
    $user = notif_user('contestant-notif3@test.test');

    notif_log($user->id, 'email', 'sent');
    notif_log($user->id, 'email', 'failed');

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/notifications?status=failed');
    $response->assertStatus(200);

    $statuses = collect($response->json('data'))->pluck('status');
    expect($statuses->every(fn ($s) => $s === 'failed'))->toBeTrue();
});

test('Notifications 16.10.4 — Admin can filter logs by user_id', function (): void {
    $admin = notif_user('admin-filter-user@test.test', 'admin');
    $user1 = notif_user('contestant-notif4a@test.test');
    $user2 = notif_user('contestant-notif4b@test.test');

    notif_log($user1->id, 'email', 'sent');
    notif_log($user2->id, 'email', 'sent');

    $response = $this->actingAs($admin)->getJson("/api/v1/admin/notifications?user_id={$user1->id}");
    $response->assertStatus(200);

    $userIds = collect($response->json('data'))->pluck('user_id');
    expect($userIds->every(fn ($id) => $id === $user1->id))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — SHOW
// ─────────────────────────────────────────────────────────────────────────────

test('Notifications 16.10.5 — Admin can view a single log with full payload', function (): void {
    $admin = notif_user('admin-show-notif@test.test', 'admin');
    $user = notif_user('contestant-notif5@test.test');
    $log = notif_log($user->id, 'email', 'sent');

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/notifications/{$log->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $log->id)
        ->assertJsonPath('data.channel', 'email')
        ->assertJsonStructure(['data' => ['payload']]); // Admin sees payload
});

test('Notifications 16.10.6 — Admin gets 404 for non-existent log', function (): void {
    $admin = notif_user('admin-404-notif@test.test', 'admin');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/notifications/'.fake()->uuid())
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — RETRY
// ─────────────────────────────────────────────────────────────────────────────

test('Notifications 16.10.7 — Admin can retry a failed notification', function (): void {
    // THE QUEUE IS FAKED SO THE RESPONSE CAN BE PINNED. Under test
    // QUEUE_CONNECTION is `sync`, so a real dispatch would run the job inline,
    // mark the row `sent`, and this endpoint would answer `sent` -- a state
    // that never occurs against the Redis queue the platform actually runs.
    // The contract being pinned here is the one production sees.
    Queue::fake();

    $admin = notif_user('admin-retry-notif@test.test', 'admin');

    // A PENDING ACCOUNT AND A REAL TEMPLATE, changed in this story.
    //
    // The fixture used to be a claimed contestant with `application.approved`,
    // a template nothing sends and nothing can rebuild. That was harmless
    // while the endpoint only wrote a status; now that a retry actually
    // re-sends, it stands for a case the platform has to refuse, and the
    // success path needs a notification that can genuinely be sent again.
    //
    // `invitation.created` about an unclaimed account is what a retryable
    // notification looks like -- it is the only mail the platform sends.
    $user = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'invitee-notif7@test.test',
        'name' => 'Notif Test',
        'type' => 'admin',
        'password_hash' => null,
        'is_active' => false,
    ]);
    $log = notif_log($user->id, 'email', 'failed', 'invitation.created');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        // BEFORE Epic 8: 'retrying' -- a fourth status the domain never
        // modelled, which this test pinned as correct because it asserted the
        // response rather than the effect. It passed over an inert feature.
        // AFTER (ADR-020 D6): a retry returns the row to `queued`, the
        // aggregate's own starting state.
        //
        // What this test still cannot see is whether anything is DISPATCHED.
        // That is asserted in NotificationDeliveryGoldenMasterTest and in
        // NotificationRetryTest, both of which fake the queue -- deliberately
        // kept there rather than duplicated here, because this file is about
        // the API's shapes.
        ->assertJsonPath('data.status', 'queued');

    $this->assertDatabaseHas('notification_logs', ['id' => $log->id, 'status' => 'queued']);
});

test('Notifications 16.10.8 — Admin cannot retry a non-failed notification (409)', function (): void {
    $admin = notif_user('admin-retry-409@test.test', 'admin');
    $user = notif_user('contestant-notif8@test.test');
    $log = notif_log($user->id, 'email', 'sent'); // already sent

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'NOT_FAILED');
});

test('Notifications 16.10.9 — Admin cannot retry a queued notification (409)', function (): void {
    $admin = notif_user('admin-retry-queued@test.test', 'admin');
    $user = notif_user('contestant-notif9@test.test');
    $log = notif_log($user->id, 'sms', 'queued');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'NOT_FAILED');
});

// ─────────────────────────────────────────────────────────────────────────────
// CONTESTANT — OWN HISTORY
// ─────────────────────────────────────────────────────────────────────────────

test('Notifications 16.10.10 — Contestant can view own notification history', function (): void {
    $user = notif_user('contestant-own-notif@test.test');
    notif_log($user->id, 'email', 'sent');
    notif_log($user->id, 'push', 'sent');

    $response = $this->actingAs($user)->getJson('/api/v1/contestant/notifications');
    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $userIds = collect($response->json('data'))->pluck('user_id');
    expect($userIds->every(fn ($id) => $id === $user->id))->toBeTrue();
});

test('Notifications 16.10.11 — Contestant cannot see other users\' notifications (list scoped)', function (): void {
    $user1 = notif_user('contestant-scope1@test.test');
    $user2 = notif_user('contestant-scope2@test.test');
    notif_log($user1->id, 'email', 'sent');
    notif_log($user2->id, 'email', 'sent');

    $response = $this->actingAs($user1)->getJson('/api/v1/contestant/notifications');
    $response->assertStatus(200);

    $userIds = collect($response->json('data'))->pluck('user_id');
    expect($userIds)->not->toContain($user2->id);
});

test('Notifications 16.10.12 — Contestant can view their own single notification', function (): void {
    $user = notif_user('contestant-single-notif@test.test');
    $log = notif_log($user->id, 'email', 'sent');

    $this->actingAs($user)
        ->getJson("/api/v1/contestant/notifications/{$log->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $log->id);
});

test('Notifications 16.10.13 — Contestant cannot view another user\'s notification (403)', function (): void {
    $user1 = notif_user('contestant-notif-owner@test.test');
    $user2 = notif_user('contestant-notif-spy@test.test');
    $log = notif_log($user1->id, 'email', 'sent');

    $this->actingAs($user2)
        ->getJson("/api/v1/contestant/notifications/{$log->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

test('Notifications 16.10.14 — Contestant can filter own notifications by channel', function (): void {
    $user = notif_user('contestant-filter-channel@test.test');
    notif_log($user->id, 'email', 'sent');
    notif_log($user->id, 'sms', 'sent');

    $response = $this->actingAs($user)->getJson('/api/v1/contestant/notifications?channel=sms');
    $response->assertStatus(200);

    $channels = collect($response->json('data'))->pluck('channel');
    expect($channels->every(fn ($c) => $c === 'sms'))->toBeTrue();
});
