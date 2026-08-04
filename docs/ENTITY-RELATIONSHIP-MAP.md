# Entity Relationship Map — Global Quran Competition Platform

> **Purpose**: Prerequisite Step 2 of the 5-step database design pipeline.
> This document defines all entity relationships — their cardinality, FK ownership,
> and cross-module boundaries — before any schema or migration is written.
>
> **Input**: DATABASE-DOMAIN-INVENTORY.md
> **Output feeds into**: ADR-005 → ERD (formal) → Migrations
> **Status**: Complete
> **Date**: 2026-07-31

---

## 1. Full Entity Relationship Diagram (Mermaid)

```mermaid
erDiagram

  %% ─────────────────────────────────────────
  %% CORE MODULE
  %% ─────────────────────────────────────────

  users {
    uuid id PK
    string email UK
    string name
    enum type "user|admin"
    boolean is_active
    timestamp deleted_at
    timestamps
  }

  roles {
    uuid id PK
    string name UK
    string guard_name
    timestamps
  }

  permissions {
    uuid id PK
    string name UK "module.action format"
    string guard_name
    timestamps
  }

  role_user {
    uuid role_id FK
    uuid user_id FK
  }

  role_has_permissions {
    uuid role_id FK
    uuid permission_id FK
  }

  languages {
    uuid id PK
    string code UK "BCP-47"
    string name
    string native_name
    boolean is_active
    boolean rtl
  }

  settings {
    string key PK
    text value
    string group
    timestamps
  }

  audit_logs {
    uuid id PK
    uuid user_id FK "nullable"
    string action
    string auditable_type
    uuid auditable_id
    json old_values
    json new_values
    string ip_address
    timestamp created_at
  }

  users ||--o{ role_user : ""
  roles ||--o{ role_user : ""
  roles ||--o{ role_has_permissions : ""
  permissions ||--o{ role_has_permissions : ""
  users ||--o{ audit_logs : "nullable"

  %% ─────────────────────────────────────────
  %% COUNTRIES MODULE
  %% ─────────────────────────────────────────

  countries {
    uuid id PK
    string iso_code UK
    boolean is_active
    integer display_order
  }

  country_translations {
    uuid id PK
    uuid country_id FK
    string locale FK "→ languages.code"
    string name
    string native_name
  }

  countries ||--|{ country_translations : "has"

  %% ─────────────────────────────────────────
  %% CONTESTANTS MODULE
  %% ─────────────────────────────────────────

  contestants {
    uuid id PK
    uuid user_id FK UK
    uuid country_id FK
    uuid photo_media_id FK "nullable"
    string phone
    date date_of_birth
    enum gender
    boolean is_active
    timestamp deleted_at
    timestamps
  }

  contestant_documents {
    uuid id PK
    uuid contestant_id FK
    uuid media_asset_id FK
    string document_type
    boolean is_verified
    timestamp deleted_at
    timestamps
  }

  users ||--o| contestants : "has profile"
  countries ||--o{ contestants : "from"
  contestants ||--o{ contestant_documents : "has"

  %% ─────────────────────────────────────────
  %% APPLICATIONS MODULE
  %% ─────────────────────────────────────────

  applications {
    uuid id PK
    uuid contestant_id FK
    uuid season_id FK
    uuid video_id FK "nullable"
    uuid reviewer_id FK "nullable → users"
    string status
    text rejection_reason
    timestamp submitted_at
    timestamp reviewed_at
    timestamp deleted_at
    timestamps
  }

  application_documents {
    uuid id PK
    uuid application_id FK
    uuid media_asset_id FK
    string document_type
    boolean is_required
    timestamp submitted_at
    timestamp deleted_at
  }

  application_status_histories {
    uuid id PK
    uuid application_id FK
    string from_status
    string to_status
    uuid changed_by FK "nullable → users"
    text reason
    timestamp created_at
  }

  contestants ||--o{ applications : "submits"
  applications ||--o{ application_documents : "has"
  applications ||--o{ application_status_histories : "tracks"
  users ||--o{ application_status_histories : "nullable changed_by"

  %% ─────────────────────────────────────────
  %% JUDGES MODULE
  %% ─────────────────────────────────────────

  judges {
    uuid id PK
    uuid user_id FK UK
    uuid country_id FK
    uuid photo_media_id FK "nullable"
    string specialization
    boolean is_active
    integer display_order
    timestamp deleted_at
    timestamps
  }

  judge_biographies {
    uuid id PK
    uuid judge_id FK UK
    string locale FK "→ languages.code"
    longtext biography
  }

  users ||--o| judges : "has profile"
  countries ||--o{ judges : "from"
  judges ||--|{ judge_biographies : "has"

  %% ─────────────────────────────────────────
  %% COMPETITION MODULE
  %% ─────────────────────────────────────────

  seasons {
    uuid id PK
    string name
    integer year UK
    string status
    boolean is_current
    timestamp starts_at
    timestamp ends_at
    timestamp deleted_at
    timestamps
  }

  stages {
    uuid id PK
    uuid season_id FK
    string name
    enum type "stage_1|stage_2|semi_final|final"
    integer order
    string status
    timestamp scheduled_at
    timestamp started_at
    timestamp completed_at
    timestamp deleted_at
    timestamps
  }

  stage_judge_assignments {
    uuid id PK
    uuid stage_id FK
    uuid judge_id FK
    uuid assigned_by FK "→ users"
    timestamp assigned_at
  }

  stage_results {
    uuid id PK
    uuid stage_id FK UK
    json results_data
    timestamp published_at
    uuid published_by FK "→ users"
  }

  seasons ||--|{ stages : "has"
  stages ||--o{ stage_judge_assignments : "has panel"
  judges ||--o{ stage_judge_assignments : "assigned to"
  stages ||--o| stage_results : "has"
  applications }|--|| seasons : "belongs to"

  %% ─────────────────────────────────────────
  %% EVALUATIONS MODULE
  %% ─────────────────────────────────────────

  evaluations {
    uuid id PK
    uuid application_id FK
    uuid stage_id FK
    uuid judge_id FK
    string status
    decimal total_score
    text notes
    integer version "optimistic lock"
    timestamp submitted_at
    timestamp approved_at
    uuid approved_by FK "nullable → users"
    timestamp deleted_at
    timestamps
  }

  evaluation_criteria {
    uuid id PK
    string code UK
    decimal max_score
    decimal weight
    integer order
    boolean is_active
    timestamps
  }

  evaluation_criterion_translations {
    uuid id PK
    uuid criterion_id FK
    string locale FK "→ languages.code"
    string name
    text description
  }

  evaluation_scores {
    uuid id PK
    uuid evaluation_id FK
    uuid criterion_id FK
    decimal score
    text note
    timestamps
  }

  applications ||--o{ evaluations : "evaluated in"
  stages ||--o{ evaluations : "contains"
  judges ||--o{ evaluations : "conducts"
  evaluations ||--|{ evaluation_scores : "has"
  evaluation_criteria ||--|{ evaluation_scores : "scored by"
  evaluation_criteria ||--|{ evaluation_criterion_translations : "translated"

  %% ─────────────────────────────────────────
  %% VIDEOS MODULE
  %% ─────────────────────────────────────────

  videos {
    uuid id PK
    uuid media_asset_id FK "original upload"
    uuid application_id FK "nullable"
    uuid contestant_id FK
    string status
    integer duration_seconds
    timestamp processed_at
    timestamp published_at
    timestamp deleted_at
    timestamps
  }

  video_variants {
    uuid id PK
    uuid video_id FK
    uuid media_asset_id FK "processed file"
    enum quality "360p|720p|1080p"
    enum format "mp4|hls_manifest"
    timestamp processed_at
  }

  video_thumbnails {
    uuid id PK
    uuid video_id FK
    uuid media_asset_id FK "image file"
    enum type "default|hd"
    integer width
    integer height
  }

  contestants ||--o{ videos : "uploads"
  applications ||--o| videos : "linked to"
  videos ||--|{ video_variants : "has"
  videos ||--|{ video_thumbnails : "has"
  media_assets ||--o| videos : "original file"
  media_assets ||--o{ video_variants : "variant file"
  media_assets ||--o{ video_thumbnails : "thumbnail file"

  %% ─────────────────────────────────────────
  %% MEDIA MODULE
  %% ─────────────────────────────────────────

  media_assets {
    uuid id PK
    string disk "local|s3|r2"
    string path
    string original_filename
    string mime_type
    bigint file_size_bytes
    string morphable_type "polymorphic"
    uuid morphable_id "polymorphic"
    uuid uploaded_by FK "nullable → users"
    timestamp deleted_at
    timestamps
  }

  %% Direct FK relationships (Media Ownership Convention)
  %% media_assets ←── contestants.photo_media_id
  %% media_assets ←── judges.photo_media_id
  %% media_assets ←── contestant_documents.media_asset_id
  %% media_assets ←── application_documents.media_asset_id
  %% media_assets ←── announcements.image_media_id
  %% media_assets ←── sponsors.logo_media_id
  %% (FK drawn on each respective entity for clarity)

  %% ─────────────────────────────────────────
  %% STREAMING MODULE
  %% ─────────────────────────────────────────

  streams {
    uuid id PK
    uuid season_id FK "nullable"
    string status "offline|live|paused"
    string title
    integer viewer_count
    timestamp started_at
    timestamp stopped_at
    timestamps
  }

  stream_sources {
    uuid id PK
    uuid stream_id FK
    enum protocol "rtmp|hls|dash"
    string url
    boolean is_primary
  }

  seasons ||--o{ streams : "has"
  streams ||--|{ stream_sources : "uses"

  %% ─────────────────────────────────────────
  %% CONTENT MODULE
  %% ─────────────────────────────────────────

  pages {
    uuid id PK
    string slug UK
    string status "draft|published"
    string template
    timestamp published_at
    timestamp deleted_at
    timestamps
  }

  page_translations {
    uuid id PK
    uuid page_id FK
    string locale FK
    string title
    longtext body
    string meta_title
    string meta_description
  }

  announcements {
    uuid id PK
    uuid image_media_id FK "nullable"
    string status "draft|published|scheduled"
    timestamp published_at
    timestamp scheduled_at
    timestamp deleted_at
    timestamps
  }

  announcement_translations {
    uuid id PK
    uuid announcement_id FK
    string locale FK
    string title
    text body
  }

  faqs {
    uuid id PK
    boolean is_active
    integer display_order
    timestamp deleted_at
    timestamps
  }

  faq_translations {
    uuid id PK
    uuid faq_id FK
    string locale FK
    string question
    text answer
  }

  pages ||--|{ page_translations : "translated"
  announcements ||--|{ announcement_translations : "translated"
  faqs ||--|{ faq_translations : "translated"

  %% ─────────────────────────────────────────
  %% SPONSORS MODULE
  %% ─────────────────────────────────────────

  sponsors {
    uuid id PK
    uuid logo_media_id FK "nullable"
    string name
    string website_url
    enum tier "platinum|gold|silver|partner"
    boolean is_active
    integer display_order
    timestamp deleted_at
    timestamps
  }

  %% ─────────────────────────────────────────
  %% NOTIFICATIONS MODULE
  %% ─────────────────────────────────────────

  notifications {
    uuid id PK
    uuid user_id FK
    string type
    enum channel "in_app|email|sms"
    json data
    timestamp read_at
    timestamp deleted_at
    timestamp created_at
  }

  notification_templates {
    uuid id PK
    string event_type UK
    enum channel
    boolean is_active
    timestamps
  }

  notification_template_translations {
    uuid id PK
    uuid template_id FK
    string locale FK
    string subject
    text body
  }

  users ||--o{ notifications : "receives"
  notification_templates ||--|{ notification_template_translations : "translated"
```

