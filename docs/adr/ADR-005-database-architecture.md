# ADR-005: Database Architecture

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-005                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-003 · ADR-004 · DATABASE-DOMAIN-INVENTORY · ENTITY-RELATIONSHIP-MAP                                   |

---

## Status

**Accepted** — This document is the constitutional reference for the platform data layer. All migrations, models, and repository implementations must conform to it. Deviations require a formal ADR amendment.

---

## Context

### Why This ADR Exists

ADR-001 established the API-First, Modular Monolith architecture. ADR-002 defined the module boundaries and declared that each module owns its data. ADR-003 defined the two authentication surfaces. ADR-004 defined the complete API contract. None of those documents specified *how the data layer is structured* — the schema decisions, naming rules, deletion policies, concurrency strategies, and the physical organisation of 41 tables across 15 modules.

Without a governing database ADR, the following failure modes are historically inevitable in enterprise systems:

- Inconsistent primary key strategies between modules break cross-module referencing and API ID contracts.
- Ad-hoc soft-delete decisions leave some tables with `deleted_at` and others without, making audit trails unreliable.
- Undocumented FK cascade rules produce silent data loss on parent deletion.
- Undocumented concurrency strategy produces race conditions in evaluation scoring and state transitions.
- Path strings stored in domain tables instead of in a unified media registry make storage provider migration impossible.
- Redundant `season_id` columns scattered across tables break normalization and produce inconsistency bugs.
- Triggers and stored procedures encode business logic at the DB layer, bypassing module boundaries and domain events.

This ADR eliminates these failure modes by establishing binding, numbered decisions before the first migration is written.

### Dependency Chain

```
ADR-001 (System Architecture)
    └── API-First, Modular Monolith, Domain-Oriented Modules
ADR-002 (Module Boundaries)
    └── Module owns its tables · No cross-module writes · Domain events for cross-module communication
ADR-003 (Authentication)
    └── users.type discriminator · Two surfaces
ADR-004 (API Standards)
    └── UUID v7 IDs in responses · snake_case JSON · ISO 8601 timestamps
DATABASE-DOMAIN-INVENTORY
    └── 42 entities · State machines · Translation strategy · Video storage · Delete policies
ENTITY-RELATIONSHIP-MAP
    └── 41 tables · Aggregate boundaries · FK relationships · Module ownership map
        │
        ▼
ADR-005 (this document) — The Database Constitution
        │
        ▼
ERD (formal) → Migration Specification → Laravel Migrations
```

### Prerequisite Documents

This ADR was authored after completing:
- [DATABASE-DOMAIN-INVENTORY.md](../DATABASE-DOMAIN-INVENTORY.md) — Entity analysis, state machines, delete policies, season isolation, concurrency policy, outbox decision, media ownership convention.
- [ENTITY-RELATIONSHIP-MAP.md](../ENTITY-RELATIONSHIP-MAP.md) — Mermaid ER diagram, aggregate boundaries, FK policy, module table ownership map.

---

## Architectural Principles

These five principles govern every decision in this ADR. When a specific decision is ambiguous, these principles are the tiebreaker.

### Principle 1: The Database is Subordinate to the Domain

The database is an implementation detail of the domain model. Schema is derived from domain analysis, never the other way around. No table is created because it is convenient for a query. Tables exist because domain entities exist.

### Principle 2: Module Ownership is Absolute

Every table has exactly one owning module. The owning module is the only module that may write Eloquent migrations, Eloquent models, and repository implementations for that table. Reading another module's data is done through that module's published API or service contract — never through a direct Eloquent model reference.

### Principle 3: Aggregate Consistency is the Transaction Boundary

The aggregate boundary defined in the Entity Relationship Map is also the transaction boundary. If two entities must change together atomically, they belong to the same aggregate. Transactions that span aggregate boundaries are prohibited. Cross-aggregate coordination is done through domain events, never through distributed transactions.

### Principle 4: No Business Logic in the Database

The database stores and retrieves data. It does not enforce business rules. State machine transitions, validation logic, calculation of derived values, and notification dispatch are all application-layer concerns. Triggers, stored procedures, and computed columns that encode business logic are prohibited.

### Principle 5: Stability Over Convenience

Breaking database changes (column removal, type changes, FK rule changes) have long-lasting consequences. When a decision requires an extra join or an extra line of application code but prevents a breaking schema change, the extra code is the correct choice.

---

## Formal Decisions

Each decision is numbered, self-contained, and binding. The consequence section of each decision describes what the decision locks in and what it intentionally sacrifices.

---

### PART I — FOUNDATION

---

### Decision 1: Database Engine, Character Set & Collation

#### Context

The platform uses MySQL as its primary relational database. Multiple character set and collation options are available. The choice affects Arabic text storage, emoji support in content fields, and string comparison behavior.

#### Decision

| Property | Value |
|---|---|
| **Engine** | MySQL 8.0 or above |
| **Storage Engine** | InnoDB (all tables) |
| **Character Set** | `utf8mb4` |
| **Collation** | `utf8mb4_unicode_ci` |
| **Timezone** | All timestamps stored in UTC; timezone conversion is an application-layer concern |
| **SQL Mode** | `STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION` |

#### Rationale

- `utf8mb4` is the only MySQL character set that supports the full Unicode BMP including emoji, Arabic text, and all characters in the supported language set (Arabic, English, Spanish).
- `utf8mb4_unicode_ci` provides correct case-insensitive comparison for all three supported languages.
- `STRICT_TRANS_TABLES` prevents silent data truncation; any value that does not fit its column type produces an error rather than silent corruption.
- InnoDB is mandatory because FOREIGN KEY enforcement and row-level locking (required by the Concurrency Policy in Decision 16) are InnoDB-only features.

#### Consequences

- All `VARCHAR` columns store Arabic correctly without additional configuration.
- Emoji in page content and announcement bodies are stored without corruption.
- Developers must never issue `ALTER TABLE ... CHARSET=latin1` or any non-utf8mb4 character set.
- All application date/time operations must use UTC at the ORM layer; never configure Laravel's DB timezone to a local timezone.

---

### Decision 2: Primary Key Strategy

#### Context

ADR-004 mandates that all API responses use UUID identifiers and never expose integer auto-increment IDs. The database primary key strategy must be consistent with this contract.

#### Decision

| Rule | Detail |
|---|---|
| **Primary key type** | UUID v7 (time-ordered UUID, RFC draft) |
| **Column name** | `id` on every table |
| **PHP generation** | Generated in the application layer before insert; never delegated to the database |
| **Storage type** | `CHAR(36)` or binary `BINARY(16)` per module implementation |
| **Auto-increment** | Prohibited on any table that is referenced by an API response |

**Exception**: Pure pivot tables with no API exposure (e.g., `role_has_permissions`, `model_has_roles`) may use auto-increment integer surrogate keys. The exception applies only when both conditions are true: (1) the table has no UUID v7 `id` column, and (2) the table is never referenced directly in an API response.

#### Rationale

- UUID v7 is time-ordered, preserving the B-tree index locality of auto-increment IDs while being globally unique and non-enumerable.
- Non-enumerable IDs prevent the resource enumeration attacks that sequential integers enable.
- Application-layer generation allows the ID to be known before the `INSERT` completes, enabling the Outbox event payload to reference the ID immediately.
- Consistency with ADR-004's ID contract eliminates the mapping layer between database IDs and API IDs.

#### Consequences

- All Eloquent models set `$keyType = 'string'` and `$incrementing = false`.
- The `HasUuids` trait or equivalent is applied to every model.
- UUID v7 generation requires a library (e.g., `ramsey/uuid`) or PHP 8.2+ `Uuid` support.
- UUID primary keys are ~36 bytes vs. 4 bytes for an integer — an acceptable storage overhead given the benefits.

---

### Decision 3: Naming Conventions & Canonical Examples

#### Context

When naming conventions are documented abstractly ("use snake_case"), developers inevitably make different choices for ambiguous cases. This decision provides **binding canonical examples** that eliminate naming debates at code review time.

#### Decision

**3.1 Table Names**

- Plural nouns in `snake_case`.
- No module prefix in the table name itself — module ownership is tracked in the Module Table Ownership Map.

```
contestants
applications
application_documents
application_status_histories
evaluation_criteria
evaluation_criterion_translations
stage_judge_assignments
notification_template_translations
media_assets
outbox_events
```

**3.2 Primary Key Column**

Always `id`. No exceptions, no aliases.

```sql
`id` CHAR(36) NOT NULL PRIMARY KEY
```

**3.3 Foreign Key Columns**

Format: `{referenced_table_singular}_id`

