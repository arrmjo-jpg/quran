<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Notifications\Application\Jobs\SendNotificationJob;
use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\ValueObjects\NotificationChannel;
use Modules\Notifications\Domain\ValueObjects\NotificationId;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('notifications', 'feature', 'golden-master');

/*
|--------------------------------------------------------------------------
| Notification delivery — what is true BEFORE Epic 8 — ADR-020
|--------------------------------------------------------------------------
|
| DELIBERATELY SMALL, AND NOT A DUPLICATE. NotificationsApiReadinessGateTest
| already covers the endpoints in fourteen tests: listing, the three filters,
| the single read, 404s, the retry's 409 guards, and the contestant-facing
| reads. Epic 8 does not change those shapes, so none of it is repeated.
|
| WHAT THAT SUITE CANNOT SEE, AND WHY THIS FILE EXISTS.
|
| Its test 16.10.7 is called "Admin can retry a failed notification". It
| asserts the response says `retrying` and that the row now says `retrying`.
| Both are true. Neither is the point: the endpoint dispatches NOTHING — the
| line after the status write is a literal
| `// TODO: dispatch RetryNotificationJob::dispatch($log->id)`.
|
| So the existing test passes over an inert feature, and would ALSO pass once
| the feature works. It can distinguish neither the defect nor its repair. The
| assertions below can, because they assert about the queue rather than about
| the response.
|
| Three facts are recorded, all of which ADR-020 sets out to change:
|
|   D5  retry dispatches nothing
|   D2  the one real send writes no notification log
|   D6  `retrying` is a status the DOMAIN never produces — only the
|       controller does, by reaching past the aggregate into Eloquent
|
| DESCRIPTIVE, NOT PRESCRIPTIVE. Every assertion here records behaviour the
| epic intends to invert. They are written now because a golden master taken
| after the change proves nothing.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function goldenNotifAdmin(): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'notif-golden@quran.test',
        'name' => 'Notification Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function goldenFailedLog(string $userId): NotificationLogModel
{
    return NotificationLogModel::query()->create([
        'id' => (string) Uuid::v7(),
        'user_id' => $userId,
        'channel' => 'email',
        'template_key' => 'invitation.created',
        'payload' => ['subject' => 'Golden master', 'to' => 'someone@quran.test'],
        'status' => 'failed',
        'sent_at' => null,
        'error' => 'SMTP connection refused',
    ]);
}

test('GOLDEN MASTER: retrying a notification queues nothing at all', function (): void {
    Queue::fake();
    Bus::fake();

    $admin = goldenNotifAdmin();
    $log = goldenFailedLog($admin->id);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertOk()
        ->assertJsonPath('success', true);

    // BEFORE Epic 8: the response says "Notification queued for retry" and
    // nothing is queued. This is the assertion the existing readiness test
    // does not make, and the reason that suite cannot see the defect.
    //
    // AFTER (ADR-020 D5) this must fail and be inverted to assert the send
    // job WAS pushed.
    Queue::assertNothingPushed();
    Bus::assertNothingDispatched();
})->group('golden-master');

test('GOLDEN MASTER: the retry writes a status the domain does not model', function (): void {
    $admin = goldenNotifAdmin();
    $log = goldenFailedLog($admin->id);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertOk();

    // BEFORE Epic 8: 'retrying'. The controller reaches NotificationLogModel
    // directly, past its own repository and aggregate, which is the only
    // reason it could write a state no invariant permits.
    // AFTER (D6): a retry returns the row to `queued`, and `retrying` exists
    // nowhere in the codebase.
    expect($log->fresh()->status)->toBe('queued');

    // The aggregate's own vocabulary, pinned beside it so the divergence is
    // visible in one place: these are the only statuses it can produce.
    $entity = NotificationLog::create(
        id: NotificationId::generate(),
        userId: (string) $admin->id,
        channel: new NotificationChannel('email'),
        templateKey: 'invitation.created',
        payload: [],
    );

    expect($entity->getStatus())->toBe('queued');
    $entity->markSent(now()->toIso8601String());
    expect($entity->getStatus())->toBe('sent');
    $entity->markFailed('nope');
    expect($entity->getStatus())->toBe('failed');
})->group('golden-master');

test('GOLDEN MASTER: the one real send writes no notification log', function (): void {
    Mail::fake();
    Queue::fake();

    $admin = goldenNotifAdmin();

    // Creating an administrator is the only thing on the platform that sends
    // mail. It goes out through Mail::to()->send() directly, synchronously,
    // and the Notifications module never hears about it.
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'email' => 'invited-golden@quran.test',
        'name' => 'Invited Person',
        'type' => 'admin',
        'preferred_locale' => 'ar',
    ])->assertCreated();

    // BEFORE Epic 8: mail was sent and nothing was logged — the table the
    // module exists to fill stayed empty.
    // AFTER (D2, D3): the send is recorded at `queued` and handed to a job.
    // The row is NOT `sent` at this point, and that is the correct answer
    // rather than a gap: the request returns before delivery is attempted.
    expect(NotificationLogModel::query()->count())->toBe(1);

    $row = NotificationLogModel::query()->sole();
    expect($row->channel)->toBe('email')
        ->and($row->template_key)->toBe('invitation.created')
        ->and($row->status)->toBe('queued')
        ->and($row->sent_at)->toBeNull();

    // AFTER (D3): the delivery is on the queue rather than in the request.
    Queue::assertPushed(SendNotificationJob::class);

    // The recipient's address is not stored. The log is read by administrators
    // looking at other people's notifications, and it already carries user_id.
    expect(json_encode($row->payload))->not->toContain('invited-golden@quran.test');
})->group('golden-master');
