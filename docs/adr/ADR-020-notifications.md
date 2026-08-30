# ADR-020: Notifications — Email, the Log, Preferences, and a Retry That Retries

| | |
|---|---|
| **Status** | **Proposed — 2026-08-31.** Awaiting the board. |
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
| **D4** | **Preferences are per account and per notification type, stored in their own table.** | ADR-001 promises them and nothing implements them. They belong beside the account rather than inside `users`: they are a growing list keyed by type, and a column per type is a migration per type — the same reasoning ADR-016 D2 used for social links. **What they are NOT:** a way to opt out of everything. Which notifications may be declined is a product question (see Q1) |
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

---

## Open questions

**Q1 — which notifications may be declined?** Preferences are meaningless
without an answer, and the answer is a product decision, not a technical one:
an invitation almost certainly may not be switched off, because it is the only
way an account can be claimed. The board should say which types are
**mandatory** and which are **optional** before D4 is built, or preferences
will be built around a guess.

**Q2 — what happens after the last retry?** A failed send that is retried and
fails again must end somewhere. Laravel's `failed_jobs` catches the job, but
the *notification* row's final state, and whether anyone is told, is undecided.
Recorded rather than assumed because "it stays failed forever and nobody looks"
is a decision if taken deliberately and a defect if taken by default.
