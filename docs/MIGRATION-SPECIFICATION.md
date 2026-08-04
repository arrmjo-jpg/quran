# Migration Specification — Global Quran Competition Platform

> **Document ID**: MIG-SPEC-001  
> **Status**: Ready for Code Implementation  
> **Date**: 2026-07-31  
> **Governing Standards**: [ADR-005: Database Architecture](./adr/ADR-005-database-architecture.md) · [ERD.md](./ERD.md)  
> **Target Engine**: MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`)

---

## 1. Overview & Execution Rules

This document specifies the exact physical schema for all **41 database tables** across the **15 platform modules**.

### Developer Implementation Mandate
1. **No Migration Without Specification**: Every Laravel Eloquent migration written in `database/migrations/` must match this specification line-for-line.
2. **Strict Ordering**: Migrations must be named with timestamp prefixes reflecting dependency order (Core → Countries → Media → Contestants → Competition → Judges → Videos → Applications → Evaluations → Streaming → Content → Sponsors → Notifications).
3. **Engine Settings**: Every migration must specify `$table->engine = 'InnoDB'` and `$table->charset = 'utf8mb4'` / `$table->collation = 'utf8mb4_unicode_ci'`.
4. **UUID Generation**: All primary keys specify `$table->uuid('id')->primary()`. Values are generated in the model application layer via UUID v7.

---

## 2. Module Migration Specifications

---

### 2.1 Module: Core

#### Table 1: `users`
- **Migration File**: `2026_01_01_000001_create_users_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `email` | `VARCHAR(255)` | `NOT NULL, UNIQUE (uk_users_email)` | Login email |
| `name` | `VARCHAR(255)` | `NOT NULL` | Full display name |
| `type` | `VARCHAR(50)` | `NOT NULL, INDEX (idx_users_type)` | Surface discriminator: `user` or `admin` |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Account status |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_users_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 2: `roles`
- **Migration File**: `2026_01_01_000002_create_roles_table.php`
- **Delete Policy**: Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `name` | `VARCHAR(255)` | `NOT NULL, UNIQUE (uk_roles_name_guard)` | Role name |
| `guard_name` | `VARCHAR(255)` | `NOT NULL DEFAULT 'web'` | Spatie guard |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 3: `permissions`
- **Migration File**: `2026_01_01_000003_create_permissions_table.php`
- **Delete Policy**: Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `name` | `VARCHAR(255)` | `NOT NULL, UNIQUE (uk_permissions_name_guard)` | Format: `module.action` |
| `guard_name` | `VARCHAR(255)` | `NOT NULL DEFAULT 'web'` | Spatie guard |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 4: `role_user` (Pivot)
- **Migration File**: `2026_01_01_000004_create_role_user_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `role_id` | `CHAR(36)` | `NOT NULL, FK → roles.id (CASCADE)` | Role FK |
| `user_id` | `CHAR(36)` | `NOT NULL, FK → users.id (CASCADE)` | User FK |

- **Primary Key**: Composite `(role_id, user_id)`

---

#### Table 5: `role_has_permissions` (Pivot)
- **Migration File**: `2026_01_01_000005_create_role_has_permissions_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `permission_id` | `CHAR(36)` | `NOT NULL, FK → permissions.id (CASCADE)` | Permission FK |
| `role_id` | `CHAR(36)` | `NOT NULL, FK → roles.id (CASCADE)` | Role FK |

- **Primary Key**: Composite `(permission_id, role_id)`

---

#### Table 6: `languages`
- **Migration File**: `2026_01_01_000006_create_languages_table.php`
- **Delete Policy**: Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `code` | `VARCHAR(10)` | `NOT NULL, UNIQUE (uk_languages_code)` | BCP-47 locale (`ar`, `en`, `es`) |
| `name` | `VARCHAR(100)` | `NOT NULL` | Language English name |
| `native_name` | `VARCHAR(100)` | `NOT NULL` | Language native name |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Active flag |
| `rtl` | `TINYINT(1)` | `NOT NULL DEFAULT 0` | Right-to-left flag |

---