```
contestant_id        -- references contestants.id
season_id            -- references seasons.id
media_asset_id       -- references media_assets.id
photo_media_id       -- references media_assets.id (named by role, not table)
image_media_id       -- references media_assets.id (named by role)
logo_media_id        -- references media_assets.id (named by role)
reviewer_id          -- references users.id (named by role)
assigned_by          -- references users.id (named by action, for actor FKs)
published_by         -- references users.id
changed_by           -- references users.id
approved_by          -- references users.id
```

**Role-named FKs**: When multiple FKs reference the same table for different roles, use the role name, not the table name.

**3.4 Boolean Columns**

Use `is_` or `has_` prefix:

```
is_active
is_current
is_verified
is_required
is_primary
is_live          ← exception: no is_ prefix when the word reads naturally without it
rtl              ← exception: established abbreviation
```

**3.5 Timestamp Columns**

```
created_at       -- all tables (except append-only which omit updated_at)
updated_at       -- all tables except append-only
deleted_at       -- soft-delete tables only
submitted_at     -- past tense + _at for domain timestamps
reviewed_at
published_at
processed_at
dispatched_at
started_at
completed_at
```

**3.6 Status Columns**

Always `status` (singular). Never `state`, `current_status`, `application_status`.

**3.7 Index Naming**

```
-- Standard index:
idx_{table}_{column(s)}

idx_applications_status
idx_applications_submitted_at
idx_videos_status
idx_notifications_user_id_read_at

-- Unique index:
uk_{table}_{column(s)}

uk_users_email
uk_contestants_user_id
uk_applications_contestant_season        -- composite
uk_evaluations_application_stage_judge   -- composite
uk_translations_entity_locale            -- generic pattern

-- Foreign key constraint:
fk_{table}_{referenced_table}

fk_contestants_users
fk_applications_contestants
fk_applications_seasons
fk_evaluations_applications
fk_page_translations_pages
fk_page_translations_languages

-- When multiple FKs reference the same table:
fk_{table}_{column}

fk_applications_reviewer       -- applications.reviewer_id → users
fk_stage_results_published_by  -- stage_results.published_by → users
```

**3.8 Enum Column Values**

Always `snake_case` strings. Never integers, never ALLCAPS, never camelCase.

```
status: 'received', 'under_review', 'under_evaluation', 'accepted', 'rejected'
type:   'stage_1', 'stage_2', 'semi_final', 'final'
tier:   'platinum', 'gold', 'silver', 'partner'
quality: '360p', '720p', '1080p'
```

#### Rationale

Canonical examples eliminate ambiguity. When a developer creates a new table `video_hls_segments`, they can look at the examples and immediately know: the FK is `video_id`, the index is `idx_video_hls_segments_video_id`, the FK constraint is `fk_video_hls_segments_videos`. No team discussion required.

#### Consequences

- Code review must reject any column, index, or FK constraint that deviates from these examples.
- A naming linter (or a checklist in the PR template) is recommended to enforce this automatically.

---

### Decision 4: Module Schema Ownership

#### Context

ADR-002 defined module boundaries at the code layer. This decision extends that boundary to the database layer.

#### Decision

The authoritative module-to-table ownership map is defined in [ENTITY-RELATIONSHIP-MAP.md — Section 6](../ENTITY-RELATIONSHIP-MAP.md). The binding rules are:

1. Every table has exactly one owning module.
2. Only the owning module may create, modify, or drop migrations for that table.
3. No other module may add columns to a table it does not own.
4. No other module may access a table it does not own using a direct Eloquent model. It must use the owning module's `RepositoryContract` or `ServiceContract`.
5. Cross-module FK references are permitted (e.g., `applications.contestant_id` referencing `contestants.id`) but they are read-only references. The Applications module may read from `contestants` via FK lookup, but must never write to `contestants`.

**Ownership is declared in each module's `ModuleServiceProvider`** via a `tableOwnership()` method that lists the tables the module claims.

#### Rationale

Module ownership at the database layer is the enforcement mechanism for the module isolation principle from ADR-002. Without it, the Modular Monolith degrades into a Big Ball of Mud where every module writes to every table.

#### Consequences

- The first action when creating a migration is declaring which module owns the affected table.
- A CI check may validate that migrations live in the correct module's directory.
- Boundary violations are defects, not style issues.

---

### PART II — DATA CLASSIFICATION

---

### Decision 5: Season Isolation (Multi-Tenancy Policy)

#### Context

The platform manages competitions across multiple seasons. Season data isolation is the multi-tenancy decision for this platform. It cannot be changed after migrations are written.

#### Decision

Every entity is classified into one of three categories. The classification determines whether the entity's table carries a `season_id` column.

**Category A — Global Reference Data**
No `season_id` column. These entities exist independently of any season.

`users`, `roles`, `permissions`, `languages`, `settings`, `countries`, `contestants`, `judges`, `evaluation_criteria`, `notification_templates`, `media_assets`, `sponsors`, `pages`, `faqs`, `audit_logs`

**Category B — Season-Scoped Operational Data**
These entities are created within and specific to a season. They carry `season_id` either directly or derivably.

| Entity | `season_id` | How |
|---|---|---|
| `applications` | Direct column | The primary season anchor |
| `stages` | Direct column | Stages belong to a season |
| `streams` | Direct column (nullable) | Optional season association |
| `stage_judge_assignments` | Derivable via `stage_id` | No direct column |
| `stage_results` | Derivable via `stage_id` | No direct column |
| `evaluations` | Derivable via `application_id` | No direct column |
| `evaluation_scores` | Derivable via `evaluation_id` | No direct column |

**Category C — Cross-Season Entities**
These entities participate in season workflows but are not owned by any single season.

`videos` (linked via `application_id`), `notifications` (linked via user context), `announcements` (platform-wide content).

**The Golden Rule**: If `season_id` is reachable through a join chain, adding a direct `season_id` column is **prohibited**. Denormalized season references are a defect.

Only three tables carry a **direct** `season_id` column: `applications`, `stages`, `streams`.

#### Rationale

The alternative — adding `season_id` to every operational table — creates denormalization. If `applications.season_id` and `evaluations.season_id` can disagree (due to a bug), the platform has no authoritative source of truth. The single-anchor model (`applications.season_id`) eliminates this inconsistency class.

#### Consequences

- Reporting queries that need to filter by season join through `applications.season_id` or `stages.season_id`. This adds joins but prevents data inconsistency.
- The unique constraint `(contestant_id, season_id)` on `applications` is the enforcement mechanism for "one application per contestant per season". No other table needs to enforce this.

---

### Decision 6: Delete Policy Matrix

#### Context

Soft delete is not the correct policy for every entity. A uniform approach produces incorrect behavior (e.g., audit logs that can be "soft-deleted" lose their integrity guarantee). This decision establishes five distinct delete policies and assigns each entity to exactly one.

#### Decision

**Policy Definitions**:

| Policy | Mechanism | Reversible |
|---|---|---|
| **Soft Delete** | `deleted_at TIMESTAMP NULL`; excluded from default scopes; restorable | Yes |
| **Hard Delete** | `DELETE FROM` — physical removal | No |
| **Archive Only** | `status = 'archived'` transition; never deleted | N/A |
| **Append Only** | INSERT only; no UPDATE, no DELETE; application-enforced | N/A |
| **Never Delete** | `ON DELETE RESTRICT` on all FKs pointing to this table; deletion blocked at DB layer | N/A |

**Policy Registry** (binding for every migration):

| Entity | Policy |
|---|---|
| `users` | Soft Delete |
| `roles` | Never Delete |
| `permissions` | Never Delete |
| `languages` | Never Delete |
| `settings` | Hard Delete (obsolete keys removed by migration) |
| `audit_logs` | Append Only |
| `countries` | Never Delete |
| `contestants` | Soft Delete |
| `contestant_documents` | Soft Delete |
| `applications` | Soft Delete |
| `application_documents` | Soft Delete |
| `application_status_histories` | Append Only + Never Delete |
| `judges` | Soft Delete |
| `judge_biographies` | Hard Delete (replaced on update) |
| `seasons` | Archive Only |
| `stages` | Soft Delete |
| `stage_judge_assignments` | Hard Delete |
| `stage_results` | Never Delete |
| `evaluations` | Soft Delete |
| `evaluation_criteria` | Never Delete |
| `evaluation_criterion_translations` | Hard Delete (replaced on update) |
| `evaluation_scores` | Archive Only (finalized on approval) |
| `videos` | Soft Delete |
| `video_variants` | Hard Delete (replaced on reprocessing) |
| `video_thumbnails` | Hard Delete (replaced on reprocessing) |
| `media_assets` | Soft Delete (physical cleanup via background job) |
| `streams` | Soft Delete |
| `stream_sources` | Hard Delete (replaced on stream reconfiguration) |
| `pages` | Soft Delete |
| `page_translations` | Hard Delete (replaced on update) |
| `announcements` | Soft Delete |
| `announcement_translations` | Hard Delete |
| `faqs` | Soft Delete |
| `faq_translations` | Hard Delete |
| `sponsors` | Soft Delete |
| `notifications` | Soft Delete |
| `notification_templates` | Soft Delete |
| `notification_template_translations` | Hard Delete |
| `outbox_events` | Archive Only (30-day retention, then purge) |

