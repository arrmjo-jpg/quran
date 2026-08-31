<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Application\UseCases\IssueInvitationUseCase;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Infrastructure\Database\Models\InvitationModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Mail\InvitationMail;
use Modules\Notifications\Application\Jobs\SendNotificationJob;
use Modules\Notifications\Contracts\NotificationMailRegistryContract;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\Exceptions\NotificationNotRetryableException;
use Modules\Notifications\Domain\ValueObjects\NotificationChannel;
use Modules\Notifications\Domain\ValueObjects\NotificationId;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('notifications', 'feature');

/*
|--------------------------------------------------------------------------
| Retry — ADR-020 D5
|--------------------------------------------------------------------------
|
| The golden master proves ONE fact: the endpoint no longer queues nothing.
| This file covers what retry has to get right beyond that, and most of it is
| about refusing rather than sending.
|
| THE MESSAGE IS REBUILT, NOT REPLAYED, and that is forced rather than chosen.
| Nothing keeps the original Mailable, and for the platform's only real
| template nothing could: IssueInvitationUseCase returns the plaintext accept
| token once and never stores it. So retrying an invitation means issuing a new
| one, and the tests below pin the consequences -- including the one that would
| otherwise be a security regression: re-issuing must REPLACE the open
| invitation, not add a second live token.
|
| WHAT A REFUSAL MUST NOT DO is move the log. Every refusal path here asserts
| the row is still `failed` and still carries its reason. A retry that reported
| `queued` and dispatched nothing is the exact defect Epic 8 exists to remove;
| reintroducing it through an error path would be the same lie in a new place.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function retryAdmin(): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'retry-admin@quran.test',
        'name' => 'Retry Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

/** Unclaimed, which is what having an invitation means. */
function retryInvitee(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'retry-invitee@quran.test',
        'name' => 'Retry Invitee',
        'type' => 'admin',
        'password_hash' => null,
        'is_active' => false,
    ]);
}

function retryFailedLog(string $userId, string $templateKey = 'invitation.created'): NotificationLogModel
{
    return NotificationLogModel::query()->create([
        'id' => (string) Uuid::v7(),
        'user_id' => $userId,
        'channel' => 'email',
        'template_key' => $templateKey,
        'payload' => ['name' => 'Retry Invitee', 'expires_in_days' => 7],
        'status' => 'failed',
        'sent_at' => null,
        'error' => 'Connection could not be established with host mailpit',
    ]);
}

/*
|--------------------------------------------------------------------------
| It sends
|--------------------------------------------------------------------------
*/

test('a retry queues the send to the address the log does not store', function (): void {
    Queue::fake();

    $invitee = retryInvitee();
    $log = retryFailedLog($invitee->id);

    $this->actingAs(retryAdmin())
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertOk();

    // The recipient is resolved from user_id at retry time. It is nowhere in
    // the log -- administrators read other people's notification rows, so the
    // address is deliberately not stored, and the golden master asserts that.
    Queue::assertPushed(
        SendNotificationJob::class,
        fn (SendNotificationJob $job): bool => $job->notificationId === $log->id
            && $job->recipient === 'retry-invitee@quran.test'
            && $job->mail instanceof InvitationMail
    );
});

test('a retry returns the log to queued and drops the superseded reason', function (): void {
    Queue::fake();

    $invitee = retryInvitee();
    $log = retryFailedLog($invitee->id);

    $this->actingAs(retryAdmin())
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertOk()
        ->assertJsonPath('data.status', 'queued');

    $row = $log->fresh();

    expect($row->status)->toBe('queued')
        // The reason described the attempt that has just been superseded. A
        // row reading `queued` while still carrying "SMTP refused" describes
        // two different attempts at once.
        ->and($row->error)->toBeNull()
        // And a queued row must not claim a delivery time. That combination
        // would be the same class of untruth in the other direction.
        ->and($row->sent_at)->toBeNull();
});

