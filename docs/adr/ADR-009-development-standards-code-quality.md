# ADR-009: Development Standards & Code Quality

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-009                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-003 · ADR-004 · ADR-005 · ADR-006 · ADR-008 · ERD · MIGRATION-SPECIFICATION                             |

---

## Status

**Accepted** — This document is the constitutional reference governing all coding standards, directory structures, language features, static analysis thresholds, testing policies, performance rules, security requirements, Definition of Done (DoD), Git strategies, pull request workflows, and documentation rules for the platform.

---

## Context

### Why This ADR Exists

With ADR-001 through ADR-008, the platform's system topology, module boundaries, authentication, API contracts, database architecture, infrastructure, and eventing have been formally established.

However, architecture docs alone do not prevent code degradation if individual engineers write code using conflicting styles, un-typed methods, missing test coverage, direct cross-module coupling, un-indexed N+1 queries, or un-validated PRs.

Without a single, binding Development & Code Quality Constitution, the following failure modes occur:

- **Codebase Degradation**: Varying code styles across modules make code reviews slow and onboarding difficult.
- **Architectural Erosion**: Developers bypass module contracts or call repositories directly inside controllers.
- **Type-Safety Regressions**: Un-typed array payloads or missing return types lead to runtime `TypeError` and `NullPointerException` bugs in production.
- **Test Decay**: Tests are written as an afterthought or skipped, leading to silent regressions when refactoring.
- **Performance Degradation**: Unnoticed N+1 queries in loop iterations choke database resources during live competition streams.
- **Security Vulnerabilities**: Mass-assignment risks (`$fillable = ['*']`), missing authorization checks, or hardcoded credentials leak into pull requests.

This ADR eliminates these failure modes by locking in binding code quality rules, static analysis gates, testing mandates, and a strict Definition of Done before any module code is written.

---

## Architectural Principles

### Principle 1: Strict Typing & Compile-Time Safety
Every PHP file enforces strict typing (`declare(strict_types=1);`). Untyped parameters, untyped properties, and missing return types are treated as build errors.

### Principle 2: Clean Architecture & Layer Isolation
Code within each module strictly obeys Clean Architecture layers (`Presentation` → `Application` → `Domain` ← `Infrastructure`). Lower layers never depend on higher layers.

### Principle 3: Zero Baseline & Zero Dead Code
Static analysis (Larastan Level 8) and code formatters (Pint) run with zero baseline overrides. Dead code, unused imports, commented-out blocks, and unaddressed `TODO` comments are strictly prohibited in `main`.

### Principle 4: Automated Quality Gating
No code enters the default branch without passing automated CI checks: Pint formatting, Larastan Level 8 analysis, Rector refactoring, and 100% passing Pest test suite with minimum 90% code coverage.

### Principle 5: Commercial-Grade Craftsmanship
The codebase is built to commercial product standards (equivalent to Filament, Laravel Nova, or Statamic core repositories). Every class, interface, and test is authored with long-term maintainability in mind.

---

## Formal Decisions

---

### PART I — ARCHITECTURE & MODULE DIRECTORY STRUCTURE

#### Decision 1: Architecture Enforcement Rules

1. **Cross-Module Communication**: Modules communicate **exclusively** through published `Contracts` interfaces or asynchronous `Domain Events` (per ADR-002 and ADR-008). Direct imports of another module's concrete classes or models are prohibited.
2. **No Facades in Domain Layer**: Laravel Facades (`DB::`, `Cache::`, `Auth::`) are prohibited inside the `Domain` layer. Domain entities and services depend on interface abstractions injected via constructor.
3. **No Service Locator Pattern**: Resolving dependencies via `app()`, `resolve()`, or `$container->make()` inside domain or application logic is prohibited. Use Dependency Injection.
4. **No Circular Dependencies**: Module A depending on Module B while Module B depends on Module A is prohibited. If circular coupling occurs, extract the shared concept into the `Core` module or communicate via events.

#### Decision 2: Standard Module Directory Structure

Every module in `backend/Modules/{ModuleName}` **must** strictly conform to the following directory layout:

