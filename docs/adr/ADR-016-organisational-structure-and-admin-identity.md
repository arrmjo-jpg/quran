# ADR-016: Organisational Structure and Admin Identity

| | |
|---|---|
| **Status** | **Accepted — 2026-08-18.** The two cross-epic questions (Q2 row-level security, Q7 user creation) are decided below. Q1 and Q3-Q6 are **deferred to the epic each belongs to** and carry a named blocking marker; they are epic-local, so they gate their own epic rather than this document. |
| **Date** | 2026-08-18 |
| **Supersedes** | **ADR-015's decision that no admin-facing user-creation endpoint exists** (see Q7). Effective on acceptance. ADR-003 is **not** superseded — it already provides for provisioning by a Super Administrator holding `users.create`, and forbids only admin self-registration, which stays forbidden. |
| **Depends on** | ADR-015 (Identity & Access), ADR-003 (Authentication), ADR-002 (module boundaries), ADR-005 (database) |

---

## Why this document exists

The board has settled a list of decisions for the next thirteen epics — user lifecycle,
contestants, admin profiles, an identity dashboard, an activity log, security, notifications,
bulk actions, search, timeline, invitations, and the remaining centre/circle work.

Those decisions were reached outside this repository. The rule the board set is that
**implementation is built against a document in the repository, never against a summary or a
conversation**, for the same reason ADR-015 had to be brought onto the implementation branch
before the identity epic could be merged: a branch that does not contain its own specification
cannot be reviewed against one.

This document is that reference. It records what has been decided, what was measured in the
code, and — explicitly — what has not been justified yet. It is a draft precisely because the
last category is not empty.

---

## What exists today (verified against the code, not assumed)

Measured on `feat/identity-epic1-users` at `f95d109`, 2026-08-18.

### Circles and Centres do not exist in any form

Fifteen modules are present (`Applications`, `Competition`, `Content`, `Contestants`, `Core`,
`Countries`, `Evaluations`, `Judges`, `Media`, `Notifications`, `Reports`, `Search`, `Sponsors`,
`Streaming`, `Videos`). A case-insensitive search across every module for `circle`, `center` and
`centre` returns **nothing** — no module, no migration, no model, no column.

This is new construction, not a behaviour layer over an existing schema. It is the opposite of
the Judges situation ADR-015 §7 describes, where the tables were finished and the use cases were
missing.

### A contestant profile already has a home; an admin profile has none

`contestants` exists and carries the profile fields:

```
id, user_id, country_id, full_name, date_of_birth, gender,
national_id, phone_number, photo_media_id, deleted_at, timestamps
```

There is no equivalent for administrators. An admin today is a `users` row and nothing else —
`type = 'admin'` plus roles, per ADR-015 §1. So "the admin profile is a separate table" is not a
split of something existing; it is a new table for data that currently has nowhere to live.

### `audit_logs` is an HTTP request log, not a record of what people did

```
correlation_id, execution_duration_ms, actor_id, actor_type, ip, user_agent,
method, path, route_name, response_status, device_id, request_size, response_size
```

Every column describes a **request**. Nothing describes a **change**: no subject, no before/after,
no entity type. It answers "what traffic hit the API" and cannot answer "who edited this record
and what did they change".

**This measurement supports the board's decision to keep the Activity Log separate**, and it does
so on stronger ground than a preference: the two logs answer different questions, and merging them
would mean either polluting a request log with domain payloads or losing the request telemetry.
ADR-015 §4.7 already assigns domain-change auditing to explicit writes from use cases, consuming
domain events — the Activity Log is the read model for those, and `audit_logs` is untouched.

### `applications` stores no organisational context

```
contestant_id, season_id, stage_id, video_id, application_number, status, submitted_at
```

There is no `circle_id` and no `center_id`. Storing them historically therefore means a migration
adding two nullable columns, not a change to how applications are written.

