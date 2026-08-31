# ADR-020: Notifications — Email, the Log, Preferences, and a Retry That Retries

| | |
|---|---|
| **Status** | **Accepted — 2026-08-31.** Q1 and Q2 closed by the board; recorded as D9 and D10. |
| **Date** | 2026-08-31 |
| **Depends on** | ADR-001 (the module and its promise), ADR-002 (module boundaries), ADR-005 D2 (UUIDs), ADR-008 (domain events), ADR-012 (application layer), ADR-017 (activity log — the store taxonomy) |
| **Relates to** | ADR-016 epic table row 8 |

---

## Why this document exists

The epic table gives Epic 8 one word: *"Notifications"*. Unlike Epic 7, the
subject is described elsewhere — ADR-001 promises a module for **"multi-channel
notification dispatch and preferences"** across **email, SMS and push**.

Measured against the code, that promise is unmet in a specific and unusual way:
the module exists as a complete-looking structure that has never done anything.
This ADR records what is actually there, and fixes a scope that closes the gap
without importing costs nobody has agreed to.

---

## What exists today (measured, not assumed)

Measured on `main` at `eef785b`, 2026-08-31. Read-only; nothing was written.

| Thing | State |
|---|---|
| **The module** | 16 PHP files: entity, repository + contract, value objects, two controllers, routes, resource, an architecture test |
| **`notification_logs`** | Exists — `id, user_id, channel, template_key, payload, status, sent_at, error, created_at, updated_at`. **0 rows in the development database** |
| **Anything that writes it** | **Nothing.** No module outside Notifications references the table, the repository or the service contract |
| **`NotificationsServiceContract`** | **An empty interface** — two comment lines, no methods. It is also **not bound** in `NotificationsServiceProvider`, which registers only the repository. Resolving it would fail |
| **Mail that is actually sent** | Exactly one site: `CreateAdminUserUseCase` calls `Mail::to()->send(new InvitationMail(...))`. **Synchronous**, and it writes no log |
| **Mailables** | One: `InvitationMail` |
| **Jobs** | **No `Jobs` directory anywhere in the repository** — while `jobs`, `failed_jobs` and `job_batches` tables exist, `QUEUE_CONNECTION=redis`, and Horizon runs as a service supervising the `default` queue |
| **Preferences** | **No table.** ADR-001 promises them; nothing implements them |
| **Channels** | `NotificationChannel` allows `email`, `sms`, `push`. Only email has an implementation anywhere |
| **Domain statuses** | The entity models `queued`, `sent`, `failed`, with `markSent()` / `markFailed()` and `releaseEvents()` |
| **The retry** | See below |

### The retry lies twice, in two layers

**In the panel**, the button calls nothing at all:

```tsx
<Button size="sm" variant="ghost" onClick={() => toast.success(t('retry_success'))}>
```

It reports success without a request.

**On the server**, had it been called, `AdminNotificationController::retry()` sets
the row to `'retrying'`, answers *"Notification queued for retry"*, and the next
line is:

```php
// TODO: dispatch RetryNotificationJob::dispatch($log->id)
```

Two independent claims of work that does not happen, stacked. This is the same
defect already recorded for the four unrouted `users.*` permissions and for
`preferred_locale`, in its clearest form yet.

`'retrying'` is also **not a status the domain knows** — the entity models
`queued`, `sent` and `failed`. The controller reaches `NotificationLogModel`
directly, bypassing its own repository and aggregate, which is why it can write
a state no invariant allows. That controller is in `CONTROLLER_MODEL_BASELINE`.

---

## Decisions