#### Rationale

Without a formal matrix, developers default to soft delete everywhere. This produces tables that accumulate deleted records indefinitely, makes audit-sensitive tables (like `audit_logs`) appear deletable when they must not be, and causes confusion about what "deleted" means for different entities.

#### Consequences

- Migration writers must check the Delete Policy Registry before adding or omitting `deleted_at`.
- "Never Delete" entities have `ON DELETE RESTRICT` on all FKs pointing to them, enforced at the DB layer.
- `audit_logs` and `application_status_histories` have no `deleted_at` column and no `updated_at` column.

---

### Decision 7: Referential Integrity Policy

#### Context

FK cascade rules determine what happens to child records when a parent is deleted. Inconsistent rules produce data loss (when CASCADE is used where RESTRICT should be) or orphan records (when SET NULL is used where CASCADE should be).

#### Decision

**Rule 1: Translation tables → `ON DELETE CASCADE`**

A translation row has no existence without its parent. If the parent is hard-deleted, the translations must be deleted.

Applies to: `page_translations`, `announcement_translations`, `faq_translations`, `country_translations`, `evaluation_criterion_translations`, `notification_template_translations`

**Rule 2: Owned aggregate children → `ON DELETE CASCADE`**

Children that belong to the parent aggregate have no independent existence.

Applies to: `video_variants`, `video_thumbnails`, `stream_sources`, `evaluation_scores`

**Rule 3: History and audit children → `ON DELETE RESTRICT`**

Historical records must not be silently removed when a parent is soft-deleted. The FK prevents hard deletion even when soft deletion has occurred.

Applies to: `application_status_histories.application_id`, `audit_logs` polymorphic references

**Rule 4: Cross-module FK references → `ON DELETE RESTRICT`**

The owning module must not silently cascade its deletions into another module's data.

Applies to: all FKs that cross module boundaries:
`contestants.user_id`, `judges.user_id`, `applications.contestant_id`, `applications.season_id`, `stages.season_id`, `evaluations.application_id`, `evaluations.stage_id`, `evaluations.judge_id`

**Rule 5: Optional FK references → `ON DELETE SET NULL`**

When the FK is nullable and the referencing entity can meaningfully exist without the referenced entity.

Applies to: `applications.video_id`, `streams.season_id`, `contestants.photo_media_id`, `judges.photo_media_id`, `announcements.image_media_id`, `sponsors.logo_media_id`

**Enforcement**: All FK constraints are enforced at the database layer (InnoDB `FOREIGN KEY` declarations in migrations), not only at the application layer.

#### Rationale

Application-layer-only FK enforcement is insufficient because direct database queries, data migrations, and bulk operations bypass Eloquent's event system. Database-level FK constraints are the last line of defense.

#### Consequences

- Every migration that creates a FK must explicitly declare the `ON DELETE` rule.
- A migration reviewer must verify that the declared rule matches the entity's classification under this decision.
- FK constraints increase `DELETE` operation cost marginally — acceptable for the integrity guarantee.

---

### PART III — STRUCTURAL PATTERNS

---

### Decision 8: Timestamps & Timezone

#### Decision

| Rule | Value |
|---|---|
| All tables include | `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| All non-append-only tables include | `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| Append-only tables omit | `updated_at` |
| All timestamp values | UTC |
| Application timezone config | `config/app.php` timezone = `UTC` |
| DB connection timezone | `timezone: '+00:00'` in `config/database.php` |
| Format in API responses | ISO 8601 UTC (`2026-07-31T12:00:00Z`) per ADR-004 |

No table is created without `created_at`. No mutation is performed without `updated_at` being automatically updated.

#### Consequences

- All date arithmetic in application code assumes UTC input and UTC output.
- Frontend applications are responsible for converting UTC to the user's local timezone for display.

---

### Decision 9: Soft Deletes

#### Context

Soft delete must be applied consistently only to the entities listed in Decision 6's Delete Policy Registry.

#### Decision

- All Soft Delete entities include `deleted_at TIMESTAMP NULL DEFAULT NULL`.
- Laravel's `SoftDeletes` trait is applied to all corresponding Eloquent models.
- The global `SoftDeletingScope` automatically excludes soft-deleted records from all standard queries.
- Restoration uses `restore()`. Physical deletion of soft-deleted records is a background job, not an immediate operation.
- Soft-deleted records are still accessible via `withTrashed()` for admin views and audit purposes.
- `Never Delete` and `Append Only` entities must **never** have a `deleted_at` column.

#### Consequences

- Queries that need to include soft-deleted records (e.g., the admin deleted-items view) must explicitly use `withTrashed()`.
- Unique constraints must account for soft-deleted records. A unique constraint on `(contestant_id, season_id)` in `applications` may need to be implemented at the application layer rather than the DB layer if resubmission after cancellation is a business requirement.

---

### Decision 10: Audit Log

#### Context

Enterprise platforms require an immutable audit trail for all significant write operations. This audit trail must be independent of application-layer logging (which may be configured off) and must survive code changes.

#### Decision

The `audit_logs` table is owned by the Core module and is the single audit table for the entire platform.

**Schema**:

```sql
CREATE TABLE audit_logs (
    id              CHAR(36)    NOT NULL,
    user_id         CHAR(36)    NULL,                  -- NULL for system-initiated actions
    action          VARCHAR(100) NOT NULL,             -- 'created', 'updated', 'deleted', 'state_changed'
    auditable_type  VARCHAR(100) NOT NULL,             -- 'Application', 'Video', 'Season'
    auditable_id    CHAR(36)    NOT NULL,
    old_values      JSON        NULL,
    new_values      JSON        NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT        NULL,
    created_at      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_auditable (auditable_type, auditable_id),
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_created_at (created_at)
);
```

**Rules**:
- No `updated_at` column — audit logs are append-only.
- No `deleted_at` column — audit logs are Never Delete.
- All domain modules write to `audit_logs` through the `AuditLoggerContract` from the Core module. No module calls the `AuditLog` model directly.
- The `old_values` and `new_values` columns contain only the changed fields, not the full entity snapshot.
- Retention policy is defined in ADR-007. After the retention window, rows are archived (not deleted) by a background job.

#### Consequences

- Every mutating operation in every module must invoke `AuditLoggerContract::log()`.
- The `AuditLoggerContract` is a Core-owned contract that all modules depend on. It must never be broken.
- Audit log reads are used by the admin dashboard. Index on `(auditable_type, auditable_id)` is mandatory.

---

### Decision 11: State Machines & Status Columns

#### Context

The platform has five state machines (Application, Season, Stage, Evaluation, Video), each with governed state transitions. The storage strategy and enforcement mechanism must be defined.

#### Decision

**Storage**: Status values are stored as `VARCHAR(50) NOT NULL` columns named `status`. Never integers, never tinyint enums, never Laravel `enum` type at the database layer.

**Rationale for VARCHAR over MySQL ENUM**: MySQL ENUM requires a schema migration to add a new status value. VARCHAR allows adding new status values without `ALTER TABLE`, using only application-layer validation.

**Permitted transitions** are enforced exclusively at the **application layer** (Use Case / Service class), never in the database. The database stores the current state; the application governs how it changes.

**State history** is recorded in dedicated history tables (currently: `application_status_histories`). Every state transition inserts a row into the history table **within the same transaction** as the status update.

**The five platform state machines**:

| Entity | Status Values |
|---|---|
| `applications.status` | `received`, `under_review`, `under_evaluation`, `accepted`, `rejected`, `needs_data`, `video_reupload_requested` |
| `seasons.status` | `draft`, `active`, `closed`, `archived` |
| `stages.status` | `scheduled`, `active`, `completed`, `results_published` |
| `evaluations.status` | `pending`, `in_progress`, `completed`, `approved` |
| `videos.status` | `uploaded`, `processing`, `processed`, `published`, `rejected`, `failed` |

