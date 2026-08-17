# ADR-015: Identity and Access Architecture

* **Status**: Proposed — awaiting approval before any implementation
* **Deciders**: Quran Competition Platform Architecture Board
* **Date**: 2026-08-17
* **Supersedes**: nothing. **Complements**: ADR-003 (which decided *authentication* and explicitly left *authorization* undefined)

---

## Context & Problem Statement

ADR-003 decided how an account proves who it is. It never decided what an account is *allowed to do*. It refers to "a Super Administrator with `users.create` permission" without defining what a permission is, where it is stored, or who may grant one. ADR-001 lists the intended actors — Super Administrators, Competition Managers, Judges, Evaluators, Data Entry operators, Moderators — and no ADR since has said how those become real.

The result is a gap that the code has been filling with placeholders. Before writing any of this epic, the actual starting point must be stated plainly, because it is not what the schema suggests.

### What exists today (verified against the code, not assumed)

| Artifact | Reality |
|---|---|
| `roles`, `permissions`, `role_user`, `role_has_permissions` tables | Exist, created by this project's own migrations (`Modules/Core/Database/Migrations/2026_01_01_00000{2,3,4,5}`). All four are **empty of behaviour** — no application code reads or writes any of them. |
| `spatie/laravel-permission` v8.3 | In `composer.json`, `config/permission.php` published. **Zero application code uses it.** No `HasRoles` trait, no `permission:` middleware, no Spatie model referenced anywhere in `Modules/` or `app/`. |
| `Modules\Core\Domain\Entities\Role` | Exists, has `guardName` and `grantPermission()`. **Zero references** anywhere in the codebase. |
| Authorization enforcement | Exactly one binary check, in `EnsureUserIsAdmin`: `$user->type !== 'admin'` → 403. Nothing finer-grained exists. |
| `roles` / `permissions` in the API response | **Fabricated in the Resource.** `UserResource` returns `'roles' => [$user->getType()]` and `'permissions' => $type === 'admin' ? ['*'] : []`. The admin panel is being told every admin holds `*`. |

Two consequences follow, and they shape every decision below.

**First**, there is no RBAC to migrate away from. This is a greenfield authorization design sitting on top of four empty tables. That is a freedom, and it means the cost of choosing correctly now is low.

**Second**, the existing tables are a *half-Spatie hybrid that is compatible with neither*. `role_has_permissions` matches Spatie's expected name; `role_user` does not (Spatie expects the polymorphic `model_has_roles`); `model_has_permissions` — which Spatie requires for direct user permissions — does not exist at all. Adopting Spatie is therefore **not** a zero-migration decision, and anyone who assumes it is will discover this halfway through.

### The `permissions: ['*']` lie is the most urgent item here

It is not cosmetic. The admin panel currently receives a permission list that is not derived from anything, for a user whose real capability is "passed a boolean `type` check". Every frontend feature that branches on `permissions` is branching on a constant. This is the same defect class the Shaabjo review rejected — *computing domain facts in a Resource* — and it exists in our own code today, not just theirs.

---

## Decision Drivers

* **The domain must not learn Eloquent.** ADR-002 and ADR-012 put persistence behind contracts; an authorization model is not an exception.
* **Nothing gets built that does not change system behaviour.** The project's own standing rule, and the reason `PermissionGroup` was rejected from Shaabjo.
* **A role created in the panel must actually work.** Shaabjo's central failure: a role with all 150 permissions still could not log into the admin panel, because admin-ness was a hardcoded list in five files.
* **No source of truth may be duplicated.** Shaabjo carried two grouping mechanisms and a role list with different contents in different files.
* **Privilege escalation must be structurally impossible**, not merely unimplemented.

---

## Decision

### 1. The vocabulary

Each term below is defined by *what decision it changes*. A term that changes no decision is not in this design.

#### User

