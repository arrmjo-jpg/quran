# ADR-016: Organisational Structure and Admin Identity

| | |
|---|---|
| **Status** | **Accepted — 2026-08-18**, amended 2026-08-19. The two cross-epic questions are decided in place (Q2 row-level security, Q7 user creation). **Q3, Q4 and Q6 are now closed** by the epics that owned them. **Q1 is now closed** by ADR-017 (Activity Log). **Q5 remains open** and gates Epic 3 — it carries a named blocking marker. |
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
| D1 | The admin profile lives in a table of its own. | **Recorded 2026-08-20.** Not a split of anything: an administrator today is a `users` row and nothing more — `type = 'admin'` plus roles (ADR-015 §1) — so there is no existing home to divide. The measurement above shows `contestants` already carries the equivalent fields for its own kind of person; `user_profiles` is the same provision for the other. Widening `users` instead would put optional presentation data on the table every authentication and authorization path reads |
| D2 | Social links live inside a JSON value object. | **Recorded 2026-08-20 — see Q5.** Eight optional links are eight columns that are almost always null, and each new platform would be a migration on a table read by every admin screen. A JSON column avoids that — but only once Q5 closed it to a known set with per-platform validation. An open JSON column would have been a text field with extra steps, which is the failure the value object exists to prevent |
| D3 | The Activity Log is separate from the Audit Log. | Supported by measurement above |
| D4 | The admin profile is separate from the contestant profile. | Supported by measurement above |
| D5 | Account deletion is soft delete only. | **Closed 2026-08-19 via Q6.** Measured to be a restatement of existing behaviour — the only `forceDelete` in the codebase is a media asset destroyed with its file, and seven models already soft-delete. No retention policy enters any epic on this basis. Now enforced by `AccountDeletionTest`, which also refuses the quieter regression of removing the SoftDeletes trait |
| D6 | A Circle is an independent entity. | **Recorded 2026-08-19.** It has its own lifecycle — CRUD, permissions, memberships, a supervisor — and will be consumed by Applications, Evaluations and the supervisor screens of Epic 14. A property of a contestant cannot be any of those, so circles and centres live in a module of their own rather than inside Contestants (ADR-002) |
| D7 | A Centre owns its location; a Circle inherits it. | **Recorded 2026-08-19 — see Q3.** A Centre holds name, country, city, address and optional coordinates; a Circle reads through and stores none of it. One source of truth, with history preserved by Q4's freeze rather than by duplication |
| D8 | An Application freezes `center_id`, `circle_id`, `center_name` and `circle_name`. | **Recorded 2026-08-19 — see Q4.** Ids alone were insufficient: with Q3's read-through location an id resolves to whatever the centre is called *now*, so a year-old application would show a name that did not exist when it was submitted. Names are frozen; address, city and coordinates are not, being no part of what an application means |
| D9 | A Circle's supervisor is a User. | **Recorded 2026-08-19.** A supervisor is a person who signs in, so they are an account with a role — not a third kind of identity. This is ADR-015's rule that roles describe accounts, applied again: inventing a Supervisor entity would create a second identity model to keep in step with the first. Note Q2: the role may exist from Epic 2 and holds no contestant-view permission until Epic 14 |
| D10 | No WhatsApp integration until a real provider exists. | Consistent with the board's standing rule against building what changes no behaviour |
| D11 | Row-level security is deferred to Epic 14, and supervisors hold no contestant-view permission until then. | **Recorded — see Q2.** Minors' data; no undoable transitional exposure |
| D12 | Device trust moves from Redis to the database. | **Recorded 2026-08-28 via ADR-018 D1.** The rationale this row never carried: a cache flush silently revokes every trust, the record was stored twice and could diverge, and under ADR-018 D5 a trust grant becomes a credential. ADR-018 D1 also corrects the wording — the store was never Redis specifically but `Cache`, which is the *database* under `.env.example`'s `CACHE_STORE=database` |
| D13 | The admin profile table is named `user_profiles`. | Naming decision, recorded |
| D14 | Accounts are created Pending Activation and activated by invitation; no admin ever sets another user's password. | **Recorded — see Q7** |
| D15 | Contestant management belongs to Epic 4, as its own story, completed before the dashboard is built. | **Recorded 2026-08-21 — see Epic 4 below.** The epic table places contestant creation, profile and search in Epic 2, and Epic 2 shipped without them by an explicit re-scope on 2026-08-20. Discovery measured the consequence: `admin/contestants` has `index` and `show` and nothing else, so "users ↔ contestants ↔ circles" has no manageable middle. Building the dashboard first would mean building it over an API that cannot create the records it displays |
| D16 | The Epic 4 dashboard is an **Identity 360** — a relationship view of one person — not a statistics page. | **Recorded 2026-08-21.** The epic's one-line name in the table does not say which, and the two are different products. 360 is chosen because the question the panel cannot answer today is "who is this person, across the system", which is exactly what the three-way link exists for |
| D17 | Contestant write permissions: `super_admin` holds create, update, delete and restore; `data_entry` holds create and update only. | **Recorded 2026-08-21.** `data_entry` is the data-entry role, and granting it create and update makes the `contestants.update` it has held since the catalogue was written mean something. Destructive operations stay with `super_admin`. See the dormant-grant note in Epic 4 below — this decision is the moment that existing grant becomes live, and it is taken deliberately rather than acquired as a side effect |
| D18 | Across the user↔contestant link, Identity 360 carries exactly four user fields — `id`, `name`, `status`, `type` — and **nothing from `user_profiles`**. | **Recorded 2026-08-21 — see Epic 4 below.** The screen answers "who is this person across the system", so it needs what identifies the account, what explains the shape of the tree, and what allows navigation to it. Everything that *describes* the person rather than locating them is either personal data or already reachable on its own screen behind its own permission |

