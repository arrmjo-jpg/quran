<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('all 41 database tables exist in the schema per MIGRATION-SPECIFICATION.md', function (): void {
    $expectedTables = [
        // Core (8)
        'users',
        'roles',
        'permissions',
        'role_user',
        'role_has_permissions',
        'languages',
        'settings',
        'outbox_events',
        // Countries (2)
        'countries',
        'country_translations',
        // Media (2)
        'media_assets',
        'media_conversions',
        // Contestants (1)
        'contestants',
        // Competition (7)
        'seasons',
        'season_translations',
        'evaluation_templates',
        'evaluation_criteria',
        'stages',
        'stage_translations',
        'rules',
        // Judges (2)
        'judges',
        'judge_assignments',
        // Videos (3)
        'videos',
        'video_variants',
        'video_thumbnails',
        // Applications (1)
        'applications',
        // Evaluations (5)
        'evaluations',
        'evaluation_scores',
        'results',
        'stage_results',
        'appeals',
        // Streaming (2)
        'streams',
        'stream_recordings',
        // Content (6)
        'announcements',
        'announcement_translations',
        'static_pages',
        'static_page_translations',
        'faqs',
        'faq_translations',
        // Sponsors (1)
        'sponsors',
        // Notifications (1)
        'notification_logs',
        // Reports (1)
        'exports',
        // Search (1)
        'indexing_logs',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist in schema.");
    }
});
