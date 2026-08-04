# ADR-001: System Architecture for the Global Quran Competition Platform

| Field        | Value                                                              |
|--------------|--------------------------------------------------------------------|
| **ID**       | ADR-001                                                            |
| **Date**     | 2026-07-31 (amended 2026-07-31)                                    |
| **Authors**  | Platform Architecture Team                                         |
| **Status**   | Accepted                                                           |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership   |

---

## Status

**Accepted**

---

## Context

Jordan Radio and Television Corporation (JRTV) is commissioning a **Global Quran Competition Platform** — a full-lifecycle digital system that governs competitor registration, application submission, judging workflows, media management, live streaming, notifications, and results publication across international audiences.

This is not a website. It is an enterprise software platform with the following operational realities:

- **Multi-role administration**: The platform serves a diverse set of internal actors — Super Administrators, Competition Managers, Judges, Evaluators, Data Entry operators, and Moderators — each with distinct, finely-grained capabilities.
- **Contestant-facing public portal**: Registered contestants access a dedicated public-facing portal. Their experience is entirely separate from the administration surface.
- **Media-intensive workflows**: The platform handles video submissions, asynchronous video processing via FFmpeg, and live streaming — workloads that must not block or degrade other system functions.
- **Regulatory and reputational sensitivity**: As a state broadcaster, JRTV requires a platform that is auditable, maintainable long-term, and can be operated independently of the original development team.
- **Reuse of proven modules**: A library of generic, battle-tested modules exists in an internal codebase (AlphaCMS), covering authentication, users, roles, permissions, profiles, media, video processing, live streaming, and notifications. These modules must be integrated as first-class building blocks. News-related modules from AlphaCMS are explicitly excluded.
- **API-first mandate**: Both the public frontend (Next.js) and the administration dashboard (React) must interact with the backend exclusively through a versioned REST API. No server-side rendering or direct database access from any frontend is permitted.
- **Global multilingual audience**: The competition is international in scope. Contestants and audiences span Arabic-speaking, English-speaking, and Spanish-speaking regions. The platform must operate as a multilingual system from its first release. Internationalization is not an optional feature; it is a core architectural requirement.
- **Compliance and documentation**: The system must be fully documented via OpenAPI (Swagger). Architecture decisions must be recorded and versioned.

The architecture must be enterprise-grade, maintainable over a multi-year horizon, and operable by teams who were not involved in the original build.

---

## Decision

### 1. Architectural Style: Modular Monolith

The backend will be implemented as a **Modular Monolith** — a single deployable Laravel application internally structured as a collection of independent, domain-oriented modules.

Each module encapsulates its own models, services, repositories, controllers, events, listeners, policies, jobs, and migrations. Modules communicate with each other exclusively through well-defined internal interfaces (service contracts or events), never by reaching directly into another module's internals.

This approach delivers the organizational clarity of microservices without the operational overhead of distributed systems — service discovery, distributed tracing, inter-service authentication — which is disproportionate to the team size and deployment context of this project.

The modular structure preserves a clear migration path: any module may be extracted into a standalone service in a future phase without requiring a rewrite of business logic.

### 2. Layered Architecture within Modules (Clean Architecture Principles)

Each module internally adopts Clean Architecture principles to separate concerns:

- **Domain Layer**: Business entities, value objects, domain events, and business rules. No framework dependencies.
- **Application Layer**: Use cases and application services that orchestrate domain logic. No HTTP or database concerns.
- **Infrastructure Layer**: Eloquent models, repository implementations, external service adapters (FFmpeg, Meilisearch, Redis), and queue jobs.
- **Presentation Layer**: HTTP controllers, API resources (transformers), form requests (validation), and route definitions.

This layering ensures that business logic remains testable and portable, independently of the web framework or any specific infrastructure technology.

### 3. API-First Design

The platform adopts an **API-First** design principle. All client–server communication is mediated through a versioned REST API.

- All API endpoints are prefixed under `/api/v1/`.
- Both the public contestant portal (Next.js) and the administration dashboard (React) are fully decoupled frontends that consume the API exclusively.
- The API is the single source of truth for all data operations. No frontend has a privileged or direct path to the database.
- The complete API surface is documented using **OpenAPI 3.x (Swagger)** and is treated as a first-class project deliverable, maintained in sync with the codebase.

### 4. Two-Surface Frontend Architecture