---

## Questions Q1-Q7

Q2 and Q7 are decided and recorded in place. The remaining five are **epic-local and deferred**:
each must be answered before its epic starts, not before this document is used.

| Question | Blocks | Status |
|---|---|---|
| Q1 — reuse or replace `spatie/laravel-activitylog` | Activity Log | open, before **Epic 5** |
| Q3 — how a Circle inherits a Centre's location | Circles/Centres | **CLOSED 2026-08-19** — see below |
| Q4 — an Application whose contestant has no circle | Applications ↔ Circles | **CLOSED 2026-08-19** — see below |
| Q5 — which social platforms, validated how | Admin profiles | **CLOSED 2026-08-20** — see below |
| Q6 — whether "soft delete only" is new or a restatement | User lifecycle | **CLOSED 2026-08-19** — narrow reading plus an enforcing guard; see `AccountDeletionTest` |

Deferring them is a decision, not an oversight: none changes more than one epic's design, so
answering them at the point of implementation costs nothing, while answering them now would mean
recording rationale the board has not supplied — the failure mode this document was written to
avoid.

### Q3 — CLOSED: a Circle reads its Centre's location and never copies it

**A Centre owns its own data in full** — name, country, city, address, and optionally
coordinates — and a Circle belongs to a Centre without duplicating any of it. Location is read
through the foreign key, so there is one source of truth and a Centre that moves is corrected
in one place.

The obvious objection to read-through is that it rewrites history: a report that said "this
circle was in Amman" starts saying Zarqa. That is answered elsewhere rather than by copying —
see Q4's freeze — and the two decisions must be read together, because either alone is wrong.

**What was NOT decided:** whether a Circle may carry its own address distinct from its Centre's.
It may not, today. A Circle that meets somewhere other than its Centre has no way to say so, and
if that turns out to be a real arrangement it is a schema change, not a workaround.

### Q4 — CLOSED: membership is historical, applications freeze names, and the rule lives in the use case

Three decisions that only make sense together:

**Membership is a table, not a column.** `contestant_memberships` carries
`contestant_id`, `circle_id`, `joined_at`, `left_at` and `reason`. A `contestants.circle_id`
column would hold only the present and lose every transfer a contestant ever made — and D8 exists
precisely because that history matters.

**An application freezes `center_id`, `circle_id`, `center_name` and `circle_name`.** Ids alone
were not enough: with read-through location, an id resolves to whatever the centre is called
*now*, so a year-old application would display a name that did not exist when it was submitted.
The names are frozen and the address, city and coordinates are not — those are not part of what
an application means.

**The columns are nullable; the use case refuses.** Option (c) of the three considered. The
database permits null so historical rows and future migrations are not trapped, while
`SubmitApplicationUseCase` refuses to create an application for a contestant with no active
membership. A NOT NULL column would have made every past row a migration problem; a rule with no
enforcement would have been decoration.

### Q5 — CLOSED: a closed set of eight platforms, storing full URLs, validated by domain

**The set is closed.** Eight platforms and no more: `website`, `x`, `linkedin`, `facebook`,
`instagram`, `youtube`, `telegram`, `tiktok`. GitHub, GitLab and Discord were considered and left
out — this is an administrator directory, not a developer profile, and a platform nobody fills in
is a field every form and every validator carries for nothing.

