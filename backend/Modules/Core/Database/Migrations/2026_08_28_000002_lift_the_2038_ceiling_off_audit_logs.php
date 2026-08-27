<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs.created_at: TIMESTAMP -> DATETIME — ADR-018 D9.
 *
 * MySQL's TIMESTAMP stops at 2038-01-19 03:14:07 UTC and rejects anything past
 * it outright — error 1292, which is how the same ceiling was found on the
 * season lifecycle columns and lifted in 407464c.
 *
 * It is in this epic's path because ADR-018 D2 makes this table the login
 * history: a screen people filter by date, over a range that already reaches
 * within thirteen years of the ceiling. ADR-017 chose DATETIME for
 * activity_logs for the same reason, and leaving the two log tables disagreeing
 * about how far time goes would be a difference nobody could explain later.
 *
 * The default is preserved. Every existing row keeps its value: DATETIME's
 * range is a superset of TIMESTAMP's, so this widens without converting.
 *
 * NOT a timezone change. TIMESTAMP stores UTC and converts on read, DATETIME
 * stores what it is given. The application writes through Laravel, which sends
 * UTC either way, so the stored instants are unchanged — but this is the reason
 * the column type is worth naming in a migration rather than adjusting quietly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dateTime('created_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->timestamp('created_at')->useCurrent()->change();
        });
    }
};