The system exposes two distinct user surfaces, each served by an independent frontend application:

- **Contestant Portal** (`frontend/`): A **Next.js 15** application using the App Router. This is a public-facing, SEO-aware surface intended for contestants worldwide. It communicates with the backend API and is authorized only for users with `type=user`.
- **Administration Dashboard** (`admin-frontend/`): A **React + Vite + TypeScript** single-page application. This is an internal tool accessible only to users with `type=admin`. It manages all competition lifecycle operations.

The two surfaces are deployed and versioned independently.

### 5. Two-Level Authorization Architecture

The platform enforces a strict, two-level authorization model:

#### Level 1 — Surface Access Control (`type` column)

A single `type` column on the `users` table governs which surface a user may access:

- `user` → Access to the Contestant Portal only.
- `admin` → Access to the Administration Dashboard only.

This is a coarse-grained gate. Its sole function is surface routing. It carries no business meaning beyond access boundary enforcement.

#### Level 2 — Business Permission Control (Spatie Laravel Permission)

All fine-grained authorization within the Administration Dashboard is managed exclusively by **Spatie Laravel Permission**. No custom RBAC or ACL system will be implemented.

- Every administrator holds one or more **Roles**.
- Every Role grants one or more **Permissions**.
- Permissions govern all business actions: viewing, creating, evaluating, approving, rejecting, streaming, and so on.

#### Permission Naming Convention

All permissions adhere to a `module.action` naming convention:

```
users.view            users.create          users.update
users.delete          users.restore         users.force-delete

videos.view           videos.upload         videos.update
videos.delete         videos.stream

applications.view     applications.review
applications.evaluate applications.approve  applications.reject
```

#### Module Ownership of Permissions

Each backend module is solely responsible for defining and registering its own permissions. There is no global permissions manifest. This enforces module isolation and makes modules independently reusable.

#### Administration Dashboard Permission UI

The Administration Dashboard must render permissions grouped by their owning module — not as a flat alphabetical list. This is a UX requirement enforced at the architecture level to ensure that role management remains intuitive as the permission surface grows.

### 6. Reuse of AlphaCMS Generic Modules

The following generic modules are imported from the AlphaCMS internal library and treated as first-class dependencies, not copied code:

| Module             | Responsibility                                                      |
|--------------------|---------------------------------------------------------------------|
| Authentication     | Login, logout, token management, session lifecycle                  |
| Users              | User entity, lifecycle management                                   |
| Roles              | Role definitions and assignment                                     |
| Permissions        | Permission definitions, registration, and seeding                   |
| Profile            | Extended user profile management                                    |
| Media              | File upload, storage abstraction, retrieval                         |
| Video Processing   | FFmpeg-backed encoding, transcoding, thumbnail generation           |
| Live Streaming     | Stream ingestion, distribution, and state management                |
| Notifications      | Multi-channel notification dispatch and preferences                 |

News-related modules from AlphaCMS are **explicitly excluded** and must not be integrated.

All AlphaCMS modules are treated as read-only upstream dependencies. Any platform-specific extensions are implemented in platform-owned modules that depend on AlphaCMS modules — never by modifying AlphaCMS module internals.

### 7. Validation Strategy

Validation is enforced at two layers, each appropriate to its context:

- **Backend**: All incoming API requests are validated using **Laravel Form Requests**. The backend is the authoritative enforcement point. API responses for validation failures follow a consistent error schema across all endpoints.
- **Frontend**: Both frontend applications use **Zod** for schema-level validation at the client side, providing immediate user feedback before API round-trips. Zod schemas are the canonical source of type inference for form data.

Client-side validation is a convenience layer. It never substitutes for backend validation.

### 8. Search Infrastructure

Full-text and faceted search is powered by **Meilisearch**, integrated via Laravel Scout. The search index is maintained asynchronously through queued indexing jobs. The backend exposes search functionality through standard API endpoints; no frontend communicates with Meilisearch directly.

### 9. Asynchronous Processing

All long-running, resource-intensive, or externally dependent operations are processed asynchronously using **Laravel Queues** backed by **Redis**:

- Video transcoding and thumbnail generation (FFmpeg)
- Search index updates (Meilisearch)
- Notification dispatch (email, SMS, push)
- Audit log writes
- Report generation

Synchronous API responses are never blocked by these operations. Job status is communicated back to frontends through polling endpoints or real-time notification channels.

