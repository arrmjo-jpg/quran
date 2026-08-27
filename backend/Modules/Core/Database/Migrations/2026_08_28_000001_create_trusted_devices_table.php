<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Trusted devices — ADR-018 D1.
 *
 * Replaces a Cache-backed store. The reasons are recorded in the ADR; the one
 * that decides the shape is D5: a trust grant now lets a device skip the MFA
 * challenge, which makes it a credential. Credentials do not live in a store
 * whose contract is "may be evicted at any time", and `php artisan cache:clear`
 * was silently revoking every device on the platform.
 *
 * The token is stored ONLY as a hash. The plaintext is returned once, when the
 * device is trusted, and never again — so reading this table cannot recover a
 * credential, and the list endpoint stops handing the secret back on every
 * read.
 *
 * DATETIME, not TIMESTAMP: the same 2038 ceiling that was lifted off the season
 * lifecycle columns in 407464c. A 30-day expiry is nowhere near it today, but
 * the column outlives the reasoning, and ADR-017 set the precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);

            // Cascade: trust is meaningless without the account it belongs to,
            // and unlike a log this is not a record of something that happened.
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // sha256 of the plaintext token. Unique because the token is the
            // credential — a collision would mean one device's token trusting
            // another's. Not bcrypt: this is a 128-bit random value we generated,
            // not a user-chosen password, so there is nothing to slow down a
            // guesser who already has to beat 2^128.
            $table->char('token_hash', 64)->unique();

            // Client-supplied, from X-Device-ID. NOT a security input (D7) —
            // it is here so the list can say which browser a row describes.
            $table->string('device_id', 100)->nullable();

            // Descriptive only (D6). The old implementation hashed the IP into
            // the identity of the device, so trust broke whenever the address
            // changed — on any mobile network, constantly.
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->dateTime('trusted_at');

            // A fixed horizon, not a sliding one: using a trusted device does
            // not extend its trust. D5's mitigation depends on this.
            $table->dateTime('expires_at');

            // Answers "is this still me?" on screen. Written on each successful
            // trust-based login.
            $table->dateTime('last_used_at')->nullable();

            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            // The login path's lookup: find a live grant for this account.
            $table->index(['user_id', 'expires_at'], 'idx_trusted_devices_user_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
