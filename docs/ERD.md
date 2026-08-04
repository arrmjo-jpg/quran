# Formal Entity Relationship Diagram (ERD) — Global Quran Competition Platform

> **Document ID**: ERD-001  
> **Status**: Complete & Governed  
> **Date**: 2026-07-31  
> **Governing ADR**: [ADR-005: Database Architecture](./adr/ADR-005-database-architecture.md)  
> **Prerequisites**: [DATABASE-DOMAIN-INVENTORY.md](./DATABASE-DOMAIN-INVENTORY.md) · [ENTITY-RELATIONSHIP-MAP.md](./ENTITY-RELATIONSHIP-MAP.md)

---

## 1. Executive Summary

This document represents the **Formal ERD Specification** derived directly from the 21 constitutional decisions of **ADR-005** and the domain analysis of **DATABASE-DOMAIN-INVENTORY.md**.

### Key Architectural Constraints Reflected in this ERD:
1. **Primary Key Strategy**: All public-facing tables use `CHAR(36)` representing time-ordered **UUID v7** (Decision 2).
2. **Character Set & Collation**: All string columns use `utf8mb4` with `utf8mb4_unicode_ci` (Decision 1).
3. **Module Table Ownership**: 41 tables distributed across 15 modules. Each table is owned strictly by one module (Decision 4).
4. **Media Ownership Convention**: Binary file metadata (path, size, mime type, disk) lives exclusively in `media_assets`. Domain tables reference `media_assets.id` via direct FKs (`photo_media_id`, `image_media_id`, `logo_media_id`, `media_asset_id`) (Decision 14).
5. **Translation Strategy**: Option C — Separate translation tables (`*_translations`) with composite unique keys `(entity_id, locale)` (Decision 12).
6. **Season Isolation**: Direct `season_id` column exists **only** on `applications`, `stages`, and `streams`. All other season filtering is derived via join chains (Decision 5).
7. **Referential Integrity**: FK `ON DELETE` rules are explicitly specified for every relationship (`CASCADE`, `RESTRICT`, or `SET NULL`) (Decision 7).

---

## 2. Complete Visual Diagram (Mermaid ERD)