### 10. Caching Strategy

**Redis** serves as the primary caching layer for:

- API response caching (configurable TTLs per endpoint)
- Session storage
- Rate limiting counters
- Queue backend
- Real-time pub/sub channels for live updates

### 11. Deployment: Docker-First

The complete platform — backend, admin frontend, contestant frontend, database, cache, and search — is containerized using **Docker** and orchestrated via **Docker Compose** for development and initial production deployment.

Each service runs in its own container with explicit dependency declarations, health checks, and environment-based configuration. No service assumes a specific host environment; all configuration is injected via environment variables.

This ensures environment parity between development, staging, and production and eliminates "works on my machine" failure modes.

### 12. Documentation as a First-Class Deliverable

Architecture and API documentation are treated as non-optional deliverables:

- All architecture decisions are recorded as ADRs in `docs/adr/`.
- The full REST API is documented in OpenAPI 3.x format and served via a Swagger UI endpoint in non-production environments.
- Module boundaries, contracts, and ownership are maintained in `docs/`.

### 13. Internationalization (i18n) Strategy

The platform is multilingual from day one. Internationalization is a first-class architectural requirement applied uniformly across all surfaces and all layers of the system.

#### Official Supported Languages

| Language | Code  | Direction | Default |
|----------|-------|-----------|--------|
| Arabic   | `ar`  | RTL       | ✓      |
| English  | `en`  | LTR       |        |
| Spanish  | `es`  | LTR       |        |

Additional languages may be added in the future without requiring changes to application architecture or code.

#### Language Registry

Supported languages are not hardcoded in configuration. They are maintained in a dedicated `languages` database table, allowing administrators to activate, deactivate, and extend language support at runtime:

```
Language
─────────────────────
id
code           (BCP-47 language tag, e.g. "ar", "en", "es")
name           (English name, e.g. "Arabic")
native_name    (Native name, e.g. "العربية")
is_default     (boolean — exactly one language is default at all times)
is_active      (boolean — controls public availability)
rtl            (boolean — controls text direction in frontends)
```

#### Scope of Localization

All user-facing content must be localization-ready. This includes, without exception:

- All UI strings: menus, buttons, labels, table headers, dialogs, forms, placeholders
- Validation error messages (backend and frontend)
- System notifications and email notifications
- Business content: pages, announcements, competition information
- API error responses where user-facing messages are returned

Hardcoded UI text is prohibited in any layer of the system.

#### Backend i18n

- Laravel's native localization system (`lang/`) is the primary mechanism for backend string externalization.
- Every module maintains its own translation files within its own directory structure. There is no global flat translation file.
- Translation files are organized per module to preserve module isolation and reusability.
- The active locale for an API request is resolved from the `Accept-Language` HTTP header or an explicit `lang` query parameter, with Arabic as the fallback default.
- Validation messages are fully translated in all supported languages.

#### Frontend i18n

- Both the Contestant Portal (Next.js) and the Administration Dashboard (React) implement internationalization using their respective ecosystem-standard libraries.
- All UI strings are externalized into locale-specific resource files. No string literals appear in component code.
- The active locale is persisted per user session and defaults to Arabic.
- Right-to-left (RTL) layout is applied automatically when the active locale has `rtl = true`. The layout system must be designed to support bidirectional text from the outset.

#### Translatable Business Content

Business entities that carry user-facing textual content (names, descriptions, body text) must support per-locale translations at the data model level. Translatable fields are stored as structured locale-keyed data. The API returns translated content in the locale matching the request context.

#### Module Translation Ownership

Every backend module is responsible for providing its own translation files for all supported languages. Translation files must not be maintained in a central global location. This follows the same module ownership principle applied to permissions and migrations.

Each module exposes its translations through a `lang/` directory within its own module root, organized by locale code:

```
Modules/
├── Users/
│   └── lang/
│       ├── ar/
│       ├── en/
│       └── es/
├── Applications/
│   └── lang/
│       ├── ar/
│       ├── en/
│       └── es/
└── Videos/
    └── lang/
        ├── ar/
        ├── en/
        └── es/
```

Translation files must cover all user-facing strings within the module: labels, validation messages, notification content, and error descriptions.

#### Adding New Languages

Adding a new language requires only:
1. Inserting a new record into the `languages` table.
2. Adding the corresponding translation files within each module.
3. Providing locale resource files in the frontend applications.

