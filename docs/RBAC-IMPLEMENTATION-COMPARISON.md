# RBAC Implementation Comparison — Spatie vs. Custom

| Field | Value |
|---|---|
| **Date** | 2026-08-17 |
| **Purpose** | Decide question 1 of [ADR-015](./adr/ADR-015-identity-and-access-architecture.md) before any Identity & Access code is written |
| **Status** | Decision brief — awaiting the board |
| **Binding** | Once decided, the answer is recorded in ADR-015 and does not change during implementation |

---

## 1. What is actually being decided

Not "should we have RBAC" — ADR-015 settles the model. The question is narrower and purely about mechanism:

> **Who owns the code that stores role/permission assignments, resolves a user's effective permissions, caches that resolution, and wires it into Laravel's `Gate`?**

Everything else in ADR-015 — `users.type` as the auth surface, derived permission groups, `roles.is_system`, the seven privilege-escalation rules, Judge as an entity — holds identically under either answer. This decision touches Infrastructure and nothing above it.

---

## 2. Baseline: facts both options start from

Verified in code, not assumed.

| Fact | Detail | Consequence |
|---|---|---|
| Spatie is installed, unused | v8.3.0, `config/permission.php` published, **zero** references in `Modules/` or `app/` | Removing it costs nothing today |
| Four tables exist, unused | `roles`, `permissions`, `role_user`, `role_has_permissions` — no code reads or writes them | Either option can reshape them freely, **now** |
| Primary keys are UUID | `PlatformBlueprint::uuidPrimary()`, per ADR-005 Decision 2 | Central to the comparison — see §3.1 |
| Authorization is one boolean | `EnsureUserIsAdmin`: `$user->type !== 'admin'` | No enforcement to preserve or migrate |
| The API lies about permissions | `UserResource`: `'permissions' => $type === 'admin' ? ['*'] : []` | Must be fixed either way |

**The window matters.** All four tables are empty of application usage. Whatever reshaping either option needs is free today and becomes a data migration with real risk once roles are assigned to real staff.

---

## 3. Option A — Adopt Spatie fully

### 3.1 The UUID problem — the largest single cost, and it is not optional

Spatie's published migration stub uses auto-incrementing integers:

```
$table->id();                                    // roles, permissions
$table->unsignedBigInteger($pivotPermission);    // pivots
$table->unsignedBigInteger($columnNames['model_morph_key']);
```

This project uses UUIDs everywhere, by ADR-005 decision. Spatie **does** support UUIDs — `config/permission.php` says so explicitly at line 102 ("this would be nice if your primary keys are all UUIDs… name this `model_uuid`") — but supporting it means:

1. Writing the migration by hand; the published stub cannot be used as-is.
2. Subclassing `Spatie\Permission\Models\Role` and `…\Permission` with `HasUuids`, `$keyType = 'string'`, `$incrementing = false`, and pointing `config/permission.php` at the subclasses.
3. Setting `'model_morph_key' => 'model_uuid'`.

None of this is exotic — it is documented and widely done. But it means **"install Spatie" is not the low-effort path it appears to be**, and the subclasses become permanent code we own and must keep aligned across package upgrades.

### 3.2 `model_has_permissions` is mandatory — correcting an error in ADR-015

ADR-015 §2 states the table "is not created". **That is wrong and must be fixed**, because Spatie does not permit it:

* `HasRoles` uses `HasPermissions` (`src/Traits/HasRoles.php:24`).
* `HasPermissions::permissions()` is a `morphToMany` against `Config::modelHasPermissionsTable()`.
* Every permission check consults direct permissions before role permissions, so the table is **queried on every authorization decision**.
* The force-delete hook calls `$model->permissions()->detach()` (`HasPermissions.php:44`).

Under Spatie, the table must exist. The ADR's *intent* — never grant permissions directly to users — survives as **policy** rather than as absence: no endpoint writes it, and an architecture test asserts it stays empty. That is weaker than "the table does not exist", and it is an honest cost of this option.

### 3.3 Required schema work