Closed rather than open because an open list makes the value object an `array<string, string>`
wearing a type name. Adding a platform later is then a deliberate act — this ADR plus whatever
migration the storage needs — instead of a value someone writes into JSON and nothing checks.

**The stored value is the full URL, not a username.** `"linkedin": "https://linkedin.com/in/name"`,
never `"linkedin": "name"`. Storing usernames would mean the platform owns a URL template for each
site, and every template is a guess about a third party's routing that breaks silently when they
change it. It would also make validation weaker, not stronger: there is nothing to check in a bare
string, while a URL can be checked as a URL.

**Validation, per platform.** Every field is optional. A field that is present must be a valid URL
*and* belong to that platform's official domain:

| Field | Accepted host |
|---|---|
| `website` | any host, `https` only |
| `x` | `x.com` or `twitter.com` |
| `linkedin` | `linkedin.com` |
| `facebook` | `facebook.com` |
| `instagram` | `instagram.com` |
| `youtube` | `youtube.com` |
| `telegram` | `t.me` |
| `tiktok` | `tiktok.com` |

`x` accepts both hosts because the rename is still in progress and refusing `twitter.com` would
reject links that work.

**No normalisation — DECIDED 2026-08-20.** A URL entered as `twitter.com/...` is stored exactly as
entered. The value object validates; it does not rewrite what someone typed. Accepting
`twitter.com` is a compatibility decision, not a data-migration one, and conflating the two would
make every save a silent edit. If the platform later wants one host in storage, that is a migration
or a one-off script that can be reviewed and reversed — not an invisible side effect of validation.

**An unset platform has no key — DECIDED 2026-08-20.** The JSON carries the links that exist and
nothing else; it is not a fixed-shape record with eight slots. So this:

```json
{ "website": "https://example.com", "linkedin": "https://linkedin.com/in/admin" }
```

and never `"facebook": null` alongside it. Three consequences, all wanted: less noise to read,
comparison and validation that operate on what is present rather than on placeholders, and a ninth
platform later that costs nothing — no backfill of nulls across every existing row.

### Q1. Does the Activity Log reuse `spatie/laravel-activitylog`, or replace it? — **CLOSED 2026-08-22**

**Answered: replace.** See ADR-017 D1, which carries the reasoning and the measurements.

The package is installed and unused. ADR-015 faced the identical question for
`laravel-permission` and answered *replace*, for reasons that are not activity-log-specific
(UUID keys, structural impossibility over policy, a cache sized for a problem this system does
not have). The board should answer this explicitly rather than let the installed package decide
by inertia — which is how the half-Spatie hybrid schema ADR-015 had to untangle came about.

It was answered explicitly, and the reasons turned out to be of the same kind after all:
`activity_log` uses a bigint auto-increment key against ADR-005 D2, stores PHP class names in
`nullableMorphs` columns across module boundaries, is driven by Eloquent model events rather than
the domain events this platform dispatches, and has no `correlation_id`. The package remains
installed and unused; removing the dependency was considered and left alone as outside the epic.

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
| 4 | Identity dashboard — users ↔ contestants ↔ circles. **Scope settled 2026-08-21 — see "Epic 4" below.** Absorbs the contestant management Epic 2 was re-scoped to drop (D15) |
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

## Epic 4 — scope, settled 2026-08-21

The epic table gives Epic 4 one line: *"Identity dashboard — users ↔ contestants ↔ circles"*. That
line names three things and decides nothing about them. What follows is the scope, measured against
the code first as the sequencing note above requires.

### What Discovery measured, before any decision was taken

| Finding | Evidence |
|---|---|
| Contestants cannot be managed at all | `admin/contestants` exposes `index` and `show`. No create, update, delete or restore route exists |
| The middle of the three-way link is the gap | `users` has full lifecycle (Epic 1); centres, circles and memberships have full CRUD (Epic 2); contestants have neither |
| No API links any two of the three | `UserResource` carries `profile` but no contestant; `ContestantPrivateResource` carries `user_id` but no membership, circle or centre |
| The contestant list is unbounded | `ContestantRepository::search()` ends in `->get()` with no limit, and three `LIKE '%…%'` predicates that use no index |
| The admin UI already draws data the API never sends | `Contestant360Drawer` renders applications and appeals tabs; no endpoint returns either |
| `contestants.update` is a dormant grant | It sits in the catalogue marked `NO ENDPOINT YET` and is granted to `data_entry` in `RolesSeeder` |

### D16 in full — what Identity 360 shows

From a user:

