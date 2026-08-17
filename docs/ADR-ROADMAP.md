# ADR Roadmap — Global Quran Competition Platform

| Field    | Value          |
|----------|----------------|
| **Date** | 2026-07-31     |
| **Owner**| Architecture Team — JRTV |

---

## Prerequisite Documents

| Document | Purpose | File |
|---|---|---|
| API Domain Inventory | Complete endpoint matrix — prerequisite for ADR-004 | [API-DOMAIN-INVENTORY.md](./API-DOMAIN-INVENTORY.md) |

---

## Accepted ADRs

| ID | Title | Status | File |
|---|---|---|---|
| ADR-001 | System Architecture | ✅ Accepted | [ADR-001](./adr/ADR-001-system-architecture.md) |
| ADR-002 | Modular Monolith & Module Boundaries | ✅ Accepted | [ADR-002](./adr/ADR-002-modular-monolith-module-boundaries.md) |
| ADR-003 | Authentication & Identity Architecture | ✅ Accepted | [ADR-003](./adr/ADR-003-authentication-identity.md) |
| ADR-004 | API Standards & Conventions | ✅ Accepted | [ADR-004](./adr/ADR-004-api-standards-conventions.md) |
| ADR-005 | Database Architecture | ✅ Accepted | [ADR-005](./adr/ADR-005-database-architecture.md) |
| ADR-006 | Infrastructure Architecture | ✅ Accepted | [ADR-006](./adr/ADR-006-infrastructure-architecture.md) |
| ADR-008 | Eventing & Domain Events Governance | ✅ Accepted | [ADR-008](./adr/ADR-008-eventing-domain-events.md) |
| ADR-009 | Development Standards & Code Quality | ✅ Accepted | [ADR-009](./adr/ADR-009-development-standards-code-quality.md) |
| ADR-010 | Competition Rules Engine Architecture | ✅ Accepted | [ADR-010](./adr/ADR-010-competition-rules-engine.md) |
| ADR-011 | Module Scaffolding & Code Generation | ✅ Accepted | [ADR-011](./adr/ADR-011-module-scaffolding-code-generation.md) |
| ADR-012 | Application Layer & Orchestration | ✅ Accepted | [ADR-012](./adr/ADR-012-application-layer-usecase-orchestration.md) |
| ADR-013 | Workflow & Process Orchestration | ✅ Accepted | [ADR-013](./adr/ADR-013-workflow-and-process-orchestration.md) |
| ADR-014 | Presentation Layer Architecture | ✅ Accepted | [ADR-014](./adr/ADR-014-presentation-layer-architecture.md) |
| ADR-015 | Identity & Access Architecture (RBAC) | ⏳ Proposed — 4 open questions for the board | [ADR-015](./adr/ADR-015-identity-and-access-architecture.md) |

---

## Planned ADRs

### ADR-004 — API Standards & Conventions
**Priority**: Critical — must be accepted before any API endpoint is implemented.

| Topic | Decision Areas |
|---|---|
| **Response Envelope** | Unified JSON structure: `data`, `meta`, `errors`, `message` |
| **Error Format** | HTTP status codes, error codes, machine-readable error bodies, field-level validation errors |
| **Validation** | Backend error schema; how Zod schemas on the frontend mirror backend validation |
| **Pagination** | Cursor vs. offset; standard page/per_page params; pagination metadata in `meta` |
| **Filtering** | Query parameter conventions: `filter[field]=value` vs. flat params |
| **Sorting** | `sort=field` and `sort=-field` (prefix for direction) conventions |
| **Search API** | How modules expose search endpoints; search query parameters; result structure |
| **Versioning** | `/api/v1/` prefix; version header strategy; deprecation policy |
| **Rate Limiting** | Per-endpoint limits; rate limit headers (`X-RateLimit-*`); 429 response format |
| **OpenAPI** | Annotation strategy; Swagger UI availability; schema generation workflow |
| **Resource Naming** | Plural nouns; kebab-case; nested resources vs. query params |
| **Idempotency** | Idempotency key header for critical write operations (application submission, evaluation approval) |
| **File Upload API** | Multipart vs. base64; chunked upload strategy; upload progress |
| **Streaming API** | Stream status endpoint; polling vs. WebSocket; stream token issuance |
| **Webhook Strategy** | Event notification contract for future third-party integrations |
| **Authentication Headers** | `Authorization: Bearer {token}` convention; token refresh flow |
| **Locale Header** | `Accept-Language` header handling; `lang` query param fallback |
| **CORS Policy** | Allowed origins per environment; preflight handling |

