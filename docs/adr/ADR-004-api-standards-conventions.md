# ADR-004: API Standards & Conventions

| Field        | Value                                                                                    |
|--------------|------------------------------------------------------------------------------------------|
| **ID**       | ADR-004                                                                                  |
| **Date**     | 2026-07-31                                                                               |
| **Authors**  | Platform Architecture Team                                                               |
| **Status**   | Accepted                                                                                 |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                         |
| **Related**  | ADR-001 (System Architecture), ADR-002 (Module Boundaries), ADR-003 (Authentication)    |

---

## Status

**Accepted**

---

## Context

ADR-001 established the API-First principle: all client–server communication is mediated exclusively through a versioned REST API. ADR-002 defined the module system and the module ownership of every API surface. ADR-003 defined the two authentication flows and token scoping.

None of those documents defined **how the API behaves** — the response envelope, error format, pagination contract, filtering conventions, file upload strategy, versioning mechanism, deprecation policy, or the dozens of other decisions that collectively constitute the API contract that the Contestant Portal (Next.js) and Administration Dashboard (React) depend upon.

This ADR is the **API Constitution** of the platform. Every decision made here is binding on every module, every endpoint, and every client. No endpoint may deviate from this specification without a formal ADR amendment.

### Why a Dedicated API Standard?

Without a unified API standard, the following failure modes are inevitable and historically well-documented:

- Inconsistent response envelopes require client-side normalization code that becomes a maintenance burden.
- Inconsistent error formats force frontend developers to write defensive code against every possible error shape.
- Inconsistent pagination conventions make it impossible to build a shared pagination component.
- Inconsistent field naming breaks generated TypeScript types and Zod schemas.
- Undocumented breaking change policies cause silent client failures in production.
- Ad-hoc versioning decisions accumulate into an unmanageable API version surface.

This ADR eliminates these failure modes by establishing a single, authoritative contract before the first endpoint is implemented.

### Source Analysis

This ADR was produced after completing the **API Domain Inventory** (`docs/API-DOMAIN-INVENTORY.md`), which exhaustively catalogued 28 resources, all endpoints, HTTP methods, authentication surfaces, pagination strategies, file upload patterns, search targets, and idempotency requirements. The inventory is a prerequisite document referenced herein and must be consulted alongside this ADR.

---

## Decision

### 1. API Philosophy

The platform API is designed around five governing principles that take precedence over any specific technical convention:

**1.1 Predictability First**
Every response, across every module, follows an identical envelope. A frontend developer who has consumed one endpoint can correctly anticipate the shape of any other endpoint's response without consulting documentation. Predictability eliminates surprise and reduces integration bugs.

**1.2 Explicit Over Implicit**
API behaviour is never inferred from context. Status codes have single, consistent meanings. Error codes are machine-readable. Pagination metadata is always present on paginated responses. Locale is always returned. Nothing is left to client interpretation.

**1.3 Backend Is Canonical**
The backend is the single authority on data, validation rules, and business state. Frontend validation (Zod) is a user experience enhancement. The backend rejects anything that violates its rules, regardless of what the frontend allowed.

**1.4 Stability by Default**
Breaking changes require deliberate, documented decisions. The default for any API change is non-breaking. The backward-compatibility policy defined in this ADR is the enforcement mechanism.

**1.5 API as a Product**
The API is a first-class deliverable, not a byproduct of the backend implementation. The OpenAPI specification is maintained in sync with the codebase and is treated with the same seriousness as production code.

---

### 2. Versioning

#### 2.1 URL Prefix

All API endpoints are prefixed with the version segment:

```
/api/v1/{resource}
```

Examples:
```
GET  /api/v1/contestants
GET  /api/v1/admin/applications
POST /api/v1/contestant/application
```

#### 2.2 Version Strategy

The platform uses **URL versioning** (path segment) rather than header versioning. This decision is made on the basis that URL versioning is:
- Visible in browser developer tools without inspecting headers.
- Unambiguous in log files, CDN configurations, and proxy rules.
- Easier to document and communicate to API consumers.

#### 2.3 Version Increment Policy

A new major API version (`/api/v2/`) is introduced **only** when:
- A breaking change cannot be avoided AND
- A backward-compatible alternative cannot be implemented within the existing version AND
- The change has been communicated to all consumers with a minimum 90-day deprecation window.

Minor additions (new endpoints, new optional response fields, new optional request parameters) do NOT constitute a new version. They are deployed within the existing version without incrementing it.

#### 2.4 Current Version

The initial platform release operates exclusively on `v1`. No `v2` surface will exist until explicitly required.

---

### 3. URL & Resource Naming

#### 3.1 Resource Name Convention

All resource segments in URLs use **plural nouns in kebab-case**:

```
/api/v1/contestants        ✅
/api/v1/applications       ✅
/api/v1/scoring-rubric     ✅
/api/v1/contestant         ❌  (singular)
/api/v1/scoringRubric      ❌  (camelCase)
/api/v1/scoring_rubric     ❌  (snake_case)
```

#### 3.2 Surface Prefix Convention

The URL structure encodes the authentication surface:

| Prefix | Auth Required | Surface | Token Type |
|---|---|---|---|
| `/api/v1/` (no prefix) | None | Public — unauthenticated | — |
| `/api/v1/contestant/` | Yes | Contestant Portal | `type=user` Bearer |
| `/api/v1/admin/` | Yes | Administration Dashboard | `type=admin` Bearer |

This is a structural guarantee: any endpoint under `/api/v1/admin/` is **inaccessible** to a contestant token at the middleware layer, before any controller or authorization policy is evaluated.

#### 3.3 Nested Resources

Nested resources are used only when the child has no independent existence outside the parent:

```
# Stage belongs to a Season — nesting is correct:
GET  /api/v1/admin/seasons/{seasonId}/stages
GET  /api/v1/admin/seasons/{seasonId}/stages/{id}

# Application is independent (queryable without a season) — no forced nesting:
GET  /api/v1/admin/applications?season_id={seasonId}    ✅
GET  /api/v1/admin/seasons/{seasonId}/applications      ❌ (avoidable deep nesting)
```

Maximum nesting depth: **two levels** (`/parent/{id}/child/{id}`). Deeper nesting is prohibited.

#### 3.4 Action Endpoints (Non-RESTful Operations)

Workflow operations that do not map to standard CRUD are expressed as **action sub-routes** using `PATCH` and a descriptive verb:

```
PATCH /api/v1/admin/applications/{id}/approve
PATCH /api/v1/admin/applications/{id}/reject
PATCH /api/v1/admin/applications/{id}/needs-data
PATCH /api/v1/admin/seasons/{id}/activate
PATCH /api/v1/admin/stream/start
PATCH /api/v1/admin/stream/stop
```

Action endpoints use `PATCH` (not `POST`) because they modify existing resource state. They are named with a past-tense-compatible verb that describes the resulting state.

---

### 4. HTTP Methods

| Method | Semantics | Idempotent | Safe |
|---|---|---|---|
| `GET` | Retrieve resource(s). Never modifies state. | Yes | Yes |
| `POST` | Create a new resource. Submit a form action. | No | No |
| `PUT` | Full replacement of an existing resource. | Yes | No |
| `PATCH` | Partial update of an existing resource, or a workflow action. | Conditional | No |
| `DELETE` | Remove or soft-delete a resource. | Yes | No |

#### Method Constraints

- `GET` requests must never have a request body. Query parameters are the only input mechanism.
- `PUT` requests replace the entire resource. Clients must send the complete representation.
- `PATCH` requests send only the fields being changed, plus the action verb on action endpoints.
- `DELETE` performs a **soft delete** by default (sets `deleted_at`). Hard deletion requires an explicit `/force-delete` action endpoint and a separate, elevated permission.

---

### 5. Response Envelope

Every API response — success or error — is wrapped in a consistent JSON envelope. No response returns a bare array, a bare string, or a bare primitive.

#### 5.1 Success Response Envelope

```json
{
  "success": true,
  "data": { },
  "meta": { },
  "message": null
}
```

| Field | Type | Always Present | Description |
|---|---|---|---|
| `success` | `boolean` | Yes | Always `true` for 2xx responses. |
| `data` | `object` or `array` | Yes | The primary response payload. `null` for 204 No Content. |
| `meta` | `object` | Yes | Pagination info, locale info, and additional context. `{}` when empty. |
| `message` | `string` or `null` | Yes | Human-readable success message in the request locale. `null` when no message is needed. |

#### 5.2 Single Resource Response

```json
{
  "success": true,
  "data": {
    "id": "01927f3a-abc1-7000-9bd4-12e3c4d5e6f7",
    "status": "approved",
    "contestant": { "id": "...", "name": "محمد علي" },
    "submitted_at": "2026-07-15T10:00:00Z",
    "links": {
      "self": "/api/v1/admin/applications/01927f3a-abc1-7000-9bd4-12e3c4d5e6f7",
      "video": "/api/v1/admin/videos/..."
    }
  },
  "meta": {
    "locale": "ar",
    "rtl": true
  },
  "message": null
}
```

#### 5.3 Collection Response (Cursor Pagination)

```json
{
  "success": true,
  "data": [ { }, { }, { } ],
  "meta": {
    "locale": "ar",
    "rtl": true,
    "pagination": {
      "type": "cursor",
      "per_page": 25,
      "total": null,
      "next_cursor": "eyJpZCI6IjAxOTI3Zi...",
      "prev_cursor": null,
      "has_more": true
    }
  },
  "message": null
}
```

#### 5.4 Collection Response (Offset Pagination)

```json
{
  "success": true,
  "data": [ { }, { }, { } ],
  "meta": {
    "locale": "ar",
    "rtl": true,
    "pagination": {
      "type": "offset",
      "current_page": 2,
      "per_page": 25,
      "total": 143,
      "last_page": 6,
      "from": 26,
      "to": 50
    }
  },
  "message": null
}
```

#### 5.5 No Content Response (204)

Responses with HTTP 204 have **no body**. This applies to: logout, mark-as-read, bulk reorder.

#### 5.6 Created Response (201)

`POST` endpoints that create a resource return HTTP 201 with the full resource representation:

```json
{
  "success": true,
  "data": { /* full created resource */ },
  "meta": { "locale": "ar", "rtl": true },
  "message": "تم إنشاء السجل بنجاح"
}
```

The `Location` header is also set to the canonical URL of the created resource.

---

