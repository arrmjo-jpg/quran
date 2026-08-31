<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Mail\InvitationMail;
use Modules\Notifications\Application\Jobs\SendNotificationJob;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Contracts\NotificationTypeCatalogContract;
use Modules\Notifications\Domain\Exceptions\MandatoryNotificationException;
use Modules\Notifications\Domain\Exceptions\NotificationDeclinedException;
use Modules\Notifications\Domain\Exceptions\UnknownNotificationTypeException;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;
use Modules\Notifications\Infrastructure\Database\Models\NotificationPreferenceModel;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('notifications', 'feature', 'preferences');

/*
|--------------------------------------------------------------------------
| Notification preferences — ADR-020 D4, D9
|--------------------------------------------------------------------------
|
| ADR-001 promised these when the platform was designed. Before this story the
| word "preference" appeared in the backend only inside comments: no table, no
| entity, no route, nothing.
|
| THE MEASUREMENT THAT SHAPES EVERY TEST BELOW: `invitation.created` is the
| only template key in production code, and D9 makes it mandatory. So the set
| of notifications an account may actually decline is EMPTY today. That is not
| a reason to postpone the machinery -- it is the reason the machinery has to
| be provable without it, which is why the optional cases here register a type
| through the catalogue, the same public seam a module will use when it starts
| notifying about something declinable.
|
| THE ONE THAT MATTERS MOST is `a stored preference cannot switch off the
| invitation`. D9's wording is that the mandatory set is expressed in code
| rather than left to the data, and the only way to prove it is to write the
| data by hand -- past the API that refuses it -- and show the send path does
| not care.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();

    // A declinable type, registered the way a module registers its own. The
    // platform has none of its own yet; this is the mechanism, exercised
    // through the seam rather than around it.
    app(NotificationTypeCatalogContract::class)->register('digest.weekly');
});

function prefAccount(string $email = 'pref-owner@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => $email,
        'name' => 'Preference Owner',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function prefInvitee(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'pref-invitee@quran.test',
        'name' => 'Pref Invitee',
        'type' => 'admin',
        'password_hash' => null,
        'is_active' => false,
    ]);
}

function prefNotifications(): NotificationsServiceContract
{
    return app(NotificationsServiceContract::class);
}

function queueDigestFor(UserModel $user): ?string
{
    return prefNotifications()->queue(
        userId: (string) $user->id,
        channel: 'email',
        templateKey: 'digest.weekly',
        payload: ['name' => $user->name],
        recipient: (string) $user->email,
        mail: new InvitationMail(name: $user->name, acceptUrl: 'http://localhost:5173/x', expiresInDays: 7),
    );
}

/*
|--------------------------------------------------------------------------
| D9 — the invitation is mandatory, and it is mandatory in code
|--------------------------------------------------------------------------
*/

test('a stored preference cannot switch off the invitation', function (): void {
    Queue::fake();

    $invitee = prefInvitee();

    // Written straight to the table, past the endpoint that refuses it. This
    // is the scenario D9 is worded against: rows can be seeded, migrated, or
    // written by a future admin screen, and if `mandatory` lived in the data
    // then one row would be enough to lock an account out of itself forever --
    // the invitation is the only way an account can ever be claimed.
    NotificationPreferenceModel::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $invitee->id,
        'notification_type' => 'invitation.created',
        'enabled' => false,
    ]);

    $id = prefNotifications()->queue(
        userId: (string) $invitee->id,
        channel: 'email',
        templateKey: 'invitation.created',
        payload: ['name' => 'Pref Invitee'],
        recipient: 'pref-invitee@quran.test',
        mail: new InvitationMail(name: 'Pref Invitee', acceptUrl: 'http://x/y', expiresInDays: 7),
    );

    expect($id)->not->toBeNull()
        ->and(NotificationLogModel::query()->where('id', $id)->exists())->toBeTrue();

    Queue::assertPushed(SendNotificationJob::class);
});