### `spatie/laravel-activitylog` is installed and completely unused

`composer.json` requires `^5.0`. Its migration was never published, so no `activity_log` table
exists, and nothing in `Modules/` references it.

**This is the same shape as the situation ADR-015 inherited** — `spatie/laravel-permission`
installed, configured, and used by nothing — which the board resolved by removing the package and
building a custom RBAC, on grounds that apply again here: this project is UUID-keyed everywhere
(ADR-005 D2) while the Spatie stub is integer-keyed, and structural guarantees are preferable to
policy. The board has not yet decided whether the Activity Log reuses this package or replaces it.
See the open questions.

---

## Decisions the board has taken

Recorded as given. Where the reasoning was stated it is included; where it was not, the entry is
marked ⚠️ and **the decision stands but its justification is missing from the record.** That
distinction is deliberate: an ADR whose rationale is invented after the fact teaches a future
reader the wrong lesson about how the decision was reached.

| # | Decision | Rationale on record |
|---|---|---|
| D1 | The admin profile lives in a table of its own. | ⚠️ not recorded |
| D2 | Social links live inside a JSON value object. | ⚠️ not recorded |
| D3 | The Activity Log is separate from the Audit Log. | Supported by measurement above |
| D4 | The admin profile is separate from the contestant profile. | Supported by measurement above |
| D5 | Account deletion is soft delete only. | ⚠️ not recorded |
| D6 | A Circle is an independent entity. | ⚠️ not recorded |
| D7 | A Centre owns its location; a Circle inherits it. | ⚠️ not recorded |
| D8 | An Application stores `circle_id` and `center_id` historically. | ⚠️ partially — "historically" implies a snapshot at submission time, so a later reorganisation cannot rewrite past results. The consequence is stated; the alternative considered is not. |
| D9 | A Circle's supervisor is a User. | ⚠️ not recorded |
| D10 | No WhatsApp integration until a real provider exists. | Consistent with the board's standing rule against building what changes no behaviour |
| D11 | Row-level security is deferred to Epic 14, and supervisors hold no contestant-view permission until then. | **Recorded — see Q2.** Minors' data; no undoable transitional exposure |
| D12 | Device trust moves from Redis to the database. | ⚠️ not recorded. Affects Epic 6 |
| D13 | The admin profile table is named `user_profiles`. | Naming decision, recorded |
| D14 | Accounts are created Pending Activation and activated by invitation; no admin ever sets another user's password. | **Recorded — see Q7** |

---

## Questions Q1-Q7

Q2 and Q7 are decided and recorded in place. The remaining five are **epic-local and deferred**:
each must be answered before its epic starts, not before this document is used.

| Question | Blocks | Must be answered before |
|---|---|---|
| Q1 — reuse or replace `spatie/laravel-activitylog` | Activity Log | **Epic 5** |
| Q3 — how a Circle inherits a Centre's location | Circles/Centres | **Epic 2** |
| Q4 — an Application whose contestant has no circle | Applications ↔ Circles | **Epic 2** |
| Q5 — which social platforms, validated how | Admin profiles | **Epic 3** |
| Q6 — whether "soft delete only" is new or a restatement | User lifecycle | **Epic 1** |

Deferring them is a decision, not an oversight: none changes more than one epic's design, so
answering them at the point of implementation costs nothing, while answering them now would mean
recording rationale the board has not supplied — the failure mode this document was written to
avoid.

### Q1. Does the Activity Log reuse `spatie/laravel-activitylog`, or replace it?

The package is installed and unused. ADR-015 faced the identical question for
`laravel-permission` and answered *replace*, for reasons that are not activity-log-specific
(UUID keys, structural impossibility over policy, a cache sized for a problem this system does
not have). The board should answer this explicitly rather than let the installed package decide
by inertia — which is how the half-Spatie hybrid schema ADR-015 had to untangle came about.

### Q2. What does D11 mean for supervisors in the meantime? — **a product decision, not only a technical one**