### 6. Error Contract

All error responses follow an identical structure. There are no bare error messages, no divergent error shapes.

#### 6.1 Error Envelope

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "الطلب يحتوي على بيانات غير صالحة.",
    "details": { }
  }
}
```

| Field | Type | Description |
|---|---|---|
| `success` | `boolean` | Always `false` for error responses. |
| `error.code` | `string` | Machine-readable error code in SCREAMING_SNAKE_CASE. |
| `error.message` | `string` | Human-readable error message in the request locale. |
| `error.details` | `object` | Additional context. For validation errors: field-level messages. |

#### 6.2 Error Code Registry

| HTTP Status | Error Code | When Used |
|---|---|---|
| 400 | `BAD_REQUEST` | Malformed request syntax, missing required parameters |
| 401 | `UNAUTHENTICATED` | No token or expired token |
| 403 | `FORBIDDEN` | Valid token, insufficient permissions |
| 403 | `WRONG_SURFACE` | Valid token, wrong surface (contestant token on admin route) |
| 404 | `NOT_FOUND` | Resource does not exist or is soft-deleted |
| 409 | `CONFLICT` | Business rule conflict (e.g., duplicate application submission) |
| 410 | `GONE` | Resource permanently deleted (hard delete) |
| 422 | `VALIDATION_FAILED` | Form validation errors |
| 423 | `LOCKED` | Resource is locked due to business state (e.g., closed season) |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `SERVER_ERROR` | Unexpected internal error |
| 503 | `SERVICE_UNAVAILABLE` | Maintenance mode or dependency unavailable |

#### 6.3 Validation Error Details

When `error.code` is `VALIDATION_FAILED`, the `details` field contains field-level errors:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "الطلب يحتوي على بيانات غير صالحة.",
    "details": {
      "fields": {
        "email": ["البريد الإلكتروني مطلوب.", "يجب أن يكون البريد الإلكتروني صالحًا."],
        "video": ["يجب أن يكون الفيديو بامتداد MP4 أو MOV.", "حجم الملف يتجاوز الحد المسموح به."]
      }
    }
  }
}
```

Field names in `details.fields` always correspond exactly to the request body field names. Nested field names use dot notation: `"profile.date_of_birth"`.

#### 6.4 Business Rule Conflict Details

When `error.code` is `CONFLICT`, the `details` field describes the violated rule:

```json
{
  "success": false,
  "error": {
    "code": "CONFLICT",
    "message": "لا يمكن تقديم أكثر من طلب واحد في الموسم الواحد.",
    "details": {
      "conflict": "duplicate_application",
      "existing_application_id": "01927f3a-abc1-7000-9bd4-12e3c4d5e6f7"
    }
  }
}
```

---

### 7. Validation Contract

#### 7.1 Backend Validation Is Authoritative

All validation is enforced in **Laravel Form Requests**. Frontend Zod schemas are a convenience — they never substitute for backend validation. Any data that passes frontend validation must still pass backend validation.

#### 7.2 Validation Response Timing

Validation is performed **before** any business logic executes. A `422 VALIDATION_FAILED` response is returned immediately upon first validation failure. Controllers never receive invalid input.

#### 7.3 Full Error Collection

Laravel's `validate()` collects **all** validation errors for all fields and returns them in a single response. Frontend developers must never see partial validation errors that require multiple round-trips to discover all failures.

#### 7.4 Validation Messages

All validation messages are:
- Stored in module-owned translation files (`lang/{ar,en,es}/validation.php`).
- Returned in the request locale.
- Human-readable — not technical references to rule names.

```
# Incorrect:
"email must be a valid email address"   ← rule name

# Correct:
"يرجى إدخال بريد إلكتروني صالح."       ← human message in Arabic
"Please enter a valid email address."   ← human message in English
```

#### 7.5 Request Body Requirements

- All `POST` and `PUT` request bodies must be `application/json` (except file upload endpoints which use `multipart/form-data`).
- The `Content-Type` header is mandatory for all mutating requests.
- Unknown fields in request bodies are silently ignored (not rejected).

---

### 8. Pagination

Two pagination strategies are used, chosen based on the data access pattern of each resource.

#### 8.1 Cursor-Based Pagination

**Applied to**: Contestants, Applications, Videos, Evaluations, Notifications, Judges.

**Rationale**: These resources have high volume, are frequently written to, and may be displayed in real-time feeds. Offset-based pagination on these resources produces inconsistent results when records are inserted or deleted between pages.

**Query Parameters**:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `cursor` | `string` | `null` | Opaque cursor from `meta.pagination.next_cursor` |
| `per_page` | `integer` | `25` | Items per page. Max: `100`. |

**Cursor Format**: Opaque base64-encoded string. Clients must not construct or parse cursors — they are treated as black boxes.

#### 8.2 Offset-Based Pagination

**Applied to**: Users, Countries, Seasons, Stages, Announcements, Notification Templates.

**Rationale**: These resources are low-to-moderate volume, stable (infrequently inserted/deleted during a page session), and benefit from the ability to jump to arbitrary pages and report total counts.

**Query Parameters**:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | `integer` | `1` | Page number (1-indexed). |
| `per_page` | `integer` | `25` | Items per page. Max: `100`. |

#### 8.3 Pagination Defaults