| Table | Now | Under Spatie | Work |
|---|---|---|---|
| `permissions` | uuid, name, guard_name, unique | same | none |
| `roles` | uuid, name, guard_name, unique | same + `is_system` | add one column |
| `role_has_permissions` | permission_id, role_id (uuid) | same names | none |
| `role_user` | role_id, user_id | `model_has_roles(role_id, model_type, model_uuid)` | **rename table, add `model_type`, rename `user_id`** |
| `model_has_permissions` | absent | **required** | **create** (then keep empty by policy) |

### 3.4 What Spatie genuinely provides

* **`PermissionRegistrar` cache.** Loads all permissions and their role links in one query and caches for 24h. Re-implementing this correctly — including invalidation on every role/permission write — is the single most valuable thing in the package.
* **`Gate` integration**, so `$this->authorize('seasons.create')` and `$user->can(...)` work the standard Laravel way, including in policies.
* **Route middleware** (`role`, `permission`, `role_or_permission`).
* **Pivot events** — `RoleAttachedEvent`, `PermissionAttachedEvent`, etc. **Default `'events_enabled' => false`.** Worth noting against ADR-015's PE-7: the underlying claim (Eloquent *model observers* never fire for pivot writes) stays true, but Spatie offers its own event channel if switched on. This is very likely why Shaabjo's audit silently recorded nothing.
* Artisan helpers (`permission:show`, `permission:cache-reset`).
* Battle-tested guard handling and a large user base finding edge cases for us.

### 3.5 Architectural friction

Spatie's `Role` and `Permission` are Eloquent models, and its API is model-centric (`$user->assignRole(...)`). ADR-002 and ADR-012 require the domain to be persistence-free. Reconciling these means:

* Spatie confined to `Modules/Core/Infrastructure/`, behind `RoleRepositoryContract`.
* A translation layer in both directions: domain `Role` ↔ Spatie `Role`.
* Domain rules (`isSystem()`, PE-2 subset checks) living in our entity, while the *storage* of the same data lives in a model we subclass.

This is normal hexagonal practice and not a defect — but it is real code that exists solely to keep the package at arm's length.

---

## 4. Option B — Custom RBAC

### 4.1 What must be built

| Piece | Effort | Notes |
|---|---|---|
| `role_user`, `role_has_permissions` queries | Small | Tables already exist in the right shape; **no migration at all** except adding `is_system` |
| Effective-permission resolution | Small | One join: `role_user → role_has_permissions → permissions` |
| **Cache + invalidation** | **Medium — the real cost** | Must cache per user or globally, and invalidate on every role edit, permission grant, and role assignment. Getting invalidation wrong produces stale authorization, which is a security bug, not a performance bug |
| `Gate` wiring | Small | `Gate::before` or `Gate::define` over the resolved set |
| Middleware | Small | One middleware; the app is API-only |
| Domain events for RBAC changes | **Free — it is the natural design** | Use cases already emit domain events per ADR-008/ADR-012 |

### 4.2 What it avoids

* **No UUID reconciliation.** The existing tables are already UUID and already the right shape. `role_user` stays `role_user` — no rename, no `model_type`, no polymorphic column the design does not use.
* **No `model_has_permissions`.** ADR-015's intent becomes structural: the table genuinely does not exist, so direct user permissions are impossible rather than merely discouraged. This is the stronger guarantee and matches the ADR's stated reasoning.
* **No translation layer.** The domain `Role` entity ADR-015 describes *is* the model; `RoleRepositoryContract` maps it to rows directly, exactly as `SeasonRepository` already does for `Season`.
* **No subclassed vendor models** to re-verify on every package upgrade.
* **No polymorphic `model_type`** stored on every assignment for a design that will only ever assign roles to users.

### 4.3 What it risks

* **Cache invalidation is on us.** This is the honest counterweight and should not be minimised. Spatie has had years of bug reports shaping this; a fresh implementation has not.
* No community edge-case coverage for guards, nested checks, or upgrade paths.
* `Gate` wiring, middleware and Artisan conveniences are small individually but add up.

### 4.4 Scale check

