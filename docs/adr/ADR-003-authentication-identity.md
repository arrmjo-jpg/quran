# ADR-003: Authentication and Identity Architecture

| Field        | Value                                                              |
|--------------|--------------------------------------------------------------------|
| **ID**       | ADR-003                                                            |
| **Date**     | 2026-07-31                                                         |
| **Authors**  | Platform Architecture Team                                         |
| **Status**   | Accepted                                                           |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership   |
| **Related**  | ADR-001 § Decision #5 (Two-Level Authorization), § Decision #14 (Authentication Architecture) |

---

## Status

**Accepted**

---

## Context

The Global Quran Competition Platform serves two fundamentally different categories of users, each with distinct identity requirements, security expectations, and trust levels:

**Contestants** (`type=user`) are members of the global public. They register independently, may not have technical sophistication, and are best served by delegating identity management to trusted social providers they already use. Asking contestants to create and remember yet another username and password is a friction point that directly impacts participation rates for a global competition.

**Administrators** (`type=admin`) are internal staff of Jordan Radio and Television Corporation — Competition Managers, Judges, Evaluators, Data Entry operators, Moderators, and Super Administrators. These are known, named individuals who are provisioned by the organization, not self-registered. Their accounts hold significant power over the competition lifecycle, including approving applications, assigning scores, and managing the platform. This privilege demands a higher, independently controlled authentication mechanism that does not depend on third-party identity providers.

A single, unified authentication mechanism cannot serve both populations appropriately. A social-login-only system is insecure and inappropriate for staff accounts. A username/password-only system creates unnecessary friction for global contestants and adds credential management responsibilities that the platform need not own.

The architecture must enforce a strict, hard separation: the authentication surface available to one user type must not be accessible by the other, under any circumstances.

---

## Decision

### 1. Two-Tier Authentication Architecture

The platform implements **two fully independent, non-overlapping authentication flows** — one for each user type. No authentication method is shared between user types. The API enforces this boundary at every request; the user's `type` is verified against the authentication channel used.

```
Contestant (type=user)          Administrator (type=admin)
───────────────────────         ───────────────────────────
Google OAuth 2.0                Email + Password
Facebook OAuth 2.0              Laravel Sanctum token
Apple Sign In (optional)        [Future: TOTP 2FA]
Microsoft OAuth (optional)
        │                                  │
        ▼                                  ▼
  Contestant Portal                Admin Dashboard
  (frontend/)                      (admin-frontend/)
        │                                  │
        └──────────────┬───────────────────┘
                       ▼
                  REST API (/api/v1/)
                  (Backend — Laravel)
```

### 2. Contestant Authentication: Social Identity Providers

Contestants authenticate exclusively through **OAuth 2.0 social identity providers**. Traditional email/password registration and login are not available to contestants on this platform.

#### Supported Providers

| Provider  | Status    | Protocol   |
|-----------|-----------|------------|
| Google    | Required  | OAuth 2.0 / OIDC |
| Facebook  | Required  | OAuth 2.0  |
| Apple     | Optional  | Sign in with Apple (OIDC) |
| Microsoft | Optional  | OAuth 2.0 / OIDC |

#### OAuth Flow

The OAuth authorization flow is initiated from the Contestant Portal (frontend) and completed via backend redirect callbacks. The backend is the sole issuer of session tokens; the frontend never holds OAuth access tokens from third-party providers.

Upon successful social authentication:
1. The backend resolves or creates the contestant's account using the provider-supplied identity (provider ID + email).
2. A **Laravel Sanctum API token** is issued and returned to the frontend.
3. The Contestant Portal uses this Sanctum token for all subsequent API requests.
4. The token is scoped to `type=user`; the API rejects any attempt to use it against administration endpoints.

#### Account Resolution

When a contestant authenticates via a social provider for the first time, the backend checks whether an account already exists with the same verified email address. If one does, the new provider is linked to the existing account. If none exists, a new contestant account is created automatically.

Contestants may link multiple social providers to a single account. Unlinking a provider is permitted only if at least one alternative provider remains linked.

#### No Password Storage for Contestants

Contestant accounts do not store a password hash. The `password` column is `null` for all `type=user` accounts. Password reset flows do not apply to contestants.

### 3. Administrator Authentication: Email and Password

Administrators authenticate exclusively through **email address and password**. Social authentication is not available to, and cannot be used by, administrative accounts.

#### Credential Management

- Administrative accounts are provisioned by a Super Administrator. Self-registration is not available for admin accounts.
- Passwords must meet a minimum complexity policy enforced at the application layer.
- Password hashing uses **bcrypt** via Laravel's default hashing configuration.

#### Session Token Issuance

Upon successful authentication:
1. The backend verifies credentials against the `users` table.
2. The `type` column is confirmed to be `admin`.
3. A **Laravel Sanctum API token** is issued and returned to the Administration Dashboard.
4. The token is scoped to `type=admin`; the API rejects any attempt to use it against contestant-only endpoints.

