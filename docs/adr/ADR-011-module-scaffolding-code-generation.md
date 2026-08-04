# ADR-011: Module Scaffolding & Code Generation Architecture

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-011                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-003 · ADR-004 · ADR-005 · ADR-006 · ADR-008 · ADR-009 · ADR-010 · ERD · MIGRATION-SPECIFICATION        |

---

## Status

**Accepted** — This document is the execution blueprint governing the canonical module template, folder layout, auto-registration mechanics, Artisan generator commands, PSR-4 namespace rules, and stub templates for all 15 modules across the platform.

---

## Context

### Why This ADR Exists

ADR-001 through ADR-010 established the complete theoretical, database, infrastructural, eventing, coding quality, and business rules constitutions for the platform.

Before generating the 15 backend modules and creating hundreds of source files, a binding **Execution Blueprint** is required to standardize:
1. The exact folder layout inside every module.
2. The custom Artisan generator commands (`make:platform-module`, `make:platform-usecase`, etc.) used by engineers.
3. The automatic registration mechanics (providers, routes, migrations, policies, translations, event subscribers, contract bindings).
4. The exact PHP stub templates for every generated file type.

Without a governing Scaffolding ADR, generating 15 modules leads to structural inconsistency, missing directories, manual registration errors in `config/app.php`, circular imports, or divergent namespace structures that force massive refactoring after code generation.

---

## Architectural Principles

### Principle 1: Immutable Module Template
Every module in `backend/Modules/{ModuleName}` adheres to a single, identical directory tree. No module may add or omit top-level folders without an ADR amendment.

### Principle 2: Zero Manual Package Configuration
Creating a new module or artifact requires zero edits to root configuration files. Modules self-register their service providers, routes, migrations, contracts, policies, translations, and event listeners via automated discovery.

### Principle 3: Code Generation Over Copy-Paste
New modules, use cases, events, repositories, and entities are instantiated strictly via dedicated `php artisan make:platform-*` CLI commands using pre-validated stub templates.

### Principle 4: Explicit Contract Expose Surface
A module's `Contracts/` folder is its **only** public export interface. Internal classes in `Domain/`, `Application/`, `Infrastructure/`, and `Presentation/` are private to the module and inaccessible to external code.

---

## Formal Decisions

---

### PART I — CANONICAL MODULE DIRECTORY LAYOUT

Every module generated in `backend/Modules/{ModuleName}` **must** contain the following 26-directory layout:

```
Modules/{ModuleName}/
├── Domain/                         ← Core Business Domain (No Framework Coupling)
│   ├── Entities/                   ← Aggregate Roots & Value Objects
│   ├── Events/                     ← Pure Domain Events
│   ├── Exceptions/                 ← Domain Exceptions
│   ├── Repositories/               ← Repository Interfaces (Contracts)
│   ├── Rules/                      ← Business Validation Rules & Invariants
│   └── Services/                   ← Domain Services
│
├── Application/                    ← Application Orchestration & Use Cases
│   ├── Commands/                   ← Write Command DTOs & Handlers
│   ├── Queries/                    ← Read Query DTOs & Handlers
│   ├── UseCases/                   ← Primary Use Case Handlers
│   └── DTOs/                       ← Request/Response Transfer Objects
│
├── Infrastructure/                 ← Persistence & External Adapters
│   ├── Database/
│   │   ├── Models/                 ← Eloquent Models (Persistence Only)
│   │   ├── Repositories/           ← Eloquent Repository Implementations
│   │   ├── Factories/              ← Pest/Pikachu Model Factories
│   │   └── Seeders/                ← Reference & Test Seeders
│   └── Services/                   ← External Adapters (S3, Mail, APIs)
│
├── Presentation/                   ← Delivery Surface (HTTP API & CLI)
│   ├── HTTP/
│   │   ├── Controllers/            ← Thin API Controllers
│   │   ├── Requests/               ← FormRequest Validation Schemas
│   │   ├── Resources/              ← JsonResource Envelope Transformers
│   │   └── Middleware/             ← Surface-Specific Middleware
│   └── CLI/                        ← Artisan Commands owned by Module
│
├── Contracts/                      ← Public Interface Exported to Other Modules
│   └── {ModuleName}ServiceContract.php
│
├── Database/
│   └── Migrations/                 ← Module Migration Files
│
├── Providers/                      ← Service Providers
│   └── {ModuleName}ServiceProvider.php
│
├── Routes/                         ← API Route Definitions
│   ├── api.php                     ← Contestant Surface Routes (/api/v1/contestant/*)
│   └── admin.php                   ← Admin Surface Routes (/api/v1/admin/*)
│
└── Tests/                          ← Module Test Suite
    ├── Unit/                       ← Pure Domain Tests (No DB)
    ├── Feature/                    ← HTTP API & Database Integration Tests
    └── Architecture/               ← Layer Isolation Assertions
```