The account. One row in `users`, identified by `UserId`, holding credentials (`email`, `password_hash`), presentation preference (`preferred_locale`), lifecycle state (`is_active`, soft delete), and MFA state. It is the only thing that authenticates.

A User is **not** a person's role, job, or profile. Those attach to it.

#### `users.type` — the authentication surface, not a role

`type` stays exactly as ADR-003 defined it: `contestant` or `admin` (currently `'user'`/`'admin'`; see §8 on the rename). It answers one question and no other:

> **Which authentication surface may this account use?**

Contestants authenticate via social providers on the public surface. Admins authenticate via email/password + MFA on the admin surface. That is an ADR-003 decision and this ADR does not reopen it.

**This is the deliberate fix for Shaabjo's `ADMIN_ROLES` defect.** There, "can this account reach the admin panel?" was answered by testing a role name against a hardcoded array copied into five files, so a fully-permissioned role created from the UI still could not log in, and there was no column to fix it with. Here the question is answered by a column that already exists and is already indexed. Admin-ness is data. **No role name may ever be hardcoded anywhere in this codebase for any purpose.**

The division of labour is therefore:

* `users.type` → *may you enter this door?*
* roles/permissions → *what may you do once inside?*

#### Admin

**Not an entity.** An Admin is a User with `type = 'admin'`. There is no `admins` table, no Admin aggregate, no Admin model. The word describes a User, it does not name a thing.

What distinguishes one admin from another is the roles they hold — a Competition Manager and a Data Entry operator are both `type = 'admin'`, differing only in roles. ADR-001's six actor names are **role names, not types**.

#### Judge

**An entity, and the schema already decided this.** `judges.user_id` is `UNIQUE` with `onDelete('RESTRICT')` — a one-to-one that refuses to let a User be deleted while a Judge hangs off it. The table carries `full_name`, `title`, `specialization`, `bio`, `photo_media_id`, `is_active`.

The justification is that none of those columns belong on a Role. "Specialization: تجويد" is not a permission and cannot be expressed as one. A Judge is a *professional profile with domain data*, which is a different kind of thing from a grant of capability.

A Judge is therefore: **a User (`type = 'admin'`) + a `judges` row + normally the `judge` role.** Three separate facts, each independently true or false, and the system must tolerate all combinations rather than inferring one from another:

* A User with the `judge` role but no `judges` row → a misconfiguration the system should surface, not crash on.
* A `judges` row whose User is deactivated → a judge on leave; assignments persist, login does not.

#### Role

A named, admin-editable bundle of permissions. `roles(id, name, guard_name, is_system, created_at, updated_at)` — plus `is_system`, added by this ADR (§6).

Roles are **data, not code**. The expected initial set — `super_admin`, `competition_manager`, `judge`, `evaluator`, `data_entry`, `moderator` — ships as a **seeder**, not as constants, not as an enum, not as a match expression. Every one of them is editable and deletable from the panel except as §6 restricts.

#### Permission

The atomic capability, named `resource.action` — `seasons.create`, `applications.review`, `users.create`. This convention is taken from Shaabjo, where it was applied consistently across ~150 permissions and worked.

Permissions are **code, not data**, in the sense that matters: they are seeded from a definitive list maintained alongside the code that checks them, because a permission with no `Gate` check behind it is a checkbox that lies. The rule is:

> A permission may only exist in the seeder if some code path actually checks it.

Adding a permission row that nothing enforces reproduces, in a different shape, exactly the "UI claims a fact that isn't true" failure this design is built to avoid.

#### Permission Group

**Not an entity. No table. No `permission_group_id`. No CRUD.**

Grouping is real and needed — 150 checkboxes require visual organisation — but it is **derived from the name**, not stored. `seasons.create`, `seasons.update`, `seasons.archive` group under `seasons` because their names say so. The group is `explode('.', $name)[0]`.