---

## 2. Cross-Module Relationship Map

The following relationships cross module boundaries. These are the most architecturally significant relationships — they define the coupling between modules at the data layer.

> **Rule from ADR-002**: Cross-module data references are expressed as FK references to the owning module's primary key only. No module may JOIN into another module's internal tables. Cross-module reads use the owning module's API.

| From Entity | From Module | FK Column | References | To Module | Nature |
|---|---|---|---|---|---|
| `contestants.user_id` | Contestants | `user_id` | `users.id` | Core | Contestant extends User — mandatory |
| `judges.user_id` | Judges | `user_id` | `users.id` | Core | Judge extends User — mandatory |
| `contestants.country_id` | Contestants | `country_id` | `countries.id` | Countries | Nationality reference |
| `judges.country_id` | Judges | `country_id` | `countries.id` | Countries | Nationality reference |
| `applications.contestant_id` | Applications | `contestant_id` | `contestants.id` | Contestants | Application belongs to Contestant |
| `applications.season_id` | Applications | `season_id` | `seasons.id` | Competition | Application belongs to Season |
| `applications.video_id` | Applications | `video_id` | `videos.id` | Videos | Application links to Video — optional |
| `videos.contestant_id` | Videos | `contestant_id` | `contestants.id` | Contestants | Video uploaded by Contestant |
| `evaluations.application_id` | Evaluations | `application_id` | `applications.id` | Applications | Evaluation of an Application |
| `evaluations.stage_id` | Evaluations | `stage_id` | `stages.id` | Competition | Evaluation belongs to Stage |
| `evaluations.judge_id` | Evaluations | `judge_id` | `judges.id` | Judges | Evaluation conducted by Judge |
| `stage_judge_assignments.judge_id` | Competition | `judge_id` | `judges.id` | Judges | Stage panel membership |
| `notifications.user_id` | Notifications | `user_id` | `users.id` | Core | Notification delivered to User |
| `audit_logs.user_id` | Core | `user_id` | `users.id` | Core | Who performed the action |
| `streams.season_id` | Streaming | `season_id` | `seasons.id` | Competition | Stream associated with Season |