```
Modules/{ModuleName}/
├── Domain/                         ← Core Business Logic (Zero Framework Dependencies)
│   ├── Entities/                   ← Aggregate Roots & Value Objects
│   ├── Events/                     ← Domain Event Classes
│   ├── Exceptions/                 ← Domain Specific Exceptions
│   ├── Repositories/               ← Repository Interfaces (Contracts)
│   └── Services/                   ← Pure Domain Services
│
├── Application/                    ← Orchestration & Use Cases
│   ├── Commands/                   ← Write Command DTOs & Handlers
│   ├── Queries/                    ← Read Query DTOs & Handlers
│   ├── UseCases/                   ← Use Case Orchestrators
│   └── DTOs/                       ← Data Transfer Objects
│
├── Infrastructure/                 ← External Technical Implementations
│   ├── Database/
│   │   ├── Models/                 ← Eloquent Models (Data Persistence)
│   │   ├── Repositories/           ← Eloquent Repository Implementations
│   │   ├── Factories/              ← Model Testing Factories
│   │   └── Seeders/                ← Reference & Test Seeders
│   └── Services/                   ← Adapters for Storage, Mail, APIs
│
├── Presentation/                   ← HTTP & External Delivery Surface
│   ├── HTTP/
│   │   ├── Controllers/            ← API Controllers (Thin)
│   │   ├── Requests/               ← Form Request Validators
│   │   ├── Resources/              ← API JsonResources (Envelope)
│   │   └── Middleware/             ← Surface & Permission Middleware
│   └── CLI/                        ├── Artisan Commands
│
├── Contracts/                      ← Published Public Interfaces for Other Modules
│   └── {ModuleName}ServiceContract.php
│
├── Database/
│   └── Migrations/                 ← Module Schema Migrations
│
├── Providers/                      ← Service Providers
│   └── {ModuleName}ServiceProvider.php
│
├── Routes/                         ← API Route Definitions
│   ├── api.php
│   └── admin.php
│
└── Tests/                          ← Module Test Suite
    ├── Unit/                       ← Pure Domain Unit Tests
    ├── Feature/                    ← HTTP API & Workflow Integration Tests
    └── Architecture/               ← Layer Isolation Assertions
```

---

### PART II — LANGUAGE & FRAMEWORK STANDARDS

#### Decision 3: PHP 8.4 Language Standards

1. **Strict Types**: Every `.php` file **must** begin with `declare(strict_types=1);` immediately after the opening PHP tag.
2. **Typed Everything**: All function parameters, class properties, and method return types must be explicitly declared. `mixed` type is prohibited unless handling dynamic JSON payloads.
3. **Constructor Property Promotion**: Use PHP 8.x constructor promotion for all DTOs, Value Objects, Use Cases, and Controllers:
   ```php
   public function __construct(
       private readonly ApplicationRepositoryContract $repository,
       private readonly AuditLoggerContract $auditLogger,
   ) {}
   ```
4. **Readonly Classes**: Declare classes `readonly` by default for all DTOs, Value Objects, Commands, Queries, and Events:
   ```php
   final readonly class SubmitApplicationCommand
   {
       public function __construct(
           public string $contestantId,
           public string $seasonId,
           public ?string $videoId = null,
       ) {}
   }
   ```
5. **Enums Over Class Constants**: String/Integer constant groups are prohibited. Use native PHP `BackedEnums`:
   ```php
   enum ApplicationStatus: string
   {
       case Received = 'received';
       case UnderReview = 'under_review';
       case UnderEvaluation = 'under_evaluation';
       case Accepted = 'accepted';
       case Rejected = 'rejected';
   }
   ```
6. **Match Expression**: Use `match` instead of `switch` statements for exhaustiveness checking:
   ```php
   $color = match($status) {
       ApplicationStatus::Accepted => 'green',
       ApplicationStatus::Rejected => 'red',
       ApplicationStatus::Received, ApplicationStatus::UnderReview => 'yellow',
   };
   ```

#### Decision 4: Laravel Framework Standards