**Enforcement rules**:
- Any code path that changes `status` must go through the designated state machine Use Case. Direct `$model->status = 'approved'` calls outside the Use Case are prohibited.
- Invalid transitions (e.g., `received` → `accepted`, skipping review) must throw a domain exception, not silently succeed.

#### Consequences

- Status filtering queries (`WHERE status = 'approved'`) must be indexed. See Decision 19.
- The absence of DB-level ENUM constraints means the application layer is the sole enforcer of valid status values. This is intentional — it avoids schema migrations for new statuses.

---

### Decision 12: Translation Strategy

#### Context

The platform supports Arabic, English, and Spanish. All translatable content must be stored in a way that supports adding new languages without schema changes.

#### Decision

**Strategy**: Option C — Separate Translation Tables (one table per translatable entity).

**Pattern**:

```sql
-- Parent table (no translatable columns)
CREATE TABLE pages (
    id          CHAR(36)    NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE,
    status      VARCHAR(50)  NOT NULL DEFAULT 'draft',
    ...
    PRIMARY KEY (id)
);

-- Translation table
CREATE TABLE page_translations (
    id          CHAR(36)    NOT NULL,
    page_id     CHAR(36)    NOT NULL,
    locale      VARCHAR(10) NOT NULL,        -- FK → languages.code
    title       VARCHAR(500) NOT NULL,
    body        LONGTEXT     NOT NULL,
    meta_title  VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_page_translations_page_locale (page_id, locale),
    CONSTRAINT fk_page_translations_pages FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_page_translations_languages FOREIGN KEY (locale) REFERENCES languages(code) ON DELETE RESTRICT
);
```

**Naming convention**: `{parent_table_singular}_translations`

**Translation tables exist for**: `pages`, `announcements`, `faqs`, `countries`, `evaluation_criteria`, `notification_templates`, `judge_biographies`

**Fallback strategy**: If a translation does not exist for the requested locale, the platform falls back to `ar` (Arabic, the default) and includes `"content_locale_fallback": true` in the API response `meta`.

#### Rationale

- Adding a new language requires inserting new rows — no `ALTER TABLE`.
- Dedicated string columns carry full-text indexes (Meilisearch sync).
- Referential integrity via FK to `languages.code` prevents orphan translations.
- Laravel's `astrotomic/laravel-translatable` or equivalent package supports this pattern natively.

#### Consequences

- Every query for translatable content requires a JOIN to the translation table.
- The application layer must always specify a locale when querying translatable content.
- Translation tables have no `timestamps` columns — translations are versioned with their parent entity.

---

### PART IV — FILE & MEDIA

---

### Decision 13: Video Storage Architecture

#### Context

The platform's primary content is recitation videos uploaded by contestants. These videos go through a multi-stage processing pipeline (upload → FFmpeg → variants + thumbnails + HLS). Each stage produces files that have different access patterns, different CDN requirements, and different lifecycle policies.

#### Decision

**File taxonomy and storage path convention**:

```
videos/
├── originals/
│   └── {video_id}/
│       └── original.{ext}               ← raw upload; PRIVATE
│
├── processed/
│   └── {video_id}/
│       ├── 360p.mp4                     ← FFmpeg MP4; PRIVATE → PUBLIC on publish
│       ├── 720p.mp4
│       └── 1080p.mp4
│
├── thumbnails/
│   └── {video_id}/
│       ├── thumb.jpg                    ← 480×270; PUBLIC on publish
│       └── thumb-hd.jpg                 ← 1280×720
│
└── hls/
    └── {video_id}/
        ├── index.m3u8                   ← master playlist
        ├── 360p/
        │   ├── index.m3u8
        │   └── segment_000.ts
        ├── 720p/
        │   └── ...
        └── 1080p/
            └── ...
```

**URL Access Policy**:

| File Type | Before Publish | After Publish |
|---|---|---|
| Originals | Signed URL (TTL: 1 hour) | Signed URL (TTL: 1 hour) |
| Processed variants | Signed URL (TTL: 1 hour) | CDN URL (permanent) |
| Thumbnails | Signed URL | CDN URL (permanent) |
| HLS segments | Signed URL | CDN URL (`Cache-Control: max-age=31536000`) |

**Processing pipeline** (after each successful step, the video status advances):

```
1. Upload → media_assets + videos records inserted (status: uploaded)
2. VideoUploaded event dispatched → VideoProcessingJob queued
3. FFmpeg generates variants → video_variants records inserted
4. FFmpeg extracts thumbnails → video_thumbnails records inserted
5. HLS segments written to storage
6. videos.status → processed
7. VideoProcessingCompleted event dispatched
8. Admin reviews and publishes → videos.status → published
9. VideoPublished event dispatched → CDN cache warmed
```

#### Consequences

- The `videos` table never stores a path string directly. All storage metadata is in `media_assets` (see Decision 14).
- Original files are never made publicly accessible via CDN URL, regardless of video status.
- Reprocessing (from `failed` → `processing`) must clean up existing `video_variants` and `video_thumbnails` records (Hard Delete per Decision 6) before creating new ones.

---

### Decision 14: Media Ownership Convention

#### Context

Without a central file registry, storage metadata (disk, path, size, MIME type) is duplicated across multiple tables. This makes storage provider migration impossible and orphan file cleanup unreliable.

#### Decision

**The Rule**: Every binary file in the platform begins life as a `media_assets` record. No domain table stores a storage path, file size, MIME type, or disk identifier directly. All file-bearing entities reference `media_assets.id` via a named FK column.

**The `media_assets` table stores** (and domain tables must not duplicate):

```
disk              ← 'local' | 's3' | 'r2'
path              ← storage path relative to disk root
original_filename ← the filename as uploaded by the user
mime_type         ← 'video/mp4' | 'image/jpeg' | 'application/pdf'
file_size_bytes   ← for storage quota and API response
```

**FK column registry** (every file-bearing FK):

| Entity | Column | Points To |
|---|---|---|
| `videos` | `media_asset_id` | Original uploaded file |
| `video_variants` | `media_asset_id` | Transcoded file (one per quality/format) |
| `video_thumbnails` | `media_asset_id` | Extracted thumbnail image |
| `contestants` | `photo_media_id` | Profile photo |
| `judges` | `photo_media_id` | Profile photo |
| `contestant_documents` | `media_asset_id` | Identity document |
| `application_documents` | `media_asset_id` | Supporting document |
| `announcements` | `image_media_id` | Banner image |
| `sponsors` | `logo_media_id` | Sponsor logo |

**The polymorphic columns** (`morphable_type`, `morphable_id`) on `media_assets` exist for the orphan cleanup background job — which scans all `media_assets` records and verifies their owner still exists. They are not used for querying in v1.

#### Rationale

A central file registry enables: (1) finding all files uploaded by a user in one query, (2) running orphan cleanup without scanning every domain table, (3) migrating storage providers by updating disk/path in one table, (4) enforcing storage quotas without aggregating across tables.

#### Consequences

- Changing the storage disk for a file requires updating only `media_assets.disk` and `media_assets.path`.
- Domain code that needs a file URL always goes through `MediaAsset::url()` — never constructs a path string.
- The `MediaService` is a Platform Service (per ADR-002) consumed by all modules via the `MediaServiceContract`.

---

### PART V — CONCURRENCY & CONSISTENCY

---

### Decision 15: Transaction Boundaries

#### Context

Multiple entities in the platform must change atomically. The aggregate boundaries from the Entity Relationship Map define what must change within a single DB transaction. Events must be dispatched after the transaction commits — never inside it.

#### Decision

**The rule**: The aggregate boundary defines the transaction boundary. All writes within one aggregate are one transaction. Cross-aggregate coordination uses domain events dispatched after commit.

**Transaction boundary map** (binding):

| Operation | Inside Transaction (atomic) | After Commit |
|---|---|---|
| Application submission | `applications` INSERT + `application_documents` (N rows) INSERT + `application_status_histories` INSERT + `outbox_events` INSERT | `ApplicationReceived` |
| Application status change | `applications.status` UPDATE + `application_status_histories` INSERT + `outbox_events` INSERT | `ApplicationStatusChanged` |
| Application approval | `applications.status = accepted` UPDATE + `application_status_histories` INSERT + `outbox_events` INSERT | `ApplicationApproved` |
| Evaluation score save | `evaluations` UPDATE + `evaluation_scores` UPSERT (N rows) + `outbox_events` INSERT | `EvaluationScoresUpdated` |
| Evaluation submission | `evaluations.status = completed` UPDATE + `evaluation_scores` final UPSERT + `outbox_events` INSERT | `EvaluationSubmitted` |
| Stage results publish | `stage_results` INSERT + `stages.status = results_published` UPDATE + `outbox_events` INSERT | `StageResultsPublished` |
| Video upload | `media_assets` INSERT + `videos` INSERT + `outbox_events` INSERT | `VideoUploaded` |
| Video processing complete | `video_variants` INSERT (N) + `video_thumbnails` INSERT (N) + `videos.status = processed` UPDATE + `outbox_events` INSERT | `VideoProcessingCompleted` |
| Video publish | `videos.status = published` UPDATE + `outbox_events` INSERT | `VideoPublished` |
| Content publish | `pages/announcements.status = published` UPDATE + `outbox_events` INSERT | `ContentPublished` |
| Notification dispatch | `notifications` INSERT (in-app) | `NotificationDispatched` → email/SMS queue |