| # | Decision | Rationale and trade-offs |
|---|---|---|
| **D1** | **Email only. SMS and push are deferred, and the seam that admits them is kept.** | `NotificationChannel` already allows all three, and the log already carries a `channel` column, so nothing needs redesigning to add one later. What SMS and push would need is a provider, credentials, and a running cost — decisions with a bill attached that nobody has taken. Implementing a channel with no provider would produce a fourth thing that claims to work and does not, which is the defect this epic exists to remove. **Trade-off:** ADR-001's "multi-channel" stays formally unmet; it is met in shape, not in delivery |
| **D2** | **Every notification the platform sends is logged, through the module.** | The log is the point of the module and it has never held a row, because the one real send bypasses it. Today that is one site — the invitation — so "every" is small and verifiable now, which is precisely when the rule is cheap to establish |
| **D3** | **Sending moves onto the queue. `SendNotificationJob` is the first job in the repository.** | Two reasons, and only the second is about notifications. `Mail::send()` is synchronous, so a slow or failing SMTP server today delays or fails the HTTP request that triggered it — user creation blocks on mail. And **a retry cannot exist without it**: retrying is re-dispatching, so D5 has no meaning until there is something to dispatch. The infrastructure is already provisioned and idle — Redis, the three tables, Horizon supervising `default`. **Trade-off:** an async send can fail after the request succeeded, which is exactly what the log and the retry are for |
| **D4** | **Preferences are per account and per notification type, stored in their own table.** | ADR-001 promises them and nothing implements them. They belong beside the account rather than inside `users`: they are a growing list keyed by type, and a column per type is a migration per type — the same reasoning ADR-016 D2 used for social links. **What they are NOT:** a way to opt out of everything. Which notifications may be declined is settled by D9: the invitation is mandatory, everything else is declinable |
| **D5** | **Retry re-dispatches, or the button goes.** | A control that reports success without acting is worse than a missing one. Under D3 there is a job to re-dispatch, so retry becomes real: it re-queues the send and the log records the new attempt. The panel's button is wired to the endpoint it has always pretended to call |
| **D6** | **`'retrying'` is not introduced as a status. The vocabulary stays `queued`, `sent`, `failed`.** | The controller invented it by writing through Eloquent past its own aggregate. A retry moves a row back to `queued`, which is what a retry IS — there is no fourth state, and adding one would mean every reader learning a word the domain does not use |
| **D7** | **`NotificationsServiceContract` gains the methods other modules call, and is bound.** | It is an empty, unbound interface today, which is why nothing consumes it: there is nothing to consume, and resolving it would fail. D2 requires a way in from Core, and ADR-002 says that way is the contract |
| **D8** | **`notification_logs` is a third store, not merged with `audit_logs` or `activity_logs`.** | ADR-017 D2 fixed the taxonomy: HTTP in one, business change in the other. A notification is neither — it is an outbound delivery attempt with a status that CHANGES after the fact, which is exactly what a log of things that happened must never do. It also already has its own table with the right shape |

---

## What this epic does **not** do

* **No SMS and no push** (D1) — no provider, no credentials, no cost.
* **No templating engine.** `template_key` is recorded as it is used; choosing a template system is not in scope.
* **No notification centre in the panel**, and no bell in the header. Epic 7 deliberately left that out and this does not add it.
* **No changes to what triggers a notification.** The invitation is the only sender today; this epic logs and queues it, it does not invent new events to notify about.

---

## Debt recorded, deliberately not fixed here

* `AdminNotificationController` reaches Eloquent directly and sits in
  `CONTROLLER_MODEL_BASELINE`. D6 removes the reason it did so; whether the
  controller is refactored off the baseline is a separate cleanup.
* `ContestantNotificationController` exposes a contestant-facing read of the same
  table. It is outside this epic's admin scope and is left alone.
* **The notifications table does not live-update while a retry runs.** The
  response returns as soon as the job is queued, so the row reads `queued`
  until somebody refreshes and Horizon has finished. Polling or a websocket is
  a larger decision than this epic; the refresh control is already there.
* **`meta.total` is not used by the panel's counters.** The three cards count
  the loaded page, which is the first twenty rows, and say so in their labels.
  Real totals need either filtered count requests or a summary endpoint.
* **Test fixtures still use `template_key` values nothing sends** —
  `application.approved` among them. Harmless, and now visibly so: retrying one
  is refused with a reason rather than silently succeeding.
* **No preferences screen.** The endpoints exist and are tested; the UI would
  render an empty list while every notification the platform sends is
  mandatory. See D12.
* **Style is unguarded.** `pint --test` fails on 7 files, none of them touched
  by this epic, and Pint appears in no CI step and no gate script. Formatting
  therefore drifts silently; whether to add it as a gate is a repository-wide
  decision, not this epic's.
* **`docker-compose.yml` still declares `version: '3.8'`**, which Compose warns
  about on every single command. Cosmetic, untouched here to keep the
  environment change to the one thing it is about.

---

## Q1 — CLOSED, and Q2 — CLOSED

Both decided by the board 2026-08-31 and recorded here for citation from code.

| # | Decision | Rationale |
|---|---|---|
| **D9** | **The account-creation invitation is mandatory and cannot be declined. Every other notification is subject to the account's preferences.** | The invitation is the only way an account can be claimed — ADR-016 D14 makes accounts Pending Activation until it is accepted, and no administrator ever sets another user's password. A preference that could switch it off would let somebody lock themselves out of an account they have not yet entered. So preferences are a table of what MAY be declined, not a switch over everything, and the mandatory set is expressed in the code rather than left to the data |
| **D10** | **When retries are exhausted the log ends at `failed`, keeping the reason. Manual retry stays available. Nothing is sent to announce the failure.** | Consistent with D6: `failed` is a status the domain already models, and no new word enters the vocabulary for "failed for the last time". The `error` column exists and holds the reason, so a human can see why rather than that. Manual retry stays because a failure is often environmental — SMTP down for an hour — and the operator is the one who knows it has been fixed. **No automatic failure notification**, deliberately: notifying about a broken notification channel through that same channel is the one message least likely to arrive, and building an alerting path is a larger decision than this epic. **Trade-off, stated:** a failed notification is only discovered by somebody looking at the screen |