```
User
 ├── Account
 ├── Profile            (user_profiles — Epic 3; ADMIN CONTEXT ONLY, see D18)
 └── Contestant         (contestants.user_id, UNIQUE — at most one)
      ├── Personal information
      ├── Country
      ├── Memberships   (contestant_memberships — historical, Q4)
      │    ├── Circle
      │    └── Center
      └── Applications / Appeals
```

And the reverse, from a contestant: `Contestant → User`, carrying the four fields D18 names and
**stopping there**. It does not continue to Profile.

Two things about this tree are new construction rather than display, and are recorded here so they
are not mistaken for wiring during estimation:

* **Applications and Appeals are not readable today.** No endpoint returns either against a
  contestant. The admin drawer draws the tabs regardless, so the screen currently promises data the
  server has never sent. Whether this branch is in Epic 4's scope is a scope decision, not a
  connection task.
* **The Profile branch does not appear in a contestant context at all.** D18 settles which fields
  cross the link, and none of `user_profiles` does.

### D18 in full — what crosses the user↔contestant link

Decided 2026-08-21 after measuring both resources and the profile table rather than choosing from
what happened to be available.

| Field | Source | Why the screen needs it | Personal | Shown |
|---|---|---|:--:|:--:|
| `id` | `users` | The navigation target for `/users/{id}`. Without it the relationship is displayed but cannot be followed | no | **yes**, as a link |
| `name` | `users` | Identifies the account — and its *divergence* from `contestants.full_name` is itself information. The two are separate columns that nothing keeps in step | no | **yes** |
| `status` | `users`, derived | The most operationally useful fact on a contestant screen: can this person sign in at all? A pending or deactivated account explains an unfinished registration without opening another screen | no | **yes** |
| `type` | `users` | Explains the shape of the rest of the tree — why a Contestant branch exists, and why a Profile branch does not | no | **yes** |
| `email` | `users` | Answers no relationship question. Reachable at `/users/{id}` behind `users.view` | **yes** | no |
| `preferred_locale` | `users` | Support convenience, not identity | no | no |
| `mfa_enabled` | `users` | Security posture — Epic 6's subject | yes | no |
| `created_at` | `users` | A users-screen fact; the contestant carries its own | no | no |
| `roles` | `role_user` | Empty for a contestant account; for an admin it belongs on the users screen | no | no |
| `display_name` | `user_profiles` | Free text written by the account holder. `users.name` is what identifies the account | yes | no |
| `bio` | `user_profiles` | A self-written biography; answers no relationship question | **yes** | no |
| `social_links` | `user_profiles` | Personal links supplied by the person | **yes** | no |
| `avatar_media_id` | `user_profiles` | A bare UUID; no resource resolves it to a displayable URL | no | no |
| `national_id` | `contestants` | Identity document of a person who may be a minor | **yes** | no |

The rule these follow, stated once so a future field can be judged against it rather than argued
case by case: **Identity 360 shows what identifies, explains, or links. It does not show what
describes.** A field that describes the person belongs on that person's own screen, behind that
screen's permission.

The consequence is that no `user_profiles` field crosses the link, so the Profile branch is absent
from the contestant context entirely rather than present and empty.

### D11 binds this epic, and it is already decided

Q2 above closed on 2026-08-18 with option (b), and its consequence names this epic directly:
*"Epics 2 and 4 must be designed without assuming a supervisor can read anything."*

So Epic 4 is designed with no supervisor visibility, and no `supervisor` role is seeded by it. This
is not re-opened here. The reasoning on record — data about minors, and no transitional exposure
that cannot be undone — applies unchanged.

Visibility for every other role was already settled and is unchanged by this epic:
`super_admin` holds everything, `competition_manager` and `data_entry` hold `contestants.view`, and
`judge`, `evaluator` and `moderator` hold nothing about contestants.

### D17 in full — and the dormant grant it makes live

The catalogue has no `contestants.create`, `contestants.delete` or `contestants.restore`; Epic 4
adds them. `contestants.update` already exists and is already granted to `data_entry`.

| Permission | `super_admin` | `data_entry` | Everyone else |
|---|:--:|:--:|:--:|
| `contestants.view` | ● | ● | `competition_manager` only |
| `contestants.create` | ● | ● | — |
| `contestants.update` | ● | ● | — |
| `contestants.delete` | ● | — | — |
| `contestants.restore` | ● | — | — |

