<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Mail\InvitationMail;
use Modules\Notifications\Application\Jobs\SendNotificationJob;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('notifications', 'feature');

/*
|--------------------------------------------------------------------------
| Delivery off the request — ADR-020 D3, D10
|--------------------------------------------------------------------------
|
| The golden master proves the job is PUSHED. This proves what it does when
| it runs, which is a different question and the one D10 rests on: a
| notification that fails must end at `failed` carrying its reason, because
| nothing announces the failure and a human has to find it.
|
| The job is exercised directly rather than through the queue. Running it is
| the behaviour under test; whether Redis hands it over is Laravel's problem,
| and the golden master already covers the dispatch.
*/

beforeEach(function (): void {
    Mail::fake();
});

function jobUser(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'job-target@quran.test',
        'name' => 'Job Target',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function queuedLogFor(UserModel $user): string
{
    return app(NotificationsServiceContract::class)->record(
        userId: (string) $user->id,
        channel: 'email',
        templateKey: 'invitation.created',
        payload: ['name' => 'Job Target'],
    );
}

function invitationMail(): InvitationMail
{
    return new InvitationMail(name: 'Job Target', acceptUrl: 'http://localhost:5173/x', expiresInDays: 7);
}

test('a delivered notification is marked sent, with the time it happened', function (): void {
    $user = jobUser();
    $id = queuedLogFor($user);

    expect(NotificationLogModel::query()->find($id)->status)->toBe('queued');

    (new SendNotificationJob($id, 'job-target@quran.test', invitationMail()))
        ->handle(app(NotificationsServiceContract::class));

    $row = NotificationLogModel::query()->find($id);

    expect($row->status)->toBe('sent')
        // sent_at is the column save() used to discard silently. Asserted
        // because a status without its timestamp is half a record.
        ->and($row->sent_at)->not->toBeNull();

    Mail::assertSent(InvitationMail::class);
});

test('the mail is only marked sent AFTER it is actually sent', function (): void {
    $user = jobUser();
    $id = queuedLogFor($user);

    // If the job marked the row before sending, a throwing mailer would leave
    // a row claiming delivery that never happened -- the exact class of lie
    // this epic exists to remove.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));

    try {
        (new SendNotificationJob($id, 'job-target@quran.test', invitationMail()))
            ->handle(app(NotificationsServiceContract::class));
    } catch (RuntimeException) {
        // Expected: handle() lets it through so the queue can retry.
    }

    expect(NotificationLogModel::query()->find($id)->status)->toBe('queued');
});

test('an exhausted notification ends at failed, keeping the reason', function (): void {
    $user = jobUser();
    $id = queuedLogFor($user);

    // failed() is what Laravel calls after the last attempt -- ADR-020 D10.
    (new SendNotificationJob($id, 'job-target@quran.test', invitationMail()))
        ->failed(new RuntimeException('Connection could not be established with host mailpit'));

    $row = NotificationLogModel::query()->find($id);

    expect($row->status)->toBe('failed')
        // The reason is the whole point: `failed` alone tells an operator
        // something broke and nothing about what.
        ->and($row->error)->toContain('mailpit');
});

test('it retries a few times and then stops', function (): void {
    // Not forever: a permanently bad address must reach the `failed` state a
    // human can act on, rather than being retried in perpetuity.
    expect((new SendNotificationJob('x', 'y@z.test', invitationMail()))->tries)->toBe(3);
});