D9 makes a Circle's supervisor a User. D11 defers row-level security to Epic 14. Between the two,
**a supervisor granted any `contestants.view`-style permission sees every contestant on the
platform, not only their own circle.**

**DECIDED 2026-08-18 — option (b): withhold the permissions until Epic 14.** The board's reasoning,
recorded because it is the kind that gets re-litigated under delivery pressure: the platform holds
data about contestants, some of them minors; there must be no transitional window in which a newly
onboarded supervisor can see every contestant; and an incomplete supervisor role is preferable to
an exposure that cannot be undone once it has happened.

Consequences that follow and are not optional:

* The supervisor role may be **created** early. It is granted **no** contestant-view permission
  until row-level security is real.
* Epics 2 and 4 must be designed without assuming a supervisor can read anything.
* The ordering cost below is accepted knowingly: scoping retrofitted onto screens and queries
  built without it is harder than designing for it, and the board has chosen the safer of the two.

The options as they stood:

* **(a) Accept the exposure temporarily.** Supervisors receive view permissions now and see all
  contestants until Epic 14 scopes them. Usable immediately; the cost is that every supervisor
  onboarded before Epic 14 has had platform-wide visibility, which is not retroactively
  revocable and may matter for personal data held about minors.
* **(b) Withhold the permissions until Epic 14.** Supervisors exist as accounts and hold no
  contestant-view capability until scoping is real. Nothing is over-exposed; the cost is that the
  supervisor role does nothing useful for however long Epic 14 takes, and epics 2 and 4 must be
  designed without assuming a supervisor can read anything.

Whichever is chosen, note the ordering risk: retrofitting scoping onto screens and queries built
without it is normally harder than designing for it, so (a) is cheaper now and dearer later.

### Q3. How does a Circle inherit a Centre's location (D7)?

Read through the foreign key at query time, or copied at creation? The two differ the moment a
Centre moves: read-through rewrites history, copying leaves circles stale. D8's "historically"
suggests the board's instinct is toward snapshots, but D7 does not say so.

### Q4. What happens to an Application when the contestant has no circle (D8)?

Nullable columns, or is circle membership a precondition for applying? This determines whether the
migration adds nullable columns or a validation rule with a refusal path.

### Q5. Which platforms does D2's JSON value object accept, and validated how?

A JSON column with no shape is a text field with extra steps. The value object needs a closed set
or a documented reason for an open one.

### Q6. Does D5 add anything?

`users` and `contestants` both already use `SoftDeletes`. If D5 is a restatement of current
behaviour it costs nothing to record; if it means *the platform never hard-deletes anything*, that
is a broader rule with retention and GDPR-shaped consequences and belongs in its own section.

---

## Implementation order

Thirteen epics, as set by the board:

| # | Epic |
|---|---|
| 1 | User lifecycle — create (invitation-based), **the invitation mechanism itself**, edit, delete/restore, password reset |
| 2 | Contestants — manual creation, full profile, circles, centres, search |
| 3 | Admin profiles |
| 4 | Identity dashboard — users ↔ contestants ↔ circles |
| 5 | Activity Log |
| 6 | Security — MFA, trusted devices, login history |
| 7 | User menu |
| 8 | Notifications |
| 9 | Bulk actions |
| 10 | Advanced search (Meilisearch) |
| 11 | Timeline |
| 12 | Invitation *management* surface — resend, revoke, expiry policy, listing — only if it proves to have content once the mechanism exists |
| 13 | Remaining centre and circle features |
| 14 | Row-level security (deferred from D11) |

**Sequencing note, from ADR-015's experience:** epic 1's scope in that document turned out to be
partly impossible — the `UserResource` fix it assigned to epic 1 required a resolver that did not
exist until epics 4–5. Before starting each epic here, the scope should be measured against the
code rather than trusted from this table.

