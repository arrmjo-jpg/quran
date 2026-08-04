# Database Domain Inventory — Global Quran Competition Platform

> **Purpose**: Prerequisite Step 1 of the 5-step database design pipeline (see ADR-ROADMAP.md).
> This document identifies all entities, their ownership, their cross-cutting characteristics,
> and the three critical cross-module patterns (State Machines, Translation, Video Storage)
> before any schema decision is made.
>
> **Output feeds into**: ENTITY-RELATIONSHIP-MAP.md → ADR-005
> **Status**: Complete
> **Date**: 2026-07-31

---

## 1. Master Entity Registry

The following table lists all 42 entities across all 15 modules. For each entity:
- **AR** = Aggregate Root
- **SD** = Soft Delete
- **AU** = Audit Logged
- **TR** = Translatable Fields
- **SR** = Searchable (Meilisearch)
- **CA** = Cacheable (Redis)
- **UUID** = UUID v7 primary key

| # | Entity | Module | AR | UUID | SD | AU | TR | SR | CA |
|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | `User` | Core | ✅ | ✅ | ✅ | ✅ | — | — | ✅ |
| 2 | `Role` | Core | ✅ | ✅ | — | ✅ | — | — | ✅ |
| 3 | `Permission` | Core | — | ✅ | — | — | — | — | ✅ |
| 4 | `Language` | Core | ✅ | ✅ | — | ✅ | — | — | ✅ |
| 5 | `Setting` | Core | — | — | — | ✅ | — | — | ✅ |
| 6 | `AuditLog` | Core | ✅ | ✅ | — | — | — | — | — |
| 7 | `Country` | Countries | ✅ | ✅ | — | — | ✅ | ✅ | ✅ |
| 8 | `Contestant` | Contestants | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| 9 | `ContestantDocument` | Contestants | — | ✅ | ✅ | ✅ | — | — | — |
| 10 | `Application` | Applications | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| 11 | `ApplicationDocument` | Applications | — | ✅ | ✅ | ✅ | — | — | — |
| 12 | `ApplicationStatusHistory` | Applications | — | ✅ | — | — | — | — | — |
| 13 | `Judge` | Judges | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| 14 | `JudgeBiography` | Judges | — | ✅ | — | — | ✅ | — | — |
| 15 | `Season` | Competition | ✅ | ✅ | ✅ | ✅ | — | — | ✅ |
| 16 | `Stage` | Competition | ✅ | ✅ | ✅ | ✅ | — | — | ✅ |
| 17 | `StageJudgeAssignment` | Competition | — | ✅ | — | ✅ | — | — | — |
| 18 | `StageResult` | Competition | — | ✅ | — | ✅ | — | — | — |
| 19 | `Evaluation` | Evaluations | ✅ | ✅ | ✅ | ✅ | — | — | — |
| 20 | `EvaluationCriterion` | Evaluations | ✅ | ✅ | — | ✅ | ✅ | — | ✅ |
| 21 | `EvaluationScore` | Evaluations | — | ✅ | — | ✅ | — | — | — |
| 22 | `Video` | Videos | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| 23 | `VideoVariant` | Videos | — | ✅ | — | — | — | — | — |
| 24 | `VideoThumbnail` | Videos | — | ✅ | — | — | — | — | — |
| 25 | `MediaAsset` | Media | ✅ | ✅ | ✅ | — | — | — | — |
| 26 | `Stream` | Streaming | ✅ | ✅ | — | ✅ | — | — | ✅ |
| 27 | `StreamSource` | Streaming | — | ✅ | — | ✅ | — | — | — |
| 28 | `Page` | Content | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| 29 | `PageTranslation` | Content | — | ✅ | — | — | — | — | — |
| 30 | `Announcement` | Content | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 31 | `AnnouncementTranslation` | Content | — | ✅ | — | — | — | — | — |
| 32 | `FAQ` | Content | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| 33 | `FAQTranslation` | Content | — | ✅ | — | — | — | — | — |
| 34 | `Sponsor` | Sponsors | ✅ | ✅ | ✅ | ✅ | — | — | ✅ |
| 35 | `Notification` | Notifications | ✅ | ✅ | ✅ | — | — | — | — |
| 36 | `NotificationTemplate` | Notifications | ✅ | ✅ | — | ✅ | ✅ | — | — |
| 37 | `NotificationTemplateTranslation` | Notifications | — | ✅ | — | — | — | — | — |
| 38 | `CountryTranslation` | Countries | — | ✅ | — | — | — | — | — |
| 39 | `EvaluationCriterionTranslation` | Evaluations | — | ✅ | — | — | — | — | — |
| 40 | `PermissionRoleAssignment` | Core | — | — | — | — | — | — | — |
| 41 | `RoleUserAssignment` | Core | — | — | — | — | — | — | — |
| — | Reports | Reports | — | — | — | — | — | — | — |
| — | Search indexes | Search | — | — | — | — | — | — | — |
| — | Cache entries | Cache | — | — | — | — | — | — | — |

