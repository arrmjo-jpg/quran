# ADR-019: The User Menu, and the Account's Language

| | |
|---|---|
| **Status** | **Accepted — 2026-08-28.** Q1 closed by the board: reuse `PATCH /me`. |
| **Date** | 2026-08-28 |
| **Depends on** | ADR-014 (presentation layer), ADR-015 (identity & access), ADR-016 (epic order), ADR-018 (account security — the screen this menu links to) |
| **Relates to** | ADR-016 epic table row 7 |

---

## Why this document exists

The epic table gives Epic 7 two words: *"User menu"*. Searched across the whole
repository, **that phrase occurs exactly once — in the table row itself**. No
ADR elaborates it and the original proposal does not mention it. This is the
first epic in the sequence with no specification at all, rather than a thin one.

So this document is not a record of decisions taken elsewhere. It is the
specification, and it exists because the alternative is inventing scope during
implementation.

---

## What exists today (measured, not assumed)

Measured on `main` at `494f664`, 2026-08-28. Read-only; nothing was written.

| Thing | State |
|---|---|
| **A user menu** | Does not exist. The header is a flat row: command palette, a hardcoded `Docker Live API: :8080` badge, quick-search button, language switcher, theme toggle, name+email linking to `/profile`, and an always-visible logout button |
| **`users.preferred_locale`** | Exists. `PATCH /me` validates it (`UpdateProfileRequest`), the controller forwards it, and `UpdateUserProfileUseCase` persists it. **The whole server chain works** |
| **The login response** | Already carries `preferred_locale` — verified against the running API, which returned `'ar'`. No extra request is needed to learn it |
| **Who can set it** | `UserFormDialog` lets an administrator set it **for somebody else**. `/profile` does not expose it at all. So nobody can set their own, and the value nobody can set is read by nothing |
| **i18n initialisation** | `initialLanguage()` reads `localStorage` only, validated against `SUPPORTED_LANGUAGES` with a fallback. It runs **at module load**, before React mounts |
| **Language switcher** | Mounted in `Header.tsx` and nowhere else. Writes `i18n.changeLanguage`, `applyDirection`, and `localStorage`. Never calls the API |
| **Theme** | `localStorage` only. There is **no theme column** on `users` or `user_profiles` — both were listed |
| **Command palette** | 12 destinations, filtered by a text match on the label. **No permission check.** 10 of those 12 already have a rule in `ROUTE_PERMISSIONS` that nothing consults |
| **Sidebar** | 22 destinations, each wrapped in `PermissionWrapper` via `requiredPermissionFor()` |
| **`requiredPermissionFor(path)`** | Exported from `routeAccess.ts` and already used by the sidebar |
| **Dropdown component** | None. No `radix`, `headless`, `popover` or `floating` package is installed |

### `preferred_locale` is a field that nothing reads

The column is stored, validated, persisted, editable by an administrator on
another account, and displayed on two screens. Nothing consumes it. The
language an administrator actually sees comes from a per-browser
`localStorage` key, so an administrator who sets a colleague's language
changes nothing the colleague will ever see.

This is the same defect already recorded for the four unrouted `users.*`
permissions and the notification retry that dispatches nothing — a stored fact
with no consumer.

---

## Decisions