1. **Form Requests Only**: Controllers **must never** execute `$request->validate()` inline. All HTTP validation is delegated to dedicated `FormRequest` classes.
2. **API Resources Only**: Controllers **must never** return raw arrays or Eloquent models. All responses use `JsonResource` implementations following the ADR-004 envelope.
3. **Policy Authorization**: Authorization is enforced using Laravel `Policies`. Controllers invoke `$this->authorize('approve', $application)` before executing use cases.
4. **Route Model Binding**: Use UUID Route Model Binding for all single-resource endpoints:
   ```php
   public function show(Application $application): ApplicationResource
   ```
5. **No Helpers in Domain Layer**: Framework helper functions (`config()`, `session()`, `env()`, `request()`, `response()`) are prohibited in `Domain` and `Application` layers. Use configuration objects or injected contracts.
6. **Thin Controllers Mandate**: Controllers must not contain business logic. A controller method consists of exactly 3 steps:
   1. Authorize request.
   2. Execute Use Case.
   3. Return API JsonResource.

---

### PART III — DOMAIN & TESTING CONSTITUTION

#### Decision 5: Domain Execution Rules

1. **Aggregate State Mutation**: State mutations happen exclusively through Aggregate Root methods. Direct attribute manipulation outside the aggregate is prohibited.
2. **No Repository in Controller**: Controllers **must never** instantiate or inject Repository classes directly. Controllers execute `UseCases`, which orchestrate repositories.
3. **No Direct Queue Dispatch in Controller**: Controllers **must never** call `dispatch()` or `Queue::push()`. Async operations are triggered via `Outbox` writes or Use Case domain events (ADR-008).
4. **Events Post-Commit Only**: Domain events are dispatched **only** after the database transaction commits (ADR-008 Decision 11).

#### Decision 6: Testing Constitution (Pest Framework)

1. **Testing Engine**: **Pest PHP 3.x** is the mandatory testing framework for the platform.
2. **Minimum Coverage Threshold**: The CI build **fails** if line coverage drops below **90%** across any module.
3. **Test Categorization**:
   - `Unit/`: Tests pure domain entities, value objects, and domain services in isolation. Zero DB access.
   - `Feature/`: Tests HTTP API endpoints, database interactions, authentication surfaces, and multi-step workflows.
   - `Architecture/`: Tests layer isolation and dependency rules using Pest Architecture assertions.
4. **Architecture Test Suite**: Every module includes an `ArchitectureTest.php` asserting boundary rules:
   ```php
   test('domain layer has no framework dependencies')
       ->expect('Modules\Applications\Domain')
       ->not->toUse(['Illuminate', 'Laravel']);

   test('contracts use strict types')
       ->expect('Modules\Applications\Contracts')
       ->toUseStrictTypes();
   ```
5. **PR Blocking Gate**: A pull request with failing tests or decreased coverage is automatically blocked from merge.

---

### PART IV — STATIC ANALYSIS, FORMATTING & PERFORMANCE

#### Decision 7: Static Analysis & Refactoring Automation

1. **Larastan Level 8 Strictness**: Static analysis is enforced using **Larastan (PHPStan for Laravel) at Level 8** (highest strictness).
2. **Zero Baseline Policy**: The use of `phpstan-baseline.neon` to suppress or ignore errors is **prohibited**. Every reported error must be fixed at the code layer.
3. **Automated Refactoring**: **Rector** runs in the CI pipeline to enforce modern PHP 8.4 syntax upgrades, type declarations, and dead code elimination.

#### Decision 8: Formatting & Code Style (Laravel Pint)

1. **Formatter**: **Laravel Pint** is the mandatory code style formatter for the codebase.
2. **Preset**: Configured using `preset: laravel` with strict PSR-12 enforcement.
3. **Automated Formatting Rules**:
   - `declare_strict_types` = `true`.
   - `ordered_imports` = `['sort_algorithm' => 'alpha']`.
   - `no_unused_imports` = `true`.
   - `final_class` = `true` (all non-abstract classes are `final` by default).
   - `single_line_after_imports` = `true`.

#### Decision 9: Performance Rules & Anti-N+1 Mandate

1. **Zero N+1 Queries**: Every relationship access in list endpoints **must** use Eager Loading (`with(['contestant.user', 'video'])`).
2. **Lazy Loading Disabled in Dev**: In `local` and `testing` environments, Eloquent lazy loading is strictly disabled via:
   ```php
   Model::preventLazyLoading(! app()->isProduction());
   ```
