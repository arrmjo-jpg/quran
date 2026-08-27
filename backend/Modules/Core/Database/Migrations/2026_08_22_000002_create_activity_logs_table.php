<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * The activity log — ADR-017 D2 and D3.
 *
 * What changed in the business, and who changed it. Deliberately separate from
 * `audit_logs`, which answers a different question (who called which endpoint,
 * and what did it return), and from `outbox_events`, whose shape fits but whose
 * purpose is reliable delivery with retries.
 *
 * NO FOREIGN KEY ON actor_id, AND THAT IS THE POINT OF A LOG. RESTRICT would
 * make a user undeletable once they had done anything; SET NULL would erase who
 * did it the moment they left. A record of what happened has to outlive the
 * people and the rows it describes, so the id is stored and not constrained.
 * The same reasoning applies to entity_id, which points at whichever table the
 * entity_type names and cannot be constrained to one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);

            // The event's own TYPE constant — already snake_case on all 46.
            $table->string('action', 100);

            // Resolved through ActivityEventRegistry, never from a naming
            // heuristic: 'all_judges_completed' is about an application.
            $table->string('entity_type', 50);
            $table->uuid('entity_id');

            // Null when nobody was logged in. actor_type says which of the
            // three D4 sources applied, so "no user" is distinguishable from
            // "we failed to record one".
            $table->uuid('actor_id')->nullable();
            $table->string('actor_type', 20);

            // The event's toPayload() verbatim. Events already decide what is
            // safe to carry — ContestantUpdated carries field NAMES and not
            // values so a national id cannot land here — and this inherits
            // that judgement rather than re-making it.
            $table->json('payload');

            // Captured by the listener from the request, never carried by the
            // event: correlation is an HTTP concept (D5). Null for anything
            // dispatched from a console command or a queue.
            $table->uuid('correlation_id')->nullable();

            // DATETIME, NOT TIMESTAMP, on both — deliberately.
            //
            // MySQL's TIMESTAMP caps at 2038, and this table records events
            // that may be backdated (a membership entered late) and will be
            // read for years. seasons.frozen_at was declared TIMESTAMP one day
            // after the same table's other columns were moved off it, and that
            // defect lived for two weeks because SQLite has no such ceiling.
            //
            // The two columns are NOT the same fact. occurred_at is when the
            // thing happened; created_at is when it was written down. A
            // membership backdated to last September happened in September and
            // was recorded today, and collapsing them would make every
            // backdated entry look like a September record.
            $table->dateTime('occurred_at');
            $table->dateTime('created_at')->useCurrent();

            // Reading an entity's history is the primary query.
            $table->index(['entity_type', 'entity_id', 'occurred_at'], 'idx_activity_entity');

            // "What did this person do", the second most common question.
            $table->index(['actor_id', 'occurred_at'], 'idx_activity_actor');

            // Filtering the feed by kind, and the plain reverse-chronological
            // feed itself.
            $table->index('action', 'idx_activity_action');
            $table->index('occurred_at', 'idx_activity_occurred');

            // Joins an activity row to the audit_logs row for the same request.
            $table->index('correlation_id', 'idx_activity_correlation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
