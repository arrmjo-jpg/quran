<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations — ADR-016 D14, Q7.
 *
 * An account is created Pending Activation and completed by its invitee, who
 * chooses their own password. No administrator ever sets another person's
 * password, so an invitation is the only path from "created" to "usable".
 *
 * WHY THIS POINTS AT A USER RATHER THAN CARRYING AN EMAIL: D14 says accounts
 * are *created* pending activation, not that they appear on acceptance. The
 * row exists from the moment the administrator acts, which is what lets the
 * users list show who has been invited and what roles they will hold, and
 * what lets PE-1 refuse an over-privileged grant at invitation time rather
 * than days later when the invitee happens to click a link.
 *
 * A pending account and a deactivated one are both is_active = false, and the
 * difference is `users.password_hash IS NULL` — never set a password versus
 * had one and was stopped. That distinction is derived rather than stored,
 * because storing it would create a second source of truth that can disagree
 * with the password column.
 *
 * The plaintext token is never stored. Only its SHA-256 lands here, following
 * DeviceTrustService, so a database disclosure does not hand over the ability
 * to claim accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('user_id');

            // 64 hex characters, the width of a SHA-256 digest. Unique because
            // a token that could address two invitations is not a token.
            $table->char('token_hash', 64)->unique('uk_invitations_token_hash');

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();

            $table->uuid('invited_by_user_id')->nullable();

            $table->timestamps();

            // Deleting the account takes its invitations with it: an invitation
            // to an account that no longer exists cannot be accepted, and
            // leaving the row would keep a live token pointing at nothing.
            $table->foreign('user_id', 'fk_invitations_user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            // The inviter is history, not a dependency. If their account goes,
            // the invitation stays and simply no longer names who sent it —
            // the same choice seasons.archived_by_user_id already makes.
            $table->foreign('invited_by_user_id', 'fk_invitations_invited_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('user_id', 'idx_invitations_user_id');

            // Answers "is there an invitation still open for this account?"
            // without scanning, which is the question every screen asks.
            $table->index(['user_id', 'accepted_at'], 'idx_invitations_user_accepted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
