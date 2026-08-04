# Notifications Module — Quran Competition Platform

## Overview
The **Notifications Module** is an event-driven consumer responsible for processing Outbox domain events (`application_submitted`, `stage_result_published`, `appeal_accepted`, etc.) asynchronously and dispatching multi-channel notifications (Email, SMS, Push).

## Responsibilities
- React asynchronously to published Outbox Domain Events via dedicated Event Subscribers.
- Manage notification logs (`NotificationLog` aggregate) for audit trails and retry queues.
- Support multi-channel delivery (`email`, `sms`, `push`).

## Aggregates
1. `NotificationLog`: Log aggregate governing dispatch status tracking (`queued`, `sent`, `failed`).

## Published Domain Events
- None (Consumer module).

## Consumed Domain Events
- `application_submitted`
- `stage_result_published`
- `appeal_accepted`
- `appeal_rejected`

## Dependencies
- Core (User Contract, Outbox Event Bus)

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-008: Eventing & Domain Events Governance](../../../docs/adr/ADR-008-eventing-domain-events.md)
- [ADR-012: Application Layer Architecture](../../../docs/adr/ADR-012-application-layer-usecase-orchestration.md)
