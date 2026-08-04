# ADR-014: Presentation Layer Architecture

* **Status**: Accepted
* **Deciders**: Quran Competition Platform Architecture Board
* **Date**: 2026-07-31

---

## Context & Problem Statement

As the Quran Competition Platform transitions to implementing presentation endpoints across all 15 modules, a constitutional specification is required to maintain architectural purity, enforce API surface isolation, standardize response envelopes, and prohibit leaky abstractions (such as embedding business logic or Eloquent calls inside Controllers or JsonResources).

---

## Decision Drivers

* **Surface Separation**: Clear boundary separation between Public, Contestant, Judge, and Admin surfaces.
* **Strict Controller Purity**: Controllers must act solely as thin HTTP adaptors calling a single UseCase.
* **Standardized JSON Contracts**: Consistent success envelopes, error payload structures, and pagination formats across all 15 modules.
* **Automated Documentation**: OpenAPI 3.1 specifications auto-generated via Scramble (`dedoc/scramble`) directly from type-hinted FormRequests and JsonResources.

---

## Technical Architecture & Specifications

### 1. API Surface Categorization

All endpoints are strictly segregated into 4 surfaces:

| Surface | Route Prefix | Auth & Middleware | Description |
|---|---|---|---|
| **Public API** | `/api/v1/...` | `api`, Rate-limited | Public competition stats, active season, reference countries, public streams. |
| **Contestant API** | `/api/v1/contestant/...` | `api`, `auth:sanctum`, `role:contestant` | Profile updates, application submissions, video upload status. |
| **Judge API** | `/api/v1/judge/...` | `api`, `auth:sanctum`, `role:judge` | Judging queue, video evaluation rubrics, score submissions. |
| **Admin API** | `/api/v1/admin/...` | `api`, `auth:sanctum`, `role:admin` | System configuration, stage management, 2-step results publishing. |

---

### 2. Standardized Success Response Envelope

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {
    "id": "018e3a2b-7c9d-7f1a-b3c4-5d6e7f8a9b0c",
    "name": "Jordan",
    "iso2": "JO"
  },
  "meta": {
    "timestamp": "2026-07-31T23:08:14+03:00"
  }
}
```

---

### 3. Standardized Error Response Contract

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email has already been taken."]
    }
  },
  "meta": {
    "timestamp": "2026-07-31T23:08:14+03:00"
  }
}
```

---

### 4. Standardized Pagination Envelope

```json
{
  "success": true,
  "message": "Resource list retrieved.",
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 150,
      "last_page": 10
    },
    "timestamp": "2026-07-31T23:08:14+03:00"
  }
}
```

---

### 5. Module-by-Module Implementation Pipeline

For every module in Phases 16.1 through 16.15, implementation progresses through this exact pipeline:

```
[ Repository ] ➔ [ UseCases ] ➔ [ Policies ] ➔ [ FormRequests ] ➔ [ Controller ] ➔ [ JsonResources ] ➔ [ Routes ] ➔ [ Feature Tests ] ➔ [ Scramble OpenAPI Export ]
```

---

## Consequences & Benefits

* **100% Type Safety & Scramble Compatibility**: FormRequests with explicit rules and type-hints enable flawless auto-generation of OpenAPI 3.1 schemas.
* **Zero Cross-Layer Pollution**: Controllers remain under 30 lines of code; business logic stays strictly in UseCases and Aggregates.
* **Predictable Client Integration**: Frontend and mobile apps consume identical JSON envelopes across all 15 modules.

---

## Related ADRs

* [ADR-001: System Architecture](../../docs/adr/ADR-001-system-architecture.md)
* [ADR-002: Modular Monolith Boundaries](../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
* [ADR-012: Application Layer Architecture](../../docs/adr/ADR-012-application-layer-usecase-orchestration.md)
* [ADR-013: Workflow & Process Orchestration](../../docs/adr/ADR-013-workflow-and-process-orchestration.md)