The costs above are load-bearing only at scale. This platform's scale, from the schema:

* ~150 permissions, ~6 roles.
* Admin users are provisioned manually by a Super Administrator (ADR-003) — tens, not thousands.
* Contestants never hold roles at all; their authorization is ownership-based.

A per-request resolution is one indexed join returning at most ~150 rows, cacheable with a single key per user invalidated on any RBAC write. **The problem Spatie's registrar solves well is not a problem this system has at this size.**

---

## 5. Head to head

| | **A — Spatie** | **B — Custom** |
|---|---|---|
| Migration needed now | rename `role_user` → `model_has_roles`, add `model_type`/`model_uuid`, **create `model_has_permissions`**, add `is_system` | add `is_system`. Nothing else |
| UUID fit | Supported, but needs hand-written migration + subclassed models + config change | Native — tables already UUID |
| "No direct user permissions" | Policy only; table must exist and is queried on every check | Structural; table does not exist |
| Permission cache | Provided, mature | **Must be built — the main cost** |
| Gate / middleware | Provided | ~Half a day |
| Fit with ADR-002/012 | Needs a translation layer to keep Eloquent out of the domain | Native — same shape as existing repositories |
| Vendor coupling | Subclassed models to re-verify on upgrades | None |
| RBAC audit (PE-7) | Package events exist but default off; use-case logging still preferred | Domain events from the use case — the natural design |
| Community hardening | Significant | None |
| New dependency | Kept | Removed |

---

## 6. Recommendation — Option B, custom

**ADR-015 currently recommends keeping Spatie. This brief revises that recommendation**, on evidence gathered after the ADR was drafted.

Three findings moved it:

1. **The UUID mismatch inverts the effort comparison.** Spatie was assumed to be the "already installed, just use it" option. It is not: it needs a hand-written migration, two subclassed models, and a config change before the first role can be created. Option B needs one added column.
2. **`model_has_permissions` cannot be omitted.** ADR-015's own reasoning — that a per-user permission is invisible in the roles UI and unauditable, and is how sprawl starts — argues for making it impossible, not merely discouraged. Only Option B delivers that.
3. **The one thing Spatie is clearly better at is sized for a problem this system does not have.** ~6 roles and ~150 permissions over tens of admins is not where a hand-written cache becomes dangerous.

Against that, the cache-invalidation risk is real and should be met deliberately: a single cache key per user, invalidated from the use cases that write RBAC (which are the only writers, since ADR-015 forbids direct grants), plus a test that asserts a role edit is visible to an affected user on the next request.

### What would change this recommendation

* If direct user-level permissions are ever wanted, Spatie's shape starts paying for itself.
* If teams/multi-tenancy appear (separate competitions with isolated staff), Spatie's teams feature is real work to replicate.
* If the board prefers vendor-maintained security-adjacent code on principle — a legitimate position that outweighs the effort arithmetic.

---

## 7. Fixes ADR-015 needs, whichever option wins

1. **§2 and §4 — `model_has_permissions`.** "Not created" is only achievable under Option B. Under Option A it must exist and the intent degrades to policy. State whichever is chosen accurately.
2. **§6 PE-7 — the audit rationale.** Correct as written about Eloquent *model observers*, but should note Spatie's own pivot events exist and default to off, since that is probably the exact mechanism of Shaabjo's silent failure.
3. **§4 — the UUID cost.** The ADR does not mention it, and it is the largest single item in the Spatie column.
4. **Implementation order.** ADR-015 lists permissions-first; the board's stated order is Users → Roles → Permissions → Judges → Judge Assignments → Departments. Adopt the board's order, with one dependency noted: a role editor has nothing to display until the permission catalogue exists. Recommend shipping the **catalogue seeder** (a data file, not a feature) during the Users epic, so Roles has something real to attach, and switching **enforcement** on at the end of the Permissions epic — which also avoids locking anyone out midway.

---

## 8. The question for the board

> **Option A (Spatie) or Option B (custom)?**

This brief recommends **B**. Either answer is defensible; what matters is that it is recorded in ADR-015 before implementation starts, and not revisited during it.
