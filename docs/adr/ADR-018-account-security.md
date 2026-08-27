# ADR-018: Account Security — MFA, Trusted Devices, Login History

| | |
|---|---|
| **Status** | **Accepted — 2026-08-28.** Records ADR-016 **D12**, which the epic table carried as `⚠️ not recorded`. |
| **Date** | 2026-08-28 |
| **Depends on** | ADR-003 (authentication & identity), ADR-005 D2 (UUIDs), ADR-015 (identity & access), ADR-017 (activity log — the three-store taxonomy) |
| **Relates to** | ADR-016 D12 (device trust moves to the database), ADR-016 epic table row 6 |

---

## Why this document exists

The epic table gives Epic 6 one line — *"Security — MFA, trusted devices, login history"*. Measured
against the code, two of those three already exist in some form and the third already exists as
**data**. Building from the table alone would have produced a second login store next to a table
that has been recording logins since the platform booted, and would have moved a decorative
feature into a database without noticing it was decorative.

ADR-016 also warns, in the sequencing note directly under that table, that each epic's scope must
be *measured against the code rather than trusted from this table*. This document is that
measurement.

---

## What exists today (measured, not assumed)

Measured on `main` at `fefa788`, 2026-08-28.

| Thing | State |
|---|---|
| **MFA** | Substantially built. `mfa/setup`, `mfa/verify`, `mfa/recovery`, `login/mfa-challenge`; `TotpService`; `users.mfa_enabled` / `mfa_secret` / `mfa_recovery_codes`; secret encrypted at rest, recovery codes bcrypt-hashed; 9 tests. **No way to turn MFA off**, and no way to regenerate recovery codes |
| **Sessions** | Built: list, revoke one, revoke others; `ActiveSessionsPage` exists |
| **Trusted devices** | Three endpoints, a `DeviceTrustService`, 3 tests — and **no effect on anything**. See below |
| **Login history** | No dedicated code. But `AuditLoggingMiddleware` is appended globally, so it has recorded every login attempt since the platform booted |
| **`audit_logs` contents** | 2 804 rows in the development database. Logins: 32 × `200`, 7 × `401`, 7 × `403`, 1 × `429`. Every row carries `ip`, `user_agent`, `route_name`, `response_status`, `actor_id` and `correlation_id` |
| **`audit_logs.device_id`** | Present on the table, read by the middleware from `X-Device-ID` — and **NULL on all 2 804 rows**, because the admin client never sends that header |

### Trusted devices do nothing

`DeviceTrustService::trustDevice()` mints a 128-bit `trust_token`, writes it to `Cache`, and
returns it in the response body. **Nothing anywhere verifies it.** The only other occurrence of
the string in the repository is a test asserting that it appears in the JSON.

The login flow (`AuthController::login`) checks `mfa_enabled` and issues an MFA challenge. It
never consults `DeviceTrustService`. So trusting a device changes no behaviour: it is three
endpoints, a service, and a secret handed to the client for no purpose — the same defect already
recorded for the four unrouted `users.*` permissions and the notification retry that dispatches
nothing.

This is why D12 could not be executed as literally written. Moving a store from `Cache` to the
database is a sound instruction, but it would have persisted a feature whose purpose was
unsettled. The purpose is settled here, in D5.

---

## Decisions