**The Outbox is written within the same transaction** as the business operation. See Decision 17.

#### Consequences

- No domain event may be dispatched inside a `DB::transaction()` block. Events are dispatched in the `after()` callback or equivalent post-commit hook.
- If the transaction rolls back, the `outbox_events` row is also rolled back — ensuring no stale event references data that was never persisted.

---

### Decision 16: Concurrency Policy

#### Context

Multiple entities in the platform are exposed to concurrent writes: judges evaluating the same application simultaneously, admins approving/rejecting at the same time, stream management, stage reordering.

#### Decision

**Available strategies**:

| Strategy | Mechanism | Laravel Implementation |
|---|---|---|
| **Optimistic Locking** | `version INTEGER` column; checked before update | Custom `WithOptimisticLocking` trait |
| **Pessimistic Locking** | `SELECT ... FOR UPDATE` row lock | `$query->lockForUpdate()` |
| **No Lock** | Standard `UPDATE WHERE id = ?` | Default Eloquent |

**Concurrency Policy Registry** (binding):

| Entity | Concurrent Operation | Strategy |
|---|---|---|
| `evaluations` | Multiple judges evaluating simultaneously | **Optimistic Locking** (version column) |
| `evaluation_scores` | Score writes within one judge's session | **Pessimistic Locking** (lock parent `evaluations` row) |
| `applications.status` | Admin state transitions | **Pessimistic Locking** (`lockForUpdate`) |
| `seasons.is_current` | Activating a season | **Pessimistic Locking** (lock all candidate rows) |
| `streams.status` | Start / stop stream | **Pessimistic Locking** (`lockForUpdate`) |
| `stages.order` | Admin reordering stages | **Pessimistic Locking** (lock all stage rows in season) |
| `contestants` | Profile update | **No Lock** |
| `pages`, `announcements`, `faqs` | CMS editing | **No Lock** |
| `settings` | Admin changes setting | **No Lock** |
| `notifications` | Dispatch | **No Lock** |

**Optimistic Locking implementation**: The `evaluations` table has a `version INT NOT NULL DEFAULT 0` column. The update uses:

```sql
UPDATE evaluations
SET status = ?, version = version + 1
WHERE id = ? AND version = ?
```

If 0 rows are affected, a `StaleEntityException` is thrown and the client must refresh and retry.

#### Consequences

- The `evaluations` table schema includes `version INT NOT NULL DEFAULT 0`.
- Pessimistic locking operations must have explicit timeout handling to prevent long-running locks.
- Deadlock detection must be implemented at the application layer for all Pessimistic Locking operations.

---

### Decision 17: Event Persistence — Transactional Outbox

#### Context

Domain events that trigger side effects (email notifications, search index updates, CDN cache invalidation) must be reliably delivered even when the queue (Redis) is temporarily unavailable at the moment of the business operation.

#### Decision

**Decision**: Implement the Transactional Outbox pattern. An `outbox_events` table is written within the same DB transaction as the business operation. A background worker polls for `pending` rows and dispatches them to the queue.

**Schema**:

```sql
CREATE TABLE outbox_events (
    id              CHAR(36)    NOT NULL,
    event_type      VARCHAR(255) NOT NULL,   -- 'ApplicationApproved'
    aggregate_type  VARCHAR(100) NOT NULL,   -- 'Application'
    aggregate_id    CHAR(36)    NOT NULL,
    payload         JSON        NOT NULL,    -- full event data snapshot
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|dispatched|failed|archived
    dispatched_at   TIMESTAMP   NULL,
    attempts        TINYINT     NOT NULL DEFAULT 0,
    created_at      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_outbox_events_status (status),
    KEY idx_outbox_events_created_at (created_at)
);
```

**Owner Module**: Core.

**Polling worker**: A Laravel command runs every 5 seconds in production (managed by Horizon), queries `WHERE status = 'pending' ORDER BY created_at LIMIT 100`, dispatches each to the appropriate Laravel queue job, and marks the row `dispatched`.

**Retry policy**: Up to 3 attempts. After 3 failures, `status = 'failed'`. Failed events trigger an alert.

**Replayability**: Any event can be replayed by resetting `status = 'pending'`. The `payload` JSON contains a complete data snapshot at the time of creation — it does not re-query the DB on replay.

**Retention**: Events retained for 30 days after `dispatched_at`. After 30 days, `status = 'archived'`. Archived events are eligible for purge by the ADR-007 backup process.

**Relationship to ADR-008**: This decision governs *how events are stored and reliably delivered*. ADR-008 governs *event naming, versioning, consumer registration, and DLQ policy*.

#### Consequences

- All business operations that produce domain events must write to `outbox_events` within the same transaction.
- The Outbox polling worker is a Core platform service — not a domain module responsibility.
- The `payload` JSON schema is the event's wire format. It must be backward-compatible per ADR-008's versioning policy.

---

### PART VI — QUERY & PERFORMANCE

---

### Decision 18: JSON Columns Policy

#### Context

JSON columns in MySQL offer schema flexibility but sacrifice indexability, referential integrity, and query clarity. Their use must be strictly governed.

#### Decision

**Permitted** (explicit approved list — any other use requires ADR amendment):

| Table | Column | Rationale |
|---|---|---|
| `notifications` | `data` | Stores the localized notification payload snapshot at dispatch time; shape varies by notification type |
| `stage_results` | `results_data` | Stores the ranked contestant list with aggregate scores; structure is stable but not normalized |
| `audit_logs` | `old_values`, `new_values` | Arbitrary changed-field snapshots; schema varies by auditable entity |
| `outbox_events` | `payload` | Event data snapshot; shape varies by event type |

**Prohibited uses**:

| Use Case | Required Alternative |
|---|---|
| Storing translatable content | Use translation tables (Decision 12) |
| Replacing a one-to-many relationship | Create the proper child table |
| Storing queryable/filterable attributes | Use typed columns with indexes |
| Storing state history | Use `application_status_histories` pattern |
| Avoiding a schema decision | Resolve the schema decision |

**Indexing JSON fields**: MySQL supports generated columns and indexes on JSON paths. This is permitted only for the `outbox_events.status` (which is a VARCHAR, not JSON) and similar non-JSON fields. JSON path indexes on `old_values` or `payload` are prohibited — if a field needs indexing, it must not be stored in JSON.

#### Consequences

- Any PR that introduces a JSON column outside the approved list is a blocking review comment.
- The approved JSON columns are exempt from the full normalization requirements of other columns.

---

### Decision 19: Index Policy

#### Context

Indexes must be defined explicitly — they do not appear automatically. Under-indexing causes slow queries; over-indexing causes slow writes. Both are defects.

#### Decision

**Mandatory indexes** (every migration must include these for every table where applicable):

| Pattern | Index |
|---|---|
| Every FK column | Non-unique index on FK column |
| Every `status` column on queryable tables | Non-unique index |
| Every `deleted_at` column | Non-unique index (WHERE NULL queries) |
| Every `created_at` / `submitted_at` on sortable tables | Non-unique index |
| Every translation table `(entity_id, locale)` | UNIQUE index |
| `users.email` | UNIQUE index |
| `contestants.user_id` | UNIQUE index |
| `judges.user_id` | UNIQUE index |
| `seasons.is_current` | Partial unique index (`WHERE is_current = TRUE`) |
| `outbox_events.status` | Non-unique index |

**Composite index guidelines**:
- The most selective column goes first in a composite index.
- A composite index `(a, b)` makes a single-column index on `a` redundant — do not create both.
- Create composite indexes for the most common filter + sort combinations (e.g., `(status, submitted_at)` on `applications`).

**Index naming**: Follow Decision 3 naming convention (`idx_{table}_{columns}`).