3. **No Queries Inside Loops**: Executing database queries (`SELECT`, `UPDATE`, `INSERT`) inside `foreach` or `while` loops is strictly prohibited. Use bulk queries or `upsert()`.
4. **Chunking for Large Datasets**: Processing datasets exceeding 1,000 records must use `lazy()`, `chunk()`, or `cursor()` to prevent memory exhaustion.

---

### PART V — SECURITY, DEFINITION OF DONE & GIT GOVERNANCE

#### Decision 10: Security Rules

1. **Mandatory Input Validation**: Every request payload is validated via a `FormRequest` before execution.
2. **Mandatory Authorization**: Every protected endpoint evaluates a Policy before executing business logic.
3. **No Mass Assignment Wildcards**: Setting `$fillable = ['*']` or `$guarded = []` on Eloquent models is **prohibited**. Explicit `$fillable` arrays are mandatory.
4. **No Raw SQL Without Approval**: Using `DB::raw()` or unescaped raw SQL is prohibited due to SQL injection risks. Complex queries must use the Query Builder or be reviewed.
5. **Secrets from Environment Only**: Passwords, API keys, and tokens must never be hardcoded. They are loaded exclusively via `config()` backed by `.env`.

#### Decision 11: Definition of Done (DoD)

A feature, task, or module component is considered **Done** if and only if all 8 conditions are satisfied:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ DEFINITION OF DONE (DoD) CHECKLIST                                         │
├─────────────────────────────────────────────────────────────────────────────┤
│ [x] 1. Feature matches API / Database inventory specification 100%.         │
│ [x] 2. Pest Test suite passes 100% (Unit, Feature, Architecture tests).     │
│ [x] 3. Test line coverage is >= 90% and has not decreased.                 │
│ [x] 4. Larastan Level 8 static analysis passes with ZERO errors/baseline.  │
│ [x] 5. Laravel Pint code formatting passes with ZERO style diffs.          │
│ [x] 6. Rector automated refactoring passes with ZERO syntax warnings.      │
│ [x] 7. Zero TODO, FIXME, or commented-out code blocks exist in the PR.      │
│ [x] 8. OpenAPI annotations, ERD, and documentation files are updated.       │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Decision 12: Pull Request & Code Review Governance

1. **Maximum PR Size**: A pull request should not exceed **400 lines of diff** (excluding generated migration specs or lock files) to ensure meaningful code review.
2. **Mandatory Reviewer Approval**: Every PR requires at least **one approved code review** from a Lead Architect or Senior Engineer before merge.
3. **Green CI Status Required**: GitHub Actions CI must be 100% green (Pint, Larastan, Pest, Rector) before the merge button is enabled.
4. **Squash & Merge Policy**: All PR merges into `develop` or `main` must use **Squash and Merge** to maintain a clean linear Git history. Direct commits to `main` or `develop` are blocked.

#### Decision 13: Git Strategy & Conventional Commits

##### 13.1 Branching Strategy
- `main`: Production-ready releases only. Tagged with SemVer (`v1.0.0`).
- `develop`: Integration branch for completed features.
- `feature/{module}-{description}`: Feature development branches (e.g. `feature/contestants-profile-photo`).
- `hotfix/{description}`: Urgent production fixes branching from `main`.

##### 13.2 Conventional Commits Standard
Commit messages must strictly follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
type(scope): concise description

[optional body]
```

**Permitted Types**: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`.

Example:
```
feat(applications): implement video reupload request use case
fix(evaluations): resolve pessimistic lock timeout on score submission
docs(api): update OpenAPI annotation for contestant registration
```

#### Decision 14: Dependency Governance

1. **Approved Registry Compliance**: Every new Composer or NPM package must be checked against ADR-006 Decision 14 (Approved vs Forbidden Packages).
2. **Banned Packages Enforced**: Attempting to install any forbidden package (`passport`, `jwt-auth`, `doctrine`, `medialibrary`, `nwidart/laravel-modules`) is an automatic PR failure.
3. **Security Audit**: Running `composer audit` and `npm audit` is integrated into CI. Dependencies with unpatched HIGH or CRITICAL vulnerabilities block the build.

