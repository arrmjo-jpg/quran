# ADR-017: Activity Log

| | |
|---|---|
| **Status** | **Accepted — 2026-08-22.** Closes ADR-016 Q1, which was open and named Epic 5 as the epic it gated. |
| **Date** | 2026-08-22 |
| **Depends on** | ADR-002 (module boundaries), ADR-005 D2 (UUIDs), ADR-012 (application layer), ADR-015 (identity & access) |
| **Relates to** | ADR-016 Q1 — answered here in full |

---

## Why this document exists

Epic 5 builds an activity log. The platform already contains three things that look like one and
are not, plus a fourth that is one and was never connected. Building without saying which is which
would produce a fifth.

This ADR records what was measured, answers ADR-016 Q1, and fixes the contract before any code is
written — because the admin panel already ships a screen that invented a contract for an endpoint
that does not exist, and that screen must not become the specification.

---

## What exists today (measured, not assumed)

| Thing | State |
|---|---|
| **46 domain event classes** across 8 modules | Every one declares a snake_case `TYPE` constant and exposes `toPayload()`. Every payload carries `occurred_at`. The first payload key is the aggregate's own id |
| **Consumers of those events** | **None.** No `Event::listen`, no listener class, no `EventServiceProvider::$listen` anywhere in `app/` or `Modules/` |
| **Events that actually reach the dispatcher** | Only from **Competition, Core, Organization, Contestants**. Aggregates in **Applications, Countries, Evaluations, Media, Notifications, Streaming, Videos** call `recordEvent()` and nothing ever calls `releaseEvents()` — those events are recorded into an array and dropped |
| **`outbox_events` table** | Created in the repository's 8th commit. Shape fits domain events exactly. **Zero rows, zero code references** |
| **`audit_logs` table + middleware** | Live. 2 713 rows in the dev database. Records actor, method, path, route name, status, ip, user agent, device, sizes, `correlation_id`, duration. **Never the request or response body** |
| **`audit.view` permission** | Exists, held by `super_admin` alone, and **no route consults it** — a dormant grant |
| **`AuditExplorerPage`** | Exists, gated on `audit.view`, calls `GET /admin/system/audit-logs` — **which does not exist** — and expects `user_name`, `user_role`, `action`, `entity_type`, `entity_id`, `result` |
| **`spatie/laravel-activitylog` v5** | In `composer.json` and `vendor/`. No migration published, no `activity_log` table, `LogsActivity` used nowhere |
| **`correlation_id`** | Lives entirely in the HTTP layer. Nothing in `Application/` or `Domain/` can see it |

---

## D1 — Replace the package, do not reuse it (ADR-016 Q1, answered)

`spatie/laravel-activitylog` is **not** used. An activity log is built for this platform.

ADR-016 asked the board to answer this explicitly "rather than let the installed package decide by
inertia — which is how the half-Spatie hybrid schema ADR-015 had to untangle came about." The
answer is the same as ADR-015 gave for `laravel-permission`, and for reasons of the same kind:

* **`$table->id()`** — bigint auto-increment. ADR-005 D2 makes every primary key a UUID. A single
  bigint table is exactly the hybrid ADR-015 spent an epic removing.
* **`nullableMorphs('subject')` / `nullableMorphs('causer')`** store PHP class names in the
  database. That writes `Modules\Contestants\Infrastructure\Database\Models\ContestantModel` into a
  row, making a rename a data migration and pointing one module's table at another module's class
  name — the coupling ADR-002 exists to prevent.
* **It is built for Eloquent model events**, via a `LogsActivity` trait on models. This platform's
  events are domain events released from aggregates. Reusing the package would mean logging a
  different thing than the one decided here.
* **No `correlation_id`**, and `description` is a required free-text column that a domain event has
  no natural value for.

The package stays installed and unused; removing a dependency is not this epic's business.

## D2 — Three stores, three purposes, no merging

`activity_logs` is a **new table**, separate from both of the following, and neither is changed.

| | Answers | Written by |
|---|---|---|
| `audit_logs` | *Who called which endpoint, and what did it return?* | `AuditLoggingMiddleware`, per HTTP request |
| `activity_logs` | *What changed in the business, and who changed it?* | A listener, per domain event |
| `outbox_events` | *What must be published reliably to somewhere else?* | Nothing yet — it keeps its purpose |

**`outbox_events` is not reused**, although its shape fits. It is the outbox pattern —
`status`, `attempts`, `error_message` describe delivery to an external consumer with retries. An
activity log is a read model that is never retried and never delivered. Two things that share a
shape and not a purpose; giving one table both jobs means neither can change without the other.

The two logs are **complements, not substitutes**. `audit_logs` sees a request that changed nothing;
`activity_logs` sees a change made by a console command with no request at all.

## D3 — The contract, decided here and not by the screen

`AuditExplorerPage` is **not** a golden master. It calls an endpoint that has never existed and its
shape was invented client-side, so there is nothing to preserve.

An activity log row is:

| Field | Source |
|---|---|
| `id` | UUID |
| `action` | the event's `TYPE` constant — already snake_case on all 46 |
| `entity_type` | from the registry in D6 |
| `entity_id` | the aggregate's id |
| `actor_id` | see D4 — nullable |
| `actor_type` | `user` or `system` |
| `payload` | the event's own `toPayload()`, in full |
| `correlation_id` | nullable — see D5 |
| `occurred_at` | the event's `occurred_at`, **not** the write time |
| `created_at` | the write time |

**`occurred_at` and `created_at` are both kept and are not the same fact.** A membership backdated
to last September is an event that *happened* in September and was *recorded* today. Collapsing
them would make every backdated entry look like a September record — the failure this whole design
exists to avoid.

**`result` is not a field.** It belongs to HTTP, where a request can fail; a domain event that has
been dispatched has already happened. `audit_logs` records failure, and the two are linked by D5.

**`user_role` is not a field.** A role at write time is a snapshot that stops being true when the
role changes, and resolving it per row is a query per row. If a reader needs it, that is a join
against live data, deliberately.

**The payload is whatever the event carries, and nothing more.** Events already make the privacy
decision: `ContestantUpdated` carries the *names* of changed fields and not their values,
specifically so a national identity document cannot sit in an event payload. The log inherits that
judgement rather than re-making it, and never reads from the database to enrich a row.

## D4 — The actor, without touching 46 contracts

19 of 46 events carry `by_user_id`; 27 do not. **No event contract is changed to add one.**

The actor is resolved at write time, in this order:

1. **The event's own `by_user_id`**, when it has one. It is the most reliable source — it knows the
   actor even when there is no HTTP request.
2. **The authenticated user of the current request**, when there is one. A listener runs
   synchronously inside the request, so this is available without the event carrying it.
3. **`system`**, otherwise — a console command, a scheduled job, a seeder.

`actor_type` records which of the three applied, so a reader can tell "nobody was logged in" from
"we did not record who". **A system actor is written as `system`, never as an invented user.**

New events *should* carry `by_user_id` when a user causes them. That is guidance for future work,
not a retrofit of existing ones.

## D5 — `correlation_id` is captured by the listener, never by the event

`correlation_id` is an HTTP concept and has no place in a domain event. Measured: nothing in
`Application/` or `Domain/` can reach it today.

The listener runs inside the request, so it reads the header at write time — which links an
activity row to the `audit_logs` row for the same request without a single event contract changing.
It is **nullable**: an event dispatched from a console command has no request and therefore no
correlation id, and that is information, not a gap.

## D6 — An explicit event registry, guarded for completeness

`entity_type` and `entity_id` cannot be derived reliably. The convention — first payload key is the
aggregate id — holds for the events checked, but a convention is not a contract, and
`all_judges_completed` shows how quickly a naming heuristic breaks.

So the mapping from event class to `entity_type` and its id key is an **explicit registry**, and a
test asserts that **every class under `Modules/*/Domain/Events` appears in it**. Adding an event
without deciding how it is logged fails the suite. This is the pattern
`ModuleBoundary::BASELINE` established: an exhaustive list beats a clever rule, and a guard makes
the list impossible to forget.

## D7 — Retention is not decided here

There is no retention policy today, and none is invented. `audit_logs` already grows unbounded —
2 713 rows in a development database alone — and `activity_logs` will grow the same way.

Epic 5 writes the log and does **not** delete or archive anything. Choosing a retention period is a
product and compliance decision with GDPR-shaped consequences, and inventing "one year" or "five
years" here would be exactly the kind of number that gets treated as a decision later because it is
written down. **Recorded as an open question, deliberately unanswered.**

## D8 — The muted modules are in scope

Seven modules record events that nothing releases. Epic 5 releases them, because an activity log
covering four modules while the rest of the system drops its events on the floor would be a log
nobody can trust to be complete.

**Releasing them is safe before the listener exists**, and that is why Story 1 precedes Story 2:
`event()` with no listener is a no-op. The order makes the behavioural change and the consumer two
separate, separately reviewable steps.

`SubmitApplicationGoldenMasterTest` contains a test named `PINNED: no domain event reaches a
listener`, whose comment says the extraction "must NOT add the dispatch loop in the same step …
this test is what will make it visible when it happens." Story 1 is that moment. The test is
**updated with BEFORE/AFTER, not deleted** — it was written to be broken deliberately, once.

---

## Stories

| # | Story | Contains |
|---|---|---|
| 0 | **ADR & contract** | This document. ADR-016 Q1 closed |
| 1 | **Events reach the dispatcher** | Release the seven muted modules; update the pinned golden master |
| 2 | **The store** | `activity_logs`, the listener, the registry and its completeness guard |
| 3 | **The API** | Read endpoint behind `audit.view`, filters, pagination |
| 4 | **The screen** | `AuditExplorerPage` rebuilt on the real contract, AR/EN/ES |
| 5 | **Hardening & review** | Gates, critical review, PR |

---

## Consequences

* The dormant `audit.view` grant becomes live in Story 3 — a real change in what `super_admin` can
  do, arriving with no diff against any permission file, exactly as D17 of ADR-016 described for
  `contestants.update`. Recorded so nobody has to find the moment it happened.
* Seven modules begin dispatching events they previously dropped. Nothing listens until Story 2, so
  the observable change is deferred by one story on purpose.
* `activity_logs` grows without bound until D7 is answered.