---

## D11 — decided during S4, because implementation asked a question the ADR had not

D5 says a retry re-dispatches. Building it surfaced the question underneath:
**re-dispatch what?** The job carries a `Mailable`, and nothing stores one.

For the platform's only real template, nothing could. `IssueInvitationUseCase`
returns the plaintext accept token once, never stores it, and states so in its
own docblock; only the sha256 is in the database. The log deliberately holds
neither the token nor the recipient's address, because administrators read
other people's rows. **The original invitation mail is unreproducible by
design.**

| # | Decision | Rationale |
|---|---|---|
| **D11** | **A retry REBUILDS the message rather than replaying it, through a per-template factory that the owning module registers. Retrying an invitation issues a fresh invitation, which REPLACES the open one. A template with no registered factory is refused with `409 NOT_RETRYABLE`.** | Decided by the board 2026-08-31 after the measurement above. Rebuilding is the only option that exists, and the module that owns a template is the only thing that knows how — so the dependency runs from Core into Notifications, never the other way (ADR-002), and Core hands back the address along with the message. **This is not the templating engine this epic excludes:** nothing here renders, stores or edits a message; the registry records which module can rebuild which `template_key`. **The consequence that had to be closed first:** `InvitationRepository::save()` is an `updateOrCreate` keyed on the invitation id, so a naive second issue ADDED a row — two unaccepted invitations, two working tokens, and `findOpenForUser` breaking the `created_at` tie in favour of the superseded one. Re-issuing now reuses the open invitation's id, so one account keeps one invitation and the old token dies. **Refusing out loud** is the alternative to the failure this epic exists to remove: a template nobody can rebuild would otherwise return success and queue nothing, one template at a time |

---

## D12 — decided during S5, building D4 and D9

| # | Decision | Rationale |
|---|---|---|
| **D12** | **A declined notification produces NO log row and no job. Absence of a preference row means enabled. Which types are mandatory lives in a code catalogue that modules register into, not in the data. A retry asks the same question the send path asks.** | Decided by the board 2026-08-31. **No row**, because D6 has no status for "suppressed" and inventing one teaches every reader a word the domain does not use — while writing `queued` and dispatching nothing is the exact defect this epic removed. **Absence means enabled**, so the table holds refusals rather than settings: an account that never opens the screen has no rows, and a new notification type starts working for every existing account without a backfill. **Mandatory in code** is D9's own wording made literal — rows can be seeded, migrated or written by a future screen, and one row saying `false` against `invitation.created` would leave an account permanently unable to receive the only message that can let it in. **Retry asks too**, because it is the second path that pushes a job, and a rule enforced on one of two paths is a rule with a hole in it; unlike an ordinary send it refuses out loud, since a person is waiting for an answer. **Trade-off, stated:** the log cannot distinguish "declined" from "never triggered" — the preference is the record |

**Measured while building it:** `invitation.created` is the only template key in
production code, and D9 makes it mandatory — so **the set of notifications an
account may actually decline is empty today**. The machinery is in the send
path regardless, and the tests prove it through the same catalogue seam a
module will use when it first notifies about something optional.

**No preferences screen was built.** It would render an empty list, and adding
a route, a menu entry and three locales to display nothing is not a saving for
anyone. The self-service endpoints exist and are tested, so the screen is a
small piece of work on the day there is a second notification type.

---

## Consequences

* The invitation send becomes queued, so user creation stops blocking on SMTP —
  and starts being able to fail after the request has already succeeded, which
  is what the log and the manual retry exist to make visible.
* `notification_logs` starts holding rows for the first time.
* The panel's retry button starts calling the endpoint it has always claimed to
  call, and shows only on a row that can actually be retried.
* **Retrying an invitation invalidates the previous accept link.** If the first
  mail eventually arrives after a retry, its link is dead. That is the correct
  trade — the alternative is two live ways into one account — but it is a
  behaviour change worth knowing about (D11).
* A module that adds mail later must register a factory before its
  notifications can be retried. Until it does, a retry says so.
* **`queue()` now returns `?string`.** Null means the account declined the
  notification, so no row was written and nothing was dispatched. Callers that
  need to know whether something was sent must check it.
* A module that adds mail later must also declare its type in the catalogue.
  Until it does, the type is unknown: it still sends — silently dropping mail
  nobody declared would be worse — but no account can decline it.
* **Notification types contain a dot.** Dot-path accessors (Laravel's
  `assertJsonPath`, lodash `get`) read `digest.weekly` as nesting. The
  vocabulary is not changing — `template_key` has always been dotted — but a
  consumer reading the preferences object should index it directly.
* A notification that exhausts its retries sits at `failed` until a person acts.
  That is a deliberate choice, not an oversight — see D10.