**Prohibited**:
- Full-text indexes in MySQL (`FULLTEXT`). All full-text search is handled by Meilisearch (see Decision 20).
- Indexes on JSON columns.
- Duplicate indexes (an index that is a prefix of another existing index).

#### Consequences

- Each new migration must include an index analysis section in its code review checklist.
- Missing indexes on FK columns and `status` columns are blocking review comments.

---

### Decision 20: Full-Text Search Policy

#### Context

MySQL provides `FULLTEXT` indexes for full-text search. The platform also has a dedicated Search Platform Service using Meilisearch.

#### Decision

**MySQL `FULLTEXT` indexes are prohibited.** All full-text search functionality is handled exclusively by Meilisearch via the Search Platform Service.

**Rationale**:
- MySQL full-text search is limited in Arabic language support and relevance ranking.
- Meilisearch provides typo tolerance, multi-language tokenization, and relevance ranking that MySQL cannot match.
- Splitting search between two engines (MySQL FULLTEXT and Meilisearch) creates a dual-maintenance burden with inconsistent results.
- The Search Platform Service provides a `SearchableContract` that all modules use — abstraction eliminates direct dependency on the search engine.

**What is indexed in Meilisearch** (per DATABASE-DOMAIN-INVENTORY Section 1):
`users`, `contestants`, `applications`, `judges`, `videos`, `announcements`, `countries`

**Synchronization**: Meilisearch indexes are updated via domain events (`ContestantUpdated`, `ApplicationStatusChanged`, etc.) dispatched through the Outbox. The Search Platform Service subscribes to these events.

#### Consequences

- No migration may include a `FULLTEXT` index constraint.
- Database queries may use `LIKE '%query%'` only for single-character prefix searches in low-volume admin lookups. They must never be used as a substitute for full-text search.

---

### Decision 21: Database Seeding Policy

#### Context

The platform requires reference data (languages, countries, roles, permissions, evaluation criteria) to be present in production. It also requires test data that must never reach production.

#### Decision

**Two seeder categories**:

| Category | Environment | Idempotent | Example |
|---|---|---|---|
| **Reference Seeders** | All (local, staging, production) | Yes — use `upsert` or `firstOrCreate` | `LanguageSeeder`, `CountrySeeder`, `RoleSeeder`, `PermissionSeeder`, `EvaluationCriteriaSeeder` |
| **Test Seeders** | Local and staging only | No — creates random data | `ContestantSeeder`, `ApplicationSeeder`, `VideoSeeder` |

**Rules**:
- Reference Seeders run in CI pipeline and on every production deployment.
- Test Seeders are guarded by `if (app()->isProduction()) { return; }`.
- Each module owns its reference seeders. Core owns Role and Permission seeders; Countries module owns CountrySeeder; etc.
- Reference Seeder data is version-controlled and treated as schema data.

#### Consequences

- Every new module that introduces reference data must include a Reference Seeder.
- The deployment pipeline calls `php artisan db:seed --class=ReferenceSeeder` after every migration run.

---

## Cross-Decision Matrix

The following table maps key inter-decision dependencies. When changing one decision, all decisions in its "Impacts" column must be reviewed.

| Decision | Key Rule | Depends On | Impacts |
|---|---|---|---|
| D1: Engine & Charset | utf8mb4, InnoDB, UTC | — | D8 (UTC), D16 (row locks) |
| D2: UUID v7 | All public PKs are UUID v7 | ADR-004 IDs | D3 (FK naming), D14 (MediaAsset IDs), D17 (Outbox aggregate_id) |
| D3: Naming | snake_case; canonical examples | D2 (UUID PKs) | D7 (FK names in constraints), D19 (Index names) |
| D4: Module Ownership | One module per table | ADR-002 | D7 (RESTRICT on cross-module FKs), D21 (module seeders) |
| D5: Season Isolation | Only 3 direct season_id columns | D4 (module ownership) | D15 (transaction boundary includes season anchor), D19 (indexes on season_id) |
| D6: Delete Policy | 5 categories per entity | D9 (soft delete), D10 (audit) | D7 (RESTRICT for Never Delete), D19 (indexes on deleted_at) |
| D7: Referential Integrity | CASCADE / RESTRICT / SET NULL | D4, D6 | D15 (hard-delete cascade scope), D16 (lock scope) |
| D9: Soft Deletes | deleted_at; SoftDeletes trait | D6, D7 | D19 (index on deleted_at) |
| D10: Audit Log | Append-only; AuditLoggerContract | D8 (timestamps), D18 (JSON) | ADR-008 (event correlation) |
| D11: State Machines | VARCHAR status; app-layer enforcement | — | D15 (state change in transaction), D16 (pessimistic lock on transitions), D19 (status indexes) |
| D12: Translation | Separate translation tables | D3 (naming), D7 (CASCADE) | D20 (no FULLTEXT; Meilisearch syncs from translation rows) |
| D13: Video Storage | 4-type file taxonomy; storage paths | D14 (MediaAsset) | ADR-006 (CDN, R2 bucket structure) |
| D14: Media Ownership | All files start as media_assets | D2 (UUID), D3 (FK naming), D6 (Soft Delete) | D13 (video files via media_assets), D7 (SET NULL on optional photo_media_id FKs) |
| D15: Transaction Boundaries | Aggregate = transaction; events after commit | D11 (state), D17 (Outbox in transaction) | D16 (lock scope matches transaction) |
| D16: Concurrency Policy | Per-entity Optimistic/Pessimistic/None | D1 (InnoDB row locks), D15 | `evaluations.version` column mandatory |
| D17: Outbox | outbox_events in same transaction | D15, D18 (JSON payload) | ADR-008 (event naming + DLQ) |
| D18: JSON Policy | Approved list; no JSON for relationships | — | D17 (Outbox payload is JSON), D10 (audit values are JSON) |
| D19: Index Policy | Mandatory indexes; no FULLTEXT | D3 (naming), D5 (season_id), D11 (status) | D20 (no FULLTEXT) |
| D20: Full-Text Search | Meilisearch only; no MySQL FULLTEXT | D19 | ADR-006 (Meilisearch configuration) |
| D21: Seeding | Reference vs. Test; module-owned | D4 (module ownership) | Production deployment pipeline |

---

## Database Naming — Canonical Examples

This section is the authoritative naming reference. When in doubt, match these examples exactly.

### Tables

```
users                                 ← plural, snake_case
contestants
applications
application_documents                 ← compound noun, plural
application_status_histories          ← compound + histories (not historys)
seasons
stages
stage_judge_assignments               ← pivot table, fully descriptive
stage_results
evaluations
evaluation_criteria                   ← criteria (not criterions)
evaluation_criterion_translations     ← singular criterion in translation table
evaluation_scores
videos
video_variants
video_thumbnails
media_assets
streams
stream_sources
pages
page_translations                     ← singular page
announcements
announcement_translations
faqs
faq_translations
countries
country_translations
judges
judge_biographies                     ← biographies (not biographys)
sponsors
notifications
notification_templates
notification_template_translations
outbox_events
audit_logs
languages
settings
roles
permissions
model_has_roles                       ← Spatie convention (keep as-is)
role_has_permissions                  ← Spatie convention (keep as-is)
```

### Primary Keys

```sql
`id` CHAR(36) NOT NULL              -- always and only 'id'
```

### Foreign Key Columns

```sql
-- Role-named (when FK column name differs from referenced table):
`reviewer_id`        CHAR(36) NULL   -- references users.id (reviewer role)
`assigned_by`        CHAR(36) NULL   -- references users.id (actor)
`published_by`       CHAR(36) NULL   -- references users.id (actor)
`changed_by`         CHAR(36) NULL   -- references users.id (actor)
`approved_by`        CHAR(36) NULL   -- references users.id (actor)
`photo_media_id`     CHAR(36) NULL   -- references media_assets.id (profile photo role)
`image_media_id`     CHAR(36) NULL   -- references media_assets.id (banner image role)
`logo_media_id`      CHAR(36) NULL   -- references media_assets.id (logo role)

-- Standard (table name singular + _id):
`contestant_id`      CHAR(36) NOT NULL
`season_id`          CHAR(36) NOT NULL
`stage_id`           CHAR(36) NOT NULL
`judge_id`           CHAR(36) NOT NULL
`application_id`     CHAR(36) NOT NULL
`evaluation_id`      CHAR(36) NOT NULL
`criterion_id`       CHAR(36) NOT NULL   -- evaluation_criteria → criterion
`video_id`           CHAR(36) NOT NULL
`media_asset_id`     CHAR(36) NOT NULL
`template_id`        CHAR(36) NOT NULL   -- notification_templates → template
`user_id`            CHAR(36) NOT NULL
`page_id`            CHAR(36) NOT NULL
`announcement_id`    CHAR(36) NOT NULL
`faq_id`             CHAR(36) NOT NULL
`country_id`         CHAR(36) NOT NULL
`sponsor_id`         CHAR(36) NOT NULL
`stream_id`          CHAR(36) NOT NULL
`language_id`        CHAR(36) NOT NULL
```

