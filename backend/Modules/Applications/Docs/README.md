# Applications Module — Quran Competition Platform

## Overview
The **Applications Module** manages contestant competition entry applications, application lifecycle state machine transitions, video re-upload requests, and judging readiness workflows.

## Responsibilities
- Govern application state transitions via `ApplicationStateMachine` (`draft` -> `submitted` -> `under_review` -> `ready_for_judging` -> `qualified` / `eliminated`).
- Link contestant profiles (`ContestantId`), competition seasons (`SeasonId`), stages (`StageId`), and media assets (`MediaAssetId`).
- Handle video re-upload workflow requests without embedding raw video processing logic in the application aggregate.

## Aggregates
1. `Application`: Aggregate root governing application submission status and media references.

## Published Domain Events
- `application_created`
- `application_submitted`

## Consumed Events
- `video_transcoding_completed` (from Videos module)

## Use Cases
- `CreateApplicationUseCase`
- `SubmitApplicationUseCase`
- `RequestVideoReuploadUseCase`
- `MarkReadyForJudgingUseCase`

## Permissions
- `applications.submit`
- `applications.review`
- `applications.reupload_request`

## API Endpoints
- `POST /api/v1/applications` (Contestant surface)
- `POST /api/v1/applications/{id}/submit` (Contestant surface)
- `POST /api/v1/admin/applications/{id}/request-reupload` (Admin surface)

## Dependencies
- Core (Value Objects, Outbox Bus)
- Competition (Season & Stage Contracts)
- Contestants (Contestant Profile Contract)

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-008: Domain Events Architecture](../../../docs/adr/ADR-008-eventing-domain-events.md)
- [ADR-012: Application Layer Architecture](../../../docs/adr/ADR-012-application-layer-usecase-orchestration.md)
