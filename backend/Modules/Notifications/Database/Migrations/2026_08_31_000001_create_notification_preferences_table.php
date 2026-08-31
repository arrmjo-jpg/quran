<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Notification preferences — ADR-020 D4.
 *
 * ADR-001 has promised these since the platform was designed and nothing has
 * ever implemented them: before this migration the word "preference" appeared
 * in the backend only inside comments.
 *
 * A TABLE, NOT COLUMNS ON `users`. Preferences are a growing list keyed by
 * notification type, so a column per type is a migration per type — the same
 * reasoning ADR-016 D2 used for social links.
 *
 * ROWS ARE EXCEPTIONS, NOT STATE. Absence means enabled. A preference row
 * exists only where somebody has actively declined something, which keeps this
 * table empty for accounts that have never opened the setting and means a new
 * notification type does not need a backfill to start working.
 *
 * NO `channel` COLUMN. D4 says per account and per notification type, and D1
 * ships email only — a channel dimension would be a guess about a feature with
 * no provider, no credentials and no cost decision behind it. The type string
 * is the unit the platform actually has.
 *
 * WHAT THIS TABLE CANNOT DO is switch off the invitation. D9 makes it
 * mandatory, and mandatory is expressed in the catalogue in code rather than
 * by the absence of a row here: data can be edited, and an account that
 * declined its own invitation could never be claimed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);

            // Cascade: a preference is meaningless without the account whose
            // preference it is. Unlike notification_logs, this is not a record
            // of something that happened, so there is nothing to preserve.
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // The `template_key` vocabulary the log already uses, so one string
            // identifies a notification everywhere: `invitation.created`.
            $table->string('notification_type', 100);

            $table->boolean('enabled')->default(true);

            $table->timestamps();

            // One answer per account per type. Without this, two contradictory
            // rows could exist and which one won would come down to ordering —
            // the same class of ambiguity that made a second invitation row a
            // security problem in the previous story.
            $table->unique(['user_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