**The dormant grant.** `data_entry` has held `contestants.update` since the catalogue was written,
and it has never done anything because no route consults it. The moment Story 1 builds the update
endpoint, that grant becomes live — a real change in what an account may do, arriving with no diff
against any permission file, because the permission was granted long ago. D17 is the board taking
that decision on purpose rather than inheriting it. Recorded because a reader six months from now
would otherwise find no moment where anyone chose it.

`super_admin` acquires the three new permissions automatically: `RolesSeeder` defines it as
`PermissionCatalog::all()` rather than a written list, by design, and a test proves a new catalogue
entry reaches it on the next seed.

Deletion is soft only, per D5. `contestants` already carries `SoftDeletes`, so `delete` and
`restore` are the pair D5 describes and not a new retention question.

### Stories

| # | Story | Contains |
|---|---|---|
| 0 | **ADR & scope** | This section. Q1 and Q2 answered; D15–D18 recorded, including which User fields cross into a contestant context and that no `user_profiles` field does |
| 1 | **Contestant management** | Create, update, delete/restore, search, pagination, validation, authorization. Golden Master on the existing `index`/`show` contracts **before** they change |
| 2 | **Identity 360 backend** | User ↔ Contestant, Contestant ↔ Memberships, Circle ↔ Centre. Applications/Appeals only if scoped in. N+1 addressed as it is written, not after |
| 3 | **Identity 360 admin UI** | The screen, search, and the relationships between the four entities |
| 4 | **Contestant profile UI** | Full profile, memberships, related data, loading/empty/error states, AR/EN/ES |
| 5 | **Hardening & review** | Permissions, tests, contracts, performance, CI, PR |

Story 1 precedes Story 2 for the reason in D15: a dashboard over an API that cannot create what it
displays would be built twice.

### Story 2 — Identity 360, settled 2026-08-21

D16 drew the tree and D18 settled which User fields cross into it. Neither said how the tree is
*served*, who may read each branch, or what happens to the branches the API cannot fill. Those are
decided here, after Discovery measured the code rather than before.

#### What Discovery measured, before any decision was taken

| Finding | Evidence |
|---|---|
| The contestant's membership history is already readable | `GET /admin/memberships?contestant_id=…` exists, eager loads `circle`, and paginates — behind `can:memberships.view` |
| The circle already carries its centre | `CircleModel::center()` and `ContestantMembershipModel::circle()` are declared relations, so `with('circle.center')` is three queries regardless of row count |
| Two boundary contracts are empty stubs | `CoreServiceContract` and `OrganizationServiceContract` still carry the generated *"Define public methods"* comment. `CountriesServiceContract` is the only one with a real method, and it is the precedent this story follows |
| The derived account status exists in exactly one place | The four-branch `match` in `AdminUserResource`. Identity 360 needs the same answer, which would make it two |
| There is no `/users/{id}` route in the panel | `router.tsx` routes `/users` to a list with dialogs. D18 calls `id` *"the navigation target for `/users/{id}`"*; that target does not exist |
| The drawer shows six branches the server has never sent | `Contestant360Drawer` renders applications, video, evaluations, a hard-coded *"94.5 / 100 — qualified"* result and a hard-coded two-entry timeline |

#### D19 — one composed endpoint, and what it does *not* carry

`GET /admin/contestants/{id}/identity`, behind `can:contestants.view`.

Composed on the server rather than assembled by the client from four calls. The client-side
alternative fails on permissions before it fails on round trips: the drawer would need
`contestants.view` **and** `memberships.view` **and** `users.view`, and `data_entry` holds only the
first — so the screen would break into partial 403s for the very role that spends the most time on
it.

**The contestant branch carries the list shape, without `national_id`.** Identity 360 is a
relationship view, and D18's own rule decides this: *shows what identifies, explains, or links; not
what describes.* An identity document describes. It stays exactly where Story 1 put it — on
`GET /admin/contestants/{id}`, which is a deliberate act on one person and not a graph anyone
browses. The D18 table already judged `national_id` **not shown**; this records that the judgement
binds the identity payload too, so nobody re-derives it from "the caller holds `contestants.view`
anyway".

**No `user_profiles` branch, and no empty placeholder for one.** D18's consequence, unchanged.

**No applications or appeals branch.** No endpoint returns either against a contestant, so there is
nothing to serve. A branch present and always empty would be indistinguishable from a contestant
who has never applied — which is a lie the current UI already tells.

#### D20 — a branch withheld is not a branch that is empty

The memberships branch is other-module data with its own permission, and `contestants.view` does
not imply `memberships.view`. Row-level scoping is deferred to Epic 14, so visibility here stays
all-or-nothing per permission, exactly as everywhere else.

