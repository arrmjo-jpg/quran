<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Domain\Events\UserDeleted;
use Modules\Core\Domain\Events\UserProfileUpdated;
use Modules\Core\Domain\Events\UserRestored;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'user-lifecycle');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function userLifecycleAccount(string $email, bool $active = true): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Original Name',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => $active,
    ]);
}

function userLifecycleAdmin(string $email = 'lifecycle-admin@quran.test'): UserModel
{
    return withSuperAdmin(userLifecycleAccount($email));
}

/** An account holding only the permissions named, and nothing else. */
function accountWith(array $permissions, string $email, UserModel $grantedBy): UserModel
{
    $user = userLifecycleAccount($email);
    $role = app(CreateRoleUseCase::class)
        // Lowercased: the Role aggregate requires snake_case and Str::random()
        // is mixed case, so the first version of this helper was refused by the
        // domain — correctly.
        ->execute('role_'.strtolower(Str::random(8)), $permissions, (string) $grantedBy->id);
    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, $role->id->value, (string) $grantedBy->id);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

test('a name and locale can be changed', function (): void {
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('subject@quran.test');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}", ['name' => 'Corrected Name', 'locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Corrected Name');

    expect($subject->fresh()->preferred_locale)->toBe('en');
});

test('editing your own name is allowed, unlike editing your own roles', function (): void {
    // PE-3 protects capability, not identity. A guard here would suggest this
    // operation could strand something, and it cannot.
    $admin = userLifecycleAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$admin->id}", ['name' => 'My New Name'])
        ->assertOk();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$admin->id}/roles", ['roles' => []])
        ->assertStatus(403);
});

test('the email address cannot be changed through this endpoint', function (): void {
    // Deliberately absent: the address is the login identity and the channel
    // an invitation went to, and changing it silently repoints both with no
    // verification that the new mailbox is reachable.
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('original@quran.test');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}", [
            'name' => 'Same Person',
            'email' => 'hijacked@quran.test',
        ])
        ->assertOk();

    expect($subject->fresh()->email)->toBe('original@quran.test');
});

test('an unchanged name records nothing', function (): void {
    Event::fake([UserProfileUpdated::class]);

    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('unchanged@quran.test');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}", ['name' => 'Original Name'])
        ->assertOk();

    Event::assertNotDispatched(UserProfileUpdated::class);
});

test('a changed name records both values', function (): void {
    Event::fake([UserProfileUpdated::class]);

    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('renamed@quran.test');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}", ['name' => 'New Name'])
        ->assertOk();

    Event::assertDispatched(UserProfileUpdated::class, function (UserProfileUpdated $e): bool {
        return $e->previousName === 'Original Name' && $e->name === 'New Name';
    });
});

