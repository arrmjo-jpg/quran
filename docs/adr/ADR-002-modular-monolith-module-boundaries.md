# ADR-002: Modular Monolith & Module Boundaries

| Field        | Value                                                                          |
|--------------|--------------------------------------------------------------------------------|
| **ID**       | ADR-002                                                                        |
| **Date**     | 2026-07-31                                                                     |
| **Authors**  | Platform Architecture Team                                                     |
| **Status**   | Accepted                                                                       |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership               |
| **Related**  | ADR-001 (System Architecture), ADR-003 (Authentication & Identity Architecture) |

---

## Status

**Accepted**

---

## Context

ADR-001 established the **Modular Monolith** as the governing architectural style for the backend of the Global Quran Competition Platform. It declared that every business capability is encapsulated within an independent, domain-oriented module, and that modules communicate exclusively through contracts, public services, and domain events.

ADR-001 did not, however, define the module system itself — the precise module map, module boundaries, inter-module dependency rules, the internal structure of each module, or the mechanisms by which modules are registered, isolated, and governed. That gap is the subject of this document.

This ADR serves as the **definitive module specification** for the entire platform backend. It governs every module that will ever be added, modified, or removed, and it is expected to remain the authoritative reference for the structural integrity of the system throughout its multi-year operational lifetime.

### Source Documents

This ADR was authored from three primary sources:

1. **Project Proposal** submitted to Jordan Radio and Television Corporation (JRTV) — the business source of truth, written in Arabic, covering: contestant registration, application management, judging workflows, competition stage management, live streaming, the public website, the administration dashboard, reporting, and technical requirements.
2. **ADR-001** — which establishes the Modular Monolith, Clean Architecture, API-First, Two-Level Authorization, i18n, and Docker-First decisions.
3. **ADR-003** — which establishes the two-tier authentication architecture (social OAuth for contestants, email/password for administrators) and its security boundaries.

This document must not contradict any decision made in ADR-001 or ADR-003.

### The Architectural Problem

Without an explicit, enforced module system, a Laravel application naturally drifts toward a flat `app/` namespace where business logic becomes entangled, domain boundaries collapse, and the codebase becomes progressively more expensive to understand, modify, and test. For a platform designed to operate across multiple competition seasons — accumulating features, changing requirements, and onboarding new development teams — this drift is an existential risk.

The platform requires a module system that:

- Enforces domain isolation as a structural property, not a convention.
- Allows modules to be developed and tested in parallel by independent teams.
- Preserves the ability to extract any module into a standalone service without a rewrite.
- Makes dependency direction visible and verifiable.
- Ensures that adding a module never requires modifying a central registry.

---

## Phase 1: Business Domain Analysis

The project proposal identifies eight explicit platform components. The following analysis maps each proposal section to business domains and applies bounded context reasoning to determine the correct module topology.

### Proposal Section Analysis

| Proposal Section (Arabic) | English Description | Business Domain |
|---|---|---|
| الموقع الإلكتروني | Public website — home, about, rules, judges, news, FAQ, sponsors, video gallery, results, live stream, contact | Content (Pages, News, Announcements) + Sponsors + Public Video Gallery |
| نظام تسجيل المشاركين | Contestant registration — account, profile, application form, document upload, recitation video, status tracking | Contestants + Applications + Media |
| إدارة الطلبات | Application lifecycle management — statuses, review, evaluation, approval, rejection, re-upload, notifications | Applications |
| نظام التحكيم | Judging system — video review, scoring rubric, notes, computed scores, result approval | Evaluations + Judges |
| إدارة مراحل المسابقة | Competition stage management — Stage 1/2, Semi-Finals, Finals, scheduling, judge assignment, results | Competition (Seasons + Stages) |
| لوحة الإدارة | Administration dashboard — users, permissions, contestants, judges, news, pages, videos, live streaming, sponsors, notifications, seasons, reports, settings | Core + all domain modules |
| البث المباشر | Live streaming — Jordan TV channel, stream source management, protocols | Streaming |
| التقارير والإحصائيات | Reports & statistics — participation counts, countries, acceptance rates, judge performance, viewership, historical archives | Reports |

### Bounded Context Decisions

**Why Applications and Contestants are separate modules:**
The proposal distinguishes between *who the contestant is* (identity, profile, personal data, country) and *what they submitted* (the application, its lifecycle, its documents, its recitation video). These are distinct bounded contexts with different ownership, different lifecycles, and different actors. A contestant exists independently of any application; an application belongs to a contestant but has its own autonomous state machine.

**Why Evaluations and Competition are separate modules:**
Competition manages the structural and temporal dimensions of the contest (seasons, stages, schedules, judge assignments). Evaluations manage the scoring activity itself (rubric, scores, notes, result approval). These serve different actors at different times: Competition is managed by Competition Managers before and during stages; Evaluations are exercised by Judges during active evaluation periods. Merging them would couple scheduling logic with scoring logic — two concerns that change for entirely different reasons.

**Why Media is a standalone module:**
Media (file upload, storage, retrieval, cloud integration) is a cross-cutting infrastructure concern used by Applications (document uploads), Videos (recitation recordings), and the public website (images). It must not be owned by any single business domain. It is a platform service depended upon by multiple modules.

**Why Videos is separate from Media:**
Media handles generic file storage. Videos handles video-specific business logic: FFmpeg processing, transcoding, format conversion, thumbnail generation, playback, and the video gallery. A recitation video has a lifecycle — uploaded, processing, processed, published, rejected — that Media knows nothing about. Videos consumes Media's storage capabilities but owns the video domain entirely.

**Why Content (Pages, Announcements, FAQ) is a separate module:**
The public website contains static and semi-static content — competition rules, organizational messages, announcements, frequently asked questions — that has its own management lifecycle, is authored by Content Managers, and is displayed publicly without authentication. This is not the same domain as contestants, applications, or competition management. Merging it with any other domain would create incorrect coupling.

**Why News is excluded:**
ADR-001 explicitly excludes news-related AlphaCMS modules. However, the proposal mentions "أخبار وإعلانات" (News and Announcements). The implementation of this feature is scoped to a platform-owned **Announcements** sub-concern inside the Content module, not reusing AlphaCMS news internals.

**Why Sponsors is its own module:**
Sponsors have their own data model (name, logo, tier, URL, display order), their own management lifecycle (creation, activation, archival), and they appear in a dedicated public section of the website. They are not related to contestants, applications, or competition logic. Merging them into Content would conflate editorial content with partnership management.

**Why Reports is a standalone module:**
Reports aggregate data from multiple source modules (Applications, Evaluations, Competition, Contestants). If Reports were placed inside any one of those modules, it would need to directly access the internals of all the others — violating module isolation. A standalone Reports module consumes only public contracts and read models from its dependency modules.

**Why Countries is a standalone module:**
The proposal explicitly requires participation statistics broken down by country. Countries are a reference data domain consumed by Contestants (nationality/residence), Applications (country of submission), Reports (country-based aggregation), and potentially Judges. A standalone Countries module provides a clean, authoritative reference that all other modules depend on without creating circular dependencies.

**Why Settings is a standalone module:**
System-wide configuration (platform name, competition email address, maintenance mode, feature flags, and similar parameters) does not belong to any business domain. It is a cross-cutting administrative concern owned by the Core module cluster.

---

## Phase 2: Official Module Map

### Module Topology Overview

The platform backend is organized into five tiers:

```
┌──────────────────────────────────────────────────────────────────────┐
│                          SHARED KERNEL                               │
│          Traits · Interfaces · Enums · Base Classes                  │
│          Helpers · Value Objects · Support Utilities                 │
└──────────────────────────────┬───────────────────────────────────────┘
                               │ all modules depend on Shared
┌──────────────────────────────▼───────────────────────────────────────┐
│                           CORE MODULE                                │
│    Auth · Users · Roles · Permissions · Profile · Settings           │
│    Audit · Languages                                                 │
└───────┬───────────────────────────────────────────────────┬──────────┘
        │ all platform services and domain modules          │
        │ depend on Core                                    │
┌───────▼───────────────────────────────────────────────────▼─────────┐
│                       PLATFORM SERVICES                             │
│                                                                     │
│  Media · Videos · Streaming · Notifications · Search · Cache        │
│                                                                     │
│  Stable, versioned infrastructure services with explicit contracts. │
│  No business logic. Domain modules depend on these services only    │
│  through published contracts — never concrete implementations.      │
└───────┬─────────────────────────────────────────────────────────────┘
        │ domain modules depend on platform services
┌───────▼─────────────────────────────────────────────────────────────┐
│                        DOMAIN MODULES                               │
│                                                                     │
│  Countries  │  Contestants  │  Applications  │  Competition         │
│  Judges     │  Evaluations  │  Content       │  Sponsors            │
│  Reports                                                            │
└─────────────────────────────────────────────────────────────────────┘
```

> **Tier Rationale — Platform Services vs. Domain Modules**
>
> The distinction between a Platform Service and a Domain Module is not arbitrary. A **Platform Service** is a stable, general-purpose technical capability that multiple business domains depend upon. It contains no business rules. Its interface changes rarely. A **Domain Module** contains business rules, evolves with business requirements, and is owned by a specific bounded context. Placing Search and Cache in the Platform Services tier makes their role explicit: they are infrastructure that serves business logic — they do not contain it.

### Module Registry

---

#### MODULE: Shared (Shared Kernel)

| Field | Detail |
|---|---|
| **Tier** | Shared Kernel |
| **Purpose** | Provides cross-cutting utilities, base abstractions, and common infrastructure used by every module in the system. Contains no business logic whatsoever. |
| **Owns** | Base service contracts, base repository interfaces, value object base classes, common traits (HasUuid, HasTimestamps, SoftDeletable, Searchable), shared enums (Status, Direction, SortOrder), helper functions, abstract base classes |
| **Does NOT own** | Any business entity, any domain rule, any API endpoint, any database table |
| **Public Contracts** | All interfaces defined here are public by definition |
| **Dependencies** | None |
| **Events Published** | None |
| **Events Consumed** | None |

---

#### MODULE: Core

| Field | Detail |
|---|---|
| **Tier** | Core |
| **Purpose** | Provides the foundational identity, authorization, and platform configuration infrastructure that every other module in the system depends upon. |
| **Owns** | User entity, authentication flows (via AlphaCMS), roles, permissions (via Spatie), profile management, system settings, audit logging, language registry |
| **Does NOT own** | Any competition-specific business logic, any contestant-specific attributes beyond identity |
| **Public Contracts** | `UserRepositoryContract`, `PermissionRegistrarContract`, `AuditLoggerContract`, `SettingsRepositoryContract`, `LanguageRepositoryContract` |
| **Dependencies** | Shared |
| **Events Published** | `UserCreated`, `UserDeactivated`, `UserRoleAssigned`, `PasswordResetRequested`, `AccountVerified` |
| **Events Consumed** | None |

---

#### MODULE: Countries

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Provides a canonical, authoritative reference of world countries, used as a lookup by Contestants, Applications, Judges, and Reports. |
| **Owns** | Country entity (name, ISO code, flag, region, active status), country management CRUD, country seeder |
| **Does NOT own** | Contestant nationality data, application country attribution |
| **Public Contracts** | `CountryRepositoryContract` |
| **Dependencies** | Core, Shared |
| **Events Published** | None |
| **Events Consumed** | None |

---

#### MODULE: Media

| Field | Detail |
|---|---|
| **Tier** | Infrastructure |
| **Purpose** | Provides a unified, storage-agnostic file management service — upload, retrieval, deletion, and metadata management — consumed by all modules that handle file assets. |
| **Owns** | Media entity, file upload pipeline, cloud storage abstraction, file type validation, media collections, file metadata |
| **Does NOT own** | Video processing logic, application document semantics, contestant profile photos beyond storage |
| **Public Contracts** | `MediaRepositoryContract`, `FileStorageContract`, `MediaUploaderContract` |
| **Dependencies** | Core, Shared |
| **Events Published** | `MediaUploaded`, `MediaDeleted` |
| **Events Consumed** | None |

---

#### MODULE: Videos

| Field | Detail |
|---|---|
| **Tier** | Infrastructure |
| **Purpose** | Manages the full lifecycle of video assets — from upload through FFmpeg processing, format transcoding, thumbnail generation, and public gallery publication. |
| **Owns** | Video entity, processing pipeline (FFmpeg), transcoding jobs, processing status, thumbnail generation, public video gallery, video playback metadata |
| **Does NOT own** | Live stream management, generic file storage (delegated to Media), evaluation scoring of videos |
| **Public Contracts** | `VideoRepositoryContract`, `VideoProcessorContract`, `VideoGalleryContract` |
| **Dependencies** | Core, Shared, Media |
| **Events Published** | `VideoUploaded`, `VideoProcessingStarted`, `VideoProcessingCompleted`, `VideoProcessingFailed`, `VideoPublished`, `VideoRejected` |
| **Events Consumed** | `MediaUploaded` |

---

#### MODULE: Streaming

| Field | Detail |
|---|---|
| **Tier** | Infrastructure |
| **Purpose** | Manages the live broadcast stream for Jordan TV during competition stages and finals, including stream source configuration, protocol management, and public display. |
| **Owns** | Stream configuration entity, stream source management, protocol settings (RTMP, HLS, DASH), stream status, admin controls, public live stream page data |
| **Does NOT own** | Video file management, stage scheduling, competition results |
| **Public Contracts** | `StreamRepositoryContract`, `StreamStatusContract` |
| **Dependencies** | Core, Shared |
| **Events Published** | `StreamStarted`, `StreamStopped`, `StreamSourceChanged` |
| **Events Consumed** | None |

---

#### MODULE: Notifications

| Field | Detail |
|---|---|
| **Tier** | Platform Service |
| **Purpose** | Provides a centralized, multi-channel notification dispatch service. Consumes domain events from other modules and dispatches notifications through appropriate channels. |
| **Owns** | Notification templates, notification channel configuration (email, SMS, push, in-app), notification delivery queue, notification preferences, notification log |
| **Does NOT own** | The business events that trigger notifications (those belong to the originating modules) |
| **Public Contracts** | `NotificationDispatcherContract`, `NotificationTemplateContract` |
| **Dependencies** | Core, Shared |
| **Events Published** | `NotificationSent`, `NotificationFailed` |
| **Events Consumed** | `ApplicationReceived`, `ApplicationApproved`, `ApplicationRejected`, `ApplicationNeedsData`, `VideoReUploadRequested`, `EvaluationCompleted`, `StageResultsPublished`, `UserCreated`, `AccountVerified` |

---

#### MODULE: Search