> **Reports, Search, Cache**: These modules have **no database tables**. Reports uses read models (queries across other modules' tables). Search uses Meilisearch indexes. Cache uses Redis. This is an explicit architectural decision.

---

## 2. Module Entity Detail Sheets

---

### MODULE: Core

#### Entity: `users`

| Property | Value |
|---|---|
| **Table name** | `users` |
| **Aggregate Root** | Yes |
| **Owner** | Core — the only module that may write to this table |
| **Key fields** | `id` (UUID v7), `email`, `type` (enum: `user`, `admin`), `name`, `is_active` |
| **Relationships** | Has many Roles (many-to-many via `role_user`); has one Contestant profile; has one Judge profile; has many AuditLogs |
| **Soft Delete** | Yes — `deleted_at` |
| **Audit** | Yes — all writes are audit logged |
| **Translatable** | No — user names are stored as-entered |
| **Searchable** | No — admin user search uses DB query, not Meilisearch |
| **Cacheable** | Yes — authenticated user profile is cached per token |
| **State** | `is_active` boolean flag — not a state machine (only two states: active/inactive) |
| **Notes** | The `type` column is the authentication surface discriminator. It is set on creation and must never be changed post-creation. |

#### Entity: `roles`

| Property | Value |
|---|---|
| **Table name** | `roles` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `name`, `guard_name` |
| **Relationships** | Has many Permissions (many-to-many via `role_has_permissions`); belongs to many Users (many-to-many via `model_has_roles`) |
| **Soft Delete** | No — roles are reference data; use deactivation if needed |
| **Audit** | Yes |
| **Translatable** | No |
| **Searchable** | No |
| **Cacheable** | Yes — role-permission mapping cached per role |
| **Notes** | Managed by Spatie Laravel Permission. Table names are Spatie's standard; the Core module owns this data. |

#### Entity: `permissions`

| Property | Value |
|---|---|
| **Table name** | `permissions` |
| **Aggregate Root** | No — seeded by modules, not user-managed |
| **Key fields** | `id` (UUID v7), `name` (format: `module.action`), `guard_name` |
| **Relationships** | Belongs to many Roles |
| **Soft Delete** | No |
| **Audit** | No — permissions are static data |
| **Cacheable** | Yes — entire permission set is cached |
| **Notes** | Permissions are never created at runtime. They are seeded by each module's `PermissionDefinition`. |

#### Entity: `languages`

| Property | Value |
|---|---|
| **Table name** | `languages` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `code` (BCP-47: `ar`, `en`, `es`), `name`, `native_name`, `is_active`, `rtl` |
| **Relationships** | Referenced by all translatable entity translation tables |
| **Soft Delete** | No |
| **Audit** | Yes |
| **Cacheable** | Yes — language registry is cached indefinitely |
| **Notes** | The `code` column is a candidate key (unique). It is used as the FK in all translation tables instead of UUID to simplify joins. |

#### Entity: `settings`

| Property | Value |
|---|---|
| **Table name** | `settings` |
| **Aggregate Root** | No — key-value store |
| **Key fields** | `key` (string, PK), `value` (text), `group` |
| **Relationships** | None |
| **Soft Delete** | No |
| **UUID** | No — `key` is the natural primary key |
| **Audit** | Yes |
| **Cacheable** | Yes — entire settings table cached |

#### Entity: `audit_logs`

| Property | Value |
|---|---|
| **Table name** | `audit_logs` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `user_id` (nullable FK → users), `action`, `auditable_type`, `auditable_id`, `old_values` (JSON), `new_values` (JSON), `ip_address`, `user_agent`, `created_at` |
| **Relationships** | Belongs to User (nullable — system actions have no user) |
| **Soft Delete** | No — audit logs are immutable and append-only |
| **Audit** | No — audit logs are not themselves audited |
| **Cacheable** | No |
| **Notes** | Append-only. No `updated_at`. No soft delete. Retention policy defined in ADR-007. Partitioned by `created_at` month in production. |

---

### MODULE: Countries

#### Entity: `countries`

| Property | Value |
|---|---|
| **Table name** | `countries` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `iso_code` (ISO 3166-1 alpha-2, unique), `is_active`, `display_order` |
| **Relationships** | Has many Contestants; has many Judges; has many CountryTranslations |
| **Soft Delete** | No |
| **Translatable** | Yes — `name`, `native_name` stored in `country_translations` |
| **Searchable** | Yes |
| **Cacheable** | Yes |
| **Notes** | `iso_code` is the natural identifier for the country. UUID is the FK used in relationships. |

#### Entity: `country_translations`

| Property | Value |
|---|---|
| **Table name** | `country_translations` |
| **Key fields** | `id` (UUID v7), `country_id` (FK → countries), `locale` (FK → languages.code), `name`, `native_name` |
| **Unique constraint** | `(country_id, locale)` |

---

### MODULE: Contestants

#### Entity: `contestants`

| Property | Value |
|---|---|
| **Table name** | `contestants` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `user_id` (FK → users, unique), `country_id` (FK → countries), `phone`, `date_of_birth`, `gender`, `photo_media_id` (FK → media_assets, nullable), `is_active` |
| **Relationships** | Belongs to User; belongs to Country; has many ContestantDocuments; has one Application (per season); has many MediaAssets |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Searchable** | Yes — `name` (from user), `email` (from user), `country.name` |
| **Cacheable** | Yes — profile data |
| **Notes** | `contestants` is a profile extension of `users`. It never duplicates fields owned by `users` (email, name). The contestant's name and email are always read from the `users` table. |

#### Entity: `contestant_documents`

| Property | Value |
|---|---|
| **Table name** | `contestant_documents` |
| **Key fields** | `id` (UUID v7), `contestant_id` (FK → contestants), `media_asset_id` (FK → media_assets), `document_type` (passport, id_card, etc.), `is_verified` |
| **Soft Delete** | Yes |
| **Audit** | Yes |

---

### MODULE: Applications

#### Entity: `applications`

| Property | Value |
|---|---|
| **Table name** | `applications` |
| **Aggregate Root** | Yes — the central aggregate of the competition workflow |
| **Key fields** | `id` (UUID v7), `contestant_id` (FK → contestants), `season_id` (FK → seasons), `status`, `submitted_at`, `reviewed_at`, `reviewer_id` (FK → users, nullable), `rejection_reason` (text, nullable), `notes` (text, nullable), `video_id` (FK → videos, nullable) |
| **Unique constraint** | `(contestant_id, season_id)` — one application per contestant per season |
| **Relationships** | Belongs to Contestant; belongs to Season; has many ApplicationDocuments; has many ApplicationStatusHistories; has one Video |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Searchable** | Yes |
| **Cacheable** | Yes — admin list view |
| **State Machine** | See Section 3 — Application State Machine |

#### Entity: `application_documents`

| Property | Value |
|---|---|
| **Table name** | `application_documents` |
| **Key fields** | `id` (UUID v7), `application_id` (FK → applications), `media_asset_id` (FK → media_assets), `document_type`, `is_required`, `submitted_at` |
| **Soft Delete** | Yes |
| **Audit** | Yes |

#### Entity: `application_status_histories`

| Property | Value |
|---|---|
| **Table name** | `application_status_histories` |
| **Key fields** | `id` (UUID v7), `application_id` (FK → applications), `from_status`, `to_status`, `changed_by` (FK → users, nullable), `reason` (text, nullable), `created_at` |
| **Soft Delete** | No — immutable history |
| **Audit** | No — this table IS the audit trail for applications |
| **Notes** | Append-only. No `updated_at`. |

---

### MODULE: Judges

#### Entity: `judges`

| Property | Value |
|---|---|
| **Table name** | `judges` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `user_id` (FK → users, unique), `country_id` (FK → countries), `specialization`, `photo_media_id` (FK → media_assets, nullable), `is_active`, `display_order` |
| **Relationships** | Belongs to User; belongs to Country; has many StageJudgeAssignments; has one JudgeBiography; has many Evaluations |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Searchable** | Yes — `name` (from user), `country.name`, `specialization` |
| **Cacheable** | Yes — public judge panel list |

#### Entity: `judge_biographies`

| Property | Value |
|---|---|
| **Table name** | `judge_biographies` |
| **Key fields** | `id` (UUID v7), `judge_id` (FK → judges, unique), `locale` (FK → languages.code), `biography` (longtext) |
| **Unique constraint** | `(judge_id, locale)` |
| **Translatable** | Yes — separate row per locale |

---

### MODULE: Competition

#### Entity: `seasons`

| Property | Value |
|---|---|
| **Table name** | `seasons` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `name`, `year` (unique), `status`, `starts_at`, `ends_at`, `is_current` (boolean) |
| **Relationships** | Has many Stages; has many Applications |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Cacheable** | Yes — current season is frequently queried |
| **State Machine** | See Section 3 — Season State Machine |
| **Constraint** | Only one Season may have `is_current = true` at any time (enforced at application layer + unique partial index) |

#### Entity: `stages`

| Property | Value |
|---|---|
| **Table name** | `stages` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `season_id` (FK → seasons), `name`, `type` (enum: `stage_1`, `stage_2`, `semi_final`, `final`), `order` (integer), `status`, `scheduled_at`, `started_at`, `completed_at` |
| **Relationships** | Belongs to Season; has many StageJudgeAssignments; has many Evaluations; has one StageResult |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Cacheable** | Yes |
| **State Machine** | See Section 3 — Stage State Machine |

#### Entity: `stage_judge_assignments`

| Property | Value |
|---|---|
| **Table name** | `stage_judge_assignments` |
| **Key fields** | `id` (UUID v7), `stage_id` (FK → stages), `judge_id` (FK → judges), `assigned_by` (FK → users), `assigned_at` |
| **Unique constraint** | `(stage_id, judge_id)` |
| **Soft Delete** | No — if a judge is removed, the row is deleted |
| **Audit** | Yes |

#### Entity: `stage_results`

| Property | Value |
|---|---|
| **Table name** | `stage_results` |
| **Key fields** | `id` (UUID v7), `stage_id` (FK → stages, unique), `results_data` (JSON — ranked contestant list with scores), `published_at`, `published_by` (FK → users) |
| **Soft Delete** | No — results are immutable once published |
| **Audit** | Yes |

---

### MODULE: Evaluations

#### Entity: `evaluations`

| Property | Value |
|---|---|
| **Table name** | `evaluations` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `application_id` (FK → applications), `stage_id` (FK → stages), `judge_id` (FK → judges), `status`, `total_score` (decimal, nullable), `notes` (text, nullable), `submitted_at` (nullable), `approved_at` (nullable), `approved_by` (FK → users, nullable), `version` (integer, optimistic lock) |
| **Unique constraint** | `(application_id, stage_id, judge_id)` — one evaluation per application per stage per judge |
| **Relationships** | Belongs to Application; belongs to Stage; belongs to Judge; has many EvaluationScores |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **State Machine** | See Section 3 — Evaluation State Machine |
| **Optimistic Lock** | `version` column — concurrent score submission protection |

#### Entity: `evaluation_criteria`

| Property | Value |
|---|---|
| **Table name** | `evaluation_criteria` |
| **Aggregate Root** | Yes — the scoring rubric is managed as criteria |
| **Key fields** | `id` (UUID v7), `code` (unique string: `tajweed`, `pronunciation`, etc.), `max_score` (decimal), `weight` (decimal), `order` (integer), `is_active` |
| **Relationships** | Has many EvaluationCriterionTranslations; has many EvaluationScores |
| **Translatable** | Yes — `name`, `description` |
| **Cacheable** | Yes — rubric changes infrequently |
| **Notes** | The rubric applies globally across all stages and seasons. Season-specific rubric variations would require extending this model — out of scope for v1. |

#### Entity: `evaluation_criterion_translations`

| Property | Value |
|---|---|
| **Table name** | `evaluation_criterion_translations` |
| **Key fields** | `id` (UUID v7), `criterion_id` (FK → evaluation_criteria), `locale` (FK → languages.code), `name`, `description` |
| **Unique constraint** | `(criterion_id, locale)` |

#### Entity: `evaluation_scores`

| Property | Value |
|---|---|
| **Table name** | `evaluation_scores` |
| **Key fields** | `id` (UUID v7), `evaluation_id` (FK → evaluations), `criterion_id` (FK → evaluation_criteria), `score` (decimal), `note` (text, nullable) |
| **Unique constraint** | `(evaluation_id, criterion_id)` |
| **Soft Delete** | No |
| **Audit** | Yes |

---

### MODULE: Videos

#### Entity: `videos`

| Property | Value |
|---|---|
| **Table name** | `videos` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `media_asset_id` (FK → media_assets — the original upload), `application_id` (FK → applications, nullable), `contestant_id` (FK → contestants), `status`, `duration_seconds` (integer, nullable), `processed_at` (nullable), `published_at` (nullable) |
| **Relationships** | Belongs to MediaAsset (original file); belongs to Application (nullable); belongs to Contestant; has many VideoVariants; has many VideoThumbnails |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Searchable** | Yes |
| **Cacheable** | Yes — gallery list |
| **State Machine** | See Section 3 — Video State Machine |
| **Notes** | File metadata (disk, path, size, mime_type, original_filename) lives entirely in `media_assets`. The `videos` table holds only domain-level attributes (status, duration, timestamps). This follows the unified Media Ownership Convention (Section 9). |

#### Entity: `video_variants`

| Property | Value |
|---|---|
| **Table name** | `video_variants` |
| **Key fields** | `id` (UUID v7), `video_id` (FK → videos), `media_asset_id` (FK → media_assets — the processed file), `quality` (enum: `360p`, `720p`, `1080p`), `format` (enum: `mp4`, `hls_manifest`), `processed_at` |
| **Notes** | Storage metadata for each transcoded variant (path, size, disk) is in `media_assets`. `video_variants` holds only the quality/format classification. |

#### Entity: `video_thumbnails`

| Property | Value |
|---|---|
| **Table name** | `video_thumbnails` |
| **Key fields** | `id` (UUID v7), `video_id` (FK → videos), `media_asset_id` (FK → media_assets — the image file), `type` (enum: `default`, `hd`), `width`, `height` |
| **Notes** | Same pattern as video_variants — media_assets holds the file, video_thumbnails holds the semantic metadata. |

---

### MODULE: Media

#### Entity: `media_assets`

| Property | Value |
|---|---|
| **Table name** | `media_assets` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `disk` (string: `local`, `s3`, `r2`), `path` (string), `original_filename`, `mime_type`, `file_size_bytes` (bigint), `uploaded_by` (FK → users, nullable) |
| **Polymorphic** | `morphable_type` + `morphable_id` — links to any entity that owns a media asset |
| **Soft Delete** | Yes — soft delete first; physical deletion is a separate cleanup job |
| **Notes** | The actual file is not deleted when the record is soft-deleted. A background job processes physical deletion of orphaned files on a schedule. |

---

### MODULE: Streaming

#### Entity: `streams`

| Property | Value |
|---|---|
| **Table name** | `streams` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `season_id` (FK → seasons, nullable), `status` (enum: `offline`, `live`, `paused`), `title`, `started_at` (nullable), `stopped_at` (nullable), `viewer_count` (integer) |
| **Soft Delete** | No |
| **Audit** | Yes |
| **Cacheable** | Yes — stream status is high-frequency public read |
| **Notes** | In v1, there is at most one active stream at a time. The table supports multiple historical stream records per season. |

#### Entity: `stream_sources`

| Property | Value |
|---|---|
| **Table name** | `stream_sources` |
| **Key fields** | `id` (UUID v7), `stream_id` (FK → streams), `protocol` (enum: `rtmp`, `hls`, `dash`), `url`, `is_primary` |

---

### MODULE: Content

#### Entity: `pages`

| Property | Value |
|---|---|
| **Table name** | `pages` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `slug` (unique), `status` (enum: `draft`, `published`), `published_at` (nullable), `template` (string, nullable) |
| **Relationships** | Has many PageTranslations |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Translatable** | Yes — `title`, `body`, `meta_title`, `meta_description` |
| **Cacheable** | Yes — public pages by slug |

#### Entity: `page_translations`

| Property | Value |
|---|---|
| **Table name** | `page_translations` |
| **Key fields** | `id` (UUID v7), `page_id` (FK → pages), `locale` (FK → languages.code), `title`, `body` (longtext), `meta_title`, `meta_description` |
| **Unique constraint** | `(page_id, locale)` |

#### Entity: `announcements`

| Property | Value |
|---|---|
| **Table name** | `announcements` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `status` (enum: `draft`, `published`, `scheduled`), `published_at` (nullable), `scheduled_at` (nullable), `image_media_id` (FK → media_assets, nullable) |
| **Relationships** | Has many AnnouncementTranslations |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Translatable** | Yes — `title`, `body` |
| **Searchable** | Yes — `title`, `body` (in resolved locale) |
| **Cacheable** | Yes |

#### Entity: `announcement_translations`

| Property | Value |
|---|---|
| **Table name** | `announcement_translations` |
| **Key fields** | `id` (UUID v7), `announcement_id` (FK → announcements), `locale` (FK → languages.code), `title`, `body` (text) |
| **Unique constraint** | `(announcement_id, locale)` |

#### Entity: `faqs`

| Property | Value |
|---|---|
| **Table name** | `faqs` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `is_active`, `display_order` (integer) |
| **Relationships** | Has many FAQTranslations |
| **Soft Delete** | Yes |
| **Translatable** | Yes — `question`, `answer` |
| **Cacheable** | Yes |

#### Entity: `faq_translations`

| Property | Value |
|---|---|
| **Table name** | `faq_translations` |
| **Key fields** | `id` (UUID v7), `faq_id` (FK → faqs), `locale` (FK → languages.code), `question`, `answer` (text) |
| **Unique constraint** | `(faq_id, locale)` |

---

### MODULE: Sponsors

#### Entity: `sponsors`

| Property | Value |
|---|---|
| **Table name** | `sponsors` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `name`, `website_url` (nullable), `logo_media_id` (FK → media_assets, nullable), `is_active`, `display_order` (integer), `tier` (enum: `platinum`, `gold`, `silver`, `partner`) |
| **Soft Delete** | Yes |
| **Audit** | Yes |
| **Cacheable** | Yes |

---

### MODULE: Notifications

#### Entity: `notifications`

| Property | Value |
|---|---|
| **Table name** | `notifications` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `user_id` (FK → users), `type` (string), `channel` (enum: `in_app`, `email`, `sms`), `data` (JSON — notification payload), `read_at` (nullable), `created_at` |
| **Soft Delete** | Yes |
| **Notes** | `data` JSON contains the localised notification content at the time of dispatch. It is not re-resolved from templates on read — it is stored as-dispatched. |

#### Entity: `notification_templates`

| Property | Value |
|---|---|
| **Table name** | `notification_templates` |
| **Aggregate Root** | Yes |
| **Key fields** | `id` (UUID v7), `event_type` (unique string: e.g. `application.approved`), `channel`, `is_active` |
| **Relationships** | Has many NotificationTemplateTranslations |
| **Audit** | Yes |
| **Translatable** | Yes — `subject`, `body` |

#### Entity: `notification_template_translations`

| Property | Value |
|---|---|
| **Table name** | `notification_template_translations` |
| **Key fields** | `id` (UUID v7), `template_id` (FK → notification_templates), `locale` (FK → languages.code), `subject`, `body` (text) |
| **Unique constraint** | `(template_id, locale)` |

---

## 3. State Machine Catalogue

This section documents every entity in the platform that has a meaningful state machine — i.e., more than two states with governed transitions.

---

### 3.1 Application State Machine

The Application is the most complex state machine in the platform. Every state transition must be validated at the application layer and recorded in `application_status_histories`.

```
                    ┌─────────────┐
                    │  RECEIVED   │ ← initial state on submission
                    └──────┬──────┘
                           │ Admin: mark for review
                           ▼
                   ┌───────────────┐
                   │ UNDER_REVIEW  │
                   └───────┬───────┘
          ┌────────────────┼────────────────┐
          │                │                │
          ▼                ▼                ▼
  ┌──────────────┐  ┌────────────┐  ┌──────────────────┐
  │  NEEDS_DATA  │  │  REJECTED  │  │ UNDER_EVALUATION │
  └──────┬───────┘  └────────────┘  └────────┬─────────┘
         │                                   │
         │ Contestant updates                │ Admin: approve/reject
         │ and resubmits                     │
         ▼                            ┌──────┴──────┐
   ┌─────────────┐                    │             │
   │  RECEIVED   │                    ▼             ▼
   └─────────────┘             ┌──────────┐  ┌──────────┐
                                │ ACCEPTED │  │ REJECTED │
                                └──────────┘  └──────────┘
         ┌─────────────────────────────────────────┐
         │ Special transition from UNDER_REVIEW:    │
         │ Admin may request video re-upload        │
         ▼                                         │
   ┌──────────────────┐                            │
   │ VIDEO_REUPLOAD   │ ────────────────────────────┘
   │ _REQUESTED       │ (after video uploaded → back to UNDER_REVIEW)
   └──────────────────┘
```

**Status column values**: `received`, `under_review`, `under_evaluation`, `accepted`, `rejected`, `needs_data`, `video_reupload_requested`

**Permitted transitions** (enforced at application layer):

| From | To | Actor | Trigger |
|---|---|---|---|
| `received` | `under_review` | Admin (Data Entry, Moderator) | Manual review action |
| `under_review` | `under_evaluation` | Admin (Moderator) | Forward to evaluation |
| `under_review` | `rejected` | Admin (Moderator) | Reject with reason |
| `under_review` | `needs_data` | Admin (Moderator) | Request additional info |
| `under_review` | `video_reupload_requested` | Admin (Moderator) | Request new video |
| `needs_data` | `received` | System | Contestant resubmits |
| `video_reupload_requested` | `under_review` | System | Contestant uploads new video |
| `under_evaluation` | `accepted` | Admin (Competition Manager) | Official approval |
| `under_evaluation` | `rejected` | Admin (Competition Manager) | Rejection after evaluation |

---

### 3.2 Season State Machine

```
┌────────┐   Create   ┌────────┐  Activate  ┌────────┐  Close  ┌────────┐  Archive  ┌──────────┐
│        │ ─────────▶ │        │ ──────────▶ │        │ ──────▶ │        │ ────────▶ │          │
│ (none) │            │ DRAFT  │            │ ACTIVE │         │ CLOSED │           │ ARCHIVED │
│        │            │        │            │        │         │        │           │          │
└────────┘            └────────┘            └────────┘         └────────┘           └──────────┘
```

**Status values**: `draft`, `active`, `closed`, `archived`
**Constraint**: Only one season may be `active` at any time.

---

### 3.3 Stage State Machine

```
┌──────────────┐  Admin starts  ┌─────────┐  Judging done  ┌───────────┐  Results out  ┌────────────────────┐
│  SCHEDULED   │ ─────────────▶ │ ACTIVE  │ ─────────────▶ │ COMPLETED │ ─────────────▶ │  RESULTS_PUBLISHED │
└──────────────┘                └─────────┘                └───────────┘                └────────────────────┘
```

**Status values**: `scheduled`, `active`, `completed`, `results_published`

---

### 3.4 Evaluation State Machine

```
┌─────────┐  Judge opens  ┌─────────────┐  Judge submits  ┌───────────┐  Manager approves  ┌──────────┐
│ PENDING │ ────────────▶ │ IN_PROGRESS │ ───────────────▶ │ COMPLETED │ ──────────────────▶ │ APPROVED │
└─────────┘               └─────────────┘                  └───────────┘                    └──────────┘
```

**Status values**: `pending`, `in_progress`, `completed`, `approved`

---

### 3.5 Video State Machine

```
┌──────────┐  Job starts  ┌────────────┐  Job done  ┌───────────┐  Admin publish  ┌───────────┐
│ UPLOADED │ ────────────▶ │ PROCESSING │ ──────────▶ │ PROCESSED │ ───────────────▶ │ PUBLISHED │
└──────────┘               └────────────┘             └───────────┘                 └───────────┘
                                 │                          │
                            Job fails                  Admin reject
                                 │                          │
                                 ▼                          ▼
                           ┌──────────┐               ┌──────────┐
                           │  FAILED  │               │ REJECTED │
                           └──────────┘               └──────────┘
```

**Status values**: `uploaded`, `processing`, `processed`, `published`, `rejected`, `failed`

**Reprocessing**: Admin can trigger reprocessing from `failed` → `processing` state.

---

## 4. Translation Strategy Decision

### The Three Options

| Option | Pattern | Pros | Cons |
|---|---|---|---|
| **A. Column per locale** | `title_ar`, `title_en`, `title_es` | Simple queries, no joins | Schema change for every new language |
| **B. JSON column** | `title JSON {"ar":"...", "en":"..."}` | Schema-flexible, no joins | JSON query syntax, no full-text indexing, no referential integrity |
| **C. Translation table** | `page_translations (page_id, locale, ...)` | Fully normalized, index-friendly, referential integrity, language-agnostic | Requires JOIN on every read of translatable content |

### Decision: Option C — Separate Translation Tables

**Rationale**:
1. **New language support without schema migration**: Adding Spanish, then French, then Urdu in future seasons requires only inserting new rows — no `ALTER TABLE`.
2. **Full-text index support**: MySQL cannot index JSON fields. A dedicated `body` column in a translation table can carry a full-text index.
3. **Referential integrity**: `locale` is a FK to `languages.code`, ensuring no orphan translations exist for deactivated languages.
4. **Laravel ecosystem**: The `spatie/laravel-translatable` package and the `astrotomic/laravel-translatable` package both support this pattern natively.
5. **Query clarity**: `JOIN page_translations ON ... WHERE locale = 'ar'` is more readable and optimizable than `JSON_UNQUOTE(JSON_EXTRACT(title, '$.ar'))`.

### Translation Table Naming Convention

```
{parent_table_singular}_translations

pages              → page_translations
announcements      → announcement_translations
faqs               → faq_translations
countries          → country_translations
evaluation_criteria → evaluation_criterion_translations
notification_templates → notification_template_translations
```

### Standard Translation Table Schema

All translation tables follow this schema:

```
{entity}_translations
├── id              UUID v7, PK
├── {entity}_id     FK → {parent_table}.id, ON DELETE CASCADE
├── locale          VARCHAR(10), FK → languages.code
├── {field_1}       translatable field
├── {field_2}       translatable field
└── (no timestamps — translations are versioned with the parent)

UNIQUE KEY uq_{entity}_locale ({entity}_id, locale)
```

### Fallback Strategy

When a translation does not exist for the requested locale:
1. Fall back to `ar` (the platform default locale).
2. Include `"content_locale_fallback": true` in the API `meta` object.
3. Never return empty/null content because a translation is missing.

---

## 5. Video Storage Architecture

### The Storage Problem

The video workflow has four distinct file types at four distinct stages of the pipeline. Each type has different access patterns, different CDN caching requirements, and different lifecycle durations.

### Video File Taxonomy

| Type | Description | Access Pattern | Lifetime | CDN |
|---|---|---|---|---|
| **Original** | Raw upload from contestant | Private — admin + processing job only | Until processed (then archiveable) | No |
| **Processed Variant** | FFmpeg-transcoded MP4 (360p, 720p, 1080p) | Private (contestant's own) or Public (after publish) | Permanent | Yes (after publish) |
| **Thumbnail** | Static image extracted from video | Public (after publish) | Permanent | Yes |
| **HLS Segments** | Adaptive streaming for published gallery | Public (after publish) | Permanent | Yes (aggressive) |

### Storage Path Convention

All paths follow the convention `{category}/{video_id}/{filename}`:

```
videos/
├── originals/
│   └── {video_id}/
│       └── original.{ext}               ← raw upload (MP4/MOV/AVI)
│
├── processed/
│   └── {video_id}/
│       ├── 360p.mp4                     ← FFmpeg output, progressive download
│       ├── 720p.mp4
│       └── 1080p.mp4
│
├── thumbnails/
│   └── {video_id}/
│       ├── thumb.jpg                    ← 480×270
│       └── thumb-hd.jpg                 ← 1280×720
│
└── hls/
    └── {video_id}/
        ├── index.m3u8                   ← master playlist
        ├── 360p/
        │   ├── index.m3u8              ← rendition playlist
        │   └── segment_000.ts
        ├── 720p/
        │   ├── index.m3u8
        │   └── segment_000.ts
        └── 1080p/
            ├── index.m3u8
            └── segment_000.ts
```

### Storage Location by Environment

| File Type | Local Dev | Staging | Production |
|---|---|---|---|
| Originals | Local disk | Cloud storage (private bucket) | Cloud storage (private bucket) |
| Processed variants | Local disk | Cloud storage (CDN-enabled bucket) | Cloud storage (CDN-enabled bucket) |
| Thumbnails | Local disk | Cloud storage (CDN-enabled bucket) | Cloud storage (CDN-enabled bucket) |
| HLS segments | Local disk | Cloud storage (CDN-enabled bucket, long-TTL) | Cloud storage (CDN-enabled bucket, long-TTL) |

### URL Generation Policy

- **Original files**: Never served via public URL. Access requires a signed time-limited URL generated by the backend. Signed URL TTL: 1 hour.
- **Processed files (before publish)**: Same as originals — signed URL only.
- **Processed files (after publish)**: CDN URL — permanent, cached aggressively.
- **Thumbnails**: CDN URL after publish.
- **HLS segments**: CDN URL after publish, with `Cache-Control: public, max-age=31536000` (1 year, content-addressed filenames).

### Video Processing Pipeline

```
1. Contestant uploads original     → stored at videos/originals/{id}/original.ext
2. VideoUploaded event dispatched  → VideoProcessingJob queued
3. FFmpeg processes:
   a. Generate 360p/720p/1080p variants → stored at videos/processed/{id}/
   b. Extract thumbnails             → stored at videos/thumbnails/{id}/
   c. Generate HLS playlist + segments → stored at videos/hls/{id}/
4. video_variants + video_thumbnails records created
5. video.status → processed
6. VideoProcessingCompleted event dispatched
7. Admin reviews → publishes
8. video.status → published
9. CDN cache primed for HLS and thumbnails
```

---

## 6. Cross-Cutting Characteristics Summary

### Entities Requiring Soft Delete

`users`, `contestants`, `contestant_documents`, `applications`, `application_documents`, `judges`, `seasons`, `stages`, `evaluations`, `videos`, `media_assets`, `pages`, `announcements`, `faqs`, `sponsors`, `notifications`

### Entities That Are Append-Only (No Soft Delete, No Update)

`audit_logs`, `application_status_histories`

### Entities With Translation Tables

`countries`, `pages`, `announcements`, `faqs`, `evaluation_criteria`, `notification_templates`, `judge_biographies`

### Entities Requiring Optimistic Locking

`evaluations` — concurrent score submission from the same judge requires optimistic locking via a `version` column.

### Entities With Meilisearch Indexes

`users`, `contestants`, `applications`, `judges`, `videos`, `announcements`, `countries`

### Entities With Redis Cache

`users`, `roles`, `permissions`, `languages`, `settings`, `contestants`, `applications`, `judges`, `seasons`, `stages`, `evaluation_criteria`, `videos`, `streams`, `pages`, `announcements`, `faqs`, `sponsors`

### Modules With No Database Tables

| Module | Why |
|---|---|
| **Search** | Uses Meilisearch indexes exclusively — no MySQL tables |
| **Cache** | Uses Redis exclusively — no MySQL tables |
| **Reports** | Read-only aggregate queries across other modules' tables — no own tables |

---

## 7. Season Isolation — Global vs. Season-Scoped Entities

This section answers the question: **which tables carry `season_id` and which do not?**
This is the Multi-Tenancy decision for the platform. It cannot be changed after migrations are written.

### Classification Principle

> A table carries `season_id` only if its records are **operationally created within and specific to a season** and their business meaning is lost outside of that season context. Global reference data never carries `season_id`.

### Category A: Global Reference Data (no `season_id`)

These entities exist independently of any season. They are created once and reused across seasons.

| Entity | Why Global |
|---|---|
| `users` | Platform accounts persist across seasons |
| `roles`, `permissions` | RBAC definitions are static |
| `languages`, `settings` | Platform-wide configuration |
| `countries` | Static reference data |
| `contestants` | A contestant's profile exists independently; they re-apply each season via `applications` |
| `judges` | A judge is assigned to stages, not tied to a specific season record |
| `evaluation_criteria` | The rubric applies platform-wide; season-specific variation is out of scope for v1 |
| `notification_templates` | Templates are reused across seasons |
| `media_assets` | Files are global; ownership is through the referencing entity |
| `sponsors` | Sponsorship relationships are independent of seasons |
| `pages`, `faqs` | CMS content is platform-wide |
| `audit_logs` | Cross-season audit trail |

### Category B: Season-Scoped Operational Data (carry `season_id`)

These entities are created within the context of a specific season. They have no meaningful existence outside it.

| Entity | `season_id` Source | Notes |
|---|---|---|
| `applications` | Direct `season_id` FK | The primary season anchor. The unique constraint `(contestant_id, season_id)` enforces one application per season |
| `stages` | Direct `season_id` FK | Stages belong to a season |
| `stage_judge_assignments` | Via `stage_id` → `stages.season_id` | No direct `season_id` needed — derivable through join |
| `stage_results` | Via `stage_id` → `stages.season_id` | Same — derivable |
| `evaluations` | Via `application_id` → `applications.season_id` | Derivable. Direct `season_id` column is redundant and prohibited |
| `evaluation_scores` | Via `evaluation_id` → derivable chain | Deep in the chain — no direct column |
| `streams` | Optional direct `season_id` FK | A stream may be associated with a season but also with one-off events |

### Category C: Cross-Season Entities (link to seasons indirectly)

These entities participate in season workflows but are not owned by any single season.

| Entity | Relationship to Season | Notes |
|---|---|---|
| `videos` | Linked via `application_id` → `applications.season_id` | A video belongs to one application which belongs to one season, but a contestant may upload a new video in a new season |
| `notifications` | Via `user_id` + event context | Notifications are delivered cross-season; their `data` JSON snapshot captures the season context at dispatch time |
| `announcements` | None — platform-wide | May *mention* a season in content but has no structural `season_id` |

### The Golden Rule

> **Derived `season_id` is never duplicated as a direct column.** If `season_id` can be reached through a join chain, adding a redundant direct column is prohibited. This prevents denormalization and the inconsistency bugs it causes.

The only entities with a **direct** `season_id` column are: `applications`, `stages`, `streams`.

---

## 8. Delete Policy Matrix

This section replaces the ad-hoc soft-delete flag with a formal, per-entity governed policy.

### Policy Definitions

| Policy | Description | DB Mechanism | Reversible? |
|---|---|---|---|
| **Soft Delete** | Record is flagged with `deleted_at` and excluded from normal queries. Record remains in DB and can be restored. | `deleted_at TIMESTAMP NULL` | Yes |
| **Hard Delete** | Record is physically removed from the database. Used for ephemeral data with no audit requirement. | `DELETE FROM` | No |
| **Archive Only** | Record transitions to an `archived` status but is never deleted. Provides historical visibility without risking data loss. | `status = 'archived'` column | Not applicable |
| **Append Only** | Records are only ever inserted. No updates. No deletes. The table grows monotonically. | No `updated_at`. No `deleted_at`. Application-enforced. | N/A |
| **Never Delete** | Records are immutable reference data. Deletion would break historical records and FK chains. Physically impossible to delete safely. | `ON DELETE RESTRICT` on all FKs referencing this table | N/A |

### Delete Policy Registry

| Entity | Policy | Rationale |
|---|---|---|
| `users` | **Soft Delete** | Accounts may be deactivated but historical records (applications, evaluations, audit logs) must remain intact |
| `roles` | **Never Delete** | Deleting a role orphans all users assigned to it |
| `permissions` | **Never Delete** | Permissions are seeded by modules; deletion would break role assignments |
| `languages` | **Never Delete** | Deleting a language would orphan all translation table rows for that locale |
| `settings` | **Hard Delete** | Settings keys are reference configuration; obsolete keys are removed by migrations |
| `audit_logs` | **Append Only** | Immutable audit trail. No update. No delete. Retention policy governs archival (ADR-007). |
| `countries` | **Never Delete** | Historical records reference country_id; deletion breaks FK integrity across contestants and judges |
| `contestants` | **Soft Delete** | Profile may be suspended; historical applications must remain intact |
| `contestant_documents` | **Soft Delete** | Documents may be replaced; the old record is soft-deleted to preserve the audit chain |
| `applications` | **Soft Delete** | An application may be cancelled; the record must be preserved for audit and reporting |
| `application_documents` | **Soft Delete** | Replaced documents are soft-deleted; audit trail preserved |
| `application_status_histories` | **Append Only** + **Never Delete** | The state transition log is immutable. Each row is a historical fact. |
| `judges` | **Soft Delete** | A judge may be removed from a season panel without deleting their historical evaluations |
| `judge_biographies` | **Hard Delete** | Replaced when a judge's biography is updated; old version has no independent value |
| `seasons` | **Archive Only** | A completed season becomes `archived`. It is never deleted. Historical data depends on it. |
| `stages` | **Soft Delete** | A stage may be cancelled; soft delete preserves the schedule record |
| `stage_judge_assignments` | **Hard Delete** | If a judge is removed from a stage, the pivot row is deleted. The judge record itself is untouched. |
| `stage_results` | **Never Delete** | Published results are official records. Deletion is prohibited. Correction requires a new publication event. |
| `evaluations` | **Soft Delete** | An evaluation session may be voided but must remain for audit |
| `evaluation_criteria` | **Never Delete** | Deleting a criterion orphans all historical `evaluation_scores`. Criteria are deactivated (`is_active = false`), not deleted. |
| `evaluation_criterion_translations` | **Hard Delete** | Translation rows are replaced when updated; hard delete + re-insert is cleaner than update |
| `evaluation_scores` | **Archive Only** | Scores are finalized on evaluation approval. They are never deleted. The parent `evaluation` may be soft-deleted but scores remain. |
| `videos` | **Soft Delete** | A video may be rejected or replaced; the record is preserved for the processing audit trail |
| `video_variants` | **Hard Delete** | If a video is reprocessed, old variants are replaced. Hard delete + re-insert. |
| `video_thumbnails` | **Hard Delete** | Same as video_variants. |
| `media_assets` | **Soft Delete** | Physical file deletion is a separate background job. Soft delete first, then orphan cleanup. |
| `streams` | **Soft Delete** | Historical stream records are preserved for analytics |
| `stream_sources` | **Hard Delete** | Sources are replaced when a stream is reconfigured |
| `pages` | **Soft Delete** | A page may be unpublished and restored |
| `page_translations` | **Hard Delete** | Translations are replaced on update; hard delete + re-insert per locale |
| `announcements` | **Soft Delete** | Announcements may be retracted and restored |
| `announcement_translations` | **Hard Delete** | Same as page_translations |
| `faqs` | **Soft Delete** | FAQs may be temporarily hidden |
| `faq_translations` | **Hard Delete** | Same pattern |
| `sponsors` | **Soft Delete** | A sponsor may be temporarily removed |
| `notifications` | **Soft Delete** | Users may dismiss notifications; soft delete preserves delivery audit |
| `notification_templates` | **Soft Delete** | A template may be deactivated and later restored |
| `notification_template_translations` | **Hard Delete** | Replaced on update |

---

## 9. Referential Integrity Policy

This section governs the `ON DELETE` rule for every FK relationship type.

### Rule Definitions

| Rule | SQL | When to Use |
|---|---|---|
| `CASCADE` | `ON DELETE CASCADE` | Child has no independent existence. Deleting the parent must delete the child. |
| `RESTRICT` | `ON DELETE RESTRICT` | Child must not be deleted if parent records exist. Protects data integrity. |
| `SET NULL` | `ON DELETE SET NULL` | FK is optional. Parent deletion nullifies the reference without deleting the child. |
| `NO ACTION` | Default (equivalent to RESTRICT in MySQL InnoDB) | Equivalent to RESTRICT — used only when the application layer is expected to handle the dependency. |

### FK Policy by Relationship Type

#### RULE 1: Translation tables → `CASCADE`

Translation rows have no existence without their parent entity. If a page is hard-deleted, its translations must be deleted.

```
pages             ←── CASCADE ── page_translations
announcements     ←── CASCADE ── announcement_translations
faqs              ←── CASCADE ── faq_translations
countries         ←── CASCADE ── country_translations
evaluation_criteria ←── CASCADE ── evaluation_criterion_translations
notification_templates ←── CASCADE ── notification_template_translations
```

#### RULE 2: Child aggregates without independent existence → `CASCADE`

These children belong to the parent aggregate and have no business meaning without it.

```
videos            ←── CASCADE ── video_variants
videos            ←── CASCADE ── video_thumbnails
streams           ←── CASCADE ── stream_sources
evaluations       ←── CASCADE ── evaluation_scores
```

#### RULE 3: History and audit tables → `RESTRICT`

History tables must never be silently deleted when a parent is soft-deleted. The application layer manages soft deletion; FK prevents hard deletion.

```
applications      ←── RESTRICT ── application_status_histories
(all tables)      ←── RESTRICT ── audit_logs (via auditable polymorphic)
```

#### RULE 4: Cross-module FK references → `RESTRICT`

The owning module must not silently cascade deletions into another module's data.

```
users             ←── RESTRICT ── contestants
users             ←── RESTRICT ── judges
contestants       ←── RESTRICT ── applications
seasons           ←── RESTRICT ── applications
seasons           ←── RESTRICT ── stages
applications      ←── RESTRICT ── evaluations
stages            ←── RESTRICT ── evaluations
judges            ←── RESTRICT ── evaluations
```

#### RULE 5: Optional FK references → `SET NULL`

When the FK is nullable and the referencing entity can exist without the referenced entity.

```
applications.video_id        → SET NULL (video may be re-uploaded or removed)
streams.season_id            → SET NULL (a stream may not be tied to a season)
contestants.photo_media_id   → SET NULL (photo is optional)
judges.photo_media_id        → SET NULL (photo is optional)
announcements.image_media_id → SET NULL (image is optional)
sponsors.logo_media_id       → SET NULL (logo is optional)
```

---

## 10. Transaction Boundaries, Concurrency Policy & Event Persistence

---

### 10.1 Transaction Boundaries

Each row in the following table defines the **exact scope of a single database transaction** and the **events dispatched after commit**.

> **Rule**: Events are dispatched AFTER the transaction commits. Never inside a transaction. This prevents the scenario where an event is dispatched but the DB commit fails, causing listeners to act on data that doesn't exist.

| Operation | Inside Transaction (atomic) | After Commit (events dispatched) |
|---|---|---|
| **Contestant submits application** | INSERT `applications` + INSERT `application_documents` (N rows) + INSERT `application_status_histories` (initial `received` row) | `ApplicationReceived` |
| **Admin updates application status** | UPDATE `applications.status` + INSERT `application_status_histories` | `ApplicationStatusChanged` |
| **Admin approves application** | UPDATE `applications.status = accepted` + INSERT `application_status_histories` | `ApplicationApproved` |
| **Judge saves evaluation scores** | UPDATE `evaluations` + UPSERT `evaluation_scores` (N rows, one per criterion) | `EvaluationScoresUpdated` |
| **Judge submits evaluation** | UPDATE `evaluations.status = completed` + final UPSERT `evaluation_scores` | `EvaluationSubmitted` |
| **Admin publishes stage results** | INSERT `stage_results` + UPDATE `stages.status = results_published` | `StageResultsPublished` |
| **Contestant uploads video** | INSERT `media_assets` + INSERT `videos` | `VideoUploaded` |
| **Video processing completes** | INSERT `video_variants` (N rows) + INSERT `video_thumbnails` (N rows) + UPDATE `videos.status = processed` | `VideoProcessingCompleted` |
| **Admin publishes video** | UPDATE `videos.status = published` + UPDATE `media_assets` visibility | `VideoPublished` |
| **Admin publishes page/announcement** | UPDATE `pages/announcements.status = published` | `ContentPublished` |
| **Notification dispatched** | INSERT `notifications` (in-app) | `NotificationDispatched` → email/SMS queue jobs |

---

### 10.2 Concurrency Policy

Every entity exposed to concurrent writes has an explicit concurrency strategy. The three strategies available are:

| Strategy | Mechanism | When to Use |
|---|---|---|
| **Optimistic Locking** | `version INTEGER` column; application checks version before update and fails if changed | High contention is rare; conflicts are detected at commit time; operation is retried by the client |
| **Pessimistic Locking** | `SELECT ... FOR UPDATE` (row-level lock in MySQL InnoDB) | Contention is frequent or the window between read and write is short; locking is preferable to conflict retry |
| **No Lock** | Standard `UPDATE WHERE id = ?` | Single writer by design, or business rules prevent concurrent modification |

#### Concurrency Policy Registry

| Entity | Concurrent Operation | Strategy | Rationale |
|---|---|---|---|
| `evaluations` | Multiple judges evaluating simultaneously; judge re-opens and saves while admin is reading | **Optimistic Locking** (`version` column) | Contention is rare (each judge has their own evaluation row by the unique constraint); optimistic is sufficient |
| `evaluation_scores` | Score rows written by judge session | **Pessimistic Locking** (`SELECT FOR UPDATE` on parent `evaluations` row) | Score writes must be serialized within one judge's session to prevent partial save |
| `applications` (status) | Admin transitions application state | **Pessimistic Locking** (`SELECT FOR UPDATE`) | Two admins must not simultaneously approve/reject the same application |
| `seasons` (`is_current` flag) | Admin activates a season | **Pessimistic Locking** (table-level advisory lock or transaction with `SELECT FOR UPDATE` on all active seasons) | The uniqueness of `is_current = true` must be enforced transactionally |
| `streams` (status) | Admin starts/stops stream | **Pessimistic Locking** (`SELECT FOR UPDATE`) | Start/stop must be serialized; two admins must not both issue start commands |
| `stages` (order) | Admin reorders stages | **Pessimistic Locking** (lock all stage rows in season) | Order values must be consistent; concurrent reorders produce conflicting `order` sequences |
| `contestants` | Profile update | **No Lock** | Only the contestant themselves updates their own profile; no concurrent writer |
| `pages`, `announcements`, `faqs` | CMS edit | **No Lock** | Admin dashboard has no collaborative editing; last write wins is acceptable for CMS content |
| `settings` | Admin changes setting | **No Lock** | Infrequent single-writer operation |
| `notifications` | Notification dispatch | **No Lock** | Each notification row is owned by one user; no concurrent write to the same row |

---

### 10.3 Event Persistence — Outbox Decision

#### The Problem

Without an Outbox, the following failure scenario is possible:

```
1. DB transaction commits (application status = approved)
2. ApplicationApproved event is dispatched to queue
3. Queue write FAILS (Redis/Queue unavailable)
4. The DB says approved. No email was ever sent. No notification was ever created.
```

#### Decision: Transactional Outbox Table

An `outbox_events` table is included in the schema. All domain events are written to this table **within the same DB transaction** as the business operation. A background worker polls the table and dispatches events to the queue, then marks them as dispatched.

```
outbox_events
├── id              UUID v7, PK
├── event_type      string (e.g. 'ApplicationApproved')
├── aggregate_type  string (e.g. 'Application')
├── aggregate_id    UUID
├── payload         JSON (event data snapshot)
├── status          enum: pending, dispatched, failed
├── dispatched_at   TIMESTAMP NULL
├── attempts        TINYINT (retry counter)
└── created_at      TIMESTAMP
```

**Owner Module**: Core (the Outbox is a platform-level infrastructure concern, not owned by any domain module).

#### Event Replayability

- Events in `outbox_events` are retained for **30 days** after successful dispatch.
- During that window, any event can be replayed by resetting its status to `pending`.
- After 30 days, rows are archived (status = `archived`) and eligible for purge by the ADR-007 backup process.
- The `payload` JSON snapshot captures the full event data at the moment of creation. It does not re-query the DB on replay.

#### Relationship to ADR-008

- This decision establishes the **persistence layer** for events.
- ADR-008 governs the **naming, versioning, consumer registration, and DLQ policy** for events.
- ADR-005 decides: *how events are stored*. ADR-008 decides: *how events are designed and consumed*.

---

## 11. Unified Media Ownership Convention

### The Rule

> **Every binary file in the platform begins life as a `media_assets` record.**
> No domain table stores a storage path, file size, MIME type, or disk name directly.
> All file-bearing entities reference `media_assets.id` via a named FK column.

### Why This Matters

| Problem Without Convention | Result |
|---|---|
| `videos.original_path` stores a path string | Path format changes require an `ALTER TABLE`. Moving storage providers breaks all path strings. |
| `video_variants.file_size_bytes` duplicates storage metadata | When a file is moved or reprocessed, two tables must be updated atomically. |
| No central file registry | It is impossible to find all files uploaded by a user, or to run orphan cleanup without scanning every table. |

### The MediaAsset FK Pattern

Every file-bearing entity uses a named FK column pointing to `media_assets`:

| Entity | FK Column | What it Points To |
|---|---|---|
| `videos` | `media_asset_id` | The original uploaded file |
| `video_variants` | `media_asset_id` | The processed variant file (one per quality/format) |
| `video_thumbnails` | `media_asset_id` | The generated thumbnail image |
| `contestants` | `photo_media_id` | The profile photo |
| `judges` | `photo_media_id` | The profile photo |
| `contestant_documents` | `media_asset_id` | Passport/ID document |
| `application_documents` | `media_asset_id` | Supporting document |
| `announcements` | `image_media_id` | The announcement banner image |
| `sponsors` | `logo_media_id` | The sponsor logo |

### What `media_assets` Stores (and domain tables do not)

```
media_assets
├── disk            ← 'local' | 's3' | 'r2' (where it lives)
├── path            ← the storage path (relative to disk root)
├── original_filename ← what the user called the file
├── mime_type       ← 'video/mp4' | 'image/jpeg' | 'application/pdf'
└── file_size_bytes ← used for storage quotas and API responses
```

### What domain tables store (that media_assets does not)

```
videos
├── status          ← domain state machine
└── duration_seconds ← extracted by FFmpeg, domain-meaningful

video_variants
├── quality         ← '360p' | '720p' | '1080p'
└── format          ← 'mp4' | 'hls_manifest'

video_thumbnails
├── type            ← 'default' | 'hd'
├── width           ← pixel dimensions
└── height          ← pixel dimensions
```

### Polymorphic vs. Direct FK

The `media_assets` table supports **both** patterns:

| Pattern | When to Use | Example |
|---|---|---|
| **Direct FK on domain table** (`media_asset_id`) | When the domain entity has a 1:1 or known-cardinality relationship with a file | `videos.media_asset_id`, `contestants.photo_media_id` |
| **Polymorphic** (`morphable_type` + `morphable_id`) | When the file registry tracks all files owned by a model without a fixed column on the owning table | Future: tracking all media uploaded within an application form |

For v1, all relationships are **direct FK**. The polymorphic columns exist for future extensibility and for the orphan cleanup job (which scans all media_assets and verifies their owner still exists).