So the branch is served only to a caller holding `memberships.view` — and when it is not, the
response says so:

```json
{ "memberships": [], "withheld": ["memberships"] }
```

The key is always present and always an array, so the shape does not change with the reader. What
changes is `withheld`, which names each branch suppressed for lack of permission. Without it,
`data_entry` would read an empty array and conclude the contestant has never belonged to a
circle — the panel telling an operator a fact about a person that is not true. This is the same
failure `routeAccess.ts` describes at the route level: *"telling the operator there is no data
rather than that they may not see it."*

`competition_manager` and `data_entry` hold `contestants.view` and neither holds
`memberships.view`, so today **only `super_admin` sees the memberships branch**. That is a
consequence of grants made in Epic 2, not a new restriction, and it is an operator's decision to
change rather than a seeder's.

#### D21 — the fabricated branches are removed, not left in place

`Contestant360Drawer`'s applications, video, evaluations, results and timeline tabs render content
no endpoint has ever returned, including a specific score and a specific verdict. Story 2 deletes
them.

Leaving them beside real data would be worse than leaving them alone was: an operator who sees a
true account status and a true circle history next to *"Average score: 94.5 / 100 — qualified"* has
every reason to believe the third. The tabs return when there is an endpoint behind them, which the
Stories table already assigns elsewhere.

#### Two things this story builds that ADR-002 has been waiting for

`CoreServiceContract` and `OrganizationServiceContract` get their first real methods, each
returning a DTO owned by the providing module — the shape `CountriesServiceContract` established:

* `Core` exposes `id`, `name`, `status`, `type` and nothing else. D18 stops being a rule someone
  has to remember and becomes a type: `email` cannot cross this boundary because there is nowhere
  to put it.
* `Organization` exposes a membership with its circle and that circle's centre, resolved in one
  eager load.

The `status` `match` moves out of `AdminUserResource` into a Core value object that both callers
read, so the contestant screen and the users screen cannot disagree about whether somebody can sign
in. `UserLifecycleTest`'s *"every account state is reported as one derived status"* already covers
all four states and is the regression guard for that move; no new characterisation was needed
because the existing one was already sufficient.

#### Deferred by this story, on purpose

* **`/users/{id}` does not exist**, so D18's `id`-as-a-link cannot be honoured. The account branch
  shows the id rather than linking to a route that would 404. Building that route is a users-screen
  change, not an Identity 360 one.
* **`data_entry` cannot see memberships** — see D20. Granting `memberships.view` is an operator
  decision through the roles panel.

### Story 3 — Identity 360 UI, settled 2026-08-21

D16 drew the tree from a **User** downward. Everything built so far runs the other way: Story 2
serves `Contestant → User`, and nothing anywhere — not `AdminUserResource`, not `UserResource`, not
the users screen — mentions a contestant. Verified by search before deciding: zero references. The
link exists in the database and in one direction of the API only.

#### D22 — what crosses the contestant→user link

The mirror of D18, judged against the same rule: *shows what identifies, explains, or links; does
not show what describes.* D18 measured what a **user** may contribute to a contestant screen; this
measures what a **contestant** may contribute to an account screen.

| Field | Source | Why the screen needs it | Personal | Shown |
|---|---|---|:--:|:--:|
| `id` | `contestants` | The navigation target for `/contestants/{id}`. Without it the relationship is displayed but cannot be followed — D18's own words about `users.id` | no | **yes**, as a link |
| `full_name` | `contestants` | Identifies the competitor, and its *divergence* from `users.name` is itself information. D18 admitted `name` in the other direction for exactly this reason; the two columns are separate and nothing keeps them in step | no | **yes** |
| `is_deleted` | `contestants` | Explains. An account that looks ordinary but whose contestant record was removed is not competing, and the account screen otherwise gives no hint why | no | **yes** |
| `national_id` | `contestants` | Identity document of a person who may be a minor. D19 already keeps it off Identity 360 itself; it certainly does not travel further out | **yes** | no |
| `date_of_birth` | `contestants` | Describes | **yes** | no |
| `gender` | `contestants` | Describes | yes | no |
| `phone_number` | `contestants` | Describes, and is contact data rather than identity | **yes** | no |
| `country_id` | `contestants` | Belongs to the contestant's own screen; an account is not located anywhere | no | no |
| `profile_completeness` | derived | A data-entry progress metric. It answers "is this record finished", which is a contestant-screen question | no | no |