test('end to end, without faking the queue, the mail goes and the log ends sent', function (): void {
    // Mail is faked; the QUEUE IS NOT. QUEUE_CONNECTION is `sync` under test,
    // so the job runs inline and this exercises dispatch, handle() and
    // markSent() together. Every other test here stops at the push, which
    // cannot tell a job that runs from one that is merely created.
    Mail::fake();

    $invitee = retryInvitee();
    $log = retryFailedLog($invitee->id);

    $this->actingAs(retryAdmin())
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertOk();

    Mail::assertSent(
        InvitationMail::class,
        fn (InvitationMail $mail): bool => $mail->hasTo('retry-invitee@quran.test')
    );

    $row = $log->fresh();

    expect($row->status)->toBe('sent')
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->error)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| What re-issuing must not do
|--------------------------------------------------------------------------
*/

test('a retry issues a new invitation and kills the one that failed to arrive', function (): void {
    Queue::fake();

    $invitee = retryInvitee();
    $original = app(IssueInvitationUseCase::class)->execute((string) $invitee->id)['token'];

    $log = retryFailedLog($invitee->id);

    $this->actingAs(retryAdmin())
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertOk();

    // ONE invitation, not two. Re-issuing replaces in place -- without that, a
    // retry would leave two working tokens for one account and the older would
    // live out its full seven days in whatever mailbox eventually received it.
    // InvitationReissueGoldenMasterTest records the behaviour this replaced.
    expect(InvitationModel::query()->where('user_id', $invitee->id)->count())->toBe(1)
        ->and(app(InvitationRepositoryContract::class)->findByToken($original))->toBeNull();

    Queue::assertPushed(SendNotificationJob::class);
});

/*
|--------------------------------------------------------------------------
| What it refuses, and what it leaves behind when it does
|--------------------------------------------------------------------------
*/

test('a template nobody can rebuild is refused, and the log does not move', function (): void {
    Queue::fake();

    $invitee = retryInvitee();
    $log = retryFailedLog($invitee->id, 'application.approved');

    $this->actingAs(retryAdmin())
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'NOT_RETRYABLE');

    $row = $log->fresh();

    // THE POINT OF THIS TEST. A refusal that had moved the row to `queued`
    // would be the original defect wearing an error code: a log claiming a
    // delivery is pending with nothing on the queue to deliver it.
    expect($row->status)->toBe('failed')
        ->and($row->error)->toBe('Connection could not be established with host mailpit');

    Queue::assertNothingPushed();
});

test('an account claimed since the invitation failed cannot be re-invited', function (): void {
    Queue::fake();

    $invitee = retryInvitee();
    $log = retryFailedLog($invitee->id);

    // The invitation never arrived, but the account was activated by some
    // other route. Re-inviting it would be a password reset wearing an
    // invitation's clothes, which IssueInvitationUseCase refuses.
    $invitee->update(['password_hash' => password_hash('Claimed1!', PASSWORD_BCRYPT)]);

    $this->actingAs(retryAdmin())
        ->postJson("/api/v1/admin/notifications/{$log->id}/retry")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CANNOT_REBUILD');

    expect($log->fresh()->status)->toBe('failed');

    Queue::assertNothingPushed();
});

test('an unknown notification is a 404, not a 500', function (): void {
    $this->actingAs(retryAdmin())
        ->postJson('/api/v1/admin/notifications/'.Uuid::v7()->toRfc4122().'/retry')
        ->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| The guard belongs to the aggregate
|--------------------------------------------------------------------------
*/

test('only a failed notification can be retried, enforced by the domain', function (string $status): void {
    $log = new NotificationLog(
        id: NotificationId::generate(),
        userId: (string) Uuid::v7(),
        channel: new NotificationChannel('email'),
        templateKey: 'invitation.created',
        payload: [],
        status: $status,
    );

    // Asserted against the aggregate rather than the endpoint, because the
    // rule used to live in one HTTP handler -- which is a rule that holds only
    // as long as nothing else ever calls it. Re-sending something `sent`
    // delivers it twice; re-sending something `queued` races the job that is
    // about to run.
    expect(fn () => $log->retry())->toThrow(NotificationNotRetryableException::class);
})->with(['sent', 'queued']);

test('the registry knows what it can and cannot rebuild', function (): void {
    $registry = app(NotificationMailRegistryContract::class);

    // Registered by Core at boot, not by this module. Notifications must not
    // import Core to rebuild Core's mail (ADR-002).
    expect($registry->has('invitation.created'))->toBeTrue()
        // Honest about the rest. Epic 8 delivers one real template; a module
        // that adds mail later registers it here, and until it does a retry
        // says so rather than silently succeeding.
        ->and($registry->has('application.approved'))->toBeFalse();
});

test('the service refuses a retry through the contract, not only through HTTP', function (): void {
    Queue::fake();

    $invitee = retryInvitee();
    $sent = NotificationLogModel::query()->create([
        'id' => (string) Uuid::v7(),
        'user_id' => $invitee->id,
        'channel' => 'email',
        'template_key' => 'invitation.created',
        'payload' => [],
        'status' => 'sent',
        'sent_at' => now(),
        'error' => null,
    ]);

    expect(fn () => app(NotificationsServiceContract::class)->retry((string) $sent->id))
        ->toThrow(NotificationNotRetryableException::class);

    Queue::assertNothingPushed();
});
