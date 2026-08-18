<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Uuid;

/**
 * Gives the bootstrap admin an id the domain will accept.
 *
 * The seeders wrote `00000000-0000-0000-0000-000000000001` by hand. Its
 * version nibble is zero, which `Symfony\Component\Uid\Uuid::isValid()` —
 * the validator UserId uses — rejects, while Ramsey's accepts. That is why it
 * survived: nothing in the codebase built a UserId from this row until
 * authorization went live, and every test builds its users with Str::uuid().
 *
 * The consequence is not cosmetic. AuthorizationService::permissionsOf() and
 * every Gate check construct a UserId from the account, so the seeded
 * platform admin threw an InvalidArgumentException on every admin request —
 * a 500, not a 403. Any environment seeded from this repository has that
 * account, so this correction belongs in a migration and not in a script that
 * someone has to remember to run.
 *
 * WHY THE ROW IS COPIED RATHER THAN UPDATED: ten foreign keys reference
 * users.id and all of them are ON UPDATE NO ACTION, so changing the primary
 * key in place is refused by the database as soon as any child row exists.
 * The account is therefore re-inserted under the new id, every child is
 * repointed, and only then is the old row removed — at which point nothing
 * references it and the ON DELETE CASCADE rules have nothing to take with it.
 *
 * Idempotent, and identifies the account two ways because environments differ:
 * by the known bad id, and by email for an environment where it was already
 * corrected by hand or created differently.
 */
return new class extends Migration
{
    private const OLD_ID = '00000000-0000-0000-0000-000000000001';

    /** Version nibble 7, variant nibble 8 — a valid UUID that stays readable. */
    private const NEW_ID = '01920000-0000-7000-8000-000000000001';

    private const BOOTSTRAP_EMAIL = 'admin@quran.test';

    /**
     * Where the clone parks its address for the length of the transaction.
     * `.invalid` is reserved by RFC 2606 and can never be a real account.
     */
    private const STAGING_EMAIL = 'uuid-migration-staging@invalid';

    /**
     * Every column that points at users.id, from information_schema at the
     * time of writing. Listed explicitly rather than discovered at runtime:
     * a migration that rewrites primary keys should say exactly what it
     * touches, and a table added later needs a reader to think about it
     * rather than have it swept up silently.
     *
     * @var array<string, string>
     */
    private const REFERENCES = [
        'appeals' => 'resolved_by_user_id',
        'contestants' => 'user_id',
        'exports' => 'user_id',
        'judges' => 'user_id',
        'media_assets' => 'uploader_id',
        'notification_logs' => 'user_id',
        'role_user' => 'user_id',
        'season_rule_versions' => 'created_by_user_id',
        'seasons' => 'archived_by_user_id',
        'stage_results' => 'published_by_user_id',
    ];

    public function up(): void
    {
        $account = DB::table('users')->where('id', self::OLD_ID)->first();

        if ($account === null) {
            // The id may already have been corrected, or the account may exist
            // under a different bad id in an environment seeded another way.
            $byEmail = DB::table('users')->where('email', self::BOOTSTRAP_EMAIL)->first();

            if ($byEmail === null || $this->isAcceptable((string) $byEmail->id)) {
                return;
            }

            $account = $byEmail;
        }

        $oldId = (string) $account->id;

        if ($this->isAcceptable($oldId)) {
            return;
        }

        // A previous partial run, or an environment where the target id is
        // taken by something else. Either way, do not create a duplicate.
        if (DB::table('users')->where('id', self::NEW_ID)->exists()) {
            return;
        }

        DB::transaction(function () use ($account, $oldId): void {
            $row = (array) $account;
            $row['id'] = self::NEW_ID;

            // users.email is unique, so the clone cannot be inserted while the
            // original still holds the address — the first run of this
            // migration died exactly there. The clone is parked on a reserved
            // address and given the real one back once the original is gone.
            // Both steps are inside this transaction, so the placeholder is
            // never visible to anything but this migration.
            $email = (string) $row['email'];
            $row['email'] = self::STAGING_EMAIL;

            DB::table('users')->insert($row);

            foreach (self::REFERENCES as $table => $column) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                DB::table($table)->where($column, $oldId)->update([$column => self::NEW_ID]);
            }

            // Not a foreign key — tokenable_id is polymorphic — so it is
            // handled separately, and by deletion rather than repointing.
            // The id in an issued token is part of what the session is bound
            // to; reissuing is one sign-in, and carrying sessions across an
            // identity change is the wrong thing to be clever about.
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $oldId)
                ->where('tokenable_type', 'like', '%UserModel')
                ->delete();

            DB::table('users')->where('id', $oldId)->delete();

            DB::table('users')->where('id', self::NEW_ID)->update(['email' => $email]);
        });
    }

    /**
     * Deliberately NOT down()-reversible.
     *
     * Restoring the old id would put back a row that throws on every
     * authorization check, and a rollback that reintroduces a fault is worse
     * than one that refuses. The account itself is unharmed by leaving this
     * applied, whatever else a rollback is trying to undo.
     */
    public function down(): void
    {
        // Intentionally empty.
    }

    private function isAcceptable(string $id): bool
    {
        return Uuid::isValid($id);
    }
};