| Field | Detail |
|---|---|
| **Tier** | Platform Service |
| **Purpose** | Provides a unified, contract-driven search infrastructure over Meilisearch. Manages index definitions, index lifecycle, and the query surface for all domain modules that expose searchable entities. No module communicates with Meilisearch directly — all search capability is accessed through Search's published contracts. |
| **Owns** | Search index registry, `SearchableContract` (interface modules implement to declare indexable entities), index configuration per entity type, index maintenance jobs (rebuild, flush, partial update), Meilisearch connection abstraction, search query abstraction, search result pagination |
| **Does NOT own** | The data being indexed (owned by originating modules), business-level result ranking or filtering logic (owned by consuming modules via query DTOs) |
| **Public Contracts** | `SearchIndexContract`, `SearchQueryContract`, `SearchableContract`, `IndexRegistrarContract` |
| **Dependencies** | Core, Shared |
| **Events Published** | `SearchIndexUpdated`, `SearchIndexRebuilt`, `SearchIndexCleared`, `SearchIndexFailed` |
| **Events Consumed** | Any domain module may register its entity create/update/delete events with the Search module's index listeners. Search listens to these events and triggers incremental index updates asynchronously via queued jobs. |

> **Search Index Strategy**
>
> Each domain module that requires search capability implements `SearchableContract` and registers its entity with the Search module's `IndexRegistrar` in its own service provider. The Search module maintains no knowledge of specific domain entities — it knows only that certain contracts are registered and what index configuration they declare. This preserves the dependency direction: domain modules depend on Search, not the reverse.

---

#### MODULE: Cache

| Field | Detail |
|---|---|
| **Tier** | Platform Service |
| **Purpose** | Provides a unified cache abstraction over Redis — with module-namespaced cache keys, configurable TTL management, a structured cache invalidation strategy, and cache warming job infrastructure. Makes caching policy visible, governable, and testable across the entire platform. |
| **Owns** | Cache key namespacing conventions (`{module}:{entity}:{id}`), `CacheRepository` abstraction, cache invalidation contracts, cache warming job registry, cache statistics collection |
| **Does NOT own** | Redis server configuration (infrastructure/operations concern covered in ADR-006), the data being cached (owned by originating modules) |
| **Public Contracts** | `CacheRepositoryContract`, `CacheInvalidatorContract`, `CacheWarmerContract` |
| **Dependencies** | Core, Shared |
| **Events Published** | `CacheWarmed`, `CacheInvalidated` |
| **Events Consumed** | Domain modules register cache invalidation listeners against their own domain events. Example: the Applications module registers a listener that invalidates the application list cache when `ApplicationApproved` is dispatched. |

> **Cache Key Namespacing**
>
> All cache keys follow the convention `{module}:{entity}:{identifier}:{variant}`. Example: `applications:list:approved:page-1`. This prevents key collision across modules and makes cache analysis and selective invalidation deterministic. No module may use a cache key outside its own namespace.

---

#### MODULE: Contestants

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Manages the contestant identity and profile — who the contestant is, their personal information, nationality, and biographical data. Distinct from what they submit (Applications). |
| **Owns** | Contestant profile entity, personal data (name, date of birth, nationality, passport, photo), social provider linkage, contestant portal profile management |
| **Does NOT own** | Application forms, application lifecycle, scoring, judging assignments |
| **Public Contracts** | `ContestantRepositoryContract`, `ContestantProfileContract` |
| **Dependencies** | Core, Shared, Countries, Media |
| **Events Published** | `ContestantRegistered`, `ContestantProfileCompleted`, `ContestantProfileUpdated` |
| **Events Consumed** | `UserCreated` |

---

#### MODULE: Applications

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Governs the complete lifecycle of a competition application — from initial submission through review, evaluation assignment, approval or rejection, and all intermediate states. This is the primary operational domain of the platform. |
| **Owns** | Application entity, application state machine (Received → Under Review → Under Evaluation → Accepted / Rejected / Needs Data / Re-upload Video), document attachments, application recitation video reference, status history, reviewer assignments, automated notification triggers |
| **Does NOT own** | Contestant identity data, scoring logic, judge identity, competition stage assignment |
| **Public Contracts** | `ApplicationRepositoryContract`, `ApplicationStatusContract`, `ApplicationReviewContract` |
| **Dependencies** | Core, Shared, Contestants, Media, Videos |
| **Events Published** | `ApplicationReceived`, `ApplicationUnderReview`, `ApplicationUnderEvaluation`, `ApplicationApproved`, `ApplicationRejected`, `ApplicationNeedsData`, `VideoReUploadRequested` |
| **Events Consumed** | `VideoProcessingCompleted` |

---

#### MODULE: Judges

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Manages the identity, profile, and panel membership of competition judges. A Judge is an administrative user (`type=admin`) with the Judge role — this module maintains the judge-specific profile and panel assignments on top of the Core identity. |
| **Owns** | Judge profile entity, panel membership, specialization, biography, judge assignment to competition stages |
| **Does NOT own** | Evaluation scores (owned by Evaluations), user authentication (owned by Core/Auth), stage scheduling (owned by Competition) |
| **Public Contracts** | `JudgeRepositoryContract`, `JudgePanelContract` |
| **Dependencies** | Core, Shared, Countries |
| **Events Published** | `JudgeAssignedToStage`, `JudgeRemovedFromStage` |
| **Events Consumed** | `UserRoleAssigned` |

---

#### MODULE: Competition

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Manages the structural and temporal organization of the competition — seasons, stages, schedules, judge panel assignments per stage, and the publication of official results. This module is the authority on "when" and "how" the competition is organized. |
| **Owns** | Season entity, Stage entity (Stage 1, Stage 2, Semi-Final, Final), stage schedule, judge panel-to-stage assignments, stage results, competition archive |
| **Does NOT own** | Individual evaluation scores (owned by Evaluations), contestant identity (owned by Contestants), application processing (owned by Applications) |
| **Public Contracts** | `SeasonRepositoryContract`, `StageRepositoryContract`, `CompetitionScheduleContract` |
| **Dependencies** | Core, Shared, Judges |
| **Events Published** | `SeasonCreated`, `SeasonActivated`, `SeasonClosed`, `StageScheduled`, `StageStarted`, `StageCompleted`, `StageResultsPublished` |
| **Events Consumed** | `ApplicationApproved`, `JudgeAssignedToStage` |

> **Architectural Note — Internal Sub-Domain Structure and Future Extraction Path**
>
> The Competition module encompasses three closely related but distinguishable sub-concerns: **Seasons** (competition lifecycle and archival), **Stages** (structural phase definitions and their sequencing), and **Scheduling** (temporal assignments, judge-panel-to-stage mapping, and slot management). At this stage of the platform, these sub-concerns share sufficient data coupling — a Stage belongs to a Season; Scheduling references both Stages and Judges — that splitting them into separate modules would introduce distributed transactions where a single transactional boundary is appropriate.
>
> The recommended approach is to enforce **internal sub-domain separation within the Competition module**:
>
> ```
> Competition/
>   Domain/
>     Seasons/          ← Season aggregate root, lifecycle, archival
>     Stages/           ← Stage definitions, sequencing, phase types
>     Scheduling/       ← Judge panel assignments, date/time slots
>   Application/
>     Seasons/UseCases/
>     Stages/UseCases/
>     Scheduling/UseCases/
> ```
>
> This internal structure makes future extraction into three standalone modules (Seasons, Stages, Scheduling) a surgical refactoring rather than a rewrite. The boundary is drawn in the code before it is drawn in the deployment topology. If — after two or more competition seasons — the Competition module demonstrates high commit frequency across its sub-domains independently (a reliable signal of diverging business forces), extraction should be initiated at that time.
>
> This decision is explicitly deferred until empirical evidence justifies the operational cost of splitting.

---

#### MODULE: Evaluations

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Governs the scoring workflow by which judges evaluate contestant recitation videos according to the approved judging rubric, calculate scores, add notes, and approve evaluation results. |
| **Owns** | Evaluation entity, scoring rubric definition, individual criterion scores, judge notes, aggregate score computation, evaluation status (Pending, In Progress, Completed, Approved), evaluation result approval |
| **Does NOT own** | Judge identity (owned by Judges), stage assignment logic (owned by Competition), video storage (owned by Videos) |
| **Public Contracts** | `EvaluationRepositoryContract`, `ScoringRubricContract`, `EvaluationResultContract` |
| **Dependencies** | Core, Shared, Judges, Competition, Applications, Videos |
| **Events Published** | `EvaluationStarted`, `EvaluationCompleted`, `EvaluationApproved`, `ScoreUpdated` |
| **Events Consumed** | `StageStarted`, `JudgeAssignedToStage` |