The default `per_page` is `25` across all paginated endpoints unless the specific resource overrides it with a lower default for performance reasons.

The maximum `per_page` is `100`. Any `per_page` value above `100` is silently capped at `100`.

---

### 9. Filtering

#### 9.1 Filter Parameter Convention

All filtering is performed via query parameters using the `filter` bracket notation:

```
GET /api/v1/admin/applications?filter[status]=approved&filter[country_id]=100
GET /api/v1/admin/contestants?filter[is_active]=true&filter[country_id]=55
GET /api/v1/admin/videos?filter[status]=processing&filter[season_id]=3
```

#### 9.2 Multi-Value Filters

Multi-value filters (OR semantics within a field) use comma-separated values:

```
GET /api/v1/admin/applications?filter[status]=approved,under_review
```

#### 9.3 Date Range Filters

Date range filters use `_from` and `_to` suffixes:

```
GET /api/v1/admin/applications?filter[submitted_at_from]=2026-01-01&filter[submitted_at_to]=2026-06-30
```

All date/time values are in **ISO 8601 UTC format** (`2026-07-31T00:00:00Z`).

#### 9.4 Boolean Filters

Boolean filter values are the strings `"true"` and `"false"` (not integers):

```
GET /api/v1/admin/contestants?filter[is_active]=true
GET /api/v1/admin/contestants?filter[has_application]=false
```

#### 9.5 Unknown Filters

Unknown filter parameters are **silently ignored** — not rejected. This ensures forward compatibility when new filters are added.

---

### 10. Sorting

#### 10.1 Sort Parameter Convention

Sorting uses a single `sort` parameter. Prefix with `-` for descending order:

```
GET /api/v1/admin/applications?sort=submitted_at        # ascending
GET /api/v1/admin/applications?sort=-submitted_at       # descending
GET /api/v1/admin/contestants?sort=name                 # alphabetical ascending
```

#### 10.2 Multi-Field Sorting

Multiple sort fields are expressed as a comma-separated list. Fields are applied left to right:

```
GET /api/v1/admin/applications?sort=-submitted_at,name
```

#### 10.3 Default Sort Orders

| Resource | Default Sort |
|---|---|
| Applications | `-submitted_at` (newest first) |
| Contestants | `name` (alphabetical) |
| Videos | `-created_at` (newest first) |
| Evaluations | `-updated_at` (most recently active) |
| Announcements | `-published_at` (newest first) |
| Notifications | `-created_at` (newest first) |
| Users | `name` |
| Countries | `name` |
| Judges | `name` |
| Sponsors | `display_order` |
| FAQ | `display_order` |

#### 10.4 Unsortable Fields

Sorting by arbitrary fields is not permitted. Each endpoint declares its sortable fields. Attempting to sort by an undeclared field returns `400 BAD_REQUEST` with `error.code: INVALID_SORT_FIELD`.

---

### 11. Search

#### 11.1 Search Parameter

Full-text search is performed via the `q` query parameter:

```
GET /api/v1/admin/contestants?q=محمد
GET /api/v1/admin/applications?q=ahmed@example.com
GET /api/v1/admin/videos?q=تلاوة
```

#### 11.2 Search Scope

`q` triggers a Meilisearch query via the Search platform service. It does not filter — it ranks results by relevance. Search and filter parameters may be combined:

```
GET /api/v1/admin/applications?q=محمد&filter[status]=approved&filter[country_id]=3
```

#### 11.3 Search Response

Search responses use the same envelope and pagination format as non-search responses. A `score` field may optionally appear in each item's representation when search ranking is meaningful.

#### 11.4 Search-Enabled Resources

| Resource | Searchable Fields |
|---|---|
| Users | `name`, `email` |
| Contestants | `name`, `email`, `country name` |
| Applications | `contestant name`, `contestant email`, `country name` |
| Videos | `title`, `contestant name` |
| Judges | `name`, `country name`, `specialization` |
| Announcements | `title`, `body` |
| Countries | `name`, `native_name`, `iso_code` |

#### 11.5 Search Is Optional on Every Endpoint

Endpoints that support `q` work identically without it — returning unranked, sorted results. Search never replaces filtering; it augments it.

---

### 12. Sparse Fieldsets (Field Selection)

A `fields` parameter allows clients to request a subset of response fields:

```
GET /api/v1/admin/contestants?fields=id,name,email,country
```

This reduces payload size for list views. The `id` field is always returned regardless of `fields` selection.

**Implementation note**: Fields selection applies only to the top-level `data` object or array items. Nested relationships are not selectable at this API version.

---

### 13. File Upload

#### 13.1 Content Type

All file upload endpoints use `multipart/form-data`. JSON endpoints do not accept file uploads.

#### 13.2 Standard File Upload (≤ 100 MB)

For images, documents, and small files:

```
POST /api/v1/contestant/profile/photo
Content-Type: multipart/form-data

file    = [binary data]
```

Response follows the standard success envelope with a Media resource in `data`:

```json
{
  "success": true,
  "data": {
    "id": "01928...",
    "url": "https://storage.example.com/media/01928.../photo.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 245760,
    "created_at": "2026-07-31T12:00:00Z"
  },
  "meta": { "locale": "ar", "rtl": true },
  "message": null
}
```