---

### Database Design Pipeline — Pre-ADR-005
**Priority**: Critical — this pipeline must be completed before ADR-005 is written.

> The database design follows a deliberate 6-step pipeline. Writing migrations before completing this pipeline is prohibited.

```
Step 1: Database Domain Inventory   docs/DATABASE-DOMAIN-INVENTORY.md  ✅
    ↓   (entities, not tables)
Step 2: Entity Relationship Map     docs/ENTITY-RELATIONSHIP-MAP.md    ✅
    ↓   (relationships, not schema)
Step 3: ADR-005                     docs/adr/ADR-005-database-architecture.md
    ↓   (governing decisions — all 7 supplemental decisions included)
Step 4: ERD (formal)                docs/ERD.md
    ↓   (formal diagram derived from ADR-005 decisions)
Step 5: Migration Specification     docs/MIGRATION-SPECIFICATION.md
    ↓   (per-table migration plan — NOT the Laravel PHP code)
Step 6: Migrations                  Each module's database/migrations/
```

> **Migration Specification** is a formal document that specifies every table, column, index, constraint, and FK in plain language — reviewed and approved before the first line of migration PHP code is written. It bridges ADR-005 (decisions) and the actual Eloquent migrations (implementation).

---

### ADR-005 — Database Architecture
**Priority**: High — must be accepted before any migration is written.

> **Prerequisite**: DATABASE-DOMAIN-INVENTORY.md and ENTITY-RELATIONSHIP-MAP.md must be complete and reviewed before this ADR is written.

| Topic | Decision Areas |
|---|---|
| **Engine & Version** | MySQL version, storage engine (InnoDB), character set (utf8mb4), collation |
| **Naming Conventions** | Table names, column names, foreign key naming, index naming |
| **Module Schema Ownership** | Each module owns its own tables; no cross-module table sharing |
| **Migration Strategy** | Module-owned migrations; migration ordering; zero-downtime migration policy |
| **Primary Key Strategy** | UUID v7 for all public-facing tables; justification; internal-only exception policy |
| **Season Isolation (Multi-Tenancy)** | Which tables carry `season_id` (operational); which are Global Reference Data with no season scope; explicit classification of every entity as Global, Season-Scoped, or Cross-Season — this decision cannot be undone after migrations are written |
| **Delete Policy Matrix** | Formal per-entity classification into five categories: Soft Delete, Hard Delete, Archive Only, Append Only, Never Delete — replaces the ad-hoc soft-delete flag with a governed policy |
| **Referential Integrity Policy** | FK enforcement at the DB layer vs. application layer; cascade rule decision for every relationship type: `ON DELETE CASCADE` (child has no existence without parent), `ON DELETE RESTRICT` (protect important children), `ON DELETE SET NULL` (optional FK references) |
| **Transaction Boundaries** | What database writes constitute a single atomic transaction (the aggregate boundary from ENTITY-RELATIONSHIP-MAP); what events are dispatched after the commit completes — making the pre-commit/post-commit boundary explicit in the architecture |
| **Concurrency Policy** | Per-entity concurrency strategy: Optimistic Locking (version column), Pessimistic Locking (SELECT FOR UPDATE / row lock), or No Lock — specified for every resource exposed to concurrent writes (evaluation scoring, application state transitions, stream management, stage reordering) |
| **Event Persistence (Outbox)** | Whether a transactional Outbox table is implemented to guarantee event delivery after DB commit; event retention period; whether events are replayable; relationship between this decision and ADR-008 |
| **Media Ownership Convention** | Every binary file starts as a `media_assets` record; all file-bearing entities reference `media_assets.id` via FK — no storage paths or file metadata are duplicated in domain tables; the `videos`, `video_variants`, and `video_thumbnails` tables reference `media_asset_id`, not raw path strings |
| **Soft Deletes** | Policy governed by the Delete Policy Matrix; `deleted_at` convention; excluded entities |
| **Timestamps** | `created_at`, `updated_at` on all tables; timezone handling (UTC always) |
| **Audit Log** | Schema for the audit log; indexing; retention policy; what constitutes an auditable event |
| **Status Columns & State Machines** | How application states are stored; state machine implementation approach; status history log pattern |
| **Translation Strategy** | Separate translation tables (Option C) — rationale confirmed in DATABASE-DOMAIN-INVENTORY |
| **Video Storage Architecture** | Storage path convention; CDN URL strategy; signed URL policy |
| **Polymorphic Relations** | Policy on morphable types; naming conventions; when to avoid |
| **JSON Columns** | When permitted; when prohibited; indexing JSON fields |
| **Full-Text Indexing** | MySQL full-text vs. Meilisearch; what is indexed where |
| **Indexes** | Indexing policy; composite indexes; covering index pattern; when NOT to index |
| **Database Seeding** | Reference data seeders; test data seeders; production seeder policy |
| **Read Replicas** | When to introduce; read/write connection separation in Laravel |