Three fields, and `ResolvedContestantDTO` has room for exactly three — the same enforcement
`ResolvedUserDTO` gives D18. `ContestantsServiceContract` gets its first real method, leaving
`Media`, `Videos`, `Judges` and the rest still stubs.

#### D23 — one surface for the graph, and two routes

**The drawer is replaced by a page at `/contestants/:id`, not joined by one.** Two surfaces
rendering the same graph are two places that must be kept in step, and this codebase already
carries the argument against that in three separate comments — `is_active` derived once rather than
recomputed by three clients, `status` derived once rather than combined from three columns,
`UserStatus` extracted the moment a second caller appeared. A quick-look drawer beside a full page
would be the same mistake with more markup. The drawer's body becomes the page's body; the table's
"Full profile" button navigates instead of opening a dialog.

The payoff is not aesthetic. A dialog has no URL, so the relationship an operator is looking at
cannot be linked to, and `User → Contestant → back` cannot be walked at all. A graph you can see
but not traverse is a diagram, not a screen.

**`/users/:id` is built.** `GET /admin/users/{id}` has existed since Epic 1 and returns the account
with its effective permission set; `useUser(id)` has existed in the panel and is called from
nowhere. What was missing was a route and a page. Building it is what makes D18's `id`-as-a-link
real rather than an id printed as text, and it is the far end of every link D22 adds.

**Both directions withhold rather than empty**, exactly as D20 established. The contestant branch
on an account screen needs `contestants.view`, which `users.view` does not imply, and the account
screen names it in `withheld` when refused. The reverse is already true of the memberships branch.
So `super_admin` sees the whole graph, and every other role sees a graph that says where it has
been cut rather than pretending those parts are empty.

#### Recorded, not fixed, by this story

The Golden Master written before the change (`AdminUserGoldenMasterTest`) records two
inconsistencies between the two admin surfaces, both pre-existing:

* a soft-deleted **account** is retrievable at `/admin/users/{id}`; a soft-deleted **contestant**
  404s at `/admin/contestants/{id}`. `withTrashed()` on the first is what makes restore reachable
  from a detail screen.
* a malformed id answers **404** on the users route and **422 INVALID_CONTESTANT_ID** on the
  contestant routes, because `ContestantId` validates shape while the users route hands the string
  to `findOrFail`.

Neither is touched here. Changing either is a users-endpoint decision with its own blast radius,
and Story 3 is not the place to take it while adding a branch to the same response.

### Story 4 — Contestant Profile, settled 2026-08-21

Story 3 shipped `/contestants/:id`, and most of what a "contestant profile" would show is already on
it. Discovery measured the overlap before this story was scoped, so the remainder is small and
specific rather than a second rendering of the same screen.

#### What Discovery measured

| Finding | Evidence |
|---|---|
| Seven of the twelve profile items already exist | `/contestants/:id` renders date of birth, gender, phone, country, account status, circle history and the completeness badge |
| The photo is a bare UUID that nothing resolves | `photo_media_asset_id` travels in every contestant payload; `MediaServiceContract` is still the generated stub, and no consumer can turn the id into anything displayable |
| Media *can* be resolved | `MediaAssetResource` derives a `url` from the asset's disk and path, and a `thumb` from `custom_properties['thumb_url']` |
| `media.view` is held by every seeded role | super_admin, competition_manager, judge, evaluator, data_entry, moderator |
| The profile page is read-only | Edit, delete and restore exist on the table row only; opening a contestant offers no action at all |
| Age is computed but never exposed | `BirthDate::calculateAgeAt()` exists and `EligibilityService::checkEligibility()` returns an age, and no endpoint carries one |
| `missing_fields` is computed and never rendered | It travels inside `profile_completeness` on every contestant payload |

#### D24 — the profile is a tab, not a second page

**One route, two tabs: `Profile` and `Relations`.** D23 replaced a drawer with a page because two
surfaces showing the same person are two places to keep in step; a second route for "the profile"
would reintroduce exactly that, one story later. Tabs give the visual separation the profile needs
without giving the person two URLs.

**Profile** holds the person's own record — photo, name, age, country, phone, date of birth,
gender, contestant status, completeness and what is missing. **Relations** holds what the contestant
is *linked to* — the account and the circle history. Nothing appears in both.

The country sits in Profile rather than Relations, although Story 3 rendered it beside the account.
It is not a relationship an operator navigates: nothing hangs off it, there is no country screen to
open, and what it answers — where this person competes from — is a fact about the person in the same
way their date of birth is. Relations is for links that lead somewhere.