#### Table 7: `settings`
- **Migration File**: `2026_01_01_000007_create_settings_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `key` | `VARCHAR(100)` | `PRIMARY KEY` | Natural setting key |
| `value` | `TEXT` | `NULL` | Setting payload |
| `group` | `VARCHAR(50)` | `NOT NULL DEFAULT 'general', INDEX (idx_settings_group)` | Config group |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 8: `audit_logs`
- **Migration File**: `2026_01_01_000008_create_audit_logs_table.php`
- **Delete Policy**: Append Only

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `user_id` | `CHAR(36)` | `NULL, FK → users.id (RESTRICT), INDEX (idx_audit_logs_user_id)` | Actor user ID |
| `action` | `VARCHAR(100)` | `NOT NULL` | Action code |
| `auditable_type` | `VARCHAR(100)` | `NOT NULL` | Target model class |
| `auditable_id` | `CHAR(36)` | `NOT NULL` | Target model ID |
| `old_values` | `JSON` | `NULL` | Pre-mutation JSON snapshot |
| `new_values` | `JSON` | `NULL` | Post-mutation JSON snapshot |
| `ip_address` | `VARCHAR(45)` | `NULL` | Client IP |
| `user_agent` | `TEXT` | `NULL` | Client User-Agent |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (idx_audit_logs_created_at)` | — |

- **Indexes**: Composite `idx_audit_logs_auditable (auditable_type, auditable_id)`

---

#### Table 9: `outbox_events`
- **Migration File**: `2026_01_01_000009_create_outbox_events_table.php`
- **Delete Policy**: Archive Only (30-day retention)

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `event_type` | `VARCHAR(255)` | `NOT NULL` | Class name of domain event |
| `aggregate_type` | `VARCHAR(100)` | `NOT NULL` | Source aggregate name |
| `aggregate_id` | `CHAR(36)` | `NOT NULL` | Source aggregate ID |
| `payload` | `JSON` | `NOT NULL` | Complete event data payload |
| `status` | `VARCHAR(20)` | `NOT NULL DEFAULT 'pending', INDEX (idx_outbox_events_status)` | `pending\|dispatched\|failed\|archived` |
| `dispatched_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Dispatch timestamp |
| `attempts` | `TINYINT` | `NOT NULL DEFAULT 0` | Retry attempts count |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (idx_outbox_events_created_at)` | — |

---

### 2.2 Module: Media (Platform Service)

#### Table 10: `media_assets`
- **Migration File**: `2026_01_02_000001_create_media_assets_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `disk` | `VARCHAR(50)` | `NOT NULL DEFAULT 'local'` | Disk storage driver (`local\|s3\|r2`) |
| `path` | `VARCHAR(1000)` | `NOT NULL` | Storage relative path |
| `original_filename` | `VARCHAR(255)` | `NOT NULL` | Uploaded file name |
| `mime_type` | `VARCHAR(100)` | `NOT NULL` | File MIME type |
| `file_size_bytes` | `BIGINT` | `NOT NULL` | File size in bytes |
| `morphable_type` | `VARCHAR(100)` | `NULL, INDEX (idx_media_assets_morphable)` | Optional polymorphic owner type |
| `morphable_id` | `CHAR(36)` | `NULL` | Optional polymorphic owner ID |
| `uploaded_by` | `CHAR(36)` | `NULL, FK → users.id (SET NULL)` | Uploader user ID |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_media_assets_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

### 2.3 Module: Countries

#### Table 11: `countries`
- **Migration File**: `2026_01_03_000001_create_countries_table.php`
- **Delete Policy**: Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `iso_code` | `VARCHAR(2)` | `NOT NULL, UNIQUE (uk_countries_iso_code)` | ISO 3166-1 alpha-2 code |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Visibility status |
| `display_order` | `INT` | `NOT NULL DEFAULT 0, INDEX (idx_countries_display_order)` | Sorting order |

---

#### Table 12: `country_translations`
- **Migration File**: `2026_01_03_000002_create_country_translations_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `country_id` | `CHAR(36)` | `NOT NULL, FK → countries.id (CASCADE)` | Parent country FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `name` | `VARCHAR(255)` | `NOT NULL` | Translated country name |
| `native_name` | `VARCHAR(255)` | `NOT NULL` | Native country name |

