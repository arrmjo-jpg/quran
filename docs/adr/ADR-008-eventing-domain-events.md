# ADR-008: Eventing & Domain Events Governance

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-008                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-003 · ADR-004 · ADR-005 · ADR-006 · DATABASE-DOMAIN-INVENTORY · ENTITY-RELATIONSHIP-MAP                          |

---

## Status

**Accepted** — This document is the constitutional reference governing all asynchronous communication, domain events, integration events, transactional outbox operations, dead-letter queues, event ordering, idempotency, payload standards, event versioning, module communication rules, and the complete platform event catalog.

---

## Context

### Why This ADR Exists

ADR-001 established the Modular Monolith architecture. ADR-002 declared that domain events are the **only permitted mechanism** for cross-module communication — prohibiting modules from directly importing or calling other modules' internal concrete classes. ADR-005 established the persistence model (`outbox_events` table in MySQL) for events. ADR-006 established the background queue infrastructure (Redis, Horizon, worker priorities).

However, none of those documents established the **event governance constitution** — how events are named, how payloads are structured, how event ordering is guaranteed, how listeners achieve idempotency, how breaking event schema changes are versioned, how dead-letter queues (DLQ) are replayed, or which modules are authorized to publish and subscribe to specific events.

Without a comprehensive Eventing ADR, the following failure modes occur:

- **Divergent Event Payloads**: Modules pass full Eloquent model objects inside event classes, causing serialization crashes when worker queues process jobs asynchronously.
- **Event-Driven Race Conditions**: An `ApplicationApproved` event is processed by a worker before `ApplicationSubmitted` finishes, corrupting application state.
- **Duplicate Execution**: Redis network retries dispatch the same `NotificationRequested` event twice, sending duplicate SMS messages to contestants.
- **Silent Event Loss**: An event listener fails due to a temporary database deadlock, but the event is discarded without dead-letter tracking or replay capabilities.
- **Cascading Event Storms**: Module A dispatches Event A, listener in Module B catches it and dispatches Event B, listener in Module A catches Event B and dispatches Event A — causing an infinite loop that crashes Redis workers.

This ADR eliminates these failure modes by establishing binding eventing decisions before any cross-module listener or background event handler is implemented.

---

## Architectural Principles

### Principle 1: Events Represent Past Immutable Facts
An event is a statement of historical fact (`ApplicationSubmitted`, `VideoProcessingCompleted`). It represents something that **has already occurred** in the domain. Events are named using past-tense verbs. Events are never commands (`SubmitApplication`) or requests (`ProcessVideo`).

### Principle 2: Strict Outbox Before Queue Dispatch
No event may be dispatched directly to a queue worker or event bus before the originating database transaction has successfully committed. All events are written to the `outbox_events` table within the same atomic transaction as the domain state change.

### Principle 3: At-Least-Once Delivery & Listener Idempotency
The platform operates strictly under **At-Least-Once Delivery** guarantees. Consumers must never assume Exactly-Once delivery. Every event listener must be strictly **Idempotent** — processing the same event multiple times must produce the exact same domain state as processing it once.

### Principle 4: Primitive-Only Data Snapshots
Event payloads carry only primitive types (UUID strings, ISO-8601 strings, scalar numbers, booleans, arrays of primitives). Passing Eloquent models, live database connections, or un-serializable objects inside an event is strictly prohibited.

### Principle 5: Encapsulated Module Communication
Modules publish events to communicate state changes to the rest of the platform. A publishing module has zero knowledge of who subscribes to its events. Subscribers receive events asynchronously and must mutate state **only within their own module boundaries**.

---

## Formal Decisions

---

### PART I — EVENT PHILOSOPHY & CLASSIFICATION

#### Decision 1: Event Classification — Domain Events vs. Integration Events

The platform recognizes two distinct event categories:

| Attribute | Domain Event (Internal) | Integration Event (External / Cross-System) |
|---|---|---|
| **Scope** | Within platform module boundaries | Dispatched to external third-party systems (Webhooks, JRTV Broadcast Systems) |
| **Transport** | In-Process Laravel Event Bus / Redis Horizon Queue via Outbox | Outbound Webhook Service / HTTP POST to external API |
| **Payload** | Minimal UUID references + domain snapshot | Full public JSON representation per ADR-004 |
| **Class Prefix** | `Modules\{Module}\Events\{EventName}` | `App\Integration\Events\{EventName}` |
| **Example** | `ApplicationApproved`, `StageCompleted` | `PublicResultBroadcasted`, `JRTVStreamStateChanged` |