---

### PART II — PLATFORM ARTISAN GENERATOR COMMANDS

To enforce uniformity, the platform provides 5 custom Artisan CLI generators located in `backend/app/Console/Commands/Scaffolding/`:

#### Command 1: `php artisan make:platform-module {ModuleName}`
Generates the complete 26-directory structure for a new module, creates the `{ModuleName}ServiceProvider.php`, default route files, `Contracts/{ModuleName}ServiceContract.php`, and registers the module in the platform module manifest.

#### Command 2: `php artisan make:platform-usecase {ModuleName} {UseCaseName}`
Generates:
1. `Application/Commands/{UseCaseName}Command.php` (Readonly DTO).
2. `Application/UseCases/{UseCaseName}UseCase.php` (Execution Logic).
3. `Presentation/HTTP/Controllers/{UseCaseName}Controller.php` (Thin Controller).
4. `Presentation/HTTP/Requests/{UseCaseName}Request.php` (Form Request).
5. `Tests/Feature/{UseCaseName}Test.php` (Pest Feature Test stub).

#### Command 3: `php artisan make:platform-aggregate {ModuleName} {EntityName}`
Generates:
1. `Domain/Entities/{EntityName}.php` (Aggregate Root).
2. `Domain/Repositories/{EntityName}RepositoryContract.php` (Interface).
3. `Infrastructure/Database/Models/{EntityName}Model.php` (Eloquent Model).
4. `Infrastructure/Database/Repositories/{EntityName}Repository.php` (Implementation).
5. `Database/Migrations/{timestamp}_create_{table}_table.php` (Migration per MIG-SPEC-001).

#### Command 4: `php artisan make:platform-event {ModuleName} {EventName}`
Generates:
1. `Domain/Events/{EventName}.php` (Outbox-compatible Event DTO).
2. `Application/Listeners/{EventName}Subscriber.php` (Idempotent Listener stub).
3. Payload schema test assertion in `Tests/Unit/Events/{EventName}Test.php`.

#### Command 5: `php artisan make:platform-contract {ModuleName} {ContractName}`
Generates a published contract interface in `Contracts/{ContractName}.php` and binds it automatically in `{ModuleName}ServiceProvider.php`.

---

### PART III — AUTOMATED REGISTRATION ARCHITECTURE

Zero manual edits in root config files are permitted. Registration is automated via two dedicated Core components separating Discovery from Registration:

1. **`ModuleDiscovery` (`app/Services/Scaffolding/ModuleDiscovery.php`)**: Responsible strictly for scanning `Modules/*/` directories, inspecting manifests, and verifying valid module structures.
2. **`ModuleRegistry` (`app/Services/Scaffolding/ModuleRegistry.php`)**: Responsible strictly for booting and registering discovered module service providers, routes, migrations, policies, translations, and outbox listeners.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ MODULE DISCOVERY & REGISTRATION PIPELINE                                    │
│ app/Providers/ModuleServiceProvider.php                                    │
│                                                                             │
│ 1. ModuleDiscovery::discover() scans backend/Modules/*/                     │
│ 2. ModuleRegistry::register($modules) registers Providers                  │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MODULE SERVICE PROVIDER BOOT CYCLE ({ModuleName}ServiceProvider.php)        │
│                                                                             │
│ ├── loadMigrationsFrom(__DIR__ . '/../Database/Migrations')                │
│ ├── loadTranslationsFrom(__DIR__ . '/../lang', '{module}')                │
│ ├── loadRoutesFrom(__DIR__ . '/../Routes/api.php')                         │
│ ├── loadRoutesFrom(__DIR__ . '/../Routes/admin.php')                       │
│ ├── bindContracts()                                                         │
│ ├── registerPolicies()                                                      │
│ └── registerOutboxSubscribers()                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Registration Rules Matrix

| Component | Auto-Registration Mechanism |
|---|---|
| **Service Provider** | Scanned automatically from `Modules/*/Providers/*ServiceProvider.php` |
| **Migrations** | Loaded via `$this->loadMigrationsFrom()` in ServiceProvider |
| **Routes** | Loaded via `$this->loadRoutesFrom()` with surface middleware (`auth:sanctum`, etc.) |
| **Translations** | Loaded via `$this->loadTranslationsFrom()` namespaced as `trans('{module}::{key}')` |
| **Service Contracts** | Injected interfaces bound via `$this->app->singleton(XContract::class, XService::class)` |
| **Policies & Gates** | Registered via `Gate::policy(Model::class, Policy::class)` in provider `boot()` |
| **Event Listeners** | Mapped in provider `$listen` array and registered with the Outbox Event Bus |

---

### PART IV — NAMESPACE & PSR-4 AUTOLOADING STANDARDS

#### Composer Autoloading Configuration
The root `backend/composer.json` mandates the following PSR-4 mapping:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Modules\\": "Modules/",
      "Database\\Factories\\": "database/factories/",
      "Database\\Seeders\\": "database/seeders/"
    }
  }
}
```

#### Class Naming & Namespace Rules

```php
// Core Module Example
namespace Modules\Core\Domain\Entities;

// Applications Module Example
namespace Modules\Applications\Application\UseCases;

// Public Contract Example
namespace Modules\Contestants\Contracts;
```

---

### PART V — CANONICAL STUB TEMPLATES (VERSIONED `v1`)

The platform provides versioned, standardized Stubs in `stubs/platform/v1/`. Stubs carry a version header (`@stub-version 1.0.0`) enabling audit and future template synchronization across modules.

#### Stub 1: Service Provider Template (`module-provider.stub`)

```php
<?php

declare(strict_types=1);

namespace Modules\{{ModuleName}}\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\{{ModuleName}}\Contracts\{{ModuleName}}ServiceContract;
use Modules\{{ModuleName}}\Infrastructure\Services\{{ModuleName}}Service;

final class {{ModuleName}}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            {{ModuleName}}ServiceContract::class,
            {{ModuleName}}Service::class
        );
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerTranslations();
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'surface:user'])
            ->prefix('api/v1/contestant')
            ->group(__DIR__ . '/../Routes/api.php');

        Route::middleware(['api', 'surface:admin', 'auth:sanctum'])
            ->prefix('api/v1/admin')
            ->group(__DIR__ . '/../Routes/admin.php');
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', '{{moduleLower}}');
    }
}
```

#### Stub 2: Use Case Template (`usecase.stub`)

```php
<?php

declare(strict_types=1);

namespace Modules\{{ModuleName}}\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\AuditLoggerContract;
use Modules\Core\Contracts\OutboxEventBusContract;
use Modules\{{ModuleName}}\Application\Commands\{{UseCaseName}}Command;
use Modules\{{ModuleName}}\Domain\Repositories\{{EntityName}}RepositoryContract;

final readonly class {{UseCaseName}}UseCase
{
    public function __construct(
        private {{EntityName}}RepositoryContract $repository,
        private AuditLoggerContract $auditLogger,
        private OutboxEventBusContract $outboxBus,
    ) {}

    public function execute({{UseCaseName}}Command $command): void
    {
        DB::transaction(function () use ($command): void {
            // 1. Business Logic & Entity Mutation
            $entity = $this->repository->findOrFail($command->id);
            $entity->executeAction($command);

            // 2. Persist Entity
            $this->repository->save($entity);

            // 3. Write Audit Log
            $this->auditLogger->log(
                action: '{{useCaseLower}}',
                auditable: $entity,
                oldValues: $entity->getOriginalState(),
                newValues: $entity->getChanges()
            );

            // 4. Record Outbox Domain Event
            $this->outboxBus->record($entity->releaseEvents());
        });
    }
}
```

#### Stub 3: Form Request Template (`request.stub`)

```php
<?php

declare(strict_types=1);

namespace Modules\{{ModuleName}}\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class {{UseCaseName}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via Policy in Controller
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Field validation rules strictly matching ADR-004 & Inventory
        ];
    }
}
```

#### Stub 4: Pest Feature Test Stub (`test.stub`)

```php
<?php

declare(strict_types=1);

use Modules\{{ModuleName}}\Infrastructure\Database\Models\{{EntityName}}Model;

uses()->group('{{moduleLower}}', 'usecases');

test('{{useCaseLower}} executes successfully and writes to outbox', function (): void {
    // Arrange
    $model = {{EntityName}}Model::factory()->create();

    // Act
    $response = $this->actingAsAdmin()
        ->postJson("/api/v1/admin/{{endpoint}}", [
            'id' => $model->id,
        ]);

    // Assert
    $response->assertOk()
        ->assertJsonPath('meta.code', 200);

    $this->assertDatabaseHas('outbox_events', [
        'aggregate_id' => $model->id,
        'status' => 'pending',
    ]);
});
```

---

## Scaffolding Anti-Patterns (Prohibited Practices)

1. ❌ **No Manual Provider Registration**: Adding a module service provider to `config/app.php` manually.
2. ❌ **No Stray Folders**: Creating ad-hoc root folders like `Modules/Applications/Helpers` or `Modules/Applications/Services` outside the approved 26-directory tree.
3. ❌ **No Concrete Imports Outside `Contracts/`**: Importing a file from another module that does not live inside that module's `Contracts/` directory.
4. ❌ **No Manual File Copying**: Creating module scaffolding by copy-pasting existing module folders. Always run `php artisan make:platform-module`.
5. ❌ **No Direct Eloquent Model Exposure**: Returning Eloquent Models directly from Controllers or Services. Use `JsonResource` and DTOs.

---

## Consequences

### Positive Consequences
- **Instant 15-Module Generation**: Engineers can execute `php artisan make:platform-module` 15 times to generate the entire platform structure in under 30 seconds with 100% architectural compliance.
- **Zero Registration Boilerplate**: Providers, routes, migrations, and contracts are discovered automatically by Laravel.
- **Perfect Structural Uniformity**: Every module shares the exact same directory layout, stub patterns, and namespace conventions.
- **Enforced Public Export Layer**: Other modules can only import interfaces from `Contracts/`, enforcing ADR-002 boundaries at the IDE and compiler layer.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-004: API Standards & Conventions](./ADR-004-api-standards-conventions.md)
- [ADR-005: Database Architecture](./ADR-005-database-architecture.md)
- [ADR-006: Infrastructure Architecture](./ADR-006-infrastructure-architecture.md)
- [ADR-008: Eventing & Domain Events Governance](./ADR-008-eventing-domain-events.md)
- [ADR-009: Development Standards & Code Quality](./ADR-009-development-standards-code-quality.md)
- [ADR-010: Competition Rules Engine Architecture](./ADR-010-competition-rules-engine.md)
- [ADR-ROADMAP.md](../ADR-ROADMAP.md)