- **Unique Constraint**: `uk_country_translations_country_locale (country_id, locale)`

---

### 2.4 Module: Contestants

#### Table 13: `contestants`
- **Migration File**: `2026_01_04_000001_create_contestants_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `user_id` | `CHAR(36)` | `NOT NULL, UNIQUE (uk_contestants_user_id), FK → users.id (RESTRICT)` | Linked user account |
| `country_id` | `CHAR(36)` | `NOT NULL, FK → countries.id (RESTRICT), INDEX (idx_contestants_country_id)` | Country of origin |
| `photo_media_id` | `CHAR(36)` | `NULL, FK → media_assets.id (SET NULL)` | Profile photo media asset |
| `phone` | `VARCHAR(50)` | `NOT NULL` | Contact phone number |
| `date_of_birth` | `DATE` | `NOT NULL` | Date of birth |
| `gender` | `VARCHAR(10)` | `NOT NULL` | `male\|female` |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Status flag |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_contestants_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 14: `contestant_documents`
- **Migration File**: `2026_01_04_000002_create_contestant_documents_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `contestant_id` | `CHAR(36)` | `NOT NULL, FK → contestants.id (CASCADE), INDEX (idx_contestant_docs_contestant)` | Parent contestant FK |
| `media_asset_id` | `CHAR(36)` | `NOT NULL, FK → media_assets.id (RESTRICT)` | Document media asset FK |
| `document_type` | `VARCHAR(50)` | `NOT NULL` | `passport\|national_id\|other` |
| `is_verified` | `TINYINT(1)` | `NOT NULL DEFAULT 0` | Document verification status |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_contestant_docs_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

### 2.5 Module: Competition

#### Table 15: `seasons`
- **Migration File**: `2026_01_05_000001_create_seasons_table.php`
- **Delete Policy**: Archive Only

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `name` | `VARCHAR(255)` | `NOT NULL` | Season title |
| `year` | `INT` | `NOT NULL, UNIQUE (uk_seasons_year)` | Competition year |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'draft', INDEX (idx_seasons_status)` | `draft\|active\|closed\|archived` |
| `is_current` | `TINYINT(1)` | `NOT NULL DEFAULT 0, INDEX (idx_seasons_is_current)` | Single active season indicator |
| `starts_at` | `TIMESTAMP` | `NOT NULL` | Season start date |
| `ends_at` | `TIMESTAMP` | `NOT NULL` | Season end date |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 16: `stages`
- **Migration File**: `2026_01_05_000002_create_stages_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `season_id` | `CHAR(36)` | `NOT NULL, FK → seasons.id (RESTRICT), INDEX (idx_stages_season_id)` | Parent season FK |
| `name` | `VARCHAR(255)` | `NOT NULL` | Stage title |
| `type` | `VARCHAR(50)` | `NOT NULL` | `stage_1\|stage_2\|semi_final\|final` |
| `order` | `INT` | `NOT NULL DEFAULT 1` | Execution sequence order |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'scheduled', INDEX (idx_stages_status)` | `scheduled\|active\|completed\|results_published` |
| `scheduled_at` | `TIMESTAMP` | `NOT NULL` | Scheduled start |
| `started_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Actual start |
| `completed_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Actual completion |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_stages_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 17: `stage_judge_assignments`
- **Migration File**: `2026_01_05_000003_create_stage_judge_assignments_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `stage_id` | `CHAR(36)` | `NOT NULL, FK → stages.id (CASCADE)` | Target stage FK |
| `judge_id` | `CHAR(36)` | `NOT NULL, FK → judges.id (RESTRICT)` | Assigned judge FK |
| `assigned_by` | `CHAR(36)` | `NOT NULL, FK → users.id (RESTRICT)` | Assigner admin FK |
| `assigned_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Assignment timestamp |

- **Unique Constraint**: `uk_stage_judge_assignments (stage_id, judge_id)`

---

#### Table 18: `stage_results`
- **Migration File**: `2026_01_05_000004_create_stage_results_table.php`
- **Delete Policy**: Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `stage_id` | `CHAR(36)` | `NOT NULL, UNIQUE (uk_stage_results_stage_id), FK → stages.id (RESTRICT)` | Target stage FK |
| `results_data` | `JSON` | `NOT NULL` | Official calculated result set |
| `published_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Publication timestamp |
| `published_by` | `CHAR(36)` | `NOT NULL, FK → users.id (RESTRICT)` | Publishing admin FK |