All rules in this ADR apply to **Domain Events** by default unless explicitly marked as Integration Events.

#### Decision 2: Naming Conventions

Domain events follow strict PascalCase naming using the format:
`{AggregateRoot}{PastTenseVerb}`

```
ContestantRegistered      ✅  (Contestant aggregate + Registered verb)
ApplicationSubmitted      ✅  (Application aggregate + Submitted verb)
VideoProcessingCompleted  ✅  (Video aggregate + ProcessingCompleted verb)

SubmitApplication         ❌  (Command verb — prohibited)
ProcessVideoTask          ❌  (Task noun — prohibited)
application_approved      ❌  (snake_case — prohibited)
```

---

### PART II — EVENT LIFECYCLE & TRANSACTIONAL OUTBOX

#### Decision 3: Complete Event Lifecycle Mechanics

Every event moves through a 5-stage lifecycle:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 1: AGGREGATE CREATION                                                 │
│ Use Case executes business logic → Aggregate records Domain Event           │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 2: TRANSACTIONAL OUTBOX WRITE (ATOMIC)                                 │
│ DB Transaction opens:                                                       │
│ 1. Aggregate state updated in MySQL                                         │
│ 2. outbox_events record inserted (status: pending)                          │
│ Transaction COMMITS                                                         │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 3: ASYNCHRONOUS DISPATCH                                              │
│ Outbox Polling Worker reads pending events → Status changed to 'processing'  │
│ Event pushed to Redis Queue via Horizon → Status changed to 'published'     │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 4: CONSUMER EXECUTION                                                 │
│ Queue Worker picks up job → Evaluates Listener Idempotency                  │
│ Listener executes business logic within subscriber module                   │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 5: ARCHIVAL & EXPIRY                                                  │
│ Event retained for 30 days → Archived by cleanup job                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Decision 4: Transactional Outbox Strategy & Schema

##### 4.1 Storage & Ownership
As established in ADR-005 Decision 17, the `outbox_events` table is owned by the Core module and serves as the single persistent outbox store for all domain modules.

##### 4.2 State Machine for `outbox_events`

```
┌─────────┐    Worker claims     ┌────────────┐   Pushed to Queue   ┌───────────┐
│ PENDING │ ───────────────────▶ │ PROCESSING │ ──────────────────▶ │ PUBLISHED │
└─────────┘                      └─────┬──────┘                     └───────────┘
                                       │
                                 Job Fails (<=3 retries)
                                       │
                                       ▼
                                 ┌────────────┐
                                 │   FAILED   │
                                 └─────┬──────┘
                                       │
                               Max retries exceeded
                                       │
                                       ▼
                                 ┌────────────┐   Admin Replay   ┌─────────┐
                                 │    DEAD    │ ───────────────▶ │ PENDING │
                                 └────────────┘                  └─────────┘
```

##### 4.3 Outbox Worker Polling Cycle
The outbox worker daemon runs as an isolated container process (`outbox-worker` per ADR-006):
```bash
php artisan platform:outbox-worker --interval=2 --batch=100
```
- Fetches up to 100 `pending` records ordered by `created_at ASC`.
- Locks records by transitioning status to `processing`.
- Pushes events to the designated Horizon queue.
- Updates status to `published` and sets `dispatched_at = CURRENT_TIMESTAMP`.

---

### PART III — RELIABILITY, ORDERING & IDEMPOTENCY

#### Decision 5: Dead Letter Queue (DLQ) & Replay Protocol

##### 5.1 DLQ Transition Rules
An event moves to status `dead` (the Dead Letter Queue state) when:
1. Max retry attempts (`attempts >= 3`) have been exhausted.
2. The event payload is corrupted or unparseable.
3. A subscriber throws an unrecoverable `FatalDomainException`.

##### 5.2 Alerting Requirements
When an event transitions to `dead`:
- An immediate `CRITICAL` log entry is written to Monolog.
- An alert notification is dispatched to the engineering Slack alert channel.
- A metric counter `events_dead_total` is incremented in Horizon.

##### 5.3 Event Replay Protocol
Events in the DLQ (`status = 'dead'`) can be replayed **only** by authorized administrators possessing the `admin.super` permission via the Administration Dashboard or CLI command:

```bash
php artisan platform:event-replay {eventId}
```

Replay behavior:
1. Verifies the event payload against current schema.
2. Updates `outbox_events.status = 'pending'`, `attempts = 0`, `dispatched_at = NULL`.
3. The outbox worker picks up the event on its next cycle and re-dispatches it.

#### Decision 6: Causal Event Ordering Strategy