This is a direct correction of Shaabjo's dead-end, and the reasoning is worth recording because it generalises. There, `PermissionGroup` was a full entity with CRUD, but `permission_group_id` was written **only by the seeder** — no endpoint ever set it. So a group created from the panel could never be populated: a feature that appeared to work, could not, and nothing in the type system said so. It was also duplicated, with a legacy `permissions.group` string column added a day before the FK and never dropped, leaving two grouping mechanisms that could disagree.

Deriving the group from the name cannot drift, cannot be empty, needs no write path, and needs no migration. The label shown to the admin (`"إدارة المواسم"` for `seasons`) is an i18n key in the admin's `permissions` namespace — presentation, where it belongs.

#### Department — **deferred, not approved**

The proposal is organisational only (الإدارة العليا، إدارة المسابقة، إدارة التسجيل، إدارة الحكام، إدارة الإعلام، إدارة البث), explicitly with no effect on permissions.

**This ADR does not approve it**, on the project's own rule: *nothing gets built that does not change system behaviour*. That rule is precisely why `PermissionGroup` was rejected, and a Department that only labels users is the same shape of thing.

It is not rejected outright, because it differs from `PermissionGroup` in one respect that could rescue it: `PermissionGroup` died for lack of a write path, while a Department has an obvious one (`users.department_id`).

**The test it must pass before it is built** — one concrete decision the system makes differently because a Department exists:

* *Filtering a user list by department* → does not qualify. That is a saved search.
* *Routing work* — "registration-desk applications go to إدارة التسجيل", "this notification goes to whoever staffs إدارة الحكام" → qualifies. That is behaviour, and nothing else in this design answers it.

Until someone names a rule of the second kind, `users.department_id` is not added. **Revisit when the notification-routing or work-queue epic is specified**, since that is where the qualifying rule would come from if it exists.

#### Judge Assignment

A Judge's membership of one Stage's panel. `judge_assignments(id, judge_id, stage_id, role, is_active)` with `unique(judge_id, stage_id)`.

Its `role` column (`head_judge` / `panel_member`) is **a role within a panel and has nothing to do with system roles.** They are deliberately separate concepts that unfortunately share a word: a `head_judge` on one stage's panel may be a `panel_member` on another's, and neither fact grants any system permission. The permission to *score at all* comes from the `judge` system role; *which* applications one may score comes from assignment. This ADR keeps them in separate tables with no FK between them, and the naming collision should be treated as a known hazard in code review.

---

### 2. Relationships

```
                 ┌───────────────────────────────────────────┐
                 │                  User                     │
                 │  type: contestant | admin  (auth surface)  │
                 └───────────────────────────────────────────┘
                        │                        │
          role_user     │                        │ 1:1 (judges.user_id UNIQUE)
          (M:N)         │                        │ RESTRICT
                        ▼                        ▼
                 ┌─────────────┐          ┌─────────────┐
                 │    Role     │          │    Judge    │
                 │  is_system  │          │ specialization
                 └─────────────┘          └─────────────┘
                        │                        │
   role_has_permissions │                        │ 1:N
          (M:N)         │                        │ unique(judge_id, stage_id)
                        ▼                        ▼
                 ┌─────────────┐          ┌──────────────────┐
                 │ Permission  │          │ JudgeAssignment  │
                 │resource.act │          │ role: head|member│
                 └─────────────┘          └──────────────────┘
                                                   │
                                                   │ stage_id (by id only —
                                                   ▼  Competition module)
                                             ╌╌╌╌╌╌╌╌╌╌╌╌
                                                 Stage
```

Notes that matter:

* **Permissions attach to Roles only, never directly to Users.** A per-user exception is invisible in the roles UI, unauditable at a glance, and is how permission sprawl starts. If a user needs a capability, either their role grants it or a role exists that does.
  **How strongly this is enforced depends on open question 1**, and the difference is not cosmetic:
  * Under a **custom** implementation, `model_has_permissions` simply does not exist — direct grants are structurally impossible.
  * Under **Spatie**, the table **must exist**: `HasRoles` pulls in `HasPermissions`, whose `permissions()` morphToMany is consulted on *every* permission check, and whose force-delete hook detaches it. The rule then degrades from structural to **policy** — no endpoint writes it, plus an architecture test asserting it stays empty.

  See [RBAC-IMPLEMENTATION-COMPARISON.md](../RBAC-IMPLEMENTATION-COMPARISON.md) §3.2.