**`age` and `photo` are added to the identity endpoint's `contestant` branch, and nowhere else.**
That branch is what the profile tab reads, and `GET /admin/contestants/{id}` is left exactly as
Story 1 shaped it — it is the record-editing contract, and widening it would be widening a response
this story has no screen for.

Age is calculated by `BirthDate::calculateAgeAt()`, which is where it already lives, and handed to
the resource as a finished integer. Not in React, which would put a business rule in a component;
not inside the resource, which would make a presentation class do domain arithmetic. Not through
`EligibilityService::checkEligibility()` either, despite it returning an age: that method answers
"may this person compete in a season with these bounds", and calling it for a number would drag
season semantics into a screen that has no season.

**The photo carries no `withheld` branch.** D20's mechanism exists for a branch a reader may not
see, and every seeded role holds `media.view` — a withheld photo would be an unreachable state
dressed as a permission boundary. If `media.view` is ever narrowed, this becomes a real decision;
today it would be theatre. The resolution still goes through `MediaServiceContract` rather than a
direct model read, because ADR-002 is about coupling, not about permissions.

**`MediaAssetResource` is not reused across the boundary.** It is a Presentation class in another
module, and importing it here would be precisely the coupling the Contracts directory exists to
prevent. Media's own service performs the same disk-and-path resolution and returns a DTO.

**Uploading is out of scope.** This story resolves an id that something else set. There is no
contestant photo upload anywhere today, and inventing one would mean deciding collection naming,
size limits and who may replace another person's picture — a media decision, not a profile one.

#### `profile_completeness` is shown, not fixed

`missing_fields` is rendered on the profile tab. The metric behind it is **not** changed, and this
records why the display is worth less than it looks:

Of the five fields `EligibilityService::calculateProfileCompleteness()` counts, `date_of_birth` is
incremented unconditionally — it is a required constructor argument, so the aggregate cannot exist
without it — and `full_name`, `phone_number` and `country_id` are all required at creation and
cannot be blanked through `UpdateContestantRequest`. The only field that can ever be absent is the
photo.

So in practice **the score is 80% or 100%, and `missing_fields` holds at most one entry.** Rendering
it is still right — an operator seeing *what* is missing beats a percentage — but nobody should read
the number as a measure of anything. Repairing the metric means deciding what a complete contestant
record actually is, which is a product question this story does not open.

#### Admin actions move onto the page

Edit, delete and restore appear on `/contestants/:id`, each behind the permission that already
governs it. **D17 is unchanged and no permission is invented**: `contestants.update` for edit,
`contestants.delete` and `contestants.restore` for the destructive pair, which `data_entry` does not
hold. A deleted contestant offers restore and nothing else, matching the table's behaviour.

### Carried into Epic 4 from Discovery, not fixed by it

Recorded so they are not attributed to this epic when they surface in a full run:

* `Modules/Organization/Tests/Feature/MembershipApiTest.php:61` holds a `static $centerId` that
  survives between tests while `RefreshDatabase` rolls its row back. Belongs to the Test
  Infrastructure epic.
* `fix/countries-name-and-pagination` is unmerged, and the countries admin screen is broken on
  `main` until it lands.
* The test connection runs with foreign keys disabled. Epic 4 is built entirely on RESTRICT
  foreign keys — `contestants.user_id`, `contestant_memberships.contestant_id` and `.circle_id` —
  so the suite will not fail on a referential-integrity breach this epic introduces.
* `Core/Routes/admin.php` still carries a comment reading *"Accounts. No create route"* directly
  above the `POST users` route that Q7 added.
* **`PATCH /me` is reachable by any authenticated account, contestants included.** The route sits
  on the plain `auth:sanctum` group in `Modules/Core/Routes/api.php`, not the `admin` group, and
  neither `UpdateSelfUseCase` nor `UpdateSelfProfileUseCase` consults `type`. So a contestant can
  create a `user_profiles` row and write `display_name`, `bio` and `social_links` for themselves —
  while D1 describes that table as the administrator's profile.

  **Recorded as a note, not a defect, and deliberately not changed by Epic 4.** Discovery
  established that the gap exists; it did not establish that the behaviour is unintended, and the
  two are different findings. Deciding it means either amending D1's description or closing the
  route, and both are Epic 3 / identity questions rather than Epic 4 ones. Measured on the dev
  database at the time of writing: seven admin accounts, zero profiles, zero contestant accounts —
  so nothing observed either confirms or contradicts intent.

  It is noted here because it bears on D18: had any `user_profiles` field crossed the link, the
  screen could have been rendering contestant-supplied free text and personal links. None does, so
  Epic 4 is unaffected either way.

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