##### 6.1 Aggregate-Level Ordering Guarantee
Events emitted by the same Aggregate Root must be processed by consumers in the exact order they occurred.

Example: `ApplicationSubmitted` **must** be processed before `ApplicationApproved`.

##### 6.2 Implementation Protocol
- All `outbox_events` carry a time-ordered UUID v7 `id` and `created_at` timestamp with microsecond precision.
- Events belonging to the same aggregate share an `aggregate_type` and `aggregate_id`.
- The outbox worker queries events ordered strictly by `created_at ASC, id ASC`.
- When pushing to Redis queues, jobs for the same aggregate use a deterministic queue key (`application-{id}`) to enforce sequential worker processing when strict ordering is required.

#### Decision 7: Listener Idempotency Strategy

##### 7.1 Idempotency Requirement
Because the transport operates under **At-Least-Once Delivery**, every event listener must assume it may receive the same event multiple times.

##### 7.2 Idempotency Check Protocol
Every listener must implement the `IdempotentListener` pattern using Redis or DB deduplication:

```php
final class SendApplicationApprovedNotification implements ShouldQueue
{
    public function handle(ApplicationApproved $event): void
    {
        $dedupKey = "processed_events:{$event->eventId}:" . self::class;

        // Atomic check-and-set with 7-day TTL
        if (! Redis::set($dedupKey, 'true', 'EX', 604800, 'NX')) {
            return; // Already processed — skip silently
        }

        // Execute idempotent business logic...
    }
}
```

##### 7.3 State-Based Idempotency
Whenever possible, listeners rely on domain entity state rather than lookup tables.
Example: A listener setting `is_verified = true` is inherently idempotent regardless of how many times it executes.

---

### PART IV — PAYLOAD STANDARDS & VERSIONING

#### Decision 8: Event Payload Standards

##### 8.1 Primitive-Only Rule
Event payloads must contain **only primitive types**. Live Eloquent model instances, PDO connections, or complex object graphs are strictly prohibited.

##### 8.2 Standard Payload Schema

Every event class must serialize to the following JSON structure:

```json
{
  "event_id": "01927f3a-abc1-7000-9bd4-12e3c4d5e6f7",
  "event_type": "Modules\\Applications\\Events\\ApplicationApproved",
  "event_version": 1,
  "aggregate_type": "Application",
  "aggregate_id": "01927f3a-9999-7000-9bd4-9999c4d5e6f7",
  "occurred_at": "2026-07-31T20:55:00.000000Z",
  "actor_id": "01927f3a-1111-7000-9bd4-1111c4d5e6f7",
  "data": {
    "contestant_id": "01927f3a-2222-7000-9bd4-2222c4d5e6f7",
    "season_id": "01927f3a-3333-7000-9bd4-3333c4d5e6f7",
    "status": "accepted"
  }
}
```

##### 8.3 Field Conventions
- `event_id`: UUID v7 identifying this specific event execution.
- `occurred_at`: ISO-8601 UTC timestamp with microsecond precision.
- `actor_id`: UUID v7 of the user who triggered the operation (`null` if system-initiated).
- `data`: Key-value map of primitive values using `snake_case` keys.

#### Decision 9: Event Versioning Policy

##### 9.1 Non-Breaking Additions (No Version Bump)
Adding a new optional key to `data` is backward-compatible. Existing consumers ignore unknown keys. No version increment is required.

##### 9.2 Breaking Schema Changes (Version Increment Required)
The following changes break compatibility and require creating a new event version:
- Removing a key from `data`.
- Renaming a key.
- Changing a key's data type (e.g. string to array).
- Changing the business semantics of an event.

##### 9.3 Version Increment Protocol
When a breaking change is necessary:
1. Create a new event class with a version suffix: `ApplicationApprovedV2`.
2. Increment `event_version: 2` in the payload.
3. Maintain the `V1` event class and listeners for a minimum **90-day deprecation window**.
4. The Use Case emits both `V1` and `V2` events during the transition window.
5. After 90 days and verification that all consumers have migrated, remove `V1`.

---

### PART V — MODULE COMMUNICATION MATRIX

#### Decision 10: Cross-Module Event Authorization Matrix