---

### ADR-006 — Infrastructure Architecture
**Priority**: High — must be accepted before any infrastructure provisioning or Docker Compose finalization.

> This ADR covers the **entire infrastructure layer** in a single, cohesive document — Redis, Cloudflare, R2, Queue, Horizon, Meilisearch, Docker, storage, cache invalidation, and CDN revalidation. A unified reference is preferable to distributing infrastructure decisions across multiple ADRs.

| Topic | Decision Areas |
|---|---|
| **Redis** | Role separation: cache vs. queue vs. session vs. pub/sub; Redis key strategy; Redis Cluster vs. single-node |
| **Queue Architecture** | Laravel Horizon; queue naming per priority (`default`, `media`, `notifications`, `reports`); worker scaling policy |
| **Cache Invalidation** | Global invalidation strategy; event-driven invalidation; TTL policy per entity type; cache warm-on-boot strategy |
| **Meilisearch** | Index naming; primary key configuration; ranking rules; filterable/sortable attributes; index rebuild strategy |
| **Object Storage** | Cloud storage provider; bucket structure per module; CDN integration; signed URL policy; storage lifecycle rules |
| **Cloudflare CDN** | Cache zones; cache-control headers; purge API integration; page rules; Workers (if applicable) |
| **CDN Cache Revalidation** | How domain events trigger CDN purge (e.g., `AnnouncementPublished` → purge public content cache) |
| **Docker Compose** | Service definitions; health checks; volume strategy; network isolation; environment variable management |
| **FFmpeg** | Container strategy for FFmpeg; queue isolation for video jobs; storage pipeline |
| **SSL & TLS** | Certificate management; HTTPS enforcement; HSTS policy |
| **Environment Parity** | Dev / Staging / Production environment configuration strategy |
| **Logging & Observability** | Structured log format; log aggregation; error tracking (Sentry or equivalent) |
| **Secrets Management** | Environment variables; `.env` convention; secrets in production |

---

### ADR-007 — Backup, Recovery & Disaster Recovery
**Priority**: Medium — must be accepted before production go-live.

> Explicitly required by the project proposal. Not a Docker configuration — a governance document.

| Topic | Decision Areas |
|---|---|
| **Backup Policy** | Backup frequency (full, incremental, differential); retention periods; what is backed up (DB, media, config) |
| **Restore Policy** | Restore procedure; RTO (Recovery Time Objective); RPO (Recovery Point Objective) |
| **Disaster Recovery Plan** | DR runbook; team responsibilities; communication protocol; failover strategy |
| **Media Backup** | Cloud storage versioning; cross-region replication policy for video assets |
| **Database Backup** | MySQL dump schedule; binary log backup; point-in-time restore capability |
| **Backup Verification** | Automated restore testing; checksum verification; backup integrity alerts |
| **Security of Backups** | Encryption at rest; access control on backup storage |

---

### ADR-008 — Eventing & Domain Events Governance
**Priority**: High — should be accepted before any module implements cross-module communication.

> ADR-002 established domain events as the primary inter-module communication mechanism and documented the official event catalogue. ADR-008 provides the full governance layer for event design, versioning, and reliability that ADR-002 intentionally deferred.