---

## 3. Aggregation Boundaries

This section defines which entities belong to which aggregate, establishing the boundary within which transactional consistency must be maintained.

> **Principle**: Transactions must not span aggregate boundaries. If two entities must change together within the same transaction, they belong to the same aggregate.

| Aggregate Root | Owned Entities | Transaction Boundary |
|---|---|---|
| `User` | — | Single table — modifications to `users` only |
| `Contestant` | `ContestantDocument` | Profile + documents in one transaction |
| `Application` | `ApplicationDocument`, `ApplicationStatusHistory` | Submit application + documents + initial status in one transaction |
| `Judge` | `JudgeBiography` | Judge + biography in one transaction |
| `Season` | — | Season standalone |
| `Stage` | `StageJudgeAssignment`, `StageResult` | Stage + panel + result publishable as unit |
| `Evaluation` | `EvaluationScore` | Scoring session — all criteria scores saved together |
| `Video` | `VideoVariant`, `VideoThumbnail` | Processing job writes all variants + thumbnails atomically |
| `Page` | `PageTranslation` | Page + all translations saved together |
| `Announcement` | `AnnouncementTranslation` | Announcement + all translations saved together |
| `FAQ` | `FAQTranslation` | FAQ + all translations saved together |
| `EvaluationCriterion` | `EvaluationCriterionTranslation` | Rubric criterion + all translations saved together |
| `NotificationTemplate` | `NotificationTemplateTranslation` | Template + all translations saved together |