### Indexes

```sql
-- Standard index:
KEY `idx_applications_status` (`status`)
KEY `idx_applications_submitted_at` (`submitted_at`)
KEY `idx_applications_contestant_id` (`contestant_id`)
KEY `idx_applications_season_id` (`season_id`)
KEY `idx_applications_deleted_at` (`deleted_at`)
KEY `idx_videos_status` (`status`)
KEY `idx_videos_published_at` (`published_at`)
KEY `idx_evaluations_status` (`status`)
KEY `idx_evaluations_application_id` (`application_id`)
KEY `idx_notifications_user_id_read_at` (`user_id`, `read_at`)
KEY `idx_audit_logs_auditable` (`auditable_type`, `auditable_id`)
KEY `idx_audit_logs_created_at` (`created_at`)
KEY `idx_outbox_events_status` (`status`)

-- Unique index:
UNIQUE KEY `uk_users_email` (`email`)
UNIQUE KEY `uk_contestants_user_id` (`user_id`)
UNIQUE KEY `uk_judges_user_id` (`user_id`)
UNIQUE KEY `uk_applications_contestant_season` (`contestant_id`, `season_id`)
UNIQUE KEY `uk_evaluations_application_stage_judge` (`application_id`, `stage_id`, `judge_id`)
UNIQUE KEY `uk_evaluation_scores_evaluation_criterion` (`evaluation_id`, `criterion_id`)
UNIQUE KEY `uk_page_translations_page_locale` (`page_id`, `locale`)
UNIQUE KEY `uk_announcement_translations_locale` (`announcement_id`, `locale`)
UNIQUE KEY `uk_stage_judge_assignments` (`stage_id`, `judge_id`)
UNIQUE KEY `uk_stage_results_stage_id` (`stage_id`)
UNIQUE KEY `uk_seasons_year` (`year`)
```

### Foreign Key Constraints

```sql
-- Standard (fk_{table}_{referenced_table}):
CONSTRAINT `fk_contestants_users`          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_judges_users`               FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_applications_contestants`   FOREIGN KEY (`contestant_id`) REFERENCES `contestants`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_applications_seasons`       FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_stages_seasons`             FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_evaluations_applications`   FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_evaluations_judges`         FOREIGN KEY (`judge_id`) REFERENCES `judges`(`id`) ON DELETE RESTRICT
CONSTRAINT `fk_page_translations_pages`    FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
CONSTRAINT `fk_page_translations_languages` FOREIGN KEY (`locale`) REFERENCES `languages`(`code`) ON DELETE RESTRICT
CONSTRAINT `fk_evaluation_scores_evaluations` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations`(`id`) ON DELETE CASCADE

-- When multiple FKs reference same table (fk_{table}_{column}):
CONSTRAINT `fk_applications_reviewer`      FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
CONSTRAINT `fk_applications_video`         FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE SET NULL
CONSTRAINT `fk_contestants_photo`          FOREIGN KEY (`photo_media_id`) REFERENCES `media_assets`(`id`) ON DELETE SET NULL
CONSTRAINT `fk_judges_photo`               FOREIGN KEY (`photo_media_id`) REFERENCES `media_assets`(`id`) ON DELETE SET NULL
```

---

## Database Anti-Patterns (Prohibited Practices)

The following practices are **explicitly prohibited** in this platform. Enforcement is at code review. Any migration, model, or query that violates these rules is rejected without exception.

---

### ❌ Anti-Pattern 1: Shared Tables Between Modules

**Prohibited**: Two modules writing to the same table without explicit ownership assignment.

**Why**: Module isolation is the primary architectural guarantee of the Modular Monolith. Shared table ownership is the fastest path to a Big Ball of Mud.

**Correct alternative**: The owning module exposes a Service Contract. Other modules use the contract.

---

### ❌ Anti-Pattern 2: Direct Cross-Module Model Access

**Prohibited**: Module A using Module B's Eloquent model class directly in Module A's code.

```php
// ❌ PROHIBITED — Applications module accessing Contestant model directly
use Modules\Contestants\Models\Contestant;
$contestant = Contestant::find($id);
```

**Correct alternative**:
```php
// ✅ CORRECT — Applications module using ContestantServiceContract
use Modules\Contestants\Contracts\ContestantServiceContract;
$contestant = $this->contestantService->findById($id);
```

---

### ❌ Anti-Pattern 3: Business Logic in SQL

**Prohibited**: Encoding domain rules in SQL queries, views, or generated columns.

```sql
-- ❌ PROHIBITED: Calculating eligibility in SQL
ALTER TABLE contestants ADD COLUMN is_eligible TINYINT(1) AS (age > 14 AND is_active = 1) STORED;
```

**Why**: Business rules change. SQL-encoded rules bypass domain events, cannot be unit tested, and create hidden coupling between schema and business logic.

**Correct alternative**: Calculate eligibility in the Use Case or Domain Service.

---

### ❌ Anti-Pattern 4: Triggers for Business Rules

**Prohibited**: MySQL triggers that enforce business rules, dispatch events, or calculate derived values.

```sql
-- ❌ PROHIBITED
CREATE TRIGGER after_application_update
AFTER UPDATE ON applications
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' THEN
        INSERT INTO notifications (...);
    END IF;
END;
```

**Why**: Triggers are invisible to the application layer. They bypass domain events, cannot be traced in logs, cannot be unit tested, and cause unexpected behavior during migrations and bulk data operations.

**Correct alternative**: Application-layer event dispatch through the Outbox (Decision 17).

---

### ❌ Anti-Pattern 5: Stored Procedures for Domain Logic

**Prohibited**: MySQL stored procedures that implement any business workflow.

**Why**: Same as triggers — invisible, untestable, bypass the domain event system, and tightly couple business logic to the database engine.

**Correct alternative**: Use Case classes in the application layer.

---

### ❌ Anti-Pattern 6: Redundant Denormalized Columns

**Prohibited**: Duplicating data that can be derived through a join chain.

```sql
-- ❌ PROHIBITED: season_id on evaluations (reachable via application_id)
ALTER TABLE evaluations ADD COLUMN season_id CHAR(36);