This decision establishes which module is authorized to **publish** specific events, and which modules are authorized to **subscribe** to them.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ PUBLISHER MODULE           EVENT NAME               AUTHORIZED SUBSCRIBERS  │
├─────────────────────────────────────────────────────────────────────────────┤
│ Core                       UserRegistered           Contestants, Notifications│
│ Core                       UserDeactivated          Contestants, Judges, Auth │
│ Contestants                ContestantUpdated        Search, Applications     │
│ Applications               ApplicationSubmitted     Notifications, Search    │
│ Applications               ApplicationStatusChanged Notifications, Search, Aud│
│ Applications               ApplicationApproved      Competition, Notifications│
│ Applications               ApplicationRejected      Notifications, Search    │
│ Competition                SeasonActivated          Cache, Applications      │
│ Competition                StageStarted             Evaluations, Streaming   │
│ Competition                StageCompleted           Evaluations, Results     │
│ Competition                StageResultsPublished    Notifications, Content   │
│ Judges                     JudgeAssigned            Notifications, Competition│
│ Evaluations                EvaluationSubmitted      Competition, Search      │
│ Evaluations                EvaluationApproved       Competition, Results     │
│ Videos                     VideoUploaded            Videos (Processor Job)   │
│ Videos                     VideoProcessingCompleted Applications, Search     │
│ Videos                     VideoPublished           Content, Search, Cache   │
│ Content                    ContentPublished         Search, Cache, CDN       │
│ Streaming                  StreamStateChanged       Cache, Notifications     │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Rule**: A module MAY NOT subscribe to an event if it is not explicitly listed as an authorized subscriber in this matrix without a formal architecture review.

---

### PART VI — ASYNC BOUNDARIES & TRANSACTION RULES

#### Decision 11: Async Boundaries — Synchronous vs. Asynchronous Operations

To guarantee sub-200ms HTTP API responses, operations are strictly segregated:

##### 11.1 Must Remain Inside Synchronous DB Transaction
- Primary entity state mutation (`UPDATE applications SET status = 'accepted'`).
- Status history append (`INSERT INTO application_status_histories`).
- Transactional Outbox write (`INSERT INTO outbox_events`).

##### 11.2 Must Exit to Asynchronous Background Workers
- Transcoding videos via FFmpeg (`video` queue).
- Sending emails, SMS, or push notifications (`notifications` queue).
- Syncing records to Meilisearch (`search` queue).
- Purging Cloudflare CDN caches (Background job).
- Generating PDF certificates or heavy export reports (`default` queue).

---

### PART VII — PLATFORM EVENT CATALOG

#### Decision 12: Exhaustive Platform Event Catalog

This catalog is the single source of truth for all domain events across the platform's 15 modules.

| # | Event Name | Publisher Module | Aggregate Root | Key Payload Data | Primary Subscribers |
|---|---|---|---|---|---|
| 1 | `UserRegistered` | Core | `User` | `user_id`, `email`, `type` | Contestants, Notifications |
| 2 | `UserDeactivated` | Core | `User` | `user_id`, `type` | Contestants, Judges |
| 3 | `ContestantProfileUpdated` | Contestants | `Contestant` | `contestant_id`, `user_id`, `country_id` | Search |
| 4 | `ContestantDocumentUploaded` | Contestants | `Contestant` | `contestant_id`, `document_id`, `media_asset_id` | Core (Audit) |
| 5 | `ApplicationSubmitted` | Applications | `Application` | `application_id`, `contestant_id`, `season_id` | Notifications, Search |
| 6 | `ApplicationStatusChanged` | Applications | `Application` | `application_id`, `from_status`, `to_status` | Notifications, Search |
| 7 | `ApplicationApproved` | Applications | `Application` | `application_id`, `contestant_id`, `season_id` | Competition, Notifications |
| 8 | `ApplicationRejected` | Applications | `Application` | `application_id`, `reason` | Notifications, Search |
| 9 | `ApplicationDataRequested` | Applications | `Application` | `application_id`, `notes` | Notifications |
| 10 | `VideoReuploadRequested` | Applications | `Application` | `application_id`, `notes` | Notifications |
| 11 | `SeasonCreated` | Competition | `Season` | `season_id`, `year` | Cache |
| 12 | `SeasonActivated` | Competition | `Season` | `season_id`, `year` | Cache, Applications |
| 13 | `SeasonClosed` | Competition | `Season` | `season_id` | Cache |
| 14 | `StageScheduled` | Competition | `Stage` | `stage_id`, `season_id`, `type` | Notifications |
| 15 | `StageStarted` | Competition | `Stage` | `stage_id`, `type` | Evaluations, Streaming |
| 16 | `StageCompleted` | Competition | `Stage` | `stage_id`, `type` | Evaluations |
| 17 | `StageResultsPublished` | Competition | `Stage` | `stage_id`, `published_by` | Notifications, Content |
| 18 | `JudgeAssignedToStage` | Competition | `Stage` | `stage_id`, `judge_id` | Notifications |
| 19 | `EvaluationStarted` | Evaluations | `Evaluation` | `evaluation_id`, `judge_id`, `application_id` | Competition |
| 20 | `EvaluationScoresUpdated` | Evaluations | `Evaluation` | `evaluation_id`, `judge_id` | Core (Audit) |
| 21 | `EvaluationSubmitted` | Evaluations | `Evaluation` | `evaluation_id`, `total_score` | Competition |
| 22 | `EvaluationApproved` | Evaluations | `Evaluation` | `evaluation_id`, `approved_by` | Competition, Results |
| 23 | `VideoUploaded` | Videos | `Video` | `video_id`, `media_asset_id`, `contestant_id` | Videos (Worker) |
| 24 | `VideoProcessingStarted` | Videos | `Video` | `video_id` | Core (Audit) |
| 25 | `VideoProcessingCompleted` | Videos | `Video` | `video_id`, `duration_seconds` | Applications, Search |
| 26 | `VideoProcessingFailed` | Videos | `Video` | `video_id`, `error_message` | Core (Audit), Notifications |
| 27 | `VideoPublished` | Videos | `Video` | `video_id`, `application_id` | Content, Search, Cache |
| 28 | `StreamStarted` | Streaming | `Stream` | `stream_id`, `season_id`, `title` | Cache, Notifications |
| 29 | `StreamStopped` | Streaming | `Stream` | `stream_id` | Cache |
| 30 | `ContentPublished` | Content | `Page / Announcement` | `content_type`, `content_id`, `slug` | Search, Cache, CDN |
| 31 | `NotificationDispatched` | Notifications | `Notification` | `notification_id`, `user_id`, `channel` | Notifications (Queue) |