* `judge_assignments.stage_id` references a Competition-module table. Per ADR-002 this crosses a module boundary, so it is referenced **by id only** — the Judges module never imports a Stage entity, and panel composition is read through a contract.

---

### 3. Aggregates and their boundaries

| Aggregate Root | Contains | Enforces | Module |
|---|---|---|---|
| **User** | identity, credentials, MFA state, `type`, `is_active`, the *set of role ids* it holds | valid email; a role cannot be assigned twice; cannot deactivate the last active `super_admin` holder | Core |
| **Role** | name, `is_system`, the *set of permission ids* it grants | name unique per guard; a system role's name and `is_system` are immutable; `super_admin`'s permission set is immutable | Core |
| **Permission** | name, guard | **reference data.** Not admin-writable. Seeded, read-only through the API. | Core |
| **Judge** | profile columns, `is_active` | must reference an existing User; deactivating cascades to no assignments (see below) | Judges |
| **JudgeAssignment** | judge id, stage id, panel role, `is_active` | one assignment per (judge, stage) | Judges |

**Why `JudgeAssignment` is its own root and not part of `Judge`:** its invariant — one assignment per judge per stage — is scoped to the *(judge, stage)* pair, and stage lives in another module. Loading a full Judge aggregate to add one panel membership would mean the Judges module reaching into Competition to validate, which ADR-002 forbids. Keeping it separate lets the assignment use case validate the stage through the Competition contract and keeps the Judge aggregate about the *person*.

**Why User holds role ids and not Role objects:** the User aggregate must be loadable and savable without loading every permission of every role. It holds ids; permission resolution is a read-model concern (§5).

---

### 4. What is Domain, and what is Spatie

This is the central technical decision, and it needs to be made with the §Context finding in view: **Spatie is installed but unused, and the current tables fit neither Spatie nor a clean own-implementation without a migration.**

> **This section is written for the Spatie path and is conditional on open question 1.** A full evidence-based comparison of both options — including the UUID cost, the `model_has_permissions` constraint, and a revised recommendation in favour of a **custom** implementation — is in
> **[RBAC-IMPLEMENTATION-COMPARISON.md](../RBAC-IMPLEMENTATION-COMPARISON.md)**.
> The layering rule below (domain never imports the mechanism) is **unconditional** and applies to either answer.

#### The decision: the mechanism lives in Infrastructure. The Domain never sees it.

```
Domain          Modules/Core/Domain/          — pure PHP. No Spatie, no Eloquent.
  ├─ Entities/User.php          holds roleIds, assignRole(), revokeRole()
  ├─ Entities/Role.php          holds permissionIds, grant(), revoke(), isSystem()
  └─ ValueObjects/PermissionName.php   validates the resource.action shape

Contracts       Modules/Core/Domain/Repositories/
  ├─ RoleRepositoryContract
  └─ PermissionCatalogContract        read-only list of seeded permissions

Infrastructure  Modules/Core/Infrastructure/  — Spatie may be used here and nowhere else.
  └─ Database/Repositories/SpatieRoleRepository.php
```

**What Spatie is used for** (its genuine value, none of which is domain logic):

* The permission **cache** — resolving a user's effective permissions on every request without N queries is a solved problem and re-solving it is waste.
* `can()` / Gate integration, so `$this->authorize('seasons.create')` works in the standard Laravel way.
* Battle-tested guard handling and the pivot management that goes with it.

**What Spatie is explicitly *not* allowed to do:**