#### 13.3 Chunked Upload (Video Files > 100 MB)

Recitation videos (3–5 minutes, potentially up to 2 GB) use a **chunked upload protocol**:

**Step 1 — Initiate Upload**:
```
POST /api/v1/contestant/application/video/upload-session
Body: { "filename": "recitation.mp4", "file_size": 524288000, "mime_type": "video/mp4" }

Response: { "upload_id": "sess_abc123", "chunk_size": 10485760 }
```

**Step 2 — Upload Chunks**:
```
POST /api/v1/contestant/application/video/upload-session/{uploadId}/chunks
Content-Type: multipart/form-data
X-Chunk-Number: 1
X-Total-Chunks: 50

chunk = [binary data]
```

**Step 3 — Finalise**:
```
POST /api/v1/contestant/application/video/upload-session/{uploadId}/complete
Response: { full Video resource with status "processing" }
```

#### 13.4 File Validation Rules

| Type | Max Size | Accepted Formats | Validation Layer |
|---|---|---|---|
| Profile photo | 5 MB | JPEG, PNG, WebP | Laravel Form Request |
| Passport / document | 10 MB | PDF, JPEG, PNG | Laravel Form Request |
| Recitation video | 2 GB | MP4, MOV, AVI | Chunk validation + job |
| Page / Announcement image | 10 MB | JPEG, PNG, WebP | Laravel Form Request |
| Sponsor logo | 5 MB | JPEG, PNG, WebP, SVG | Laravel Form Request |

#### 13.5 Virus Scanning

All uploaded files are queued for virus scanning before being made accessible. Files in scanning status are not publicly accessible. The API returns a `processing` status on uploaded files until scanning (and, for videos, transcoding) is complete.

---

### 14. Streaming API

#### 14.1 Stream Status Endpoint

The live stream status is available without authentication:

```
GET /api/v1/stream

Response:
{
  "success": true,
  "data": {
    "is_live": true,
    "protocol": "hls",
    "embed_url": "https://stream.example.com/quran-competition/live.m3u8",
    "thumbnail_url": "https://stream.example.com/thumb/current.jpg",
    "title": "نصف النهائي — الدورة 2026",
    "started_at": "2026-07-31T18:00:00Z"
  },
  "meta": { "locale": "ar", "rtl": true },
  "message": null
}
```

When the stream is not live:
```json
{
  "success": true,
  "data": { "is_live": false },
  "meta": { "locale": "ar", "rtl": true },
  "message": null
}
```

#### 14.2 Polling vs. WebSocket

The public stream status endpoint is designed for **polling** (not WebSocket) at the initial release. Recommended poll interval: 30 seconds. Real-time stream state change notification for the admin dashboard will be addressed in ADR-006 (Infrastructure — Redis Pub/Sub / Laravel Broadcasting).

---

### 15. Authentication & Authorization

#### 15.1 Token Format

All authenticated requests must include the Bearer token in the `Authorization` header:

```
Authorization: Bearer {token}
```

No other authentication mechanism is supported. Tokens are not accepted as query parameters or in the request body.

#### 15.2 Surface Enforcement

Token surface enforcement is performed at the middleware level, before routing reaches any controller:

| Route Prefix | Middleware | Rejects |
|---|---|---|
| `/api/v1/contestant/` | `auth.contestant` | No token; `type=admin` tokens |
| `/api/v1/admin/` | `auth.admin` | No token; `type=user` tokens |
| `/api/v1/` (public) | None | — |

#### 15.3 Permission Checking

All admin endpoints enforce permission gates using Spatie Laravel Permission. The required permission is checked **after** surface enforcement:

```
Route → Surface Middleware (type check) → Controller → Policy/Gate (permission check) → Use Case
```

No endpoint assumes implicit permissions from role membership. Every protected endpoint has an explicit, documented permission requirement.

#### 15.4 Token Expiry Behavior

When a token has expired, the response is:

```json
HTTP 401
{
  "success": false,
  "error": {
    "code": "UNAUTHENTICATED",
    "message": "انتهت صلاحية الجلسة. يرجى تسجيل الدخول من جديد.",
    "details": { "reason": "token_expired" }
  }
}
```

Clients must refresh their token and retry the request. Refresh token endpoint: `POST /api/v1/auth/refresh`.

---

### 16. Localization

#### 16.1 Locale Resolution

The active locale is resolved from the following sources in priority order:

1. `Accept-Language` HTTP header (BCP-47 format, e.g., `ar`, `en`, `es`)
2. `?lang={code}` query parameter
3. Fallback: `ar` (Arabic — the platform default)

#### 16.2 Locale in Response

The resolved locale is always present in `meta.locale` and `meta.rtl`:

```json
"meta": {
  "locale": "ar",
  "rtl": true
}
```

#### 16.3 Translatable Content

For resources with translatable fields (pages, announcements, FAQ, sponsor names), the API returns the content in the resolved locale. If a translation does not exist for the requested locale, the API falls back to the default locale (`ar`) and includes a `content_locale_fallback: true` flag in `meta`.

#### 16.4 API Error Messages

All user-facing error messages in `error.message` and `error.details` are returned in the resolved locale.

---

### 17. Idempotency

#### 17.1 Idempotency Key Header