No application code changes, no configuration file changes, and no architectural modifications are required.

### 14. Authentication Architecture

The platform implements two completely distinct, non-overlapping authentication flows — one per user type. Authentication providers are selected based on user type; no authentication method is shared across types.

- **Contestants** (`type=user`) authenticate exclusively through **social identity providers** (Google, Facebook, and optionally Apple and Microsoft) via the Contestant Portal. Traditional email/password login is not available to contestants.
- **Administrators** (`type=admin`) authenticate exclusively through **email and password** via the Administration Dashboard, protected by **Laravel Sanctum**. Administrative accounts cannot authenticate using social providers.

This boundary is enforced at the API layer. The authentication surface for each user type is isolated. A valid contestant social token cannot be used to access the Administration Dashboard, and a valid administrator session cannot be established through a social provider.

> Full authentication and identity architecture — including OAuth flow, session management, password reset, email verification, security boundaries, and future 2FA strategy — is documented in **ADR-003: Authentication and Identity Architecture**.

---

## Consequences

### Positive Consequences

- **Operational simplicity**: A single deployable backend unit simplifies CI/CD pipelines, deployment procedures, and incident response compared to a distributed microservices topology.
- **Domain clarity**: Module boundaries enforce separation of concerns and make it straightforward to identify ownership, trace bugs, and onboard new engineers.
- **Decoupled frontends**: The API-first design means either frontend can be rebuilt, replaced, or extended without touching the backend.
- **Fine-grained access control**: The combination of `type`-based surface access and Spatie-managed permissions gives administrators full flexibility in defining roles without requiring code changes.
- **Reuse and consistency**: AlphaCMS modules eliminate the need to solve solved problems, ensuring consistency in patterns across authentication, media, and notifications.
- **Auditability**: Module-owned permissions, ADR documentation, and OpenAPI specs ensure the system remains auditable and explainable to external stakeholders.
- **Testability**: Clean Architecture layering within modules isolates business logic from infrastructure, making unit and integration tests straightforward to author and maintain.
- **Future scalability path**: Any module can be extracted into a standalone service if load profiles demand it, without requiring a logic rewrite.
- **Multilingual by design**: Implementing i18n from the initial release avoids the prohibitive cost and risk of retrofitting localization onto a completed system. All surfaces, all modules, and all content types are localization-ready from day one.
- **Runtime language extensibility**: The database-driven language registry allows new languages to be activated without code deployments, giving operations teams direct control.

### Negative Consequences / Trade-offs

- **Module discipline is an ongoing responsibility**: The modular monolith pattern only delivers its benefits if module boundaries are actively enforced. Unchecked cross-module coupling will degrade the architecture over time. This requires team discipline, code review standards, and ideally automated boundary enforcement tooling.
- **Monolith scaling limits**: Unlike microservices, the entire backend scales as a unit. If a single module (e.g., video processing) becomes a bottleneck, horizontal scaling applies to all modules. This is mitigated by offloading heavy workloads to the async queue layer.
- **AlphaCMS dependency**: Modules imported from AlphaCMS introduce an upstream dependency. Changes in AlphaCMS that are incompatible with platform requirements must be managed carefully. A clear versioning and integration contract must be established.
- **Docker Compose for production**: Docker Compose is appropriate for initial production deployment and small-to-medium scale. At higher scale, migration to Kubernetes or a managed container orchestration platform will be necessary. This migration is out of scope for the initial release but must be considered in infrastructure planning.
- **Zod/Laravel duplication**: Maintaining validation schemas in both Zod (frontend) and Laravel (backend) introduces a risk of divergence. This is a known and accepted trade-off; the backend schema remains canonical, and frontend schemas must be kept in sync through disciplined API contract management and generated types where tooling allows.
- **Translation file maintenance burden**: Every module must provide translation files for every supported language. As the module count grows, keeping all translations complete and up to date across three languages requires ongoing editorial discipline. Incomplete translations must be caught during code review, not at runtime.
- **RTL/LTR layout complexity**: Supporting right-to-left (Arabic) alongside left-to-right languages in the same frontend codebase increases CSS and layout complexity. The layout system must be designed for bidirectionality from the start; retrofitting RTL support is significantly more expensive.

---

## Alternatives Considered

### Alternative 1: Microservices Architecture

