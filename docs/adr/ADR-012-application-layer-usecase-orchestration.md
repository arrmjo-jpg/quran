# ADR-012: Application Layer & Use Case Orchestration Architecture

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-012                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-004 · ADR-005 · ADR-008 · ADR-009 · ADR-010 · ADR-011 · ERD · MIGRATION-SPECIFICATION                   |

---

## Status

**Accepted** — This constitutional ADR defines the binding execution rules for the Application Layer, Use Case orchestration lifecycle, transaction boundaries, outbox event integration, repository contracts, and cross-module interactions across all 15 platform modules.

---

## Context

### Why This ADR Exists

ADR-001 through ADR-011 established the system architecture, module boundaries, database schemas, infrastructure, domain eventing, development quality standards, competition rules engine, and module scaffolding.

As the platform transitions into Phase 15C (Module Implementation), a binding constitution for **Application Layer & Use Case Orchestration** is required to ensure:
1. Every Use Case follows the exact same execution lifecycle.
2. Transaction boundaries (`DB::transaction()`) are strictly enforced at the Use Case level.
3. Domain Events are recorded in the Transactional Outbox inside the *same* database transaction as aggregate state mutations.
4. Controllers remain ultra-thin, performing zero business or orchestration logic.
5. Cross-module communications strictly follow published `Contracts/` and `Outbox Domain Events`.

---

## Architectural Decisions

### 1. Single Responsibility Use Cases
Every Use Case represents a single business operation (e.g., `RegisterContestantUseCase`, `SubmitEvaluationUseCase`). Use Cases:
- MUST be marked `final readonly`.
- MUST declare a single public method `execute(Command $command): Result|void`.
- MUST NOT extend framework classes.

### 2. Transaction Boundaries & Outbox Event Co-location
All aggregate state mutations and outbox event recordings MUST occur within an atomic `DB::transaction()` block inside the Use Case:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ USE CASE ATOMIC TRANSACTION BOUNDARY                                         │
│ DB::transaction(function() {                                               │
│   1. Load Aggregate via RepositoryContract                                 │
│   2. Execute Domain Logic on Aggregate                                      │
│   3. Save Aggregate via RepositoryContract                                  │
│   4. Record Released Domain Events in outbox_events table                  │
│ })                                                                          │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3. Request Lifecycle Pipeline
Every HTTP request follows a strict 6-stage unidirectional flow:

1. **Presentation Layer (FormRequest)**: Validates HTTP payload & policy authorization.
2. **Presentation Layer (Controller)**: Instantiates an immutable Command DTO (`final readonly`).
3. **Application Layer (UseCase)**: Opens `DB::transaction()`, fetches Aggregate from Repository, invokes Aggregate business methods.
4. **Domain Layer (Aggregate Root)**: Enforces business invariants, mutates internal state, records Domain Event DTOs internally.
5. **Infrastructure Layer (Repository & Outbox)**: Persists model state and writes event DTOs to `outbox_events` table.
6. **Presentation Layer (JsonResource)**: Transforms result DTO into ADR-004 compliant JSON payload.

### 4. CQRS Command & Query Separation
- **Commands**: Modify state via Use Cases. Must return `void` or a minimal result DTO (e.g. ID).
- **Queries**: Read state directly via Query Services/Repositories returning DTOs or JsonResources. Bypasses aggregate mutation rules for maximum performance.

### 5. Cross-Module Communication Governance
- **Synchronous**: Modules communicate with other modules strictly via interfaces published in `Modules/{TargetModule}/Contracts/`. Direct imports of concrete Repositories, Use Cases, or Eloquent Models from another module are strictly prohibited.
- **Asynchronous**: Cross-module side-effects (e.g., sending notifications upon registration) MUST react asynchronously to published Outbox Domain Events via subscribers.

---

## Consequences

### Positive Consequences
- **Absolute Architectural Consistency**: All engineers write Use Cases using the exact same atomic transaction pattern.
- **Zero Partial State Bugs**: Outbox events and database mutations commit together or roll back together.
- **Testability**: Use Cases depend strictly on repository interfaces and can be unit-tested using mock repositories without database dependencies.

---

## References

- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-008: Eventing & Domain Events Governance](./ADR-008-eventing-domain-events.md)
- [ADR-009: Development Standards & Code Quality](./ADR-009-development-standards-code-quality.md)
- [ADR-011: Module Scaffolding Architecture](./ADR-011-module-scaffolding-code-generation.md)