---

## Eventing Anti-Patterns (Prohibited Practices)

The following practices are **explicitly prohibited**. Any pull request containing these patterns will be automatically rejected.

1. ❌ **No Pre-Commit Event Dispatch**: Dispatching events to queue workers before `DB::commit()` executes. (Violates Principle 2).
2. ❌ **No Eloquent Models in Payloads**: Passing `$event->application` as a model object instead of primitive UUID strings. (Violates Principle 4).
3. ❌ **No Command Verbs for Event Names**: Naming events with imperative verbs like `SubmitApplication` or `ProcessVideo`. (Violates Principle 1).
4. ❌ **No Non-Idempotent Listeners**: Writing a listener that fails or duplicates side-effects when executed multiple times with the same event. (Violates Principle 3).
5. ❌ **No Cross-Module Direct DB Writes Inside Listeners**: A listener in Module A directly executing `DB::table('module_b_table')->update()`. Listeners must invoke the subscriber module's own Use Case / Domain Service. (Violates ADR-002).
6. ❌ **No Infinite Event Chaining**: Listener for Event A emits Event B, whose listener emits Event A. Maximum event chain depth: 2 levels.
7. ❌ **No Events as Synchronous Request/Response Replacements**: Using events when an immediate synchronous return value is required by the caller.

---

## Consequences

### Positive Consequences
- **Complete Decoupling**: Modules communicate strictly through asynchronous domain events without knowing about each other's internal concrete classes.
- **Zero Event Loss Guarantee**: The Transactional Outbox pattern guarantees that no event is lost during Redis queue downtime.
- **Fast HTTP Response Times**: Offloading notifications, transcoding, indexing, and CDN purges to queues guarantees sub-200ms API response times.
- **Replayability & Auditability**: System events are preserved in `outbox_events` for 30 days, enabling instant replay and full event-sourcing visibility.
- **Predictable Event Catalog**: All 31 platform events are cataloged with strict primitive schemas.

### Negative Consequences / Trade-offs
- **Outbox Worker Polling Delay**: Introduces a 2–5 second latency between DB commit and queue processing.
- **Deduplication Overhead**: Listeners must check Redis deduplication keys before executing, adding minor Redis read overhead.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-004: API Standards & Conventions](./ADR-004-api-standards-conventions.md)
- [ADR-005: Database Architecture](./ADR-005-database-architecture.md)
- [ADR-006: Infrastructure Architecture](./ADR-006-infrastructure-architecture.md)
- [ADR-ROADMAP.md](../ADR-ROADMAP.md)
- [Transactional Outbox Pattern — Martin Fowler](https://martinfowler.com/articles/patterns-of-distributed-systems/outbox.html)
- [Enterprise Integration Patterns — Gregor Hohpe](https://www.enterpriseintegrationpatterns.com/)