#### Laravel Sanctum

**Laravel Sanctum** is the exclusive token management solution for both user types. Sanctum issues opaque personal access tokens stored in the `personal_access_tokens` table. Token-based authentication is used in preference to cookie/session authentication to support the SPA + API architecture cleanly.

Token capabilities:
- Tokens have configurable expiry.
- Tokens may be revoked individually (logout) or globally (revoke all sessions).
- Tokens store the user's `type` in their abilities or are validated against the user record on each request.

### 4. Authentication Boundary Enforcement

The separation between contestant and administrator authentication is enforced at multiple layers:

| Layer | Enforcement Mechanism |
|---|---|
| **Middleware** | Route groups are protected by middleware that verifies both token validity and `user.type`. |
| **API Design** | Contestant endpoints (`/api/v1/portal/*`) and admin endpoints (`/api/v1/admin/*`) are in separate route groups with separate middleware stacks. |
| **Token Issuance** | Social auth callbacks never issue tokens valid for admin routes. Password auth never issues tokens valid for contestant-only routes. |
| **Provider Restriction** | The social OAuth callback routes are unreachable from the Admin Dashboard. The admin login form is unreachable from the Contestant Portal. |

A contestant holding a valid Sanctum token cannot access any administration endpoint. An administrator holding a valid Sanctum token cannot authenticate via a social provider. These are not runtime checks alone — the separate route groups, separate middleware, and separate login entry points make cross-contamination architecturally impossible.

### 5. Password Reset

Password reset applies **only** to administrative accounts.

The password reset flow follows Laravel's standard mechanism:
1. Administrator requests a reset by submitting their email address.
2. The backend issues a signed, time-limited reset token and dispatches a reset link via email.
3. The administrator follows the link and sets a new password.
4. All existing Sanctum tokens for the account are revoked upon successful reset.

Contestant accounts do not have passwords and therefore do not have a password reset flow. If a contestant loses access to all linked social providers, account recovery is handled through a dedicated support process, not an automated flow.

### 6. Email Verification

Email verification applies **only** to administrative accounts.

When an admin account is provisioned:
1. The administrator receives an email verification link.
2. The account is functional but flagged as unverified until the link is followed.
3. Certain sensitive operations may be restricted to verified accounts at the discretion of the Super Administrator.

Contestant accounts receive their verified email address from the social identity provider. The platform trusts the provider's email verification status and does not re-verify contestant emails independently.

### 7. Future: Two-Factor Authentication (2FA) for Administrators

Two-Factor Authentication (TOTP-based, e.g., Google Authenticator / Authy) is architecturally anticipated for administrative accounts in a future release.

The current authentication implementation must not preclude this:
- The admin login flow must be designed as a multi-step flow (credentials → optional 2FA challenge) even before 2FA is enabled.
- No assumptions about single-step admin login may be hardcoded into the Administration Dashboard.

2FA for contestant accounts is not planned and is out of scope.

### 8. Session Management

| Concern | Contestant (type=user) | Administrator (type=admin) |
|---|---|---|
| **Token storage** | Frontend secure storage (httpOnly cookie or memory) | Frontend secure storage (httpOnly cookie or memory) |
| **Token expiry** | Configurable (e.g., 30 days) | Configurable, shorter (e.g., 8 hours) |
| **Logout** | Revoke current token | Revoke current token |
| **Revoke all** | Supported (security events) | Supported (password reset, suspicious activity) |
| **Concurrent sessions** | Permitted | Permitted (with audit logging) |

### 9. Security Boundaries Summary

The following constraints are absolute and must never be relaxed:

1. A contestant social token **cannot** be used to access any administration endpoint.
2. An administrator **cannot** authenticate via a social provider.
3. Admin account provisioning is performed only by a Super Administrator with `users.create` permission.
4. Social provider OAuth callbacks are not accessible from administration routes.
5. Password reset links are single-use and expire after a fixed time window.
6. All authentication events (login, logout, failed attempts, password reset, token revocation) must be logged for audit purposes.

---

## Consequences

### Positive Consequences

- **Reduced friction for contestants**: Delegates identity management to providers contestants already trust, eliminating the need to manage yet another password. This directly increases participation.
- **Organizational control over admin identities**: Administrative accounts are provisioned and decommissioned by the organization, not self-managed. There is no risk of a former employee retaining access via a social provider.
- **Independent security postures**: A breach of a social provider's OAuth credentials does not expose the Administration Dashboard. A compromise of an admin credential does not affect contestant social identities.
- **Sanctum consistency**: Using Laravel Sanctum for both user types means a single, well-understood token infrastructure, regardless of how the token was issued.
- **No password storage risk for contestants**: Eliminating passwords for contestants eliminates the most common vector for credential-based attacks on that population.
- **Clear audit trail**: Separate authentication channels make it straightforward to audit which administrators performed which actions and when.