---

#### MODULE: Content

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Manages all public-facing editorial content displayed on the competition website — pages, announcements, FAQ entries, and the organizational message. Supports multilingual content for all supported languages. |
| **Owns** | Page entity (about, rules, contact), Announcement entity, FAQ entity, translatable content fields, publication status, content scheduling |
| **Does NOT own** | News in the AlphaCMS sense (explicitly excluded per ADR-001), media file storage (delegated to Media), sponsor data |
| **Public Contracts** | `PageRepositoryContract`, `AnnouncementRepositoryContract`, `FaqRepositoryContract` |
| **Dependencies** | Core, Shared, Media |
| **Events Published** | `AnnouncementPublished`, `PageUpdated` |
| **Events Consumed** | None |

---

#### MODULE: Sponsors

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Manages the competition sponsors and partners — their identity, tier classification, logo, website link, display order, and active status. |
| **Owns** | Sponsor entity, sponsorship tier, display configuration, activation status |
| **Does NOT own** | Content pages, media storage (delegated to Media) |
| **Public Contracts** | `SponsorRepositoryContract` |
| **Dependencies** | Core, Shared, Media |
| **Events Published** | None |
| **Events Consumed** | None |

---

#### MODULE: Reports

| Field | Detail |
|---|---|
| **Tier** | Domain |
| **Purpose** | Provides aggregated statistics, operational dashboards, and historical archive reports across all business domains. Acts as a read-side aggregation layer; it does not own any primary data. |
| **Owns** | Report query services, statistics aggregation, dashboard data projections, historical season archives, report generation jobs |
| **Does NOT own** | Primary data in any other module. Reports reads from public contracts and read models only. |
| **Public Contracts** | `ReportGeneratorContract`, `StatisticsDashboardContract` |
| **Dependencies** | Core, Shared, Applications, Contestants, Evaluations, Competition, Countries, Videos |
| **Events Published** | None |
| **Events Consumed** | (Read-only; consumes public read models, not events) |

---

## Phase 3: ADR-002

---

## Decision

### 1. Module Philosophy

Every identifiable business capability in the platform is encapsulated within a dedicated **Module**. A Module is the fundamental unit of organization in the backend system. It is not a technical layer — it is a business boundary.

This principle has three corollaries:

- **No business logic exists outside a Module.** Code that does not belong to a specific business capability belongs in the Shared Kernel (cross-cutting utilities) or the Core Module (platform-wide identity and configuration). Under no circumstances does business logic exist in a global Laravel service provider, a global helper file, or a catch-all utility namespace.
- **Every Module is a bounded context.** The boundary of a Module corresponds to a bounded context in the domain model. Two concerns that evolve together, are owned by the same team, and share the same language belong in the same Module. Concerns that evolve independently, serve different actors, or use different domain language belong in separate Modules.
- **Module boundaries are enforced structurally.** A Module's internals are private. Only the public contracts, events, and declared public services of a Module may be accessed from outside it. This is not a convention — it is a structural rule that must be enforced through code review policy and, where tooling allows, automated analysis.

### 2. Bounded Contexts

The official bounded contexts of this platform, derived from the project proposal and domain analysis, are:

| Module | Tier | Bounded Context | Primary Actor |
|---|---|---|---|
| **Shared** | Shared Kernel | Cross-cutting kernel | All modules |
| **Core** | Core | Identity, authorization, platform configuration | All administrators |
| **Media** | Platform Service | File asset management | All modules handling files |
| **Videos** | Platform Service | Video processing & gallery | Contestants, Judges, Competition Managers |
| **Streaming** | Platform Service | Live broadcast management | Competition Manager, Public audience |
| **Notifications** | Platform Service | Notification dispatch | System (event-driven) |
| **Search** | Platform Service | Search index management & query surface | All modules with searchable entities |
| **Cache** | Platform Service | Redis cache abstraction & invalidation strategy | All modules with cacheable data |
| **Countries** | Domain | Geographic reference data | System, Contestants, Reports |
| **Contestants** | Domain | Contestant identity & profile | Contestants (public portal) |
| **Applications** | Domain | Application lifecycle | Contestants, Data Entry, Moderators |
| **Judges** | Domain | Judge identity & panel membership | Super Admin, Competition Manager |
| **Competition** | Domain | Season & stage management *(Seasons / Stages / Scheduling sub-domains)* | Competition Manager |
| **Evaluations** | Domain | Scoring & judging workflow | Judges |
| **Content** | Domain | Public website content | Content Manager, Public audience |
| **Sponsors** | Domain | Sponsor & partner management | Admin, Public audience |
| **Reports** | Domain | Statistics & historical archives | All administrators |

### 3. Module Ownership

Each Module owns exclusively and completely:

- **Domain**: Entities, value objects, domain events, business rules, and domain exceptions.
- **Application**: Use cases, application services, DTOs, and command/query handlers.
- **Infrastructure**: Eloquent models, repository implementations, external service adapters, queue jobs, cache interactions, and search indexing.
- **Presentation**: HTTP controllers, API resources (response transformers), form request validators, and API route definitions.
- **Configuration**: Module-specific configuration files, registered independently.
- **Database**: Migrations and seeders for all database tables owned by the module.
- **Permissions**: Definitions and registration of all permissions in the `module.action` naming convention.
- **Translations**: Language files for Arabic, English, and Spanish, organized by locale within the module.
- **Tests**: Unit tests, feature tests, and integration tests.
- **Factories**: Model factories for testing purposes.

No two Modules share ownership of any of the above artifacts. Shared ownership is prohibited. When a concern appears to belong to two modules, the correct solution is either to move it to the module that owns the primary entity, to place it in a shared contract, or to publish a domain event.

### 4. Module Independence

Module independence is the most critical structural constraint in this architecture. It is defined by the following absolute rules:

**Rule 1 — No direct model access across module boundaries.**
A module must never instantiate, query, or modify an Eloquent model that belongs to another module. Doing so creates an implicit coupling between database schemas that makes independent evolution impossible.

**Rule 2 — No direct service instantiation across module boundaries.**
A module must never directly `new` or resolve from the container a concrete service class defined in another module. All cross-module service access must go through a declared contract (interface).

**Rule 3 — No cross-module direct method calls on domain objects.**
Domain objects (entities, value objects) of one module must not be passed as arguments to or returned from the services of another module. Module boundaries define a type boundary as well as a code boundary.

**Rule 4 — Communication through contracts, public services, or events only.**
The three and only three legitimate cross-module communication channels are: (a) calling a method on a published service contract, (b) dispatching a domain event, (c) consuming a domain event via a registered listener.

### 5. Module Communication

Module communication is governed by a strict protocol:

#### 5.1 Contracts (Synchronous, Request/Response)

When Module A requires data or action from Module B synchronously, it must:
1. Define a contract (interface) in its own `Contracts/` directory describing what it needs.
2. Bind the contract to Module B's implementation in Module B's service provider.
3. Inject the contract via constructor injection — never the concrete class.

This preserves the Dependency Inversion Principle and ensures Module A never has a compile-time dependency on Module B's internals.

#### 5.2 Domain Events (Asynchronous, Fire-and-Forget)

When Module A completes a meaningful business operation and other modules need to react, Module A **dispatches a domain event**. Module A has no knowledge of, and no dependency on, the listeners that will consume this event.