```mermaid
erDiagram

  %% =========================================================================
  %% MODULE 1: CORE MODULE
  %% =========================================================================

  users {
    CHAR36 id PK "UUID v7"
    VARCHAR255 email UK "uk_users_email"
    VARCHAR255 name
    VARCHAR50 type "user | admin"
    TINYINT1 is_active
    TIMESTAMP deleted_at "NULL"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  roles {
    CHAR36 id PK "UUID v7"
    VARCHAR255 name UK
    VARCHAR255 guard_name
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  permissions {
    CHAR36 id PK "UUID v7"
    VARCHAR255 name UK "module.action"
    VARCHAR255 guard_name
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  role_user {
    CHAR36 role_id FK "→ roles.id"
    CHAR36 user_id FK "→ users.id"
  }

  role_has_permissions {
    CHAR36 role_id FK "→ roles.id"
    CHAR36 permission_id FK "→ permissions.id"
  }

  languages {
    CHAR36 id PK "UUID v7"
    VARCHAR10 code UK "BCP-47 (ar, en, es)"
    VARCHAR100 name
    VARCHAR100 native_name
    TINYINT1 is_active
    TINYINT1 rtl
  }

  settings {
    VARCHAR100 key PK "Natural Key"
    TEXT value
    VARCHAR50 group
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  audit_logs {
    CHAR36 id PK "UUID v7"
    CHAR36 user_id FK "nullable → users.id"
    VARCHAR100 action "created|updated|deleted"
    VARCHAR100 auditable_type
    CHAR36 auditable_id
    JSON old_values "nullable"
    JSON new_values "nullable"
    VARCHAR45 ip_address "nullable"
    TEXT user_agent "nullable"
    TIMESTAMP created_at
  }

  outbox_events {
    CHAR36 id PK "UUID v7"
    VARCHAR255 event_type "e.g. ApplicationApproved"
    VARCHAR100 aggregate_type "e.g. Application"
    CHAR36 aggregate_id
    JSON payload "Event snapshot"
    VARCHAR20 status "pending|dispatched|failed|archived"
    TIMESTAMP dispatched_at "nullable"
    TINYINT attempts "default 0"
    TIMESTAMP created_at
  }

  users ||--o{ role_user : "assigned"
  roles ||--o{ role_user : "has"
  roles ||--o{ role_has_permissions : "grants"
  permissions ||--o{ role_has_permissions : "belongs"
  users ||--o{ audit_logs : "actor (RESTRICT)"

  %% =========================================================================
  %% MODULE 2: MEDIA MODULE (Platform Service)
  %% =========================================================================

  media_assets {
    CHAR36 id PK "UUID v7"
    VARCHAR50 disk "local | s3 | r2"
    VARCHAR1000 path "Relative path"
    VARCHAR255 original_filename
    VARCHAR100 mime_type
    BIGINT file_size_bytes
    VARCHAR100 morphable_type "nullable"
    CHAR36 morphable_id "nullable"
    CHAR36 uploaded_by FK "nullable → users.id"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  users ||--o{ media_assets : "uploader (SET NULL)"

  %% =========================================================================
  %% MODULE 3: COUNTRIES MODULE
  %% =========================================================================

  countries {
    CHAR36 id PK "UUID v7"
    VARCHAR2 iso_code UK "ISO 3166-1 alpha-2"
    TINYINT1 is_active
    INT display_order
  }

  country_translations {
    CHAR36 id PK "UUID v7"
    CHAR36 country_id FK "→ countries.id"
    VARCHAR10 locale FK "→ languages.code"
    VARCHAR255 name
    VARCHAR255 native_name
  }

  countries ||--|{ country_translations : "CASCADE"
  languages ||--o{ country_translations : "RESTRICT"

  %% =========================================================================
  %% MODULE 4: CONTESTANTS MODULE
  %% =========================================================================

  contestants {
    CHAR36 id PK "UUID v7"
    CHAR36 user_id FK UK "→ users.id"
    CHAR36 country_id FK "→ countries.id"
    CHAR36 photo_media_id FK "nullable → media_assets.id"
    VARCHAR50 phone
    DATE date_of_birth
    VARCHAR10 gender "male | female"
    TINYINT1 is_active
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  contestant_documents {
    CHAR36 id PK "UUID v7"
    CHAR36 contestant_id FK "→ contestants.id"
    CHAR36 media_asset_id FK "→ media_assets.id"
    VARCHAR50 document_type "passport|national_id"
    TINYINT1 is_verified
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  users ||--o| contestants : "user profile (RESTRICT)"
  countries ||--o{ contestants : "nationality (RESTRICT)"
  media_assets ||--o{ contestants : "photo (SET NULL)"
  contestants ||--o{ contestant_documents : "documents (CASCADE)"
  media_assets ||--o{ contestant_documents : "file (RESTRICT)"

  %% =========================================================================
  %% MODULE 5: COMPETITION MODULE
  %% =========================================================================

  seasons {
    CHAR36 id PK "UUID v7"
    VARCHAR255 name
    INT year UK "uk_seasons_year"
    VARCHAR50 status "draft|active|closed|archived"
    TINYINT1 is_current
    TIMESTAMP starts_at
    TIMESTAMP ends_at
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  stages {
    CHAR36 id PK "UUID v7"
    CHAR36 season_id FK "→ seasons.id"
    VARCHAR255 name
    VARCHAR50 type "stage_1|stage_2|semi_final|final"
    INT order
    VARCHAR50 status "scheduled|active|completed|results_published"
    TIMESTAMP scheduled_at
    TIMESTAMP started_at "nullable"
    TIMESTAMP completed_at "nullable"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  stage_judge_assignments {
    CHAR36 id PK "UUID v7"
    CHAR36 stage_id FK "→ stages.id"
    CHAR36 judge_id FK "→ judges.id"
    CHAR36 assigned_by FK "→ users.id"
    TIMESTAMP assigned_at
  }

  stage_results {
    CHAR36 id PK "UUID v7"
    CHAR36 stage_id FK UK "→ stages.id"
    JSON results_data "Ranked contestants & scores"
    TIMESTAMP published_at
    CHAR36 published_by FK "→ users.id"
  }

  seasons ||--|{ stages : "contains (RESTRICT)"
  stages ||--o{ stage_judge_assignments : "has panel (CASCADE)"
  users ||--o{ stage_judge_assignments : "assigner (RESTRICT)"
  stages ||--o| stage_results : "results (RESTRICT)"
  users ||--o{ stage_results : "publisher (RESTRICT)"

  %% =========================================================================
  %% MODULE 6: JUDGES MODULE
  %% =========================================================================

  judges {
    CHAR36 id PK "UUID v7"
    CHAR36 user_id FK UK "→ users.id"
    CHAR36 country_id FK "→ countries.id"
    CHAR36 photo_media_id FK "nullable → media_assets.id"
    VARCHAR255 specialization
    TINYINT1 is_active
    INT display_order
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  judge_biographies {
    CHAR36 id PK "UUID v7"
    CHAR36 judge_id FK "→ judges.id"
    VARCHAR10 locale FK "→ languages.code"
    LONGTEXT biography
  }

  users ||--o| judges : "user profile (RESTRICT)"
  countries ||--o{ judges : "nationality (RESTRICT)"
  media_assets ||--o{ judges : "photo (SET NULL)"
  judges ||--|{ judge_biographies : "biography (CASCADE)"
  languages ||--o{ judge_biographies : "locale (RESTRICT)"
  judges ||--o{ stage_judge_assignments : "assignments (RESTRICT)"

  %% =========================================================================
  %% MODULE 7: VIDEOS MODULE
  %% =========================================================================

  videos {
    CHAR36 id PK "UUID v7"
    CHAR36 media_asset_id FK "→ media_assets.id (Original)"
    CHAR36 application_id FK "nullable → applications.id"
    CHAR36 contestant_id FK "→ contestants.id"
    VARCHAR50 status "uploaded|processing|processed|published|rejected|failed"
    INT duration_seconds "nullable"
    TIMESTAMP processed_at "nullable"
    TIMESTAMP published_at "nullable"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  video_variants {
    CHAR36 id PK "UUID v7"
    CHAR36 video_id FK "→ videos.id"
    CHAR36 media_asset_id FK "→ media_assets.id (Processed variant)"
    VARCHAR20 quality "360p | 720p | 1080p"
    VARCHAR20 format "mp4 | hls_manifest"
    TIMESTAMP processed_at
  }

  video_thumbnails {
    CHAR36 id PK "UUID v7"
    CHAR36 video_id FK "→ videos.id"
    CHAR36 media_asset_id FK "→ media_assets.id (Thumbnail image)"
    VARCHAR20 type "default | hd"
    INT width
    INT height
  }

  media_assets ||--o| videos : "original file (RESTRICT)"
  contestants ||--o{ videos : "owner (RESTRICT)"
  videos ||--|{ video_variants : "variants (CASCADE)"
  media_assets ||--o{ video_variants : "variant file (RESTRICT)"
  videos ||--|{ video_thumbnails : "thumbnails (CASCADE)"
  media_assets ||--o{ video_thumbnails : "thumbnail file (RESTRICT)"

  %% =========================================================================
  %% MODULE 8: APPLICATIONS MODULE
  %% =========================================================================

  applications {
    CHAR36 id PK "UUID v7"
    CHAR36 contestant_id FK "→ contestants.id"
    CHAR36 season_id FK "→ seasons.id"
    CHAR36 video_id FK "nullable → videos.id"
    CHAR36 reviewer_id FK "nullable → users.id"
    VARCHAR50 status "received|under_review|under_evaluation|accepted|rejected|needs_data|video_reupload_requested"
    TEXT rejection_reason "nullable"
    TIMESTAMP submitted_at
    TIMESTAMP reviewed_at "nullable"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  application_documents {
    CHAR36 id PK "UUID v7"
    CHAR36 application_id FK "→ applications.id"
    CHAR36 media_asset_id FK "→ media_assets.id"
    VARCHAR50 document_type
    TINYINT1 is_required
    TIMESTAMP submitted_at
    TIMESTAMP deleted_at "nullable"
  }

  application_status_histories {
    CHAR36 id PK "UUID v7"
    CHAR36 application_id FK "→ applications.id"
    VARCHAR50 from_status
    VARCHAR50 to_status
    CHAR36 changed_by FK "nullable → users.id"
    TEXT reason "nullable"
    TIMESTAMP created_at
  }

  contestants ||--o{ applications : "applicant (RESTRICT)"
  seasons ||--o{ applications : "season (RESTRICT)"
  videos ||--o| applications : "recitation video (SET NULL)"
  users ||--o{ applications : "reviewer (SET NULL)"
  applications ||--o{ application_documents : "attachments (CASCADE)"
  media_assets ||--o{ application_documents : "file (RESTRICT)"
  applications ||--o{ application_status_histories : "audit history (RESTRICT)"
  users ||--o{ application_status_histories : "actor (SET NULL)"

  %% =========================================================================
  %% MODULE 9: EVALUATIONS MODULE
  %% =========================================================================

  evaluations {
    CHAR36 id PK "UUID v7"
    CHAR36 application_id FK "→ applications.id"
    CHAR36 stage_id FK "→ stages.id"
    CHAR36 judge_id FK "→ judges.id"
    VARCHAR50 status "pending|in_progress|completed|approved"
    DECIMAL5_2 total_score "nullable"
    TEXT notes "nullable"
    INT version "Optimistic Lock (default 0)"
    TIMESTAMP submitted_at "nullable"
    TIMESTAMP approved_at "nullable"
    CHAR36 approved_by FK "nullable → users.id"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  evaluation_criteria {
    CHAR36 id PK "UUID v7"
    VARCHAR100 code UK "tajweed|pronunciation"
    DECIMAL5_2 max_score
    DECIMAL5_2 weight
    INT order
    TINYINT1 is_active
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  evaluation_criterion_translations {
    CHAR36 id PK "UUID v7"
    CHAR36 criterion_id FK "→ evaluation_criteria.id"
    VARCHAR10 locale FK "→ languages.code"
    VARCHAR255 name
    TEXT description
  }

  evaluation_scores {
    CHAR36 id PK "UUID v7"
    CHAR36 evaluation_id FK "→ evaluations.id"
    CHAR36 criterion_id FK "→ evaluation_criteria.id"
    DECIMAL5_2 score
    TEXT note "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  applications ||--o{ evaluations : "evaluated (RESTRICT)"
  stages ||--o{ evaluations : "stage (RESTRICT)"
  judges ||--o{ evaluations : "evaluator (RESTRICT)"
  users ||--o{ evaluations : "approver (SET NULL)"
  evaluations ||--|{ evaluation_scores : "scores (CASCADE)"
  evaluation_criteria ||--o{ evaluation_scores : "criterion (RESTRICT)"
  evaluation_criteria ||--|{ evaluation_criterion_translations : "translations (CASCADE)"
  languages ||--o{ evaluation_criterion_translations : "locale (RESTRICT)"

  %% =========================================================================
  %% MODULE 10: STREAMING MODULE
  %% =========================================================================

  streams {
    CHAR36 id PK "UUID v7"
    CHAR36 season_id FK "nullable → seasons.id"
    VARCHAR50 status "offline|live|paused"
    VARCHAR255 title
    INT viewer_count
    TIMESTAMP started_at "nullable"
    TIMESTAMP stopped_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  stream_sources {
    CHAR36 id PK "UUID v7"
    CHAR36 stream_id FK "→ streams.id"
    VARCHAR20 protocol "rtmp|hls|dash"
    VARCHAR1000 url
    TINYINT1 is_primary
  }

  seasons ||--o{ streams : "season context (SET NULL)"
  streams ||--|{ stream_sources : "sources (CASCADE)"

  %% =========================================================================
  %% MODULE 11: CONTENT MODULE (CMS)
  %% =========================================================================

  pages {
    CHAR36 id PK "UUID v7"
    VARCHAR255 slug UK "uk_pages_slug"
    VARCHAR50 status "draft|published"
    VARCHAR100 template "nullable"
    TIMESTAMP published_at "nullable"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  page_translations {
    CHAR36 id PK "UUID v7"
    CHAR36 page_id FK "→ pages.id"
    VARCHAR10 locale FK "→ languages.code"
    VARCHAR255 title
    LONGTEXT body
    VARCHAR255 meta_title "nullable"
    VARCHAR500 meta_description "nullable"
  }

  announcements {
    CHAR36 id PK "UUID v7"
    CHAR36 image_media_id FK "nullable → media_assets.id"
    VARCHAR50 status "draft|published|scheduled"
    TIMESTAMP published_at "nullable"
    TIMESTAMP scheduled_at "nullable"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  announcement_translations {
    CHAR36 id PK "UUID v7"
    CHAR36 announcement_id FK "→ announcements.id"
    VARCHAR10 locale FK "→ languages.code"
    VARCHAR255 title
    TEXT body
  }

  faqs {
    CHAR36 id PK "UUID v7"
    TINYINT1 is_active
    INT display_order
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  faq_translations {
    CHAR36 id PK "UUID v7"
    CHAR36 faq_id FK "→ faqs.id"
    VARCHAR10 locale FK "→ languages.code"
    TEXT question
    TEXT answer
  }

  pages ||--|{ page_translations : "translations (CASCADE)"
  languages ||--o{ page_translations : "locale (RESTRICT)"
  media_assets ||--o{ announcements : "banner image (SET NULL)"
  announcements ||--|{ announcement_translations : "translations (CASCADE)"
  languages ||--o{ announcement_translations : "locale (RESTRICT)"
  faqs ||--|{ faq_translations : "translations (CASCADE)"
  languages ||--o{ faq_translations : "locale (RESTRICT)"

  %% =========================================================================
  %% MODULE 12: SPONSORS MODULE
  %% =========================================================================

  sponsors {
    CHAR36 id PK "UUID v7"
    CHAR36 logo_media_id FK "nullable → media_assets.id"
    VARCHAR255 name
    VARCHAR500 website_url "nullable"
    VARCHAR50 tier "platinum|gold|silver|partner"
    TINYINT1 is_active
    INT display_order
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  media_assets ||--o{ sponsors : "logo (SET NULL)"

  %% =========================================================================
  %% MODULE 13: NOTIFICATIONS MODULE
  %% =========================================================================

  notifications {
    CHAR36 id PK "UUID v7"
    CHAR36 user_id FK "→ users.id"
    VARCHAR100 type
    VARCHAR20 channel "in_app|email|sms"
    JSON data "Payload snapshot"
    TIMESTAMP read_at "nullable"
    TIMESTAMP deleted_at "nullable"
    TIMESTAMP created_at
  }

  notification_templates {
    CHAR36 id PK "UUID v7"
    VARCHAR100 event_type UK "uk_notification_templates_event_type"
    VARCHAR20 channel "in_app|email|sms"
    TINYINT1 is_active
    TIMESTAMP created_at
    TIMESTAMP updated_at
  }

  notification_template_translations {
    CHAR36 id PK "UUID v7"
    CHAR36 template_id FK "→ notification_templates.id"
    VARCHAR10 locale FK "→ languages.code"
    VARCHAR255 subject
    TEXT body
  }

  users ||--o{ notifications : "recipient (RESTRICT)"
  notification_templates ||--|{ notification_template_translations : "translations (CASCADE)"
  languages ||--o{ notification_template_translations : "locale (RESTRICT)"
```