---

### 2.6 Module: Judges

#### Table 19: `judges`
- **Migration File**: `2026_01_06_000001_create_judges_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `user_id` | `CHAR(36)` | `NOT NULL, UNIQUE (uk_judges_user_id), FK → users.id (RESTRICT)` | Linked user account |
| `country_id` | `CHAR(36)` | `NOT NULL, FK → countries.id (RESTRICT), INDEX (idx_judges_country_id)` | Country of origin |
| `photo_media_id` | `CHAR(36)` | `NULL, FK → media_assets.id (SET NULL)` | Profile photo media asset |
| `specialization` | `VARCHAR(255)` | `NOT NULL` | Specialization focus |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Status flag |
| `display_order` | `INT` | `NOT NULL DEFAULT 0` | UI presentation order |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_judges_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 20: `judge_biographies`
- **Migration File**: `2026_01_06_000002_create_judge_biographies_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `judge_id` | `CHAR(36)` | `NOT NULL, FK → judges.id (CASCADE)` | Parent judge FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `biography` | `LONGTEXT` | `NOT NULL` | Translated biography |

- **Unique Constraint**: `uk_judge_biographies_judge_locale (judge_id, locale)`

---

### 2.7 Module: Videos

#### Table 21: `videos`
- **Migration File**: `2026_01_07_000001_create_videos_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `media_asset_id` | `CHAR(36)` | `NOT NULL, FK → media_assets.id (RESTRICT)` | Original upload media asset |
| `application_id` | `CHAR(36)` | `NULL, FK → applications.id (SET NULL), INDEX (idx_videos_application_id)` | Associated application |
| `contestant_id` | `CHAR(36)` | `NOT NULL, FK → contestants.id (RESTRICT), INDEX (idx_videos_contestant_id)` | Contestant owner |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'uploaded', INDEX (idx_videos_status)` | `uploaded\|processing\|processed\|published\|rejected\|failed` |
| `duration_seconds` | `INT` | `NULL` | Video duration in seconds |
| `processed_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Transcoding completion timestamp |
| `published_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_videos_published_at)` | Public gallery publish timestamp |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_videos_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 22: `video_variants`
- **Migration File**: `2026_01_07_000002_create_video_variants_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `video_id` | `CHAR(36)` | `NOT NULL, FK → videos.id (CASCADE), INDEX (idx_video_variants_video_id)` | Parent video FK |
| `media_asset_id` | `CHAR(36)` | `NOT NULL, FK → media_assets.id (RESTRICT)` | Variant media asset FK |
| `quality` | `VARCHAR(20)` | `NOT NULL` | `360p\|720p\|1080p` |
| `format` | `VARCHAR(20)` | `NOT NULL` | `mp4\|hls_manifest` |
| `processed_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Creation timestamp |

---

#### Table 23: `video_thumbnails`
- **Migration File**: `2026_01_07_000003_create_video_thumbnails_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `video_id` | `CHAR(36)` | `NOT NULL, FK → videos.id (CASCADE), INDEX (idx_video_thumbnails_video_id)` | Parent video FK |
| `media_asset_id` | `CHAR(36)` | `NOT NULL, FK → media_assets.id (RESTRICT)` | Thumbnail image media asset FK |
| `type` | `VARCHAR(20)` | `NOT NULL` | `default\|hd` |
| `width` | `INT` | `NOT NULL` | Thumbnail pixel width |
| `height` | `INT` | `NOT NULL` | Thumbnail pixel height |

---

### 2.8 Module: Applications