#### Decision 15: Documentation Rules

1. **Self-Documenting Code**: Write expressive class, method, and variable names. PHPDoc comments are reserved for complex array shape definitions or generics.
2. **Mandatory Architectural Updates**:
   - Any new architectural decision requires an ADR amendment.
   - Any database schema change requires updating `DATABASE-DOMAIN-INVENTORY.md`, `ENTITY-RELATIONSHIP-MAP.md`, `ERD.md`, and `MIGRATION-SPECIFICATION.md` **before** writing the migration PHP code.
   - Any API route change requires updating `API-DOMAIN-INVENTORY.md` and OpenAPI annotations.

---

## Development Anti-Patterns (Prohibited Practices)

The following 10 practices are **explicitly prohibited**. Enforcement is automated via CI static analysis and code review gates.

1. ❌ **No `declare(strict_types=1);` missing**: Any PHP file lacking strict type declaration.
2. ❌ **No Cross-Module Model Import**: Importing `Modules\Contestants\Infrastructure\Database\Models\Contestant` inside the Applications module.
3. ❌ **No Facades in Domain Layer**: Invoking `DB::table()`, `Cache::get()`, or `Auth::user()` inside a `Domain` class.
4. ❌ **No Untyped Method Arguments or Returns**: Defining `public function handle($data)` without type hints.
5. ❌ **No Larastan Baseline Overrides**: Adding ignored errors to a PHPStan baseline file to force CI to pass.
6. ❌ **No Raw Validation in Controllers**: Executing `$request->validate()` inside a controller instead of using a `FormRequest`.
7. ❌ **No Un-Eager-Loaded Relationship Iteration**: Accessing `$application->contestant->user->name` inside a loop without `with()`.
8. ❌ **No `$fillable = ['*']` or `$guarded = []`**: Wildcard mass assignment on Eloquent models.
9. ❌ **No `TODO` Comments in Merged Code**: Leaving temporary `// TODO: fix this later` comments in PRs merged to `develop` or `main`.
10. ❌ **No Direct Commits to `main` or `develop`**: Bypassing PR code review and CI checks.

---

## Consequences

### Positive Consequences
- **Uniform Commercial Quality**: Every line of code across all 15 modules looks as if it were authored by a single senior engineer.
- **Zero Runtime Type Surprises**: PHP 8.4 strict types and Larastan Level 8 eliminate whole classes of null-pointer and type-mismatch crashes.
- **Maintainable Test Suite**: Pest architecture tests automatically guard module boundaries and layer rules.
- **Sub-200ms Performance**: Anti-N+1 enforcement, eager loading, and thin controllers guarantee optimal database and API performance.
- **Fast Onboarding**: Clear directory structures, DoD checklists, and canonical conventions allow new engineers to contribute confidently without breaking architectural rules.

### Negative Consequences / Trade-offs
- **Strict Disciplined Overhead**: Writing Pest tests, Larastan annotations, and FormRequests adds initial development time per feature. This investment pays off immediately in reduced debugging and refactoring cost.
- **PR Rejection Rigor**: CI gates will strictly reject PRs that fail Pint, Larastan, or coverage checks — requiring developer discipline.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-003: Authentication & Identity Architecture](./ADR-003-authentication-identity.md)
- [ADR-004: API Standards & Conventions](./ADR-004-api-standards-conventions.md)
- [ADR-005: Database Architecture](./ADR-005-database-architecture.md)
- [ADR-006: Infrastructure Architecture](./ADR-006-infrastructure-architecture.md)
- [ADR-008: Eventing & Domain Events Governance](./ADR-008-eventing-domain-events.md)
- [ADR-ROADMAP.md](../ADR-ROADMAP.md)
- [Pest PHP Documentation](https://pestphp.com/)
- [Larastan (PHPStan for Laravel) Documentation](https://github.com/larastan/larastan)
- [Laravel Pint Documentation](https://laravel.com/docs/pint)
- [Rector Documentation](https://getrector.com/)
- [Conventional Commits Specification](https://www.conventionalcommits.org/)