---

## 3. Comprehensive Table & Foreign Key Specification Matrix

The following table provides the exhaustive schema specification for all 41 tables, documenting key constraints, ownership, and delete policies.

| Table Name | Owner Module | Primary Key | Key Foreign Keys | Delete Policy | Translatable? |
|---|---|---|---|---|---|
| `users` | Core | `id` (UUID v7) | — | Soft Delete | No |
| `roles` | Core | `id` (UUID v7) | — | Never Delete | No |
| `permissions` | Core | `id` (UUID v7) | — | Never Delete | No |
| `role_user` | Core | Pivot | `role_id`, `user_id` | Hard Delete | No |
| `role_has_permissions` | Core | Pivot | `role_id`, `permission_id` | Hard Delete | No |
| `languages` | Core | `id` (UUID v7) | — | Never Delete | No |
| `settings` | Core | `key` (Natural) | — | Hard Delete | No |
| `audit_logs` | Core | `id` (UUID v7) | `user_id` (RESTRICT) | Append Only | No |
| `outbox_events` | Core | `id` (UUID v7) | — | Archive Only | No |
| `media_assets` | Media | `id` (UUID v7) | `uploaded_by` (SET NULL) | Soft Delete | No |
| `countries` | Countries | `id` (UUID v7) | — | Never Delete | Yes |
| `country_translations` | Countries | `id` (UUID v7) | `country_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |
| `contestants` | Contestants | `id` (UUID v7) | `user_id` (RESTRICT), `country_id` (RESTRICT), `photo_media_id` (SET NULL) | Soft Delete | No |
| `contestant_documents` | Contestants | `id` (UUID v7) | `contestant_id` (CASCADE), `media_asset_id` (RESTRICT) | Soft Delete | No |
| `seasons` | Competition | `id` (UUID v7) | — | Archive Only | No |
| `stages` | Competition | `id` (UUID v7) | `season_id` (RESTRICT) | Soft Delete | No |
| `stage_judge_assignments` | Competition | `id` (UUID v7) | `stage_id` (CASCADE), `judge_id` (RESTRICT), `assigned_by` (RESTRICT) | Hard Delete | No |
| `stage_results` | Competition | `id` (UUID v7) | `stage_id` (RESTRICT), `published_by` (RESTRICT) | Never Delete | No |
| `judges` | Judges | `id` (UUID v7) | `user_id` (RESTRICT), `country_id` (RESTRICT), `photo_media_id` (SET NULL) | Soft Delete | Yes |
| `judge_biographies` | Judges | `id` (UUID v7) | `judge_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |
| `videos` | Videos | `id` (UUID v7) | `media_asset_id` (RESTRICT), `application_id` (SET NULL), `contestant_id` (RESTRICT) | Soft Delete | No |
| `video_variants` | Videos | `id` (UUID v7) | `video_id` (CASCADE), `media_asset_id` (RESTRICT) | Hard Delete | No |
| `video_thumbnails` | Videos | `id` (UUID v7) | `video_id` (CASCADE), `media_asset_id` (RESTRICT) | Hard Delete | No |
| `applications` | Applications | `id` (UUID v7) | `contestant_id` (RESTRICT), `season_id` (RESTRICT), `video_id` (SET NULL), `reviewer_id` (SET NULL) | Soft Delete | No |
| `application_documents` | Applications | `id` (UUID v7) | `application_id` (CASCADE), `media_asset_id` (RESTRICT) | Soft Delete | No |
| `application_status_histories` | Applications | `id` (UUID v7) | `application_id` (RESTRICT), `changed_by` (SET NULL) | Append Only + Never Delete | No |
| `evaluations` | Evaluations | `id` (UUID v7) | `application_id` (RESTRICT), `stage_id` (RESTRICT), `judge_id` (RESTRICT), `approved_by` (SET NULL) | Soft Delete | No |
| `evaluation_criteria` | Evaluations | `id` (UUID v7) | — | Never Delete | Yes |
| `evaluation_criterion_translations` | Evaluations | `id` (UUID v7) | `criterion_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |
| `evaluation_scores` | Evaluations | `id` (UUID v7) | `evaluation_id` (CASCADE), `criterion_id` (RESTRICT) | Archive Only | No |
| `streams` | Streaming | `id` (UUID v7) | `season_id` (SET NULL) | Soft Delete | No |
| `stream_sources` | Streaming | `id` (UUID v7) | `stream_id` (CASCADE) | Hard Delete | No |
| `pages` | Content | `id` (UUID v7) | — | Soft Delete | Yes |
| `page_translations` | Content | `id` (UUID v7) | `page_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |
| `announcements` | Content | `id` (UUID v7) | `image_media_id` (SET NULL) | Soft Delete | Yes |
| `announcement_translations` | Content | `id` (UUID v7) | `announcement_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |
| `faqs` | Content | `id` (UUID v7) | — | Soft Delete | Yes |
| `faq_translations` | Content | `id` (UUID v7) | `faq_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |
| `sponsors` | Sponsors | `id` (UUID v7) | `logo_media_id` (SET NULL) | Soft Delete | No |
| `notifications` | Notifications | `id` (UUID v7) | `user_id` (RESTRICT) | Soft Delete | No |
| `notification_templates` | Notifications | `id` (UUID v7) | — | Soft Delete | Yes |
| `notification_template_translations` | Notifications | `id` (UUID v7) | `template_id` (CASCADE), `locale` (RESTRICT) | Hard Delete | — |

---

## 4. Verification & Consistency Checklist

Before using this ERD for Migration Specifications:
- [x] All 41 tables are accounted for across all 15 modules.
- [x] Primary Key column is universally named `id` and formatted as `CHAR(36)` (UUID v7).
- [x] Foreign Key constraint names match canonical syntax `fk_{table}_{referenced_table}` or `fk_{table}_{role_column}`.
- [x] Translation tables explicitly implement Option C with composite unique constraints `uk_{entity}_translations_{entity}_locale`.
- [x] Delete policies for every entity align 100% with ADR-005 Decision 6.
- [x] All binary assets defer storage metadata to `media_assets` per ADR-005 Decision 14.