#### Table 24: `applications`
- **Migration File**: `2026_01_08_000001_create_applications_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `contestant_id` | `CHAR(36)` | `NOT NULL, FK → contestants.id (RESTRICT)` | Applicant FK |
| `season_id` | `CHAR(36)` | `NOT NULL, FK → seasons.id (RESTRICT)` | Competition season FK |
| `video_id` | `CHAR(36)` | `NULL, FK → videos.id (SET NULL)` | Recitation video FK |
| `reviewer_id` | `CHAR(36)` | `NULL, FK → users.id (SET NULL)` | Assigned reviewer FK |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'received', INDEX (idx_applications_status)` | State machine status |
| `rejection_reason` | `TEXT` | `NULL` | Rejection rationale if rejected |
| `submitted_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (idx_applications_submitted_at)` | Submission timestamp |
| `reviewed_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Review timestamp |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_applications_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

- **Unique Constraint**: `uk_applications_contestant_season (contestant_id, season_id)`

---

#### Table 25: `application_documents`
- **Migration File**: `2026_01_08_000002_create_application_documents_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `application_id` | `CHAR(36)` | `NOT NULL, FK → applications.id (CASCADE), INDEX (idx_app_docs_application_id)` | Parent application FK |
| `media_asset_id` | `CHAR(36)` | `NOT NULL, FK → media_assets.id (RESTRICT)` | Attachment file FK |
| `document_type` | `VARCHAR(50)` | `NOT NULL` | Document category code |
| `is_required` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Mandatory flag |
| `submitted_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Upload timestamp |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_app_docs_deleted_at)` | Soft delete timestamp |

---

#### Table 26: `application_status_histories`
- **Migration File**: `2026_01_08_000003_create_application_status_histories_table.php`
- **Delete Policy**: Append Only + Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `application_id` | `CHAR(36)` | `NOT NULL, FK → applications.id (RESTRICT), INDEX (idx_app_hist_application_id)` | Target application FK |
| `from_status` | `VARCHAR(50)` | `NOT NULL` | Previous status state |
| `to_status` | `VARCHAR(50)` | `NOT NULL` | New status state |
| `changed_by` | `CHAR(36)` | `NULL, FK → users.id (SET NULL)` | Transition actor FK |
| `reason` | `TEXT` | `NULL` | Transition notes or rationale |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (idx_app_hist_created_at)` | Log timestamp |

---

### 2.9 Module: Evaluations

#### Table 27: `evaluations`
- **Migration File**: `2026_01_09_000001_create_evaluations_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `application_id` | `CHAR(36)` | `NOT NULL, FK → applications.id (RESTRICT)` | Evaluated application FK |
| `stage_id` | `CHAR(36)` | `NOT NULL, FK → stages.id (RESTRICT)` | Competition stage FK |
| `judge_id` | `CHAR(36)` | `NOT NULL, FK → judges.id (RESTRICT)` | Evaluating judge FK |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'pending', INDEX (idx_evaluations_status)` | `pending\|in_progress\|completed\|approved` |
| `total_score` | `DECIMAL(5,2)` | `NULL` | Aggregate calculated score |
| `notes` | `TEXT` | `NULL` | Overall judge feedback |
| `version` | `INT` | `NOT NULL DEFAULT 0` | Optimistic Concurrency Lock Version |
| `submitted_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Submission timestamp |
| `approved_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Manager approval timestamp |
| `approved_by` | `CHAR(36)` | `NULL, FK → users.id (SET NULL)` | Approving manager FK |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_evaluations_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

- **Unique Constraint**: `uk_evaluations_app_stage_judge (application_id, stage_id, judge_id)`

---

#### Table 28: `evaluation_criteria`
- **Migration File**: `2026_01_09_000002_create_evaluation_criteria_table.php`
- **Delete Policy**: Never Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `code` | `VARCHAR(100)` | `NOT NULL, UNIQUE (uk_evaluation_criteria_code)` | `tajweed\|pronunciation\|vocal_control` |
| `max_score` | `DECIMAL(5,2)` | `NOT NULL DEFAULT 100.00` | Maximum points |
| `weight` | `DECIMAL(5,2)` | `NOT NULL DEFAULT 1.00` | Rubric weighting multiplier |
| `order` | `INT` | `NOT NULL DEFAULT 0` | Sheet presentation order |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Status flag |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 29: `evaluation_criterion_translations`
- **Migration File**: `2026_01_09_000003_create_evaluation_criterion_translations_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `criterion_id` | `CHAR(36)` | `NOT NULL, FK → evaluation_criteria.id (CASCADE)` | Parent criterion FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `name` | `VARCHAR(255)` | `NOT NULL` | Criterion display title |
| `description` | `TEXT` | `NOT NULL` | Rubric guidance text |