Domain events are the primary integration mechanism between modules. They are the only way modules remain fully decoupled while enabling complex workflows.

#### 5.3 Platform Services (Shared Capabilities)

Platform Services (Media, Videos, Streaming, Notifications, Search, Cache) expose public-facing service contracts that are stable, versioned interfaces available to all domain modules. These are not internal services leaked outward — they are deliberately designed integration surfaces. The Platform Services tier is where general-purpose technical capabilities live. Domain modules express what they need (through contract injection); Platform Services provide it. The direction of knowledge always flows from Platform Services toward Domain Modules — never the reverse.

#### 5.4 Communication That Is Forbidden

The following patterns are forbidden under this architecture and constitute boundary violations:

- Direct access to another module's Eloquent models
- Direct access to another module's database tables via raw queries
- Direct instantiation of another module's services or repositories
- Importing another module's internal service provider or config
- Sharing a database table between two modules

### 6. Clean Architecture Inside Every Module

Every module enforces the following internal layer structure. Dependencies flow strictly inward — outer layers depend on inner layers; inner layers have no knowledge of outer layers.

```
┌──────────────────────────────────────────────────────────────────┐
│                       PRESENTATION LAYER                         │
│  Controllers · API Resources · Form Requests · Route Definitions │
│  (depends on Application Layer only)                             │
├──────────────────────────────────────────────────────────────────┤
│                       APPLICATION LAYER                          │
│  Use Cases · Application Services · DTOs · Commands · Queries    │
│  (depends on Domain Layer only; uses contracts for I/O)          │
├──────────────────────────────────────────────────────────────────┤
│                      INFRASTRUCTURE LAYER                        │
│  Eloquent Models · Repository Implementations · External Adapters│
│  Queue Jobs · Cache · Search Indexing · Event Subscribers        │
│  (implements contracts defined in Domain/Application layers)     │
├──────────────────────────────────────────────────────────────────┤
│                         DOMAIN LAYER                             │
│  Entities · Value Objects · Domain Events · Business Rules       │
│  Domain Exceptions · Repository Contracts · Service Contracts    │
│  (no framework dependencies; no infrastructure dependencies)     │
└──────────────────────────────────────────────────────────────────┘
```

#### Domain Layer Responsibilities

The Domain Layer is the heart of the module. It contains:

- **Entities**: Objects with identity that persist over time. An Entity has business behavior, not just data.
- **Value Objects**: Immutable objects defined by their attributes, not identity. Examples: `Score`, `ApplicationStatus`, `LanguageCode`.
- **Domain Events**: Records of business-significant facts that have occurred. Published by entities and aggregates. Examples: `ApplicationApproved`, `EvaluationCompleted`.
- **Business Rules**: Invariants that must always hold true for the domain to be in a valid state.
- **Repository Contracts**: Interfaces describing how entities are persisted. The Domain Layer defines what it needs; the Infrastructure Layer provides it.
- **Service Contracts**: Interfaces describing capabilities the domain requires from external systems.

The Domain Layer has **zero dependencies** on Laravel, Eloquent, Redis, HTTP, or any infrastructure technology.

#### Application Layer Responsibilities

The Application Layer coordinates domain objects to accomplish use cases:

- **Use Cases**: Orchestrate domain objects to fulfill a single business operation. Examples: `SubmitApplicationUseCase`, `AssignJudgeToStageUseCase`, `ApproveEvaluationUseCase`.
- **Application Services**: Stateless services that handle complex orchestration spanning multiple entities or modules (through contracts).
- **Data Transfer Objects (DTOs)**: Typed, immutable objects used to carry data across layer boundaries without exposing domain internals.
- **Commands and Queries**: Input and output objects for use cases, following CQRS-style separation where appropriate.

The Application Layer may reference domain objects and contracts freely. It must not reference Eloquent models, HTTP request objects, or any infrastructure concern directly.

#### Infrastructure Layer Responsibilities

The Infrastructure Layer implements the technical concerns required to run the system:

- **Eloquent Models**: ORM representations of database tables. They implement persistence contracts defined in the Domain Layer.
- **Repository Implementations**: Concrete implementations of domain repository contracts using Eloquent.
- **External Adapters**: Wrappers around third-party libraries (FFmpeg, Meilisearch, cloud storage SDKs, OAuth providers). They implement contracts defined in the Domain or Application layers.
- **Queue Jobs**: Background processing tasks dispatched by the Application Layer.
- **Cache Interactions**: Cache warming, invalidation, and retrieval strategies.
- **Search Indexing**: Meilisearch index management and Scout integration.

#### Presentation Layer Responsibilities

The Presentation Layer is the HTTP surface of the module:

- **Controllers**: Thin. They receive an HTTP request, delegate immediately to a use case or application service, and return an API resource. No business logic.
- **API Resources**: Transform domain objects or DTOs into the JSON structure expected by the API contract. They enforce the response schema.
- **Form Requests**: Validate incoming HTTP request data using Laravel's Form Request mechanism. They authorize the request and validate the input before it reaches the use case.
- **Route Definitions**: Module-specific route files, registered by the module's service provider. All routes are prefixed with `/api/v1/`.

### 7. Module Registration

Every module is **self-registering**. When a new module is added to the system, no existing file requires modification. Each module registers itself through its own service provider.

Every module includes a dedicated `ModuleServiceProvider` that performs the following registrations at boot time:

- **Routes**: Module route files are loaded and prefixed appropriately.
- **Migrations**: Module migration directories are registered so migrations run as part of the standard `artisan migrate` command.
- **Config**: Module-specific configuration files are merged into the application configuration namespace.
- **Translations**: Module language files are loaded under a module-prefixed namespace.
- **Policies**: Module policies are registered in Laravel's Gate.
- **Permissions**: The module's permission definitions are registered with the platform's PermissionRegistrar contract.
- **Event Listeners**: Module event listeners are registered with the Laravel event dispatcher.
- **Observers**: Module Eloquent observers are registered.

The root application configuration includes only a list of module service providers. Adding a module requires adding a single entry to this list. Nothing else in the application requires modification.

### 8. Module Directory Structure

Every module adheres to the following directory structure. Not every directory will be populated in every module, but the structure is canonical across all modules for consistency and predictability.