### Negative Consequences / Trade-offs

- **OAuth provider dependency for contestants**: If all supported social providers are simultaneously unavailable, contestant authentication is entirely non-functional. This is mitigated by supporting multiple providers.
- **OAuth provider account deletion**: If a contestant deletes their Google account, for example, and has no other linked provider, they lose access. This must be communicated clearly and a multi-provider linking strategy encouraged.
- **Admin account provisioning overhead**: Administrators cannot self-register. Every admin account requires manual provisioning by a Super Administrator. For large organizations this is the correct posture, but it adds operational overhead for onboarding.
- **No unified login page**: Two separate login experiences must be designed, built, and maintained. This is an intentional trade-off in exchange for the security boundary it enforces.
- **Social provider API changes**: OAuth provider APIs and SDK contracts can change. The platform is dependent on keeping OAuth integrations current with provider requirements (token formats, scopes, verification endpoints).

---

## Alternatives Considered

### Alternative 1: Unified Email/Password Authentication for All Users

A single authentication system where all users — contestants and administrators alike — authenticate with email and password.

**Rejected because**:
- Contestants should not be required to manage another password. A global competition platform competing for international participation cannot afford avoidable registration friction.
- The platform would become responsible for securely storing and managing passwords for potentially millions of contestant accounts — a security and operational burden with no commensurate benefit.
- Social authentication is the expected norm for public consumer-facing applications in this domain.

### Alternative 2: Social Authentication for All Users, Including Admins

Allowing administrators to authenticate via social providers (Google Workspace, Microsoft Entra) in addition to or instead of email/password.

**Rejected because**:
- Administrative accounts hold significant privileges over competition data. Delegating their authentication entirely to a third-party provider creates a dependency that JRTV does not control.
- If a social provider account is compromised, suspended, or deleted, the administrator loses access to the platform — a potentially catastrophic scenario for a running competition.
- Email/password with 2FA provides a more directly controllable, auditable, and organization-controlled authentication mechanism for internal staff.

### Alternative 3: JWT Instead of Laravel Sanctum

Using JSON Web Tokens (JWT) as the primary token mechanism instead of Laravel Sanctum opaque tokens.

**Rejected because**:
- Sanctum's opaque tokens are stored server-side, enabling instant revocation. JWTs are stateless and cannot be individually revoked without an additional token blacklist — which eliminates the primary advantage of JWT.
- Instant token revocation is a security requirement for administrative accounts (password reset, incident response). Sanctum satisfies this requirement without additional infrastructure.
- Sanctum is the Laravel-native solution and is already integrated with AlphaCMS authentication modules.

### Alternative 4: Cookie-Based Session Authentication for Admins

Using Laravel's traditional cookie-based session authentication for the Administration Dashboard instead of Sanctum token-based authentication.

**Rejected because**:
- The Administration Dashboard is a fully decoupled SPA served from a different origin than the API. Cookie-based sessions introduce cross-origin complexity (CORS, SameSite policies) that is avoided entirely with token-based authentication.
- Token-based authentication is consistent with the API-first architecture established in ADR-001 § Decision #3.
- Sanctum supports SPA authentication via cookies as an option, but the token model is simpler and more predictable for cross-origin SPA + API deployments.

### Alternative 5: Third-Party Identity Provider for Admins (e.g., Auth0, Okta)

Delegating all authentication — including admin authentication — to a managed Identity-as-a-Service platform.

**Rejected because**:
- Introduces a critical external dependency for a state broadcaster's internal operational tool. If the IdP is unreachable, the competition cannot be administered.
- Adds recurring licensing cost and vendor lock-in without commensurate benefit for the organization's scale.
- Laravel Sanctum with organizational provisioning satisfies all requirements without the dependency.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md) — § Decision #5 (Two-Level Authorization), § Decision #14 (Authentication Architecture)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [OAuth 2.0 Authorization Framework — RFC 6749](https://www.rfc-editor.org/rfc/rfc6749)
- [OpenID Connect Core 1.0](https://openid.net/specs/openid-connect-core-1_0.html)
- [Sign in with Apple — Apple Developer](https://developer.apple.com/sign-in-with-apple/)
- [Google Identity — OAuth 2.0](https://developers.google.com/identity/protocols/oauth2)
- [Facebook Login for the Web](https://developers.facebook.com/docs/facebook-login/web)
- [Microsoft Identity Platform](https://learn.microsoft.com/en-us/entra/identity-platform/)
- [TOTP — RFC 6238](https://www.rfc-editor.org/rfc/rfc6238)
- [Laravel Password Reset](https://laravel.com/docs/passwords)
- [Laravel Email Verification](https://laravel.com/docs/verification)