| Topic | Decision Areas |
|---|---|
| **Event Naming Standard** | Past-tense verb convention; `{Aggregate}{Verb}` pattern; prohibited naming styles |
| **Domain Events vs. Integration Events** | When to use in-process Laravel events vs. durable queued jobs vs. Redis Pub/Sub |
| **Event Payload Contract** | What an event may and may not carry; payload immutability; ID-only vs. full entity |
| **Event Versioning** | How events evolve over time; backward-compatible additions; breaking event changes |
| **Event Ordering** | Are ordered delivery guarantees required? When does ordering matter? |
| **Event Idempotency** | Listener idempotency; deduplication strategy; retry safety |
| **Outbox Pattern** | When to use transactional outbox for event reliability (event + DB write atomicity) |
| **Event Consumers** | How listeners are registered; who may subscribe to which events; ownership rules |
| **Cache Invalidation Events** | How domain events trigger cache invalidation via the Cache Platform Service |
| **Search Index Events** | How domain events trigger index updates via the Search Platform Service |
| **Notification Events** | Which domain events the Notifications module subscribes to and why |
| **Dead Letter Queue** | Failed event handling; retry strategy; alerting on event failure |
| **Event Logging & Tracing** | How event chains are traced for debugging; correlation IDs |
| **Testing Events** | How to assert events are dispatched in feature tests |

---

## ADR Dependency Graph

```
ADR-001 (System Architecture)
    │
    ├── ADR-002 (Module Boundaries) ✅   ← module map + event catalogue
    │       │
    │       ├── feeds → DATABASE-DOMAIN-INVENTORY (entity analysis)
    │       │               │
    │       │               └── feeds → ENTITY-RELATIONSHIP-MAP
    │       │                               │
    │       │                               └── feeds → ADR-005 (Database)
    │       │
    │       └── feeds → ADR-008 (Eventing governance)
    │
    ├── ADR-003 (Authentication) ✅      ← auth flows
    │
    ├── ADR-004 (API Standards) ✅       ← all endpoint contracts
    │       │
    │       └── prerequisite: API-DOMAIN-INVENTORY ✅
    │
    ├── ADR-005 (Database) ✅            ← all schema decisions
    │       │
    │       ├── prerequisite: DATABASE-DOMAIN-INVENTORY ✅
    │       ├── prerequisite: ENTITY-RELATIONSHIP-MAP ✅
    │       └── produces → ERD (formal) → Migrations
    │
    ├── ADR-006 (Infrastructure)         ← Redis, CDN, Queue, Storage, FFmpeg
    │       │
    │       └── ADR-005 Video Storage decision feeds into ADR-006
    │
    ├── ADR-007 (Backup & Recovery)      ← operational resilience
    │
    └── ADR-008 (Eventing)               ← cross-module event contracts
```

---

## Implementation Order