- **Unique Constraint**: `uk_eval_criterion_translations (criterion_id, locale)`

---

#### Table 30: `evaluation_scores`
- **Migration File**: `2026_01_09_000004_create_evaluation_scores_table.php`
- **Delete Policy**: Archive Only

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `evaluation_id` | `CHAR(36)` | `NOT NULL, FK → evaluations.id (CASCADE)` | Parent evaluation session FK |
| `criterion_id` | `CHAR(36)` | `NOT NULL, FK → evaluation_criteria.id (RESTRICT)` | Evaluated criterion FK |
| `score` | `DECIMAL(5,2)` | `NOT NULL` | Awarded points |
| `note` | `TEXT` | `NULL` | Specific criterion note |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

- **Unique Constraint**: `uk_evaluation_scores_eval_criterion (evaluation_id, criterion_id)`

---

### 2.10 Module: Streaming

#### Table 31: `streams`
- **Migration File**: `2026_01_10_000001_create_streams_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `season_id` | `CHAR(36)` | `NULL, FK → seasons.id (SET NULL), INDEX (idx_streams_season_id)` | Associated season context |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'offline', INDEX (idx_streams_status)` | `offline\|live\|paused` |
| `title` | `VARCHAR(255)` | `NOT NULL` | Live broadcast title |
| `viewer_count` | `INT` | `NOT NULL DEFAULT 0` | Concurrent viewer stats |
| `started_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Broadcast start timestamp |
| `stopped_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Broadcast stop timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 32: `stream_sources`
- **Migration File**: `2026_01_10_000002_create_stream_sources_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `stream_id` | `CHAR(36)` | `NOT NULL, FK → streams.id (CASCADE), INDEX (idx_stream_sources_stream_id)` | Parent stream FK |
| `protocol` | `VARCHAR(20)` | `NOT NULL` | `rtmp\|hls\|dash` |
| `url` | `VARCHAR(1000)` | `NOT NULL` | Ingest/Playback URL |
| `is_primary` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Primary feed flag |

---

### 2.11 Module: Content (CMS)

#### Table 33: `pages`
- **Migration File**: `2026_01_11_000001_create_pages_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `slug` | `VARCHAR(255)` | `NOT NULL, UNIQUE (uk_pages_slug)` | URL slug identifier |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'draft', INDEX (idx_pages_status)` | `draft\|published` |
| `template` | `VARCHAR(100)` | `NULL DEFAULT 'default'` | Page layout template |
| `published_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Publication timestamp |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_pages_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 34: `page_translations`
- **Migration File**: `2026_01_11_000002_create_page_translations_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `page_id` | `CHAR(36)` | `NOT NULL, FK → pages.id (CASCADE)` | Parent page FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `title` | `VARCHAR(255)` | `NOT NULL` | Page title |
| `body` | `LONGTEXT` | `NOT NULL` | Page HTML/Markdown body |
| `meta_title` | `VARCHAR(255)` | `NULL` | SEO title |
| `meta_description` | `VARCHAR(500)` | `NULL` | SEO meta description |

- **Unique Constraint**: `uk_page_translations_page_locale (page_id, locale)`

---

#### Table 35: `announcements`
- **Migration File**: `2026_01_11_000003_create_announcements_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `image_media_id` | `CHAR(36)` | `NULL, FK → media_assets.id (SET NULL)` | Banner image media asset |
| `status` | `VARCHAR(50)` | `NOT NULL DEFAULT 'draft', INDEX (idx_announcements_status)` | `draft\|published\|scheduled` |
| `published_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Actual publication timestamp |
| `scheduled_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Scheduled publishing time |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_announcements_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 36: `announcement_translations`
- **Migration File**: `2026_01_11_000004_create_announcement_translations_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `announcement_id` | `CHAR(36)` | `NOT NULL, FK → announcements.id (CASCADE)` | Parent announcement FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `title` | `VARCHAR(255)` | `NOT NULL` | Announcement title |
| `body` | `TEXT` | `NOT NULL` | Announcement content |

- **Unique Constraint**: `uk_announcement_translations_loc (announcement_id, locale)`

---

#### Table 37: `faqs`
- **Migration File**: `2026_01_11_000005_create_faqs_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Visibility status |
| `display_order` | `INT` | `NOT NULL DEFAULT 0, INDEX (idx_faqs_display_order)` | Sorting order |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_faqs_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 38: `faq_translations`
- **Migration File**: `2026_01_11_000006_create_faq_translations_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `faq_id` | `CHAR(36)` | `NOT NULL, FK → faqs.id (CASCADE)` | Parent FAQ FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `question` | `TEXT` | `NOT NULL` | Translated question |
| `answer` | `TEXT` | `NOT NULL` | Translated answer |

- **Unique Constraint**: `uk_faq_translations_faq_locale (faq_id, locale)`

---

### 2.12 Module: Sponsors

#### Table 39: `sponsors`
- **Migration File**: `2026_01_12_000001_create_sponsors_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `logo_media_id` | `CHAR(36)` | `NULL, FK → media_assets.id (SET NULL)` | Logo image media asset |
| `name` | `VARCHAR(255)` | `NOT NULL` | Sponsor official name |
| `website_url` | `VARCHAR(500)` | `NULL` | External website URL |
| `tier` | `VARCHAR(50)` | `NOT NULL DEFAULT 'partner'` | `platinum\|gold\|silver\|partner` |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Active visibility |
| `display_order` | `INT` | `NOT NULL DEFAULT 0, INDEX (idx_sponsors_display_order)` | Presentation order |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_sponsors_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