Non-idempotent `POST` and `PATCH` operations that carry business risk on retry (double-submission, double-notification) must include the `Idempotency-Key` header:

```
POST /api/v1/contestant/application
Idempotency-Key: {uuid-v4}
```

#### 17.2 Idempotency Semantics

- If a request with the same `Idempotency-Key` was received and successfully processed within the last 24 hours, the server returns the **original response** (cached) with HTTP 200, regardless of the current state.
- If a request with the same key is currently in-flight, the server returns HTTP 409 with `error.code: REQUEST_IN_FLIGHT`.
- Idempotency keys expire after 24 hours.

#### 17.3 Endpoints Requiring Idempotency

| Endpoint | Business Risk |
|---|---|
| `POST /contestant/application` | One application per season — duplicate submission risk |
| `POST /contestant/application/video` | Duplicate upload creates duplicate processing jobs |
| `PATCH /admin/applications/{id}/approve` | Double approval triggers double notification |
| `PATCH /admin/applications/{id}/reject` | Same as approve |
| `POST /admin/notifications/broadcast` | Double broadcast sends duplicate messages to all recipients |

---

### 18. Rate Limiting

#### 18.1 Rate Limit Strategy

Rate limiting is applied per-IP and per-authenticated-user. Authenticated user limits are higher than unauthenticated limits.

#### 18.2 Rate Limit Tiers

| Tier | Limit | Window | Applied To |
|---|---|---|---|
| Unauthenticated | 60 requests | 1 minute | All `/api/v1/` public endpoints |
| Authenticated Contestant | 300 requests | 1 minute | All `/api/v1/contestant/` endpoints |
| Authenticated Admin | 600 requests | 1 minute | All `/api/v1/admin/` endpoints |
| Authentication endpoints | 10 requests | 1 minute | `POST /auth/login`, `POST /auth/social/*` |
| File upload | 20 uploads | 1 hour | All upload endpoints |
| Report export | 5 requests | 1 hour | All `/admin/reports/*/export` endpoints |

#### 18.3 Rate Limit Headers

All responses include rate limit headers:

```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 247
X-RateLimit-Reset: 1722438600
```

#### 18.4 Rate Limit Exceeded Response

```json
HTTP 429
{
  "success": false,
  "error": {
    "code": "RATE_LIMITED",
    "message": "لقد تجاوزت عدد الطلبات المسموح به. يرجى المحاولة لاحقًا.",
    "details": {
      "retry_after": 37
    }
  }
}
```

The `Retry-After` response header is also set to the number of seconds until the rate limit resets.

---

### 19. OpenAPI Governance

#### 19.1 Specification Format

The complete API surface is documented using **OpenAPI 3.1.x**. The specification is the authoritative contract between the backend and all frontend applications.

#### 19.2 Documentation Maintenance Rule

The OpenAPI specification is updated **simultaneously** with every API change. An API change is not considered complete until its documentation is updated. This rule is enforced at code review.

#### 19.3 Swagger UI

A Swagger UI interface is served at `/api/documentation` in non-production environments (local, staging). It is disabled in production by environment configuration.

#### 19.4 Type Generation

Frontend applications generate TypeScript interfaces from the OpenAPI specification. The generated types are the single source of type safety on the frontend. Manual type definitions that duplicate the OpenAPI spec are prohibited.

#### 19.5 Schema First vs Code First

The platform adopts a **Code-First** approach to OpenAPI: PHP annotations on controllers and API resources generate the OpenAPI spec. The alternative (writing the spec by hand then generating code) is rejected due to the discipline overhead of keeping a handwritten spec in sync at the scale of this platform's endpoint surface.

---

### 20. Naming Conventions

#### 20.1 JSON Field Naming

All JSON request and response fields use **snake_case**:

```json
{
  "contestant_name": "محمد علي",
  "date_of_birth": "1990-05-15",
  "country_id": 100,
  "submitted_at": "2026-07-31T12:00:00Z"
}
```

camelCase, PascalCase, and kebab-case are prohibited in JSON payloads.

#### 20.2 Timestamp Format

All timestamps are in **ISO 8601 UTC format**:

```
"created_at": "2026-07-31T12:00:00Z"
"submitted_at": "2026-07-31T09:30:00.000Z"
```

Timestamps are never returned as Unix epoch integers.

#### 20.3 ID Format

All resource identifiers use **UUID v7** (time-ordered UUIDs). IDs are strings — never integers — in API responses:

```json
{ "id": "01927f3a-abc1-7000-9bd4-12e3c4d5e6f7" }
```

Integer IDs (auto-increment primary keys) are internal database details and must never appear in API responses.

#### 20.4 Boolean Field Naming

Boolean fields use `is_` or `has_` prefix to communicate type from the field name:

```json
{
  "is_active": true,
  "is_live": false,
  "has_application": true,
  "rtl": true
}
```

#### 20.5 Status Fields

Status values are lowercase strings, not integers:

```json
{ "status": "under_review" }
{ "status": "approved" }
{ "status": "rejected" }
```

Status enumerations are documented in the OpenAPI spec for each resource.

---

### 21. Bulk Operations

#### 21.1 Bulk Reorder

Resources with a `display_order` field (Sponsors, FAQ) support bulk reorder via:

```
PATCH /api/v1/admin/sponsors/reorder
Body: { "order": ["id-1", "id-3", "id-2"] }
```

