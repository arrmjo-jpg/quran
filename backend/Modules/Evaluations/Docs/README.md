# Evaluations Module — Quran Competition Platform

## Overview
The **Evaluations Module** is the scoring and ranking engine of the platform. It manages judge evaluation rubrics, criteria scores, stage result rankings, deterministic tie-breaking, 2-stage result publishing, and contestant appeals.

## Domain Design Architecture (DOM-EVAL-001)
See full architectural blueprint: [evaluations_domain_design.md](file:///C:/Users/retan/.gemini/antigravity/brain/fefbe572-44a5-4255-9de5-98f184dee1e6/evaluations_domain_design.md)

## Aggregates
1. `Evaluation`: Judge evaluation session per application per stage.
2. `EvaluationScore`: Individual rubric criteria scores.
3. `StageResult`: Aggregated stage results, rankings, and publication status.
4. `Appeal`: Contestant appeal and admin decision workflow.

## Published Domain Events
- `evaluation_started`
- `evaluation_submitted`
- `evaluation_returned_for_revision`
- `evaluation_approved`
- `stage_result_calculated`
- `stage_result_published`
- `appeal_created`
- `appeal_accepted`
- `appeal_rejected`

## Key Domain Services
- `RankingService`: Calculates stage averages, executes `RuleEngineContract::breakTies()` and `evaluateQualification()`, and constructs `StageResult`.

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-010: Competition Rules Engine](../../../docs/adr/ADR-010-competition-rules-engine.md)
- [ADR-012: Application Layer Architecture](../../../docs/adr/ADR-012-application-layer-usecase-orchestration.md)