A microservices topology was evaluated, where each domain (applications, contestants, judging, media, streaming) would be a standalone deployable service communicating over HTTP or a message broker.

**Rejected because**:
- The team size and operational maturity of the initial deployment do not justify the infrastructure overhead of service discovery, distributed tracing, inter-service authentication, and independent deployment pipelines.
- Distributed transactions across service boundaries (e.g., approving an application while updating a contestant profile and dispatching a notification) introduce significant complexity.
- The Modular Monolith provides all the organizational benefits with a clear extraction path if scale demands it in a future phase.

### Alternative 2: Traditional Layered Monolith (Single Namespace)

A conventional Laravel application with a flat `app/` structure (no module boundaries) was considered for simplicity.

**Rejected because**:
- At enterprise scale, a flat structure leads to a "big ball of mud" — high coupling, unclear ownership, and difficult onboarding.
- It does not accommodate the reuse model required for AlphaCMS modules.
- It provides no natural extraction boundary if individual components need to be scaled or isolated in the future.

### Alternative 3: Full-Stack Framework (Next.js Everywhere)

Using Next.js for both the contestant portal and the administration dashboard, relying on Next.js API routes as the backend.

**Rejected because**:
- A Node.js/JavaScript backend cannot adequately represent the domain complexity, queue infrastructure, and media processing capabilities required by this platform.
- Laravel's ecosystem (Eloquent, Horizon, Sanctum, Spatie Permission, Scout, Broadcasting) is purpose-built for this class of problem.
- A single Next.js codebase for both surfaces would conflate concerns between a public-facing portal and a privileged internal tool.

### Alternative 4: Custom RBAC Implementation

Building a bespoke role and permission system instead of adopting Spatie Laravel Permission.

**Rejected because**:
- Spatie Laravel Permission is the de facto standard in the Laravel ecosystem, battle-tested, and actively maintained.
- A custom implementation introduces maintenance burden and potential security surface without providing any differentiated capability.
- It would also break compatibility with AlphaCMS permission modules, which already integrate with Spatie.

### Alternative 5: Server-Side Rendered Admin (Blade / Inertia.js)

Using Laravel Blade templates or Inertia.js for the administration dashboard instead of a standalone React SPA.

**Rejected because**:
- The administration dashboard involves complex, stateful UI interactions (drag-and-drop judging panels, real-time status updates, bulk operations) that are best served by a dedicated SPA framework.
- An API-first backend is an explicit architectural requirement; tight coupling between the server and the admin view layer would contradict this principle.
- A standalone React application allows the admin frontend to be developed, versioned, and deployed independently of the backend release cycle.

### Alternative 6: Deferred Internationalization (i18n as a Phase 2 Feature)

Delaying multilingual support until after the initial release, treating it as a follow-on feature rather than a foundational architectural concern.

**Rejected because**:
- The platform is explicitly described as a global competition. Arabic, English, and Spanish audiences are present from launch. Delivering a monolingual platform at release is a product failure, not a technical trade-off.
- Retrofitting i18n onto a completed system — database schemas, API contracts, frontend components, email templates — carries a cost that is a multiple of implementing it correctly from the start.
- RTL layout support (required for Arabic) cannot be added as an afterthought to a layout system designed only for LTR; it requires foundational CSS and component decisions made at the outset.
- Module-owned translation files are consistent with the module isolation principle already established in Decision #1. Deferring i18n would create a future architectural divergence.

---

## References

- [Michael Nygard — Documenting Architecture Decisions](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions)
- [Building Microservices — Sam Newman](https://samnewman.io/books/building_microservices/)
- [Clean Architecture — Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/)
- [OpenAPI Specification 3.x](https://spec.openapis.org/oas/v3.1.0)
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Localization](https://laravel.com/docs/localization)
- [Next.js 15 Internationalization](https://nextjs.org/docs/app/building-your-application/routing/internationalization)
- [BCP-47 Language Tags — IETF](https://www.rfc-editor.org/rfc/rfc5646)
- [Unicode CLDR — Common Locale Data Repository](https://cldr.unicode.org/)
- [AlphaCMS Internal Module Library — Internal Reference]
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-003: Authentication and Identity Architecture](./ADR-003-authentication-identity.md)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [OAuth 2.0 — RFC 6749](https://www.rfc-editor.org/rfc/rfc6749)