### 2.13 Module: Notifications

#### Table 40: `notifications`
- **Migration File**: `2026_01_13_000001_create_notifications_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `user_id` | `CHAR(36)` | `NOT NULL, FK → users.id (RESTRICT), INDEX (idx_notifications_user_id)` | Recipient user FK |
| `type` | `VARCHAR(100)` | `NOT NULL` | Notification class name |
| `channel` | `VARCHAR(20)` | `NOT NULL` | `in_app\|email\|sms` |
| `data` | `JSON` | `NOT NULL` | Rendered content payload snapshot |
| `read_at` | `TIMESTAMP` | `NULL DEFAULT NULL` | Read confirmation timestamp |
| `deleted_at` | `TIMESTAMP` | `NULL DEFAULT NULL, INDEX (idx_notifications_deleted_at)` | Soft delete timestamp |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (idx_notifications_created_at)` | Dispatch timestamp |

- **Composite Index**: `idx_notifications_user_read (user_id, read_at)`

---

#### Table 41: `notification_templates`
- **Migration File**: `2026_01_13_000002_create_notification_templates_table.php`
- **Delete Policy**: Soft Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `event_type` | `VARCHAR(100)` | `NOT NULL, UNIQUE (uk_notif_temp_event_type)` | Domain event code |
| `channel` | `VARCHAR(20)` | `NOT NULL` | `in_app\|email\|sms` |
| `is_active` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Status flag |
| `created_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | — |
| `updated_at` | `TIMESTAMP` | `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | — |

---

#### Table 42: `notification_template_translations`
- **Migration File**: `2026_01_13_000003_create_notification_template_translations_table.php`
- **Delete Policy**: Hard Delete

| Column Name | Type | Modifiers | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | `PRIMARY KEY` | UUID v7 |
| `template_id` | `CHAR(36)` | `NOT NULL, FK → notification_templates.id (CASCADE)` | Parent template FK |
| `locale` | `VARCHAR(10)` | `NOT NULL, FK → languages.code (RESTRICT)` | Locale code |
| `subject` | `VARCHAR(255)` | `NOT NULL` | Notification subject/header template |
| `body` | `TEXT` | `NOT NULL` | Notification body template |

- **Unique Constraint**: `uk_notif_temp_trans (template_id, locale)`

---

## 3. Sign-off & Transition to Implementation

This specification is complete. The backend team can now write the 42 migration files strictly using these field signatures and constraints.

- Next Phase: **Module Code Implementation & Migrations**
- Following Phase: **ADR-008: Eventing & Domain Events Governance**