### Epic 1 supersedes a recorded decision in ADR-015 — Q7

ADR-015 records, and the Users API implements, that there is deliberately **no admin-facing
"create user" endpoint**: ADR-003 provisions administrators by hand, contestants arrive through
public registration, and an admin-facing "add user" would be a third way in that neither document
describes.

**Epic 1 reverses that.** The reversal may well be correct — provisioning administrators by hand
does not scale past a small team, and the alternative in Epic 12 (invitations) is itself a
creation path. But it is the reversal of a decision recorded in another accepted ADR, and it must
be written down as one:

* **what changes** — an admin with `users.create` may create accounts through the panel;
* **why the original reasoning no longer holds** — this is what the board must supply;
* **what it means for ADR-003**, which is where hand-provisioning is actually specified, and which
  may need its own amendment rather than being overridden silently from here;
* **whether the created account gets a password, an invitation, or neither** — this decides
  whether Epic 1 depends on Epic 12 or stands alone.

**DECIDED 2026-08-18 — the reversal is accepted, and creation is invitation-based.**

* An admin holding `users.create` may create accounts from the panel. **This supersedes ADR-015's
  decision that no such endpoint exists.**
* **No password is ever set by the creating admin.** The account is created in a
  **Pending Activation** state and an invitation is sent; the user chooses their own password and
  activates the account themselves. Hand-distributed passwords are explicitly refused.
* **ADR-003 needs no amendment — it already specifies this.** Checked rather than assumed, and the
  earlier claim in this document that it mandates hand-provisioning was wrong. ADR-003 §180 reads:
  *"Admin account provisioning is performed only by a Super Administrator with `users.create`
  permission"*, and §147 already describes provisioning as sending the new administrator a link
  they follow themselves. What ADR-003 forbids is admin **self-registration**, which is untouched
  here. So the conflict is with ADR-015 alone, which recorded that no such endpoint exists.

**This made Epic 1 depend on the invitation mechanism, which the epic table originally placed at
Epic 12 — eleven epics later.**

**RESOLVED 2026-08-18 — the invitation mechanism moves into Epic 1.** Creation and the means of
completing it ship together, because an epic that can create an account it cannot activate has
delivered nothing usable. The board accepted the consequence knowingly: Epic 1 grows, and is
closer to two epics than one.

Rejected alternatives, recorded so they are not revisited:

* *Build a minimal invitation inside Epic 1 and expand it in Epic 12* — the known failure is that
  "minimal" becomes permanent and nobody returns to it.
* *Defer user creation itself until after Epic 12* — keeps Epic 1 small, but leaves the panel
  unable to create accounts for eleven epics while ADR-003's hand-provisioning carries the load.

Epic 12 is reduced to the management surface around the mechanism, and should be dropped outright
if that surface turns out to be thin.

---

## Consequences

* Circles and Centres are a new module under ADR-002's boundaries, with the full Domain /
  Application / Infrastructure / Presentation layering — not tables bolted onto `Contestants`.
* `applications` gains two nullable columns; nothing about how applications are written changes.
* The admin profile table is additive; no existing account data moves.
* The permission catalogue grows. Under ADR-015 §4.4 every new action needs an explicit verb from
  the closed set, and each addition is a catalogue change reviewed on its own — not folded into an
  existing permission.
* Row-level security deferred to epic 14 means every epic before it is built against a model where
  visibility is all-or-nothing per permission. Retrofitting scoping is normally harder than
  designing for it; Q2 is where that cost is decided.

---

## References

* ADR-015 — Identity and Access Architecture (roles, permissions, PE-1…PE-7, the audit rule)
* ADR-003 — Authentication and identity (how administrators are provisioned)
* ADR-002 — Module boundaries
* ADR-005 D2 — UUIDs everywhere
* `docs/RBAC-IMPLEMENTATION-COMPARISON.md` — the custom-vs-package analysis whose reasoning Q1 revisits