test('updating requires users.update', function (): void {
    $admin = userLifecycleAdmin();
    $viewer = accountWith(['users.view'], 'viewer@quran.test', $admin);
    $subject = userLifecycleAccount('subject@quran.test');

    $this->actingAs($viewer)->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs($viewer)
        ->patchJson("/api/v1/admin/users/{$subject->id}", ['name' => 'Nope'])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Delete — the door ADR-016 recorded as "closed by absence"
|--------------------------------------------------------------------------
*/

test('deleting is soft: the row survives with its roles', function (): void {
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('deletable@quran.test');
    app(AssignRoleToUserUseCase::class)->execute(
        (string) $subject->id,
        app(Modules\Core\Domain\Repositories\RoleRepositoryContract::class)->findByName('moderator')->id->value,
        (string) $admin->id
    );

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();

    expect(UserModel::query()->find($subject->id))->toBeNull();
    expect(UserModel::withTrashed()->find($subject->id))->not->toBeNull();
    expect(DB::table('role_user')->where('user_id', $subject->id)->count())->toBe(1);
});

test('a deleted account cannot act on its next request', function (): void {
    $admin = userLifecycleAdmin();
    $subject = withSuperAdmin(userLifecycleAccount('soon-deleted@quran.test'));

    $this->actingAs($subject)->getJson('/api/v1/admin/users')->assertOk();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();

    $this->actingAs(UserModel::withTrashed()->find($subject->id))
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

test('you cannot delete yourself', function (): void {
    // PE-6, the same rule as self-deactivation and deliberately the same
    // exception: "you cannot remove your own access" is one decision, not two.
    $admin = userLifecycleAdmin();
    withSuperAdmin(userLifecycleAccount('another-super@quran.test'));

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/users/{$admin->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'SELF_DELETION');

    expect(UserModel::query()->find($admin->id))->not->toBeNull();
});

test('deleting the last active holder of a system role is refused', function (): void {
    // THE DOOR THIS STORY OPENS. ADR-016's PE-5 amendment recorded that
    // deletion had no endpoint, so the third route to stranding the platform
    // was shut by absence. Building the endpoint reopens it.
    $actor = userLifecycleAccount('actor@quran.test');
    $lastHolder = withSuperAdmin(userLifecycleAccount('last-super@quran.test'));

    $operator = app(CreateRoleUseCase::class)
        ->execute('deleter', ['users.view', 'users.delete'], (string) $lastHolder->id);
    app(AssignRoleToUserUseCase::class)
        ->execute((string) $actor->id, $operator->id->value, (string) $lastHolder->id);

    $this->actingAs($actor->fresh())
        ->deleteJson("/api/v1/admin/users/{$lastHolder->id}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'LAST_SYSTEM_ROLE_HOLDER');

    expect(UserModel::query()->find($lastHolder->id))->not->toBeNull();
});

test('deleting a system role holder is allowed while another remains', function (): void {
    // The guard must protect the last one, not every one.
    $admin = userLifecycleAdmin();
    $second = withSuperAdmin(userLifecycleAccount('second-super@quran.test'));

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$second->id}")->assertOk();

    expect(UserModel::query()->find($second->id))->toBeNull();
});

test('deletion is recorded', function (): void {
    Event::fake([UserDeleted::class]);

    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('audited-delete@quran.test');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();

    Event::assertDispatched(UserDeleted::class, function (UserDeleted $e) use ($subject, $admin): bool {
        return $e->userId === (string) $subject->id && $e->byUserId === (string) $admin->id;
    });
});

test('deleting requires users.delete', function (): void {
    $admin = userLifecycleAdmin();
    $editor = accountWith(['users.view', 'users.update'], 'editor@quran.test', $admin);
    $subject = userLifecycleAccount('safe@quran.test');

    $this->actingAs($editor)
        ->deleteJson("/api/v1/admin/users/{$subject->id}")
        ->assertForbidden();

    expect(UserModel::query()->find($subject->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Restore
|--------------------------------------------------------------------------
*/

test('a deleted account can be restored with its roles intact', function (): void {
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('restorable@quran.test');
    app(AssignRoleToUserUseCase::class)->execute(
        (string) $subject->id,
        app(Modules\Core\Domain\Repositories\RoleRepositoryContract::class)->findByName('moderator')->id->value,
        (string) $admin->id
    );

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();
    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.roles', ['moderator']);

    expect(UserModel::query()->find($subject->id))->not->toBeNull();
});

test('restoring does not decide whether the account may act', function (): void {
    // Deleted and deactivated are different states. An account deactivated
    // before deletion is still deactivated after restore, and needs
    // users.activate as well — two decisions, not collapsed into one.
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('was-inactive@quran.test', active: false);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();
    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$subject->id}/restore")->assertOk();

    expect((bool) UserModel::query()->find($subject->id)->is_active)->toBeFalse();
});

test('restoring a live account changes nothing and records nothing', function (): void {
    Event::fake([UserRestored::class]);

    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('never-deleted@quran.test');

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$subject->id}/restore")->assertOk();

    Event::assertNotDispatched(UserRestored::class);
});

test('restoration is recorded', function (): void {
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('audited-restore@quran.test');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();

    Event::fake([UserRestored::class]);
    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$subject->id}/restore")->assertOk();

    Event::assertDispatched(UserRestored::class, function (UserRestored $e) use ($subject): bool {
        return $e->userId === (string) $subject->id;
    });
});

test('restoring requires users.restore, which deleting does not grant', function (): void {
    $admin = userLifecycleAdmin();
    $deleter = accountWith(['users.view', 'users.delete'], 'deleter-only@quran.test', $admin);
    $subject = userLifecycleAccount('victim@quran.test');

    $this->actingAs($deleter)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();
    $this->actingAs($deleter)
        ->patchJson("/api/v1/admin/users/{$subject->id}/restore")
        ->assertForbidden();
});

test('a deleted account is absent from the list but findable with the flag', function (): void {
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('hidden@quran.test');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();

    $default = $this->actingAs($admin)->getJson('/api/v1/admin/users')->json('data');
    expect(array_column($default, 'email'))->not->toContain('hidden@quran.test');

    $withDeleted = $this->actingAs($admin)->getJson('/api/v1/admin/users?with_deleted=1')->json('data');
    expect(array_column($withDeleted, 'email'))->toContain('hidden@quran.test');

    $row = collect($withDeleted)->firstWhere('email', 'hidden@quran.test');
    expect($row['is_deleted'])->toBeTrue();
});

test('the repository default never loads a deleted account', function (): void {
    // findWithTrashed() is separate from find() so every caller wanting a
    // deleted account has said so. An authorization path silently loading one
    // would be answering questions about someone who is gone.
    $admin = userLifecycleAdmin();
    $subject = userLifecycleAccount('gone@quran.test');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$subject->id}")->assertOk();

    $users = app(UserRepositoryContract::class);
    $id = new UserId((string) $subject->id);

    expect($users->find($id))->toBeNull();
    expect($users->findWithTrashed($id))->not->toBeNull();
    expect($users->findWithTrashed($id)->isDeleted())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The state a screen reads
|--------------------------------------------------------------------------
*/

test('every account state is reported as one derived status', function (): void {
    // Three booleans a client has to combine is three chances to combine them
    // wrongly — and getting it wrong tells an administrator that an invited
    // colleague was disabled.
    $admin = userLifecycleAdmin();

    $pending = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'not-yet@quran.test',
        'name' => 'Not Yet',
        'type' => UserType::ADMIN,
        'password_hash' => null,
        'is_active' => false,
    ]);
    $deactivated = userLifecycleAccount('off@quran.test', active: false);
    $active = userLifecycleAccount('on@quran.test');
    $deleted = userLifecycleAccount('gone@quran.test');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$deleted->id}")->assertOk();

    $rows = collect($this->actingAs($admin)->getJson('/api/v1/admin/users?with_deleted=1')->json('data'))
        ->keyBy('email');

    expect($rows['not-yet@quran.test']['status'])->toBe('pending_activation');
    expect($rows['off@quran.test']['status'])->toBe('deactivated');
    expect($rows['on@quran.test']['status'])->toBe('active');
    expect($rows['gone@quran.test']['status'])->toBe('deleted');
});

test('an invited account stops being pending once it is claimed', function (): void {
    $admin = userLifecycleAdmin();

    Illuminate\Support\Facades\Mail::fake();
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'email' => 'claiming@quran.test',
        'name' => 'Claiming Person',
    ])->assertCreated();

    $created = UserModel::query()->where('email', 'claiming@quran.test')->first();

    $before = collect($this->actingAs($admin)->getJson('/api/v1/admin/users')->json('data'))
        ->firstWhere('email', 'claiming@quran.test');
    expect($before['status'])->toBe('pending_activation');

    $token = null;
    Illuminate\Support\Facades\Mail::assertSent(
        Modules\Core\Infrastructure\Mail\InvitationMail::class,
        function ($mail) use (&$token): bool {
            parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $q);
            $token = $q['token'];

            return true;
        }
    );

    $this->postJson('/api/v1/invitations/accept', [
        'token' => $token,
        'password' => 'TheyChose123!',
        'password_confirmation' => 'TheyChose123!',
    ])->assertOk();

    $after = collect($this->actingAs($admin)->getJson('/api/v1/admin/users')->json('data'))
        ->firstWhere('email', 'claiming@quran.test');

    expect($after['status'])->toBe('active');
    expect($created->fresh()->password_hash)->not->toBeNull();
});