---

## 4. Cardinality Reference

### One-to-One Relationships

| Entity A | Entity B | Constraint |
|---|---|---|
| `users` | `contestants` | One user may have at most one contestant profile |
| `users` | `judges` | One user may have at most one judge profile |
| `stages` | `stage_results` | One stage has at most one published result set |
| `applications` | `videos` | One application links to at most one video (latest re-upload wins) |

### One-to-Many Relationships

| Parent | Children | Constraint |
|---|---|---|
| `seasons` | `stages` | A season has 4 stages (but this is a business rule, not a DB constraint) |
| `stages` | `stage_judge_assignments` | Multiple judges per stage |
| `contestants` | `applications` | One per season (unique constraint enforces this) |
| `applications` | `application_documents` | Multiple documents per application |
| `applications` | `application_status_histories` | History grows with every transition |
| `evaluations` | `evaluation_scores` | One score per criterion per evaluation |
| `videos` | `video_variants` | Three variants (360p, 720p, 1080p) |
| `users` | `notifications` | Many notifications per user |
| `notification_templates` | `notification_template_translations` | One translation per supported locale |

### Many-to-Many Relationships (via pivot table)

| Entity A | Entity B | Pivot Table | Notes |
|---|---|---|---|
| `users` | `roles` | `model_has_roles` | Spatie convention |
| `roles` | `permissions` | `role_has_permissions` | Spatie convention |