-- ❌ PROHIBITED: contestant_name on applications (reachable via contestant_id → user_id)
ALTER TABLE applications ADD COLUMN contestant_name VARCHAR(255);
```

**Why**: Denormalized columns create two sources of truth that can diverge. When they diverge, the platform has no authoritative answer. (See Decision 5 Golden Rule.)

**Correct alternative**: Join through the normalized chain or use the owning module's service.

---

### ❌ Anti-Pattern 7: JSON as Relationship Substitute

**Prohibited**: Storing arrays of IDs or embedded objects in JSON columns to avoid creating a proper relationship table.

```sql
-- ❌ PROHIBITED: Storing judge IDs as JSON instead of a pivot table
ALTER TABLE stages ADD COLUMN judge_ids JSON;
-- e.g., ["uuid1", "uuid2", "uuid3"]
```

**Why**: JSON arrays cannot be enforced by FK constraints, cannot be indexed, cannot be joined efficiently, and break when a referenced entity is deleted.

**Correct alternative**: Create `stage_judge_assignments` as a proper pivot table.

---

### ❌ Anti-Pattern 8: Storage Paths in Domain Tables

**Prohibited**: Storing file storage paths, MIME types, file sizes, or disk identifiers directly in domain tables.

```sql
-- ❌ PROHIBITED
ALTER TABLE videos ADD COLUMN original_path VARCHAR(500);
ALTER TABLE videos ADD COLUMN file_size_bytes BIGINT;
ALTER TABLE videos ADD COLUMN mime_type VARCHAR(100);
```

**Why**: Violates the Media Ownership Convention (Decision 14). Storage metadata belongs in `media_assets`. Domain tables reference `media_assets.id` via a named FK.

**Correct alternative**: `media_asset_id CHAR(36) NOT NULL` FK → `media_assets.id`.

---

### ❌ Anti-Pattern 9: Status as Integer Enum

**Prohibited**: Using integers or MySQL ENUM types for status columns.

```sql
-- ❌ PROHIBITED
`status` TINYINT NOT NULL DEFAULT 0   -- 0=received, 1=reviewed...
`status` ENUM('received','under_review','accepted') NOT NULL  -- MySQL ENUM
```

**Why**: Integer statuses require a lookup table for human readability and make SQL queries unreadable. MySQL ENUM requires `ALTER TABLE` to add a new status value. (See Decision 11.)

**Correct alternative**: `VARCHAR(50) NOT NULL` with application-layer validation.

---

### ❌ Anti-Pattern 10: State Transitions Outside the State Machine Use Case

**Prohibited**: Direct model attribute assignment for status changes anywhere outside the designated state machine Use Case.

```php
// ❌ PROHIBITED — bypasses transition validation and history logging
$application->status = 'approved';
$application->save();
```

**Correct alternative**:
```php
// ✅ CORRECT — goes through the Use Case which validates transition and records history
$this->approveApplicationUseCase->execute($applicationId, $reviewerId);
```

---

## Implementation Constraints

These constraints are binding on all migrations, models, and repository implementations. Violations are blocking code review comments.

| # | Constraint |
|---|---|
| IC-01 | No migration may be created before the Migration Specification document for the affected module is reviewed and approved. |
| IC-02 | No table may exist without a declared owning module in the Module Table Ownership Map. |
| IC-03 | No migration may add a `season_id` column to a table not listed in Decision 5's Season-Scoped category. |
| IC-04 | No migration may use a delete policy (soft, hard, cascade, restrict) that differs from Decision 6's Delete Policy Registry for that entity. |
| IC-05 | No FK constraint may omit the `ON DELETE` rule. Every FK must be one of: CASCADE, RESTRICT, or SET NULL per Decision 7. |
| IC-06 | No JSON column may be added outside the approved list in Decision 18. |
| IC-07 | No `FULLTEXT` index may be created. |
| IC-08 | No migration may add a storage path, MIME type, file size, or disk identifier column to any table except `media_assets`. |
| IC-09 | No application code may change a `status` column directly outside the designated state machine Use Case for that entity. |
| IC-10 | No domain event may be dispatched inside a `DB::transaction()` block. Events are dispatched after commit. |
| IC-11 | No trigger, stored procedure, or view that encodes business logic may be created. |
| IC-12 | No cross-module Eloquent model reference. Use Service Contracts. |
| IC-13 | No UUID column may use auto-increment. UUID values are generated in the application layer. |
| IC-14 | No column naming that deviates from Decision 3's Canonical Examples without an ADR amendment. |
| IC-15 | No FK column on a mandatory relationship may be nullable. Nullable FKs are only permitted for optional relationships classified under Decision 7 Rule 5. |

---

## Future Evolution

This section distinguishes decisions that can evolve without a new ADR from decisions that require a formal ADR amendment.

### Can Change Without ADR Amendment (Additive/Non-Breaking)

- Adding new indexes to existing tables.
- Adding new optional nullable columns to existing tables (with zero default).
- Adding new rows to reference seeder data (new country, new language).
- Adding new status values to an existing state machine (since VARCHAR allows this).
- Adjusting TTL or retention periods within the same policy category.
- Selecting a different cloud storage provider disk (only `media_assets.disk` value changes).
- Adding new tables owned by a new module.
- Adding new entries to the Module Table Ownership Map.

### Requires a Formal ADR Amendment (Breaking or Constitutional)

| Potential Change | Why It Requires ADR |
|---|---|
| Changing from UUID v7 to a different ID strategy | Breaks ADR-004 API contract, affects all FK naming |
| Switching from translation tables to JSON columns | Reverses Decision 12; breaks all translation joins and Meilisearch sync |
| Removing the Outbox table | Reverses Decision 17; breaks event reliability guarantees |
| Changing an FK cascade rule | Reverses Decision 7; may cause silent data loss |
| Reclassifying an entity's season scope | Reverses Decision 5; may require data backfill |
| Reclassifying an entity's delete policy | Reverses Decision 6; may cause data loss |
| Adding a stored procedure for domain logic | Violates Anti-Pattern 5 |
| Allowing cross-module model access | Violates Principle 2 and ADR-002 |
| Changing the character set or collation | Reverses Decision 1; requires full table rebuild |

---

## Consequences

### Positive Consequences

- **Complete database predictability**: Every table's PKs, FKs, indexes, cascade rules, delete policy, and concurrency strategy are documented before the first migration is written. No schema surprise at code review time.
- **Zero naming debates**: Canonical examples in Decision 3 resolve every naming question before it becomes a team disagreement.
- **Reliable event delivery**: The Outbox pattern ensures no domain event is lost when the queue is temporarily unavailable.
- **Storage provider independence**: The Media Ownership Convention means changing from S3 to R2 to local disk requires updating `media_assets.disk` only — no domain table schema changes.
- **Season data integrity**: The Season Isolation Golden Rule means `applications.season_id` is always the single authoritative anchor — no possibility of denormalized `season_id` columns diverging.
- **Audit completeness**: The append-only Audit Log and `application_status_histories` ensure a complete, tamper-resistant history of all significant operations.
- **Developer onboarding efficiency**: A new developer can read this document and immediately know the schema conventions, prohibited patterns, and decision rationale without asking any team member.

### Negative Consequences / Trade-offs

- **Translation join overhead**: Every query for translatable content requires a JOIN to a translation table. This is the price of language extensibility without schema migrations.
- **Outbox polling latency**: Domain events are not dispatched instantly — the Outbox polling worker introduces a maximum 5-second delay between commit and event dispatch. This is acceptable for all current use cases but would not be acceptable for true real-time features.
- **UUID storage overhead**: UUID PKs consume ~9× more storage than integer PKs per row. On a platform of this scale (tens of thousands of rows, not billions), this overhead is negligible.
- **Pessimistic locking contention**: `SELECT FOR UPDATE` on `applications.status` means that under high concurrent load, admin users may experience brief waits. This is the correct trade-off for correctness.
- **Constitutional rigidity**: Many changes require a formal ADR amendment. This slows down ad-hoc schema changes — which is the intended behavior.

---

## Alternatives Considered

### Alternative 1: Auto-Increment Integer Primary Keys

**Rejected because**: Integer IDs are enumerable (security risk per ADR-004), require a mapping layer between DB IDs and API IDs, and cannot be known before the `INSERT` completes (needed for Outbox pre-commit event writes).

### Alternative 2: MySQL ENUM for Status Columns

**Rejected because**: Adding a new status value requires `ALTER TABLE` which locks the table in production. VARCHAR with application-layer validation is flexible without schema migrations.

### Alternative 3: MySQL FULLTEXT Indexes for Search

**Rejected because**: Arabic language tokenization support is poor in MySQL FULLTEXT. Meilisearch provides significantly better relevance ranking and typo tolerance for all three supported languages.

### Alternative 4: In-Memory Event Dispatch (No Outbox)

**Rejected because**: Redis unavailability during a business operation would silently drop domain events. The Outbox pattern guarantees at-least-once delivery even during queue downtime.

### Alternative 5: JSON Columns for Translatable Content

**Rejected because**: JSON fields cannot carry FULLTEXT or Meilisearch-sync-compatible indexes, cannot enforce referential integrity to the `languages` table, and make queries harder to read and optimize.

### Alternative 6: `season_id` on Every Operational Table

**Rejected because**: Denormalized `season_id` columns create two sources of truth that can diverge. The single-anchor model (`applications.season_id`) is the authoritative source for all season context.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-003: Authentication & Identity Architecture](./ADR-003-authentication-identity.md)
- [ADR-004: API Standards & Conventions](./ADR-004-api-standards-conventions.md)
- [DATABASE-DOMAIN-INVENTORY.md](../DATABASE-DOMAIN-INVENTORY.md)
- [ENTITY-RELATIONSHIP-MAP.md](../ENTITY-RELATIONSHIP-MAP.md)
- [ADR-ROADMAP.md](../ADR-ROADMAP.md)
- [MySQL 8.0 Reference — InnoDB Storage Engine](https://dev.mysql.com/doc/refman/8.0/en/innodb-storage-engine.html)
- [MySQL 8.0 Reference — Character Sets](https://dev.mysql.com/doc/refman/8.0/en/charset.html)
- [UUID v7 Draft RFC](https://www.ietf.org/archive/id/draft-peabody-dispatch-new-uuid-format-04.txt)
- [Martin Fowler — Transactional Outbox Pattern](https://martinfowler.com/articles/patterns-of-distributed-systems/outbox.html)
- [Vaughn Vernon — Implementing Domain-Driven Design](https://www.oreilly.com/library/view/implementing-domain-driven-design/9780133039900/)
- [Google — Database Schema Design Principles](https://cloud.google.com/spanner/docs/schema-design)