The `order` array must contain all IDs of the collection. Partial reorder is not supported.

#### 21.2 Bulk Status Update

No bulk status update endpoints are defined at v1. Workflow status transitions (approve, reject) are performed one resource at a time to preserve the audit trail and ensure each transition triggers its correct domain event.

---

### 22. CORS Policy

#### 22.1 Allowed Origins

CORS is configured per environment:

| Environment | Allowed Origins |
|---|---|
| Local | `http://localhost:3000`, `http://localhost:5173` |
| Staging | Staging domain of frontend + admin frontend |
| Production | Production domains of frontend + admin frontend only |

Wildcard (`*`) origins are **prohibited** in all environments including local.

#### 22.2 Credentials

Credentials (cookies, authorization headers) are allowed for CORS requests from permitted origins.

---

### 23. API Compatibility & Deprecation Policy

This section defines the rules governing how the API evolves. It is the most critical section for long-term platform health, particularly as additional clients (mobile applications, third-party integrations) may consume this API in future seasons.

#### 23.1 Non-Breaking Changes (Allowed Without Version Increment)

The following changes are **backward-compatible** and may be deployed to v1 without notification or a new version:

| Change | Rationale |
|---|---|
| Adding a new endpoint | New surface, no existing client is affected |
| Adding a new optional response field | Clients that ignore unknown fields are unaffected |
| Adding a new optional request parameter | Clients that omit the parameter continue to work |
| Adding a new `filter` parameter | Clients that don't use it are unaffected |
| Adding a new sort field | Clients that don't use it are unaffected |
| Extending an enum with a new value | Clients must already handle unknown enum values |
| Adding a new error code | Clients handling `error.code` generically are unaffected |

#### 23.2 Breaking Changes (Require Deprecation Process)

The following changes **break backward compatibility** and require the full deprecation process:

| Change | Impact |
|---|---|
| Removing a field from a response | Clients reading that field will receive `null` or error |
| Renaming a field | Clients reading the old name will receive `null` |
| Changing a field's type | Clients parsing the old type will error |
| Removing an endpoint | Clients calling the endpoint will receive 404 |
| Changing HTTP method of an existing endpoint | Clients using the old method will receive 405 |
| Removing a filter or sort option | Clients using the option will receive 400 |
| Changing the semantics of a status value | Clients that branch on the old meaning will behave incorrectly |
| Making an optional field required | Clients that omit the field will receive 422 |

#### 23.3 Breaking Change Process

When a breaking change is unavoidable:

1. **Flag for deprecation**: Add `X-Deprecated: true` and `X-Sunset-Date: {date}` response headers to the affected endpoint or field.
2. **Document**: Update OpenAPI spec with `deprecated: true` on the affected element.
3. **Communicate**: Notify all known API consumers (frontend teams) with the sunset date.
4. **Minimum notice period**: 90 days between deprecation announcement and removal.
5. **Version transition**: If the breaking change affects a core contract, introduce `/api/v2/` for the affected surface.

#### 23.4 Field Removal Policy

Response fields may only be removed after:
- The field has been marked as deprecated (`deprecated: true` in OpenAPI) for at least 90 days.
- The sunset date has passed.
- All known clients have confirmed migration.

A response field that has existed in production may never be removed in fewer than 90 days, regardless of business pressure.

#### 23.5 Additive-Only Policy for v1

During the lifetime of v1, the API is **additive-only by default**. Engineers must actively justify any field removal, endpoint removal, or semantic change. The default answer to "can we remove this?" is **no**.

#### 23.6 Version Support Policy

| Version | Support Status | End of Life |
|---|---|---|
| v1 | Active — full support | Defined when v2 is released |
| v2 (future) | Not yet created | — |

Once v2 is released, v1 enters **maintenance mode**: security fixes only, no new features, 12-month support window before decommission.

---

### 24. Endpoint Lifecycle

Every endpoint moves through the following lifecycle:

```
Planned → Implemented → Active → Deprecated → Sunset
```

| State | OpenAPI Status | Behaviour |
|---|---|---|
| **Planned** | Schema only, `x-status: planned` | Not deployed |
| **Implemented** | Annotated, in codebase | Deployed but not announced |
| **Active** | `x-status: active` | Fully supported, advertised |
| **Deprecated** | `deprecated: true` | Supported with sunset date in headers |
| **Sunset** | Removed | Returns 410 `GONE` for 30 days, then removed |

#### Sunset Response

For 30 days after a sunset date passes, the endpoint returns HTTP 410 with a migration guide:

```json
HTTP 410
{
  "success": false,
  "error": {
    "code": "GONE",
    "message": "هذا المسار لم يعد مدعومًا.",
    "details": {
      "sunset_date": "2026-10-01",
      "migration_guide": "https://docs.platform.jo/api/migration/v1-to-v2"
    }
  }
}
```

---

### 25. Security Conventions

#### 25.1 No Sensitive Data in URLs

Tokens, passwords, and sensitive identifiers must never appear in URL paths or query parameters. They belong in request headers or the encrypted request body only.

#### 25.2 No Enumerable IDs

All resource IDs are UUIDs v7. Sequential integer IDs are not exposed in any API response, preventing enumeration attacks.