| # | Decision | Rationale and trade-offs |
|---|---|---|
| **D1** | **The user menu is the account's single entry point: Profile, Security, Language, Theme, Logout.** Nothing new is invented to fill it. | The header today scatters account concerns across a flat row and hides `/profile` behind a name that does not look like a link. Epic 6 added `/security`, which has no entry point at all outside the sidebar. Gathering them is the epic. **What it does NOT gather:** notifications (Epic 8), and anything that does not already exist |
| **D2** | **`users.preferred_locale` becomes the source of truth for language.** | It is already the source of truth in the database, in the API, and in the administrator-facing form. Only the browser disagreed. Making the switcher write it turns a dead column into the account's setting, and makes the existing administrator control mean something. **No backend work:** the server chain is complete and measured |
| **D3** | **`localStorage` stays the boot value; the account value reconciles after authentication.** | Forced by measurement, not preference. i18n initialises at module load — before React mounts and before any request — so the account's language cannot be known synchronously. Reading `localStorage` first and reconciling after auth is the only sequence that does not flash. **Trade-off:** for one render after signing in on a new browser, the language may be the browser's rather than the account's. The alternative is blocking the whole app on a request, which trades a flash for a blank screen |
| **D4** | **The switcher moves into the menu. No second language control remains in the header.** | Two controls for one setting is how they drift. Measured consequence: `LanguageSwitcher` is mounted **only** in `Header.tsx`, and the login page has no switcher at all — so moving it takes nothing away from anybody, including signed-out users, who never had it |
| **D5** | **`/profile` is not modified.** | Language is a header/menu concern under D4, and `/profile` is Epic 3's screen. Adding a field there would mean two places to change one setting, which is what D4 exists to prevent |
| **D6** | **Theme stays in `localStorage`. No column is added.** | There is no theme column on either table, so unlike language there is no server-side value being ignored. `localStorage` is not a workaround here; it is the right home for a per-browser display preference |
| **D7** | **The command palette filters by `requiredPermissionFor()` — the same function the sidebar uses.** | It advertises destinations the operator cannot open; the route guard then refuses. A permission mechanism already exists and is already applied to the sidebar, so this is a use of it, not a new one. Its destinations are also reconciled with the routes that exist — **without adding routes to round out the list** |
| **D8** | **`/about` and `/audit-logs` stay out of the sidebar.** | Both are reachable from the dashboard today, which an earlier reading of mine got wrong and this corrects. Epic 7 is a user menu, not a navigation redesign |
| **D9** | **No new permission is introduced.** | The menu is self-service: it acts on the signed-in account, so holding an account is the authorisation — the same reasoning ADR-018 D3 used for `/security` itself. `audit.view` keeps meaning the activity log and nothing else |

---

## What this epic does **not** do

* **No notifications.** Epic 8, including any bell in the header.
* **No navigation redesign** (D8).
* **No changes to MFA, sessions or trusted devices.** Epic 6's screens and contracts are linked to, not rebuilt.
* **No new settings.** If it is not already a stored preference, it does not appear.
* **No `/profile` changes** (D5).

---

## Debt recorded, deliberately not fixed here

* **`Docker Live API: :8080`** is hardcoded into the admin header — a development artifact in the shell. Untouched: it is not a user-menu concern, and removing it is a visible change nobody asked for.
* **`/about` exposes PHP, Laravel, MySQL and FFmpeg versions plus the build commit, with no permission rule.** Whether system diagnostics should be open is a decision for whoever owns that screen.
* **The command palette lists 12 of 22 destinations.** D7 reconciles it with routes that exist; deciding which destinations *belong* in a palette is a navigation question, not this one.

---

## Consequences

* An administrator changing language changes it for their account, on every
  device, rather than for one browser.
* An administrator setting a colleague's language starts having an effect.
* A signed-out user still sees the language their browser last used, because
  there is no account to read from until they authenticate.
* The command palette becomes narrower for anyone who is not `super_admin` —
  which is the point.

---

## Q1 — CLOSED: reuse `PATCH /me`

**Decided 2026-08-28.** The language write goes through the existing
`PATCH /me`. **No new endpoint and no new permission.**

That route is already the self-service write path, already validates
`preferred_locale` against `in:ar,en,es`, and already persists it through
`UpdateUserProfileUseCase`. A second endpoint would mean two ways to write one
column, which is the same failure D4 avoids on the front end.

Recorded as **D10** for citation from code:

| # | Decision | Rationale |
|---|---|---|
| **D10** | **The menu writes language through `PATCH /me`.** | The whole server chain exists and is measured. This epic adds no route, no request class, no use case and no permission on the backend — the only backend artefact it produces is a golden master over a contract it is about to depend on |

---

## Testing reality, recorded because it bounds what S1 can prove

**There is no frontend test runner.** No `vitest`, `jest`, `testing-library`,
`playwright` or `cypress` is installed, and `src/` contains no test file. So
the frontend half of this epic — i18n boot order, the switcher, the menu — is
**not pinnable by an automated test**, and this ADR does not pretend otherwise.

Worse, three scripts named like gates are not gates: `gate:e2e`,
`gate:reliability` and `gate:observability` are `echo` statements that exit 0.
Only `gate:functional` does real work (typecheck, authz model, permissions
mirror, build).

**Consequence for S1:** the golden master covers the `PATCH /me` contract,
which is real and testable. The frontend's current behaviour is characterised
in prose in that file and verified by hand against the running app. Building a
frontend test framework is a separate epic already on the backlog, and
inventing one here would be exactly the scope expansion this epic forbids.