test('the endpoint refuses to turn the invitation off, and stores nothing', function (): void {
    $user = prefAccount();

    $this->actingAs($user)
        ->putJson('/api/v1/admin/me/notification-preferences', [
            'preferences' => ['invitation.created' => false],
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'PREFERENCE_REFUSED');

    // A refusal that had written the row anyway would leave the rule resting
    // entirely on the send path -- one guard instead of two.
    expect(NotificationPreferenceModel::query()->count())->toBe(0);
});

test('the service refuses it through the contract as well, not only over HTTP', function (): void {
    $user = prefAccount();

    expect(fn () => prefNotifications()->setPreference((string) $user->id, 'invitation.created', false))
        ->toThrow(MandatoryNotificationException::class);
});

test('a mandatory type is not offered as something to decline', function (): void {
    $user = prefAccount();

    $body = $this->actingAs($user)
        ->getJson('/api/v1/admin/me/notification-preferences')
        ->assertOk()
        ->json('data.preferences');

    // Absent rather than present-and-locked: a control that cannot be operated
    // is a question the reader has to answer twice.
    expect($body)->not->toHaveKey('invitation.created')
        ->and($body)->toHaveKey('digest.weekly');
});

/*
|--------------------------------------------------------------------------
| D4 — a declined notification is never attempted
|--------------------------------------------------------------------------
*/

test('a declined notification produces no log row and no job', function (): void {
    Queue::fake();

    $user = prefAccount();
    prefNotifications()->setPreference((string) $user->id, 'digest.weekly', false);

    $id = queueDigestFor($user);

    // NOTHING, not a suppressed row. D6 has no status for "suppressed", and a
    // row at `queued` with no job behind it is precisely the defect this epic
    // removed. The consequence, accepted openly: the log cannot tell
    // "declined" from "never triggered". The preference is the record.
    expect($id)->toBeNull()
        ->and(NotificationLogModel::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('an undeclined notification still goes', function (): void {
    Queue::fake();

    $user = prefAccount();

    expect(queueDigestFor($user))->not->toBeNull()
        ->and(NotificationLogModel::query()->count())->toBe(1);

    Queue::assertPushed(SendNotificationJob::class);
});

test('an account that has never opened the setting receives everything', function (): void {
    $user = prefAccount();

    // No rows at all: absence means enabled, so a new notification type starts
    // working for every existing account without a backfill.
    expect(NotificationPreferenceModel::query()->count())->toBe(0)
        ->and(prefNotifications()->preferencesFor((string) $user->id))
        ->toBe(['digest.weekly' => true]);
});

test('one account declining does not affect another', function (): void {
    Queue::fake();

    $declined = prefAccount('declined@quran.test');
    $other = prefAccount('other@quran.test');

    prefNotifications()->setPreference((string) $declined->id, 'digest.weekly', false);

    expect(queueDigestFor($declined))->toBeNull()
        ->and(queueDigestFor($other))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The table holds refusals, not settings
|--------------------------------------------------------------------------
*/

test('turning something back on removes the row rather than storing true', function (): void {
    $user = prefAccount();

    prefNotifications()->setPreference((string) $user->id, 'digest.weekly', false);
    expect(NotificationPreferenceModel::query()->count())->toBe(1);

    prefNotifications()->setPreference((string) $user->id, 'digest.weekly', true);

    // Absence already means enabled, so a row saying `true` would be a second
    // representation of one state -- and two representations is how a query
    // ends up having to remember which one it is looking at.
    expect(NotificationPreferenceModel::query()->count())->toBe(0)
        ->and(prefNotifications()->preferencesFor((string) $user->id))
        ->toBe(['digest.weekly' => true]);
});

test('declining twice does not create a second row', function (): void {
    $user = prefAccount();

    prefNotifications()->setPreference((string) $user->id, 'digest.weekly', false);
    prefNotifications()->setPreference((string) $user->id, 'digest.weekly', false);

    // The unique index would refuse it, but the repository must not rely on a
    // constraint violation as control flow -- and it must not rewrite the
    // primary key of the row that already exists either.
    expect(NotificationPreferenceModel::query()->count())->toBe(1);
});

test('a type nobody declared cannot be stored', function (): void {
    $user = prefAccount();

    expect(fn () => prefNotifications()->setPreference((string) $user->id, 'nobody.sends.this', false))
        ->toThrow(UnknownNotificationTypeException::class);

    $this->actingAs($user)
        ->putJson('/api/v1/admin/me/notification-preferences', [
            'preferences' => ['nobody.sends.this' => false],
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'PREFERENCE_REFUSED');
});

/*
|--------------------------------------------------------------------------
| The endpoints
|--------------------------------------------------------------------------
*/

test('the round trip through HTTP changes what the send path does', function (): void {
    Queue::fake();

    $user = prefAccount();

    $body = $this->actingAs($user)
        ->putJson('/api/v1/admin/me/notification-preferences', [
            'preferences' => ['digest.weekly' => false],
        ])
        ->assertOk()
        ->json('data.preferences');

    // Read as an array rather than with assertJsonPath. Notification types
    // contain a dot, and dot-path accessors read that as nesting -- Laravel's
    // here, and lodash `get` on the client. Worth knowing before a consumer
    // reaches for one; the vocabulary is not changing, since `template_key`
    // has always been dotted.
    expect($body)->toBe(['digest.weekly' => false]);

    // The assertion that makes this more than a settings screen: the value
    // written over HTTP is the value the sender reads.
    expect(queueDigestFor($user))->toBeNull();

    Queue::assertNothingPushed();
});

test('an account with no notification permissions still manages its own preferences', function (): void {
    // Deliberately NOT withSuperAdmin: this account holds no roles at all, so
    // it has neither notifications.view nor notifications.retry. Self-service
    // means the account acts on itself whatever role it holds -- an
    // administrator edited into a narrower role must not lose control of their
    // own mailbox.
    $user = prefAccount('no-permissions@quran.test');

    $this->actingAs($user)
        ->getJson('/api/v1/admin/me/notification-preferences')
        ->assertOk();

    $this->actingAs($user)
        ->putJson('/api/v1/admin/me/notification-preferences', [
            'preferences' => ['digest.weekly' => false],
        ])
        ->assertOk();

    expect(NotificationPreferenceModel::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('the preferences endpoint reads the account from the token, not the payload', function (): void {
    $mine = prefAccount('mine@quran.test');
    $theirs = prefAccount('theirs@quran.test');

    $this->actingAs($mine)
        ->putJson('/api/v1/admin/me/notification-preferences', [
            // A user_id in the body is ignored because nothing reads one.
            'user_id' => (string) $theirs->id,
            'preferences' => ['digest.weekly' => false],
        ])
        ->assertOk();

    expect(NotificationPreferenceModel::query()->where('user_id', $mine->id)->count())->toBe(1)
        ->and(NotificationPreferenceModel::query()->where('user_id', $theirs->id)->count())->toBe(0);
});

test('a preference must be a boolean', function (): void {
    $user = prefAccount();

    $this->actingAs($user)
        ->putJson('/api/v1/admin/me/notification-preferences', [
            'preferences' => ['digest.weekly' => 'sometimes'],
        ])
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Retry asks the same question
|--------------------------------------------------------------------------
*/

test('a retry will not override a preference the account has since set', function (): void {
    Queue::fake();

    $user = prefAccount();
    prefNotifications()->setPreference((string) $user->id, 'digest.weekly', false);

    $log = NotificationLogModel::query()->create([
        'id' => (string) Uuid::v7(),
        'user_id' => $user->id,
        'channel' => 'email',
        'template_key' => 'digest.weekly',
        'payload' => [],
        'status' => 'failed',
        'sent_at' => null,
        'error' => 'SMTP connection refused',
    ]);

    // A rule enforced on one of the two paths that push a job is a rule with a
    // hole in it. The operator is told rather than silently obeyed, because
    // unlike an ordinary send, somebody is waiting for an answer.
    expect(fn () => prefNotifications()->retry((string) $log->id))
        ->toThrow(NotificationDeclinedException::class);

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->error)->toBe('SMTP connection refused');

    Queue::assertNothingPushed();
});