```
Modules/
└── {ModuleName}/
    │
    ├── Actions/                    # Single-responsibility action classes (thin use case steps)
    │
    ├── Contracts/                  # Interfaces this module exposes for cross-module consumption
    │   ├── {Entity}RepositoryContract.php
    │   └── {Capability}ServiceContract.php
    │
    ├── database/
    │   ├── factories/              # Model factories for testing
    │   ├── migrations/             # All database migrations owned by this module
    │   └── seeders/                # Reference data and test data seeders
    │
    ├── Domain/
    │   ├── Entities/               # Core domain objects with identity and behavior
    │   ├── Enums/                  # Domain-specific enumerations
    │   ├── Events/                 # Domain events — facts that have occurred
    │   ├── Exceptions/             # Domain-specific exception types
    │   ├── Repositories/           # Repository contract interfaces (implemented in Infrastructure)
    │   ├── Services/               # Domain service interfaces (pure business logic contracts)
    │   └── ValueObjects/           # Immutable value types
    │
    ├── Application/
    │   ├── DTOs/                   # Data Transfer Objects crossing layer boundaries
    │   ├── Services/               # Application services orchestrating use cases
    │   └── UseCases/               # One class per use case; thin orchestrators
    │
    ├── Infrastructure/
    │   ├── Adapters/               # Third-party service adapters (FFmpeg, storage, etc.)
    │   ├── Cache/                  # Cache warming and invalidation strategies
    │   ├── Jobs/                   # Queue jobs dispatched by the application layer
    │   ├── Models/                 # Eloquent ORM models (implements domain repository contracts)
    │   ├── Observers/              # Eloquent model observers
    │   ├── Repositories/           # Concrete repository implementations
    │   └── Search/                 # Meilisearch index configuration and Scout integration
    │
    ├── Presentation/
    │   └── Http/
    │       ├── Controllers/        # Thin HTTP controllers; delegate to use cases immediately
    │       ├── Middleware/         # Module-specific HTTP middleware
    │       ├── Requests/           # Laravel Form Requests (validation + authorization)
    │       └── Resources/          # API Resource transformers (domain → JSON response)
    │
    ├── config/
    │   └── {module-name}.php       # Module-specific configuration file
    │
    ├── lang/
    │   ├── ar/                     # Arabic translations
    │   ├── en/                     # English translations
    │   └── es/                     # Spanish translations
    │
    ├── Listeners/                  # Event listeners consuming events from other modules
    │
    ├── Notifications/              # Laravel Notification classes (email, SMS, push, in-app)
    │
    ├── Policies/                   # Laravel authorization policies for module entities
    │
    ├── Providers/
    │   └── {ModuleName}ServiceProvider.php  # Self-registering service provider
    │
    ├── Routes/
    │   ├── api.php                 # Public/contestant API routes
    │   └── admin.php               # Administration API routes
    │
    ├── Support/                    # Module-internal utilities (not to be shared externally)
    │   ├── Helpers/
    │   └── Traits/
    │
    └── Tests/
        ├── Feature/                # HTTP-level feature tests
        ├── Integration/            # Cross-layer integration tests
        └── Unit/                   # Pure unit tests for domain and application layers
```

### 9. Shared Kernel

The **Shared Kernel** is a special module (`Modules/Shared/`) that contains code shared by all modules. It is the only exception to the rule that modules do not share code.

The Shared Kernel contains **only**:

| Category | Examples |
|---|---|
| Abstract base classes | `BaseEntity`, `BaseRepository`, `BaseServiceProvider` |
| Common interfaces | `RepositoryContract`, `ServiceContract`, `EventContract` |
| Cross-cutting traits | `HasUuid`, `SoftDeletable`, `Searchable`, `HasTranslations`, `HasAuditLog` |
| Shared value objects | `Money`, `DateRange`, `LocalizedString`, `Pagination` |
| Shared enumerations | `SortDirection`, `PaginationSize`, `LocaleCode` |
| Helper utilities | `CollectionHelper`, `StringHelper`, `DateHelper` |
| Exception base classes | `DomainException`, `ApplicationException`, `InfrastructureException` |

The Shared Kernel **must never contain**:

- Business logic of any kind
- Entities from any domain
- Eloquent models
- API controllers or routes
- Database migrations
- Domain events from any specific domain

Violations of the Shared Kernel's scope are the most insidious form of architectural decay. Code added to Shared "for convenience" frequently becomes load-bearing logic that cannot be removed without touching every module that depends on it.

### 10. Core Module

The **Core Module** is the foundational module of the system. All other modules depend on Core. Core depends on nobody except Shared.

Core's primary responsibility is identity, authorization, and platform-wide configuration — concerns that must be stable, consistent, and available everywhere.

Core owns:

| Sub-domain | Responsibilities |
|---|---|
| **Authentication** | Login, logout, social OAuth flows, email/password flows, Sanctum token management. Implemented via AlphaCMS Authentication module. |
| **Users** | The `users` table, user lifecycle (creation, activation, deactivation, deletion), the `type` column that governs surface access. |
| **Roles** | Role definitions, role assignment, role hierarchy. Managed via Spatie Laravel Permission. |
| **Permissions** | The permission registry mechanism. Each module registers its permissions; Core's PermissionRegistrar aggregates them and seeds the database. |
| **Profile** | Extended user profile management shared between contestant and admin user types. |
| **Settings** | System-wide configuration key-value store (platform name, contact email, feature flags, maintenance mode). |
| **Audit** | Audit log writes — recording who performed what action, when, and on what entity. Consumed by all modules through the AuditLogger contract. |
| **Languages** | The `languages` table and language registry service. The authoritative source of supported locales. |

Core is the only module that has the authority to define the `users` table schema. All other modules that need to associate data with a user do so via foreign key reference to `users.id` — they never add columns to the `users` table.

### 11. Dependency Rules

The allowed dependency directions are governed by the tier hierarchy established in Phase 2. The rules are:

#### Allowed Dependencies

```
Domain Modules       → Core, Shared, Platform Services (via contracts)
Platform Services    → Core, Shared
Core Module          → Shared
Shared Kernel        → (nothing)
Reports Module       → Core, Shared, all other modules' public contracts only
```

#### Dependency Direction Diagram

```
Shared ← Core ← Platform Services ← Domain Modules
  ↑                    ↑
  └────────────────────┘
  (all tiers depend on Shared)
```

#### Forbidden Dependencies

- **Core must never depend on any Domain Module or Platform Service.** Core's stability is its defining property. Any dependency from Core to a business domain or platform service would make Core contingent on external change.
- **Shared must never depend on any Module.** Shared is the lowest-level dependency. Any dependency from Shared to another module creates a cycle.
- **Circular dependencies are prohibited.** If Module A depends on Module B and Module B depends on Module A, the boundary design is incorrect. The resolution is always to (a) extract the shared concern into a contract, or (b) invert the dependency using a domain event.
- **Platform Services must not depend on Domain Modules.** Media, Videos, Streaming, Notifications, Search, and Cache are general-purpose platform capabilities. If any of them acquires a dependency on a business domain module, it has absorbed business logic and must be refactored.
- **Domain Modules must not access Platform Service concrete implementations.** They must only inject platform service contracts. The binding of contract to implementation is the responsibility of the Platform Service's own service provider.

#### Dependency Validation

All inter-module dependencies must be traceable to one of the following permitted forms:

1. Constructor injection of a declared contract interface.
2. Event dispatching (no dependency on the consumer).
3. Event listening (the listener references the event class, not the publisher's internals).

Any form of cross-module coupling not expressible as one of these three forms is a boundary violation.

### 12. Domain Events

Domain events are the primary integration mechanism between modules. They allow modules to communicate business facts without creating direct dependencies.

#### Event Naming Convention

All domain events are named in the past tense, describing a business fact that has occurred:

```
{AggregateRoot}{PastTenseVerb}

ApplicationReceived
ApplicationApproved
ApplicationRejected
ApplicationNeedsData
VideoUploaded
VideoProcessingCompleted
EvaluationCompleted
EvaluationApproved
StageStarted
StageCompleted
StageResultsPublished
JudgeAssignedToStage
ContestantRegistered
SeasonActivated
StreamStarted
AnnouncementPublished
```

#### Official Domain Event Catalogue

| Event | Published By | Consumed By |
|---|---|---|
| `UserCreated` | Core | Contestants, Judges |
| `UserDeactivated` | Core | — |
| `UserRoleAssigned` | Core | Judges |
| `ContestantRegistered` | Contestants | Notifications |
| `ContestantProfileCompleted` | Contestants | — |
| `MediaUploaded` | Media | Videos |
| `MediaDeleted` | Media | — |
| `VideoUploaded` | Videos | — |
| `VideoProcessingCompleted` | Videos | Applications, Notifications |
| `VideoProcessingFailed` | Videos | Notifications |
| `VideoPublished` | Videos | — |
| `ApplicationReceived` | Applications | Notifications |
| `ApplicationUnderReview` | Applications | Notifications |
| `ApplicationApproved` | Applications | Competition, Notifications |
| `ApplicationRejected` | Applications | Notifications |
| `ApplicationNeedsData` | Applications | Notifications |
| `VideoReUploadRequested` | Applications | Notifications |
| `JudgeAssignedToStage` | Judges | Competition, Notifications |
| `SeasonActivated` | Competition | — |
| `StageStarted` | Competition | Evaluations |
| `StageCompleted` | Competition | — |
| `StageResultsPublished` | Competition | Notifications |
| `EvaluationCompleted` | Evaluations | Notifications |
| `EvaluationApproved` | Evaluations | — |
| `StreamStarted` | Streaming | — |
| `AnnouncementPublished` | Content | Notifications |

#### Event Payload Principles

- Events carry only the data necessary to identify the event subject. They do not carry full entity graphs.
- Consumers that need additional data must fetch it through the appropriate module's public contract.
- Events are immutable value objects. Once dispatched, they cannot be modified.

### 13. Permissions

Each module is the sole owner of the permissions governing access to its capabilities. There is no global permissions file. Permission definitions live inside the module that owns the business capability they protect.

#### Permission Naming Convention

All permissions follow the `module.action` pattern:

```
contestants.view          contestants.create
contestants.update        contestants.delete

applications.view         applications.review
applications.evaluate     applications.approve
applications.reject       applications.force-delete

judges.view               judges.create
judges.update             judges.delete
judges.assign

competition.view          competition.manage
competition.seasons       competition.stages
competition.results

evaluations.view          evaluations.score
evaluations.approve       evaluations.manage

videos.view               videos.upload
videos.update             videos.delete
videos.publish            videos.stream

streaming.view            streaming.manage

content.view              content.create
content.update            content.delete
content.publish

sponsors.view             sponsors.create
sponsors.update           sponsors.delete

reports.view              reports.export

users.view                users.create
users.update              users.delete
users.restore             users.force-delete

settings.view             settings.update
```

#### Module Registration of Permissions

Each module exposes a `PermissionDefinition` class that declares the module's permissions. The Core module's `PermissionRegistrar` collects all registered `PermissionDefinition` instances and seeds the database. No central permissions list exists.

#### Administration Dashboard Permission Grouping

The Administration Dashboard must present permissions grouped by module, not as a flat list. This is an architectural requirement established in ADR-001 and reaffirmed here. The module name in the permission's `module.action` identifier is the grouping key.

### 14. Translations

Each module owns its translation files for all three supported languages. No global translation files exist.

Translation files are organized under `lang/{locale}/` within the module root:

```
Modules/
├── Applications/
│   └── lang/
│       ├── ar/
│       │   ├── messages.php
│       │   ├── validation.php
│       │   └── notifications.php
│       ├── en/
│       │   ├── messages.php
│       │   ├── validation.php
│       │   └── notifications.php
│       └── es/
│           ├── messages.php
│           ├── validation.php
│           └── notifications.php
├── Evaluations/
│   └── lang/
│       ├── ar/ ...
│       ├── en/ ...
│       └── es/ ...
```

Translation keys are prefixed with the module namespace to prevent collision:

```
applications::messages.submitted
applications::validation.video_required
applications::notifications.approved
evaluations::messages.score_recorded
```

Every module's translation files must include translations for:

- User-facing labels and messages
- Validation error messages
- Notification content (email subjects, body templates)
- Error descriptions
- Status labels

### 15. Testing Strategy

Each module is fully responsible for its own test coverage. No centralized test suite exists for cross-module concerns beyond integration tests at the API level.

#### Test Categories

**Unit Tests** (`Tests/Unit/`)
Target the Domain and Application layers exclusively. They have no database, no HTTP, and no filesystem access. They test:
- Entity behavior and invariants
- Domain rule enforcement
- Use case logic with mocked contracts
- Value object correctness

**Feature Tests** (`Tests/Feature/`)
Target the full HTTP stack of the module — from request to JSON response. They use an in-memory SQLite database or a dedicated test database. They test:
- API endpoint behavior (status codes, response schemas, error formats)
- Authentication and authorization (correct permission gates)
- Validation rejection of invalid inputs
- Full use case execution via HTTP

**Integration Tests** (`Tests/Integration/`)
Target cross-layer and cross-module integration. They use the full application stack, including real database connections. They test:
- Module interactions through domain events
- Contract implementations against real infrastructure
- Background job behavior
- Cache and search index behavior

#### Test Isolation

Every module's test suite must be executable in isolation, without requiring other modules to be present. A module that cannot be tested independently has broken isolation and is not correctly bounded.

### 16. Design Principles

The module system is governed by the following design principles. These principles are not suggestions — they are architectural laws that govern every decision made within the module system.

**High Cohesion**
Everything within a module belongs together. The code within a module serves the same business purpose, changes for the same reasons, and is owned by the same team. A module with low cohesion — one that contains code from multiple unrelated domains — is an incorrectly bounded module.

**Low Coupling**
Modules must minimize their dependencies on each other. Every dependency between modules is a potential breaking change and a constraint on independent evolution. When a dependency is unavoidable, it is expressed as a contract — the weakest possible form of coupling.

**Single Responsibility**
A module has one reason to change. It serves one bounded context. If a module has two reasons to change — two independent business forces that evolve it — it should be two modules.

**Dependency Inversion**
High-level modules (domain logic) must not depend on low-level modules (infrastructure). Both must depend on abstractions (contracts). The domain defines what it needs; infrastructure implements what was asked for. This is enforced by the Clean Architecture layer structure.

**Open/Closed Principle**
Each module is open for extension and closed for modification. New behaviors are added by extending through new use cases, new event listeners, and new contract implementations — not by modifying existing domain entities or use cases.

**Module Isolation**
The module system exists to enforce isolation as a structural property. Isolation is not a courtesy extended by developers who happen to be disciplined — it is enforced by the architecture. Boundary violations are not refactoring opportunities; they are defects.

**API First**
Every capability exposed by a module is accessible only through its HTTP API surface. No module exposes server-side rendered views. No frontend has direct access to module internals. This principle is inherited from ADR-001 Decision #3.

---

## Consequences

### Positive Consequences

- **Predictable codebase navigation**: Every engineer working on any feature knows exactly where to find the relevant code. The module name is the address of every business capability.
- **Parallel development**: Independent module boundaries allow multiple teams or individuals to develop, test, and deploy module changes simultaneously without merge conflicts or coordination overhead.
- **Safe refactoring within boundaries**: Changes confined within a module's boundaries are guaranteed not to break any other module, as long as public contracts and domain events remain stable.
- **Extractability**: Any module is extractable into a standalone microservice with minimal effort — the boundaries, contracts, and events are already defined.
- **Onboarding velocity**: A new engineer can become productive in a single module without needing to understand the entire system. The module is a self-contained unit of understanding.
- **Independent testability**: Each module's test suite can be run in isolation, enabling fast CI pipelines and rapid feedback cycles.
- **Permission governance at scale**: As the permission surface grows over time, module-owned permissions ensure the governance model scales linearly with the module count. There is no global permissions debt.
- **Localization at scale**: Module-owned translation files mean that adding a new language requires a predictable, linear amount of work — one set of translation files per module.

### Negative Consequences / Trade-offs

- **Structural overhead**: The four-layer Clean Architecture within every module, combined with the module directory structure, generates more files per feature than a flat application would. This overhead is a justified investment in long-term maintainability but has a real upfront cost.
- **Discipline requirement**: The module system only delivers its value if boundary rules are actively enforced. A single unchecked boundary violation that ships to production becomes a precedent that justifies others. Code review processes must explicitly check for boundary violations.
- **Event choreography complexity**: Event-driven inter-module communication distributes workflow logic across multiple listeners. Debugging a multi-step workflow (e.g., ApplicationApproved → Competition → Evaluations → Notifications) requires tracing an event chain rather than reading a single service method. Proper event logging and tracing infrastructure is essential.
- **Contract versioning overhead**: Public contracts between modules must be versioned and evolved carefully. A breaking change to a contract requires coordinated updates to all consuming modules. This is a discipline cost, not a technical limitation.
- **Initial setup investment**: The scaffolding for each new module — providers, contracts, directory structure, base tests — has a setup cost. This is mitigated by module scaffolding tooling but remains a real consideration.

### Trade-offs Accepted

| Trade-off | Justification |
|---|---|
| More files per feature vs. flat structure | Module isolation is more valuable than file count minimization for a platform with a 10-year operational horizon |
| Event chains vs. direct service calls | Decoupling is more valuable than call-stack simplicity for modules that must evolve independently |
| Contract versioning discipline vs. direct access | Preventing boundary violations is more valuable than avoiding the overhead of contract management |
| Onboarding to module structure vs. familiar flat layout | A new engineer learns the module system once; a flat application's implicit dependencies must be rediscovered continuously |

---

## Alternatives Considered

### Alternative 1: Traditional Laravel `app/` Flat Structure

The conventional Laravel application structure — `app/Models/`, `app/Http/Controllers/`, `app/Services/` — with no explicit module boundaries.

**Rejected because**:
- As the application grows beyond a handful of features, the flat structure produces a namespace that contains every entity from every domain simultaneously, making it impossible to understand which code belongs to which business capability.
- There are no structural enforcement mechanisms to prevent cross-domain coupling. All separation is enforced by convention, which degrades over time as team composition changes and delivery pressure increases.
- The flat structure makes it impossible to test a single business capability in isolation.
- It provides no extraction boundary for future microservice separation.

### Alternative 2: nWidart Laravel-Modules Package

The `nWidart/laravel-modules` package provides a module system for Laravel applications and is widely used in the ecosystem.

**Rejected because**:
- The package imposes its own architectural opinions and directory structure that are not fully aligned with the Clean Architecture layering required by ADR-001.
- It introduces a third-party dependency into the core structural fabric of the application — a dependency whose deprecation, major version change, or abandonment would have platform-wide consequences.
- The module system defined in this ADR is sufficiently simple to implement using Laravel's native service provider mechanism without a package dependency.
- Using a custom module system gives the team full ownership and understanding of the registration mechanism, which is preferable for a platform intended to outlast its original development team.

### Alternative 3: Microservices Architecture

A topology in which each module is a standalone deployable service communicating over HTTP or a message broker.

**Rejected in ADR-001**. The rejection reasoning from ADR-001 is incorporated by reference. In brief: the operational overhead of service discovery, distributed tracing, inter-service authentication, and independent deployment pipelines is disproportionate to the team size and organizational context. The Modular Monolith provides equivalent organizational benefits with a clear extraction path.

### Alternative 4: Domain Namespace Separation Without Service Providers

Organizing code into domain namespaces (e.g., `App\Applications\`, `App\Evaluations\`) without module service providers, relying only on namespace conventions for separation.

**Rejected because**:
- Namespace conventions are not enforced. Without a service provider that explicitly registers the module's routes, migrations, and translations, there is nothing preventing developers from reaching across namespace boundaries.
- Module self-registration — the ability to add a module without modifying any existing file — requires a service provider mechanism. Namespace-only separation cannot deliver this property.
- There is no clear registration point for module-owned permissions or event listeners without a service provider.

### Alternative 5: Shared Business Logic in Core

Placing cross-domain business logic — logic that appears to relate to multiple modules — in the Core module, on the grounds that Core is shared infrastructure.

**Rejected because**:
- Core is responsible for identity, authorization, and platform configuration. It has a specific, stable purpose. Accumulating business logic in Core would destabilize it and create dependencies from Core to every business domain — inverting the dependency hierarchy.
- Logic that appears to span multiple modules is almost always the sign of an incorrectly bounded module. The correct solution is to find the right bounded context for the logic, not to place it in Core.
- Business logic in Core cannot be tested in isolation, because it would depend on domain entities from multiple modules.

### Alternative 6: Shared Database Tables Between Modules

Allowing two or more modules to read from and write to the same database table, on the grounds that the data model is shared.

**Rejected because**:
- Shared database tables are the most pervasive form of module coupling. Any schema change to a shared table potentially breaks all modules that reference it.
- If two modules require access to the same data, the correct solution is: (a) the data belongs to one module and is accessed by the other through a contract, or (b) the two modules should be one module because they form a single bounded context.
- Shared tables make it impossible to extract a module into a standalone service, as the service would need access to a table co-owned by another service.

---

## Architectural Recommendations

The following concerns are not explicitly represented in the project proposal as discrete modules but are recommended as standard components of an enterprise competition platform. Items marked **[Promoted]** have been elevated to official Platform Service modules in this ADR. Items marked **[Future ADR]** will be addressed in a dedicated future ADR.

| Concern | Status | Rationale |
|---|---|---|
| **Search** | **[Promoted — Platform Service]** | The proposal explicitly requires a fast internal search engine. Search has been elevated from a package integration to a first-class Platform Service module with contracts, events, and an index strategy. See Module Registry above. |
| **Cache** | **[Promoted — Platform Service]** | Redis is declared in ADR-001 as the caching layer. Cache has been elevated to a first-class Platform Service module with namespaced keys, invalidation contracts, and warming infrastructure. See Module Registry above. |
| **Audit** | **[Sub-domain of Core]** | The proposal's security requirements imply a full audit trail. Audit log management is implemented as a sub-domain within Core via the `AuditLoggerContract`, available to all modules through dependency injection. |
| **Countries** | **[Official Domain Module]** | Required for international participation tracking. The proposal explicitly mentions country-based statistics. Countries is already included in the official module map. |
| **Feature Flags** | **[Recommended — sub-domain of Core/Settings]** | For a platform that must evolve across seasons, feature flags allow new capabilities to be deployed safely. Recommended as a Settings sub-domain in an initial implementation. |
| **API Rate Limiting** | **[Recommended — cross-cutting]** | A global competition platform is a target for abuse. Per-endpoint, per-user rate limiting should be a first-class concern. Covered in ADR-004 (API Standards). |
| **Backup & Recovery** | **[Future ADR]** | The proposal explicitly requires backup and data recovery. This is not merely a Docker or operational configuration — it requires a documented Backup Policy, Restore Policy, and Disaster Recovery plan. A dedicated ADR (tentatively ADR-007) will govern this. |
| **Competition Module Split** | **[Deferred Decision]** | The Competition module internally organizes Seasons, Stages, and Scheduling as sub-domains. External extraction into three standalone modules is deferred pending empirical evidence from production operation. See Competition module note above. |

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-003: Authentication and Identity Architecture](./ADR-003-authentication-identity.md)
- [Michael Nygard — Documenting Architecture Decisions](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions)
- [Domain-Driven Design — Eric Evans](https://www.domainlanguage.com/ddd/)
- [Building Microservices — Sam Newman](https://samnewman.io/books/building_microservices/)
- [Clean Architecture — Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Implementing Domain-Driven Design — Vaughn Vernon](https://vaughnvernon.com/?page_id=168)
- [Modular Monolith with DDD — Kamil Grzybek](https://github.com/kgrzybek/modular-monolith-with-ddd)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/)
- [Laravel Service Providers](https://laravel.com/docs/providers)
- [Laravel Events](https://laravel.com/docs/events)
- [Laravel Queues](https://laravel.com/docs/queues)
- [Project Proposal — JRTV Global Quran Competition Platform (Internal Reference)]