### Polymorphic Relationships

| Entity | Morphs To | Used For |
|---|---|---|
| `media_assets` | `contestants`, `judges`, `applications`, `pages`, `announcements`, `sponsors` | Universal file attachment |
| `audit_logs` | Any auditable entity (`applications`, `contestants`, `users`, etc.) | Universal audit trail |

---

## 5. Index Strategy Preview

> Detailed index decisions are deferred to ADR-005. This section flags the most critical indexes identified during entity analysis.

| Table | Column(s) | Index Type | Reason |
|---|---|---|---|
| `users` | `email` | UNIQUE | Login lookup |
| `users` | `type` | INDEX | Surface discriminator filter |
| `contestants` | `user_id` | UNIQUE | One contestant per user |
| `applications` | `(contestant_id, season_id)` | UNIQUE | One application per season |
| `applications` | `status` | INDEX | Frequently filtered |
| `applications` | `submitted_at` | INDEX | Default sort |
| `evaluations` | `(application_id, stage_id, judge_id)` | UNIQUE | One evaluation per combo |
| `evaluations` | `status` | INDEX | Frequently filtered |
| `videos` | `status` | INDEX | Frequently filtered |
| `videos` | `published_at` | INDEX | Gallery sort |
| `stage_judge_assignments` | `(stage_id, judge_id)` | UNIQUE | No duplicate assignments |
| `audit_logs` | `(auditable_type, auditable_id)` | INDEX | Entity audit lookup |
| `audit_logs` | `created_at` | INDEX | Time-based partitioning |
| `notifications` | `(user_id, read_at)` | INDEX | Unread inbox query |
| `media_assets` | `(morphable_type, morphable_id)` | INDEX | Polymorphic lookup |
| `*_translations` | `(entity_id, locale)` | UNIQUE | Translation lookup |
| `seasons` | `is_current` | PARTIAL UNIQUE | Only one current season |

---

## 6. Module Schema Boundaries (Table Ownership Map)

The following table is the authoritative record of which module owns which database tables. A module owns a table if and only if it is the only module that writes migrations, models, and repository implementations for that table.

| Table | Owner Module | Notes |
|---|---|---|
| `users` | Core | Only Core may add columns |
| `roles` | Core | Spatie tables — owned by Core |
| `permissions` | Core | Spatie tables — owned by Core |
| `model_has_roles` | Core | Spatie pivot |
| `role_has_permissions` | Core | Spatie pivot |
| `languages` | Core | |
| `settings` | Core | |
| `audit_logs` | Core | All modules write via `AuditLoggerContract` |
| `countries` | Countries | |
| `country_translations` | Countries | |
| `contestants` | Contestants | |
| `contestant_documents` | Contestants | |
| `applications` | Applications | |
| `application_documents` | Applications | |
| `application_status_histories` | Applications | |
| `judges` | Judges | |
| `judge_biographies` | Judges | |
| `seasons` | Competition | |
| `stages` | Competition | |
| `stage_judge_assignments` | Competition | |
| `stage_results` | Competition | |
| `evaluations` | Evaluations | |
| `evaluation_criteria` | Evaluations | |
| `evaluation_criterion_translations` | Evaluations | |
| `evaluation_scores` | Evaluations | |
| `videos` | Videos | |
| `video_variants` | Videos | |
| `video_thumbnails` | Videos | |
| `media_assets` | Media | |
| `streams` | Streaming | |
| `stream_sources` | Streaming | |
| `pages` | Content | |
| `page_translations` | Content | |
| `announcements` | Content | |
| `announcement_translations` | Content | |
| `faqs` | Content | |
| `faq_translations` | Content | |
| `sponsors` | Sponsors | |
| `notifications` | Notifications | |
| `notification_templates` | Notifications | |
| `notification_template_translations` | Notifications | |

**Total tables**: 41
**Modules with no tables**: Search (Meilisearch), Cache (Redis), Reports (read-only)