| # | Decision | Rationale and trade-offs |
|---|---|---|
| **D1** | **Trusted devices move to a `trusted_devices` table, which becomes the single source of truth. `Cache` is not consulted for trust.** | Records ADR-016 D12, which carried no rationale. Three reasons, measured. **(a) A cache flush silently revokes every trust.** `php artisan cache:clear` is a routine operation; a security decision that a user made deliberately must not be collateral damage of one. **(b) The current implementation stores each record twice** — once at `device_trust:{user}:{fingerprint}` and again inside a `user_devices:{user}` array — so the two can diverge, and the list is rewritten with a fresh 30-day TTL on every revoke, which extends the life of the records that were *not* revoked. **(c) A trust grant is a fact about an account**, and D5 makes it a credential; credentials do not live in a store whose contract is "may be evicted at any time". **Trade-off:** a database read joins the login path, which was previously a cache read. Accepted: it is one indexed lookup by token hash, and login already performs several writes. **Correction to D12's wording:** the store was never Redis specifically — it is `Cache`, which is Redis in development and the *database* under `.env.example`'s `CACHE_STORE=database`. The defect is the contract, not the driver |
| **D2** | **Login history is read from `audit_logs`. No second table is created.** | ADR-017 D2 fixed a taxonomy: `audit_logs` records HTTP — who called what — and `activity_logs` records business change. **A login is an HTTP call**, so it is already in the right store by that taxonomy, and 2 804 rows prove it has been landing there all along. Creating a `login_history` table would mean writing a second row for an event already recorded, and the two would drift. This is ADR-016 Q1 asked again and answered the other way: Q1 replaced `spatie/laravel-activitylog` because it was the *wrong shape*; `audit_logs` is the right shape and already populated. **Trade-off:** login history inherits `audit_logs`' retention policy, which is to say none — see D8 |
| **D3** | **Login history and the security screens are gated by a new `security.view`. `audit.view` is not reused.** | Epic 5 made `audit.view` mean one specific thing — the activity feed — three commits ago. Overloading it now would make one permission govern two screens with different audiences, and a grant intended for one would silently confer the other. `security.view` is added to the catalogue; `super_admin` receives it automatically because `RolesSeeder` seeds that role from `PermissionCatalog::all()`. **Trade-off:** one more permission to hold. Accepted — ADR-016 D17 established that a grant becoming live is a decision to take deliberately, not to acquire as a side effect |
| **D4** | **MFA stays optional. Nothing in this epic requires any user to enable it.** | Enforcement is a product decision about who is compelled to carry a second factor, and it is not made here. What *is* delivered is the management surface that makes "optional" true: **MFA cannot currently be turned off**, which means a user who enables it is permanently committed. An optional feature you cannot leave is not optional, so `mfa/disable` and recovery-code regeneration complete the existing mechanism rather than extending it |
| **D5** | **A trusted device skips the MFA challenge for 30 days. `trust_token` becomes a real credential: stored hashed, returned once, verified at login.** | Decided by the board 2026-08-28 when discovery showed the feature was inert. This is the only purpose that explains why the feature was built, and without it D1 would persist something that does nothing. **Trade-off, stated plainly: this deliberately weakens MFA.** For 30 days, possession of the trust token substitutes for the second factor on that browser. That is what "remember this device" means everywhere it is offered; it is recorded here so it is a decision rather than an accident. Mitigations: the token is returned exactly once and stored only as a hash, so a database read cannot recover it; trust is revocable per device and revocation takes effect on the next login; and the grant expires on a fixed 30-day horizon that is not extended by use |
| **D6** | **The trust credential is the token, not the fingerprint. `ip` and `user_agent` are descriptive only.** | The existing `generateFingerprint()` hashes `ip \| user_agent \| device_id`, so trust broke whenever the IP changed — on any mobile network, constantly. That was tolerable while trust did nothing; under D5 it would make the feature useless and push users toward disabling MFA. The token is the credential because it is the only part the client holds secretly. IP and user agent are kept to answer "which device is this?" on screen, and are not consulted in the trust decision |
| **D7** | **The admin client sends a persistent `X-Device-ID`.** | The header is already read by `AuditLoggingMiddleware` and by `trustDevice`, and is NULL on all 2 804 audit rows because nothing sends it. Without it, `audit_logs.device_id` stays a column that lies, and login history cannot say which device an attempt came from. Generated once per browser and persisted in `localStorage`. **It is not a security control** — it is client-supplied and trivially forged, so nothing authorises on it; it is an identifier for display and correlation only. The trust decision rests on the token (D6), never on this header |
| **D8** | **Retention is not decided here, for either store.** | ADR-017 D7 deliberately left `activity_logs` retention open; `audit_logs` has never had a policy either. D2 makes login history a read over `audit_logs`, so it inherits that. Inventing a retention window as a side effect of building a screen would be exactly the failure ADR-016's deferral rules exist to prevent. Recorded as known and unresolved: **`audit_logs` grows unbounded** |
| **D9** | **`audit_logs.created_at` moves from `TIMESTAMP` to `DATETIME`.** | `TIMESTAMP` caps at 2038-01-19. ADR-017 chose `DATETIME` for `activity_logs` for this reason, and the same ceiling was removed from the season lifecycle columns in `407464c` after MySQL rejected a 2040 date with error 1292. A login history is a table people query by date, over a range that already reaches within thirteen years of the ceiling. In the path because D2 makes this table the login history's source |

---

## What this epic does **not** do

* **Does not enforce MFA** on any user or role (D4). That decision is the board's, separately.
* **Does not add a retention or purge policy** to either log (D8).
* **Does not touch row-level security**, which ADR-016 D11 defers to Epic 14.
* **Does not change the session mechanism.** Sanctum tokens stay as they are; the sessions screen is left on its existing contract.
* **Does not treat `X-Device-ID` as an authentication input** (D7).

---

## Consequences

* `Cache` keeps exactly one security role: the 10-minute pending MFA secret during setup. That is a
  transient ceremony, not a source of truth, so D1 does not apply to it.
* Login stops being a pure `mfa_enabled` branch: it consults trusted devices first. The MFA
  challenge path is unchanged for untrusted devices.
* `security.view` starts life held by `super_admin` alone.
* Anyone reading `audit_logs` for a login history must filter by `route_name`, not by path — paths
  differ between the public and admin login routes while the route names are stable.