* Appear in any `Modules/*/Domain/` file. Not an import, not a type hint.
* Appear in any Controller. Controllers call use cases (ADR-014); authorization checks go through Gate/policies.
* Be the source of truth for *business* rules. "Can this role be deleted?" is `Role::isSystem()`, a domain question, not a Spatie one.
* Provide direct user permissions. Under Spatie the table must exist, so this is enforced as policy plus an architecture test rather than by absence (§2).

#### The migration this requires — stated, not glossed

Adopting Spatie means reconciling the existing tables with what Spatie expects:

| Table | Now | Spatie expects | Action |
|---|---|---|---|
| `permissions` | uuid, name, guard_name | same | none |
| `roles` | uuid, name, guard_name | same (+ `team_id` if teams; not used) | add `is_system` (§6) |
| `role_has_permissions` | permission_id, role_id | same | none |
| `role_user` | role_id, user_id | `model_has_roles(role_id, model_type, model_id)` | **rename + add polymorphic columns** |
| `model_has_permissions` | absent | **required — `HasRoles` pulls in `HasPermissions`, which queries it on every check** | **must be created**, then kept empty by policy (§2) |
| `roles` / `permissions` keys | uuid (ADR-005 D2) | stub ships `$table->id()` (bigint) | hand-written migration + subclassed models with `HasUuids`, `$keyType='string'`, `$incrementing=false`, and `'model_morph_key' => 'model_uuid'` |

All four tables are empty of application usage, so this migration carries **no data risk today** and considerable risk if deferred until they hold real assignments. That is the strongest argument for settling this ADR before the epic starts rather than during it.

> **If the board prefers to drop Spatie entirely** and implement against the existing `role_user` shape: that is a defensible alternative — the app is API-only, so Blade directives and much of the package's surface are irrelevant, and it removes a dependency. The costs are re-implementing the permission cache and the Gate wiring by hand. This ADR recommends keeping Spatie, but the decision is genuinely open and should be taken explicitly rather than by default. **See Alternatives.**

---

### 5. The `permissions: ['*']` fix

`UserResource` stops computing anything. It reports what the aggregate holds:

```
'roles'       => the user's role names, from the aggregate
'permissions' => the effective permission names, resolved through the
                 permission read-model (cached), never a literal
```

A super admin's response then contains the real ~150 names rather than `['*']`. If the frontend wants a shorthand, it derives one; the API does not invent one.

**Wildcards are not introduced.** `*` is not a permission and cannot be granted. `super_admin` is powerful because it holds every permission, not because it holds a magic one — which means the admin UI can show exactly what it can do, and an audit log can record exactly what changed.

---

### 6. Privilege escalation rules

These are invariants, enforced in the domain and the use case layer, not merely absent from the UI.

**PE-1 — You cannot grant what you do not hold.**
A user may only assign a role whose permission set is a **subset** of their own effective permissions. Prevents a Data Entry operator with `users.update` from assigning themselves `super_admin`.

**PE-2 — You cannot edit a role into something you could not grant.**
Editing a role's permissions is restricted to permissions the editor holds. Otherwise PE-1 is trivially bypassed by editing a role you *can* assign.

**PE-3 — You cannot change your own roles.** Self-assignment is refused regardless of permissions held. Role changes for an account are made by a different account.

**PE-4 — System roles are protected by a column, never by a list.**
`roles.is_system` is a real boolean column. `super_admin` is seeded with `is_system = true` and: cannot be renamed, cannot be deleted, cannot have permissions revoked.

> This column exists specifically because of Shaabjo's failure mode, where `is_system` was *computed in a Resource from a hardcoded list* — so the UI displayed a protection badge on eight roles while exactly one was actually protected. A badge that does not correspond to enforcement is worse than no badge. Here the badge reads the same column the guard reads.

**PE-5 — The last `super_admin` cannot be removed.**
Neither by revoking the role, nor deactivating the account, nor soft-deleting it. Refused with a distinct error, not a generic 403. Guards against locking the organisation out of its own platform.

