# Competition Module — Quran Competition Platform

## Overview
The **Competition Module** is the core business engine governing seasons, registration windows, competition stages, dynamic evaluation rubrics, and deterministic tie-breaking rules.

## Responsibilities
- Manage season lifecycles (`draft`, `registration_open`, `registration_closed`, `active`, `completed`).
- Govern stage ordering (`preliminary`, `semi_final`, `final`) and execution schedules.
- Provide dynamic evaluation rubric configurators (`EvaluationTemplate` & `EvaluationCriteria`).
- Execute deterministic tie-breaking rules via `CompetitionRuleEngine` per ADR-010.

## Aggregates
1. `Season`: Root aggregate governing registration windows and season status.
2. `Stage`: Aggregate governing stage numbers, types, and stage results publication.
3. `EvaluationTemplate`: Aggregate governing dynamic rubric criteria and 100% weight sum invariants.
4. `CompetitionRules`: Rule Engine interface (`RuleEngineContract`) decoupling rules from Use Cases.

## Published Domain Events
- `season_created`
- `registration_opened`
- `registration_closed`
- `stage_created`
- `results_published`

## Consumed Events
- None.

## Use Cases
- `CreateSeasonUseCase`
- `OpenRegistrationUseCase`
- `CloseRegistrationUseCase`
- `CreateStageUseCase`
- `PublishStageResultsUseCase`

## Permissions
- `competition.seasons.manage`
- `competition.stages.manage`
- `competition.results.publish`

## API Endpoints
- `GET /api/v1/seasons/active` (Public / Contestant surface)
- `POST /api/v1/admin/seasons` (Admin surface)
- `POST /api/v1/admin/seasons/{id}/open-registration` (Admin surface)

## Dependencies
- Core (Value Objects, Outbox Bus)

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-010: Competition Rules Engine](../../../docs/adr/ADR-010-competition-rules-engine.md)
- [ADR-012: Application Layer Architecture](../../../docs/adr/ADR-012-application-layer-usecase-orchestration.md)