| # | Phase | Artifact | Status |
|---|---|---|---|
| 1 | **Project Proposal** | `docs/project-proposal.docx` | ✅ Complete |
| 2 | **Architecture Foundation** | ADR-001 · ADR-002 · ADR-003 | ✅ Complete |
| 3 | **API Analysis** | `API-DOMAIN-INVENTORY.md` | ✅ Complete |
| 4 | **API Contract** | ADR-004 | ✅ Complete |
| 5 | **Database Analysis** | `DATABASE-DOMAIN-INVENTORY.md` | ✅ Complete |
| 6 | **Entity Relationships** | `ENTITY-RELATIONSHIP-MAP.md` | ✅ Complete |
| 7 | **Database Governance** | ADR-005 (incl. 7 new sections) | ✅ Complete |
| 8 | **Formal ERD** | `ERD.md` | ✅ Complete |
| 9 | **Migration Specification** | `MIGRATION-SPECIFICATION.md` | ✅ Complete |
| 10 | **Infrastructure Governance** | ADR-006 (Deployment, CDN, Storage, Queues, Packages) | ✅ Complete |
| 11 | **Event Governance** | ADR-008 (Outbox, Events, DLQ) | ✅ Complete |
| 12 | **Code Standards & Quality** | ADR-009 (Pest, Larastan L8, Pint, Rector, DoD) | ✅ Complete |
| 13 | **Competition Rules Engine** | ADR-010 (Rubrics, Tie-Break, Quorum, Appeals) | ✅ Complete |
| 14 | **Module Scaffolding Specification** | ADR-011 (Canonical Layout, Stubs, Generators) | ✅ Complete |
| 15 | **Application Layer & Orchestration** | ADR-012 (Use Case Lifecycle, Transactions, Outbox Co-location) | ✅ Complete |
| 15A | **Platform Foundation & Scaffolding** | `ModuleDiscovery`, `ModuleRegistry`, 5 Artisan tools, 15 Modules created | ✅ Complete |
| 15B | **Database Migrations** | 42 Migration files created & verified via `migrate:fresh` (41 tables) | ✅ Complete |
| 15C.1 | **Domain Foundation & Core Module** | Clean Core Domain (User, Role, Value Objects, Use Cases, Repositories) | ✅ Complete |
| 15C.2 | **Countries Reference Module** | Reference Data Aggregate, Value Objects, Specs, Seeder, Option C Translations | ✅ Complete |
| 15C.3 | **Competition Engine Core** | 4 Aggregates (Season, Stage, EvalTemplate, Rules), RuleEngineContract | ✅ Complete |
| 15C.4 | **Contestants & Judges Profiles** | Contestant & Judge Profiles (Pure Profile Aggregates & Repositories) | ✅ Complete |
| 15C.5 | **Applications Workflow & StateMachine** | Application Aggregate, ApplicationStateMachine, Re-upload Workflow | ✅ Complete |
| 15C.6 | **Evaluations & Scoring Engine** | DOM-EVAL-002 Design (4 Aggregates, Pure RankingService, TieBreakerStrategy, AllJudgesCompleted) | ✅ Complete |
| 15C.7 | **Notifications & Event Subscribers** | NotificationLog Aggregate, Multi-channel (Email, SMS, Push), Event Consumers | ✅ Complete |
| 15C.8 | **Media Assets & Storage Engine** | MediaAsset Aggregate, Private/Public Cloudflare R2 Disks, SHA-256 Deduplication | ✅ Complete |
| 15C.9 | **Videos & HLS Transcoding Engine** | Video Aggregate, FFmpeg HLS Master Playlist & Adaptive Bitrate Variants (ADR-006) | ✅ Complete |
| 15C.10| **Streaming Engine & Failover** | Stream Aggregate, Primary & Backup RTMP/HLS Source Failover | ✅ Complete |
| 15C.11| **Master API Implementation Matrix** | API-MAT-001 Matrix mapping all 15 module endpoints to UseCases, DTOs, Policies | ✅ Complete |
| 15D  | **Platform Bootstrap (Docker Runtime)**| `docker-compose.yml`, PHP 8.4-FPM, FFmpeg, Nginx, MySQL, Redis, Horizon, Mailpit, Health | ✅ Complete |
| 15E  | **Platform Readiness Gate (PRG-001)**  | 10 Verification Checkpoints (Docker, Cache, Horizon, FFmpeg, Health, Memory) | ✅ Passed (100%) |
| 16.1 | **Core Module Presentation API**       | Register, Login, Refresh, Sanctum Token, GET/PATCH /me, Languages, Settings | ✅ Complete |
| 16.2 | **Countries Module Presentation API**  | Canonical Reference Data API (Pagination, Search, ETag 304, Deactivation) | ✅ Complete |
| 16.3 | **Competition Module Presentation API**| Seasons, Stages, Dynamic Rules, Preview Results, Dry-Run Simulation APIs | ✅ Complete |
| 16.4 | **Contestants Module Presentation API** | Contestant Profiles, Completeness Service, Age Eligibility & Demographics APIs | ✅ Complete |
| 16.5 | **Judges Module Presentation API**      | Judge Profiles, Specialization & Panel Assignment APIs | ✅ Complete |
| 16.6 | **Applications Module Presentation API**| Contestant Submissions, Review Queue (`/content/review-queue`) & Re-upload Request Workflows | ✅ Complete |
| 16.7 | **Evaluations Module Presentation API** | Evaluation Scoring, 5-Judge Quorum, Ranking & Appeals APIs | ⏳ Next |
| IRG-001 | **System Integration Readiness Gate** | 8 Pillar cross-module integration gate — 67 tests / 1,334 assertions — 100% passing | ✅ Cleared |
| 17   | **OpenAPI 3.1 Specification Export**   | Auto-generated OpenAPI 3.1 (`api.json`) via Scramble (`scramble:export`) | ✅ Generated |
| 17 | **Production Readiness** | ADR-007 (Backup & Disaster Recovery) | ⏳ Before go-live |