**PE-6 — You cannot deactivate or delete yourself.** Same reasoning, cheaper to enforce.

**PE-7 — Every RBAC change is explicitly audited.**

This is the Shaabjo insight worth transferring wholesale, and it is subtle enough to be worth restating: **Eloquent model observers never fire for role and permission changes, because those changes are writes to pivot tables (`role_user`, `role_has_permissions`), not to model attributes.** Any audit system relying on model observers records nothing at all and appears to work.

Precisely: `spatie/laravel-permission` *does* ship its own pivot events (`RoleAttachedEvent`, `PermissionAttachedEvent`, …) — but `config/permission.php` defaults `'events_enabled' => false`, which is very likely the exact mechanism of Shaabjo's silent failure. Relying on them would also be wrong here for a second reason: a package event knows *what* changed but not *why*, and ADR-012 puts that knowledge in the use case.

So RBAC mutations are logged explicitly, from the use case, recording: causer, subject, and old/new/added/removed sets. `spatie/laravel-activitylog` is already a dependency; it is used from the use case, never from an observer, under either answer to question 1.

---

### 7. Lifecycles

#### Creating a contestant

Self-service, per ADR-003. Social provider → `users` row with `type = 'contestant'`, no roles, no password. Authorization on the public surface is ownership-based ("your own application"), not role-based.

#### Creating an admin

No self-registration (ADR-003). A user holding `users.create`:

1. `CreateAdminUserUseCase` — email, name, `type = 'admin'`, `is_active = true`, no password.
2. Roles assigned in the same transaction, **subject to PE-1**.
3. An invitation/set-password flow issues the credential; the creator never sets another user's password and never sees it.
4. MFA enrolment on first login, per the existing TOTP flow.
5. `AdminUserCreated` + explicit RBAC audit entry (PE-7).

#### Creating a judge

Three facts, one transaction, in this order — because each depends on the previous:

1. **User** — `type = 'admin'` (judges use the admin authentication surface).
2. **`judge` role assigned** — grants *the capability to score*.
3. **`judges` row** — the profile: specialization, title, bio, photo.

Then, separately and repeatedly over the season: **JudgeAssignment** per stage, granting *the scope* — which panel, and whether head or member.

The separation is the point. Step 2 says "may score". Step 4 says "may score *this*". Removing a judge from a panel does not strip their capability or delete their profile; deactivating their User stops their login while assignments and history remain intact, because `RESTRICT` on `judges.user_id` means historical evaluations never lose their author.

---

### 8. Consequences

**Positive**

* Admin-ness is a column, so a role created in the panel works immediately — Shaabjo's central bug is structurally impossible here.
* `is_system` is enforcement and display from one source, so the UI cannot lie about protection.
* No `PermissionGroup` table, no `permission_group_id`, no CRUD, no possible drift between two grouping mechanisms — and grouping still works.
* The domain stays testable without a database; Spatie is swappable because nothing above Infrastructure knows it exists.
* `permissions` in the API becomes true.

**Negative / costs**

* `role_user` → `model_has_roles` migration is unavoidable if Spatie is adopted. Cheap now (tables unused), expensive later.
* PE-1 and PE-2 require resolving the actor's effective permissions on every RBAC write — a real cost, mitigated by Spatie's cache.
* No direct user permissions means an exception for one person requires a role. Intentional, occasionally inconvenient.
* `users.type` currently stores `'user'`, while this ADR's vocabulary says `'contestant'`. **A rename is proposed but not required for correctness**; it touches `EnsureUserIsAdmin`, seeders, and tests. If the board prefers, `'user'` may stand as-is and this ADR's `contestant` is read as its synonym. Recommendation: rename during this epic, since the epic already touches these files.

---

## Alternatives Considered

