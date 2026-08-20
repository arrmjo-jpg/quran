<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Administrator profiles — ADR-016 D1, D2, D13.
 *
 * NOT A SPLIT OF ANYTHING. An administrator today is a `users` row and nothing
 * more — `type = 'admin'` plus roles, per ADR-015 §1 — so this is not carved
 * out of an existing home; it is the first one. `contestants` already carries
 * the equivalent fields for its own kind of person, and D4 keeps the two
 * apart: a contestant profile and an admin profile answer different questions
 * and would drift the moment either grew a field the other did not want.
 *
 * WHY NOT WIDEN `users` INSTEAD. Every authentication and authorization path
 * in the platform reads that table. Putting optional presentation data —
 * a biography, an avatar, a set of social links — on the row that answers
 * "may this request proceed" makes the hot path carry weight it never uses.
 *
 * A PROFILE, NOT A PERSON. Deliberately the smallest set that serves the
 * product today: a name to display, a short biography, a picture, and links.
 * Phone number, nationality, address, date of birth and gender are absent on
 * purpose — they are operational or personal data that no ADR has established
 * as part of an administrator's profile, and a column added "while we are here"
 * is a column someone will eventually feel obliged to fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);

            $table->uuid('user_id');

            $table->string('display_name', 255)->nullable();
            $table->string('bio', 1000)->nullable();
            $table->uuid('avatar_media_id')->nullable();

            // D2 — a closed set of eight platforms, full URLs, validated per
            // platform by the value object in Story 2. Nullable rather than
            // defaulted to '{}': an account that never opened the form has no
            // links, which is a different fact from having supplied an empty
            // set, and Q5 settled that an unset platform carries no key at all.
            $table->json('social_links')->nullable();

            $table->timestamps();

            // ONE PROFILE PER ACCOUNT, enforced by the database rather than by
            // whichever use case happens to write next. Without it, two
            // concurrent first-time saves both find no row and both insert.
            $table->unique('user_id', 'uk_user_profiles_user_id');

            // CASCADE, unlike most user references in this schema, which are
            // SET NULL. Those columns record that a user did something and
            // outlive them; this row IS the user's profile and means nothing
            // without them. In practice it will not fire — accounts are soft
            // deleted (ADR-005) — so this governs a hard delete only, and there
            // the profile should go with the account rather than linger
            // unreachable.
            $table->foreign('user_id', 'fk_user_profiles_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // SET NULL, following contestants.photo_media_id rather than
            // PlatformBlueprint::mediaForeign's RESTRICT. An avatar is not
            // worth blocking the deletion of a media asset over; losing the
            // picture is the correct outcome, losing the profile is not.
            $table->foreign('avatar_media_id', 'fk_user_profiles_avatar_media_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