#### 25.3 Ownership Enforcement

Every endpoint that returns or modifies a resource owned by the authenticated user (e.g., `GET /contestant/application`) must verify ownership at the application or policy layer. A contestant must not be able to access another contestant's application by manipulating an ID.

#### 25.4 Audit Logging

All mutating API operations (`POST`, `PUT`, `PATCH`, `DELETE`) are automatically logged to the audit log via the `AuditLoggerContract` from the Core module. The audit log records: authenticated user ID, HTTP method, endpoint, resource ID, before/after state summary, and timestamp.

---

## Consequences

### Positive Consequences

- **Zero integration ambiguity**: Every frontend developer knows the exact shape of every response before writing a single line of client code.
- **Consistent Zod schemas**: Type generation from OpenAPI ensures Zod schemas are always aligned with backend contracts, eliminating the primary source of frontend validation drift.
- **Onboarding acceleration**: New engineers joining either frontend team can understand the entire API surface from a single document.
- **Safe iteration**: The additive-only policy and deprecation process allow the API to evolve without breaking existing clients.
- **Future-proofing**: A mobile application or third-party integration added in a future season inherits a complete, documented, stable contract.
- **Audit completeness**: Automatic audit logging on all mutating operations satisfies JRTV's regulatory and compliance requirements.

### Negative Consequences / Trade-offs

- **Envelope overhead**: Every response carries the envelope structure even for simple operations. This adds ~100 bytes per response — a negligible overhead given modern network conditions and the improvement in client-side consistency.
- **Disciplined evolution required**: The additive-only and deprecation policies slow down the removal of legacy endpoints. This is a feature, not a bug — it protects clients — but it requires discipline from the development team.
- **Code-First OpenAPI maintenance**: Annotations on controllers and resources must be kept in sync with actual behavior. An unmaintained annotation is worse than no annotation.
- **Chunked upload complexity**: The multi-step chunked upload protocol is more complex to implement and test than a simple single-file upload. The complexity is justified by the 2 GB video file requirement.

---

## Alternatives Considered

### Alternative 1: No Unified Envelope (Bare Responses)

Returning bare JSON objects or arrays without an envelope, relying on HTTP status codes for result semantics.

**Rejected because**:
- Different endpoints naturally evolve different shapes without a shared contract, creating a fragmented client experience.
- Adding metadata (pagination, locale, links) to bare responses requires ad-hoc field names that differ per endpoint.
- Frontend code that handles one endpoint's response shape cannot be reused for another's.

### Alternative 2: GraphQL

Using GraphQL as the API technology instead of REST.

**Rejected because**:
- GraphQL introduces significant backend complexity (resolver architecture, N+1 query management, schema definition language) that is disproportionate for this team size and timeline.
- The frontend applications are well-defined in scope — the flexibility benefit of GraphQL (arbitrary client-driven queries) is not needed when the client surfaces are known and bounded.
- The OpenAPI toolchain for REST is mature; Laravel's REST ecosystem (Form Requests, API Resources, Scout, Sanctum) is purpose-built for REST, not GraphQL.
- File uploads, streaming, and chunked upload protocols are cumbersome in GraphQL.

### Alternative 3: Header-Based Versioning

Using an `Accept: application/vnd.platform.v1+json` header for API versioning instead of URL path versioning.

**Rejected because**:
- Header-based versioning is invisible in browser developer tools, CDN logs, and proxy configurations.
- URL versioning is universally understood and requires no special client configuration.
- Routing middleware in Laravel is simpler to write for URL-based versioning.

### Alternative 4: Integer IDs in Responses

Exposing auto-increment integer primary keys in API responses for simplicity.

**Rejected because**:
- Sequential integer IDs are enumerable — an attacker can iterate over all resource IDs and test access control by incrementing a single integer.
- UUID v7 IDs are time-ordered (retaining the performance characteristic of sequential IDs for index locality) while being non-enumerable.
- UUID IDs are safe to expose in public-facing URLs without leaking information about platform scale.

### Alternative 5: CamelCase JSON Fields

Using camelCase for JSON field names to match JavaScript conventions.

**Rejected because**:
- Laravel's native serialization is snake_case. Maintaining a camelCase mapping layer throughout the codebase requires either global transformation middleware or per-resource manual mapping — both are error-prone.
- TypeScript type generators and Zod schema generators work correctly with either convention; the choice of snake_case is consistent with Laravel ecosystem conventions.
- snake_case is also the natural format for OpenAPI examples that are readable by non-JavaScript engineers.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-003: Authentication & Identity Architecture](./ADR-003-authentication-identity.md)
- [API Domain Inventory](../API-DOMAIN-INVENTORY.md)
- [JSON:API Specification](https://jsonapi.org/)
- [OpenAPI 3.1.x Specification](https://spec.openapis.org/oas/v3.1.0)
- [RFC 7807 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc7807)
- [RFC 7234 — HTTP/1.1 Caching](https://www.rfc-editor.org/rfc/rfc7234)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
- [Laravel Form Requests](https://laravel.com/docs/validation#form-request-validation)
- [Google API Design Guide](https://cloud.google.com/apis/design)
- [Stripe API Reference — Versioning](https://stripe.com/docs/api/versioning)
- [ADR-ROADMAP](../ADR-ROADMAP.md)