**A1 — Drop Spatie, implement RBAC directly on the existing tables.**
Attractive: removes a dependency, `role_user` already fits, and an API-only app uses little of the package. Originally rejected here as the default — on the grounds that the permission cache and Gate wiring are real work — but **that judgement has since been revised**. Evidence gathered after this ADR was drafted (Spatie's integer-keyed stub versus this project's UUID keys; the mandatory `model_has_permissions` table; the scale actually involved) reverses the effort comparison. See [RBAC-IMPLEMENTATION-COMPARISON.md](../RBAC-IMPLEMENTATION-COMPARISON.md) §6. **This is now the recommended option, pending the board's decision.**

**A2 — Adopt Spatie fully, including direct user permissions.**
Rejected. `model_has_permissions` makes a user's effective capability the union of two sources, one of which is invisible in the roles UI. Unauditable in practice.

**A3 — Roles as a PHP enum.**
Rejected outright. It is Shaabjo's `ADMIN_ROLES` in a nicer costume: creating a role would require a deployment, and the panel's role-management UI would be decorative.

**A4 — Model Judge as a role only, with no `judges` table.**
Rejected. `specialization`, `title`, `bio`, `photo_media_id` are profile data with no meaning as permissions, and the existing schema already committed to the entity with a `RESTRICT` FK protecting evaluation history.

**A5 — Approve Department now as an organisational label.**
Rejected for now, per §1. Reconsider only against the concrete test stated there.

---

## Open Questions for the Board

1. **Spatie or custom** (§4, A1) — the one decision that must be settled before any code. Everything else in this ADR holds either way.
   **A full comparison with costs, required migrations and architectural impact is in [RBAC-IMPLEMENTATION-COMPARISON.md](../RBAC-IMPLEMENTATION-COMPARISON.md), which revises this ADR's original recommendation and now recommends the custom implementation.** Once answered, the answer is recorded here and is not reopened during implementation.
2. **`users.type`: rename `'user'` → `'contestant'`?** (§8)
3. **Department** — is there a routing/queue rule that qualifies it? If not, it stays deferred (§1).
4. **The initial role set** — are ADR-001's six actors (`super_admin`, `competition_manager`, `judge`, `evaluator`, `data_entry`, `moderator`) the right seed, and what does each hold?

---

## Implementation Order (after approval)

Set by the board:

1. **Users** — admin CRUD, activation/deactivation, PE-3/PE-5/PE-6, audit; `UserResource` truth fix (§5).
2. **Roles** — CRUD, `is_system` enforcement, role assignment to users, PE-1/PE-2/PE-4, audit.
3. **Permissions** — the catalogue, attachment to roles, and **enforcement switched on**.
4. **Judges** — an extension of User, never a Role. The behaviour layer over a finished schema (the module today has 0 use cases, 0 domain events, 2 routes).
5. **Judge Assignments** — currently no repository, no controller and **no route at all**; the table is referenced as a restore blocker yet no assignment can be created through the API.
6. **Departments** — only if question 3 is answered affirmatively; otherwise deferred indefinitely.

Two notes on sequencing, neither of which changes the order:

* **A role editor has nothing to display until the permission catalogue exists.** Recommend shipping the catalogue **seeder** — a data file, not a feature — during epic 1, so epic 2 has real permissions to attach. The *enforcement* (Gate checks, middleware) still lands in epic 3.
* **Enforcement goes on last, deliberately.** Until epic 3, `EnsureUserIsAdmin` remains the gate, so no one can be locked out of the admin panel by a half-populated role table midway through the sequence.

**Schema work** (`is_system`, plus whatever question 1 requires) lands at the start of epic 2, which is the first moment it is needed and still inside the window where all four tables are unused.

---

## References

* ADR-001 — actor list
* ADR-002 — module boundaries; cross-module reference by id
* ADR-003 — authentication; the two-tier surface this ADR builds on
* ADR-005 — UUID keys, delete policy, FK rules
* ADR-012 — use case orchestration and transaction boundaries
* ADR-014 — controllers stay thin; Resources do not compute domain facts
