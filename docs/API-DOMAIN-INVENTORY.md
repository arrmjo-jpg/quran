# API Domain Inventory — Global Quran Competition Platform

> **Purpose**: This document is the prerequisite analysis for ADR-004.
> It exhaustively maps every API resource, operation, surface, and constraint
> derived from the project proposal, ADR-001, ADR-002, and ADR-003.
> ADR-004 must not be written until this inventory is complete and reviewed.
>
> **Status**: Complete — feeds directly into ADR-004.
> **Date**: 2026-07-31

---

## Master Resource Matrix

| # | Resource | Module | Public Portal | Admin Dashboard | CRUD | Workflow Ops | Search | Pagination | File Upload |
|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | Auth (Contestant) | Core | ✅ | — | — | login, callback, logout, refresh | — | — | — |
| 2 | Auth (Admin) | Core | — | ✅ | — | login, logout, refresh, password-reset | — | — | — |
| 3 | Languages | Core | ✅ | ✅ | Admin CRUD | activate, deactivate | — | — | — |
| 4 | Settings | Core | — | ✅ | Update only | — | — | — | — |
| 5 | Users | Core | — | ✅ | Full | restore, force-delete | ✅ | ✅ | — |
| 6 | Roles | Core | — | ✅ | Full | assign-permissions | — | ✅ | — |
| 7 | Permissions | Core | — | ✅ | Read only | — | — | — | — |
| 8 | Countries | Countries | ✅ | ✅ | Admin CRUD | activate, deactivate | ✅ | ✅ | — |
| 9 | Contestants | Contestants | ✅ (own) | ✅ | Public reg. + Admin manage | suspend, reinstate | ✅ | ✅ | ✅ (photo, passport) |
| 10 | Applications | Applications | ✅ (own) | ✅ | Submit + Admin manage | review, approve, reject, needs-data, re-upload-video | ✅ | ✅ | ✅ (docs) |
| 11 | Application Documents | Applications | ✅ (upload) | ✅ | Upload + Admin view | — | — | — | ✅ |
| 12 | Judges | Judges | ✅ (list, public) | ✅ | Admin CRUD | assign-to-stage, remove-from-stage | ✅ | ✅ | ✅ (photo, bio) |
| 13 | Seasons | Competition | ✅ (public archive) | ✅ | Admin CRUD | activate, close, archive | — | ✅ | — |
| 14 | Stages | Competition | ✅ (public schedule) | ✅ | Admin CRUD | start, complete, publish-results | — | ✅ | — |
| 15 | Stage Judge Assignments | Competition | — | ✅ | Admin CRUD | — | — | ✅ | — |
| 16 | Evaluations | Evaluations | — | ✅ (Judges) | Create + Update | submit, approve | — | ✅ | — |
| 17 | Scoring Rubric | Evaluations | — | ✅ | Admin CRUD | — | — | — | — |
| 18 | Videos | Videos | ✅ (gallery) | ✅ | Upload + Admin manage | publish, reject, reprocess | ✅ | ✅ | ✅ (video file) |
| 19 | Video Processing Status | Videos | ✅ (own) | ✅ | Read only | — | — | — | — |
| 20 | Media (generic files) | Media | — (internal) | ✅ | Upload + Delete | — | — | ✅ | ✅ |
| 21 | Stream | Streaming | ✅ (view) | ✅ | Admin CRUD | start, stop, change-source | — | — | — |
| 22 | Pages | Content | ✅ | ✅ | Admin CRUD | publish, unpublish | — | ✅ | ✅ (images) |
| 23 | Announcements | Content | ✅ | ✅ | Admin CRUD | publish, unpublish, schedule | ✅ | ✅ | ✅ (images) |
| 24 | FAQ | Content | ✅ | ✅ | Admin CRUD | publish, unpublish, reorder | — | ✅ | — |
| 25 | Sponsors | Sponsors | ✅ (list) | ✅ | Admin CRUD | activate, deactivate, reorder | — | ✅ | ✅ (logo) |
| 26 | Notifications | Notifications | ✅ (own inbox) | ✅ | Read + delete (own) | mark-read, mark-all-read | — | ✅ | — |
| 27 | Notification Templates | Notifications | — | ✅ | Admin CRUD | — | — | ✅ | — |
| 28 | Reports / Dashboard | Reports | — | ✅ | Read only | export | — | — | — |

---

## Resource Detail Sheets

---

### 1. Authentication — Contestant (Social OAuth)

**Module**: Core  
**Surface**: Public Portal only  
**Base URL**: `/api/v1/auth/`

| Endpoint | Method | Description | Auth Required | Permission |
|---|---|---|---|---|
| `/auth/social/{provider}/redirect` | `GET` | Redirect to OAuth provider | No | — |
| `/auth/social/{provider}/callback` | `GET` | Handle OAuth callback, issue token | No | — |
| `/auth/refresh` | `POST` | Refresh access token | Yes (refresh token) | — |
| `/auth/logout` | `POST` | Revoke token | Yes | — |
| `/auth/me` | `GET` | Return authenticated contestant profile | Yes | `type=user` |

**Supported Providers**: `google`, `facebook`, `apple` (optional), `microsoft` (optional)  
**Token Type**: Sanctum Bearer token scoped to `type=user`  
**Events**: `ContestantRegistered` (first OAuth login)

---

### 2. Authentication — Admin (Email/Password)

**Module**: Core  
**Surface**: Admin Dashboard only  
**Base URL**: `/api/v1/admin/auth/`

| Endpoint | Method | Description | Auth Required | Permission |
|---|---|---|---|---|
| `/admin/auth/login` | `POST` | Authenticate with email + password | No | — |
| `/admin/auth/logout` | `POST` | Revoke admin token | Yes | `type=admin` |
| `/admin/auth/refresh` | `POST` | Refresh admin access token | Yes (refresh token) | — |
| `/admin/auth/me` | `GET` | Return authenticated admin profile | Yes | `type=admin` |
| `/admin/auth/password/reset-request` | `POST` | Send password reset email | No | — |
| `/admin/auth/password/reset` | `POST` | Confirm password reset | No | — |

**Token Type**: Sanctum Bearer token scoped to `type=admin`  
**Events**: `PasswordResetRequested`

---

### 3. Languages

**Module**: Core  
**Surface**: Public Portal (read) + Admin Dashboard (manage)  
**Base URL**: `/api/v1/`

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/languages` | `GET` | No | Public | — | Returns active languages only |
| `/admin/languages` | `GET` | Admin | Admin | `settings.view` | Returns all languages incl. inactive |
| `/admin/languages` | `POST` | Admin | Admin | `settings.update` | Add new language |
| `/admin/languages/{code}` | `PUT` | Admin | Admin | `settings.update` | Update language |
| `/admin/languages/{code}/activate` | `PATCH` | Admin | Admin | `settings.update` | Activate language |
| `/admin/languages/{code}/deactivate` | `PATCH` | Admin | Admin | `settings.update` | Deactivate language |

**Filterable**: `is_active`, `rtl`  
**Sortable**: `name`, `code`

---

### 4. Settings

**Module**: Core  
**Surface**: Admin Dashboard only  
**Base URL**: `/api/v1/admin/settings`

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/settings` | `GET` | Admin | `settings.view` | Returns all system settings as key-value |
| `/admin/settings` | `PUT` | Admin | `settings.update` | Bulk update settings |
| `/admin/settings/{key}` | `PATCH` | Admin | `settings.update` | Update single setting |

**No pagination** — settings is a finite flat structure.

---

### 5. Users

**Module**: Core  
**Surface**: Admin Dashboard only  
**Base URL**: `/api/v1/admin/users`

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/users` | `GET` | Admin | `users.view` | Paginated list |
| `/admin/users` | `POST` | Admin | `users.create` | Create admin user |
| `/admin/users/{id}` | `GET` | Admin | `users.view` | Single user detail |
| `/admin/users/{id}` | `PUT` | Admin | `users.update` | Update user |
| `/admin/users/{id}` | `DELETE` | Admin | `users.delete` | Soft delete |
| `/admin/users/{id}/restore` | `PATCH` | Admin | `users.restore` | Restore soft-deleted |
| `/admin/users/{id}/force-delete` | `DELETE` | Admin | `users.force-delete` | Permanent delete |
| `/admin/users/{id}/roles` | `PATCH` | Admin | `users.update` | Assign / sync roles |

**Searchable**: `name`, `email`  
**Filterable**: `type`, `is_active`, `role`  
**Sortable**: `name`, `email`, `created_at`  
**Pagination**: cursor-based

---

### 6. Roles & Permissions

**Module**: Core  
**Surface**: Admin Dashboard only

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/roles` | `GET` | Admin | `users.view` | List all roles |
| `/admin/roles` | `POST` | Admin | `users.create` | Create role |
| `/admin/roles/{id}` | `GET` | Admin | `users.view` | Role detail with permissions |
| `/admin/roles/{id}` | `PUT` | Admin | `users.update` | Update role |
| `/admin/roles/{id}` | `DELETE` | Admin | `users.delete` | Delete role |
| `/admin/roles/{id}/permissions` | `PUT` | Admin | `users.update` | Sync permissions to role |
| `/admin/permissions` | `GET` | Admin | `users.view` | All permissions grouped by module |

**Special**: `/admin/permissions` always returns permissions **grouped by module name** (ADR-001 requirement).

---

### 7. Countries

**Module**: Countries  
**Surface**: Public Portal (read) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/countries` | `GET` | No | Public | — | Active countries only |
| `/admin/countries` | `GET` | Admin | Admin | `settings.view` | All countries |
| `/admin/countries` | `POST` | Admin | Admin | `settings.update` | Add country |
| `/admin/countries/{id}` | `PUT` | Admin | Admin | `settings.update` | Update |
| `/admin/countries/{id}/activate` | `PATCH` | Admin | Admin | `settings.update` | Activate |
| `/admin/countries/{id}/deactivate` | `PATCH` | Admin | Admin | `settings.update` | Deactivate |

**Searchable**: `name` (ISO name + native name)  
**Filterable**: `is_active`, `region`  
**Sortable**: `name`, `iso_code`  
**Pagination**: offset-based (fixed reference data)

---

### 8. Contestants

**Module**: Contestants  
**Surface**: Public Portal (own profile) + Admin Dashboard (manage all)

#### Contestant Portal Endpoints (type=user)

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/contestant/profile` | `GET` | Contestant | Own profile |
| `/contestant/profile` | `PUT` | Contestant | Update own profile |
| `/contestant/profile/photo` | `POST` | Contestant | Upload profile photo |
| `/contestant/profile/documents` | `GET` | Contestant | Own documents |

#### Admin Endpoints

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/contestants` | `GET` | Admin | `contestants.view` | Paginated list |
| `/admin/contestants/{id}` | `GET` | Admin | `contestants.view` | Detail |
| `/admin/contestants/{id}` | `PUT` | Admin | `contestants.update` | Update |
| `/admin/contestants/{id}/suspend` | `PATCH` | Admin | `contestants.update` | Suspend account |
| `/admin/contestants/{id}/reinstate` | `PATCH` | Admin | `contestants.update` | Reinstate |

**Searchable**: `name`, `email`, `country`  
**Filterable**: `country_id`, `is_active`, `has_application`  
**Sortable**: `name`, `country`, `registered_at`  
**Pagination**: cursor-based  
**File Uploads**: profile photo (image), passport scan (document) — via Media module  
**Events**: `ContestantRegistered`, `ContestantProfileCompleted`, `ContestantProfileUpdated`

---

### 9. Applications

**Module**: Applications  
**Surface**: Public Portal (own application) + Admin Dashboard (manage all)

#### Application State Machine

```
Received → Under Review → Under Evaluation → Accepted
                       ↘ Rejected
                       ↘ Needs Data (→ Received again)
                       ↘ Re-upload Video (→ Under Review again)
```

#### Contestant Portal Endpoints

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/contestant/application` | `GET` | Contestant | Own application status |
| `/contestant/application` | `POST` | Contestant | Submit application (idempotent per season) |
| `/contestant/application` | `PUT` | Contestant | Update application (if status permits) |
| `/contestant/application/documents` | `POST` | Contestant | Upload required documents |
| `/contestant/application/video` | `POST` | Contestant | Upload recitation video |
| `/contestant/application/status` | `GET` | Contestant | Status history / timeline |
| `/contestant/application/notifications` | `GET` | Contestant | Notifications related to application |

#### Admin Endpoints

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/applications` | `GET` | Admin | `applications.view` | Paginated list with filters |
| `/admin/applications/{id}` | `GET` | Admin | `applications.view` | Full detail |
| `/admin/applications/{id}/review` | `PATCH` | Admin | `applications.review` | Mark as Under Review |
| `/admin/applications/{id}/evaluate` | `PATCH` | Admin | `applications.evaluate` | Mark as Under Evaluation |
| `/admin/applications/{id}/approve` | `PATCH` | Admin | `applications.approve` | Approve application |
| `/admin/applications/{id}/reject` | `PATCH` | Admin | `applications.reject` | Reject with reason |
| `/admin/applications/{id}/needs-data` | `PATCH` | Admin | `applications.review` | Request additional data |
| `/admin/applications/{id}/request-video` | `PATCH` | Admin | `applications.review` | Request video re-upload |
| `/admin/applications/{id}/documents` | `GET` | Admin | `applications.view` | View attached documents |
| `/admin/applications/{id}/history` | `GET` | Admin | `applications.view` | Full status history |

**Searchable**: `contestant name`, `email`, `country`  
**Filterable**: `status`, `season_id`, `country_id`, `has_video`, `submitted_at` (date range)  
**Sortable**: `submitted_at`, `contestant_name`, `status`, `country`  
**Pagination**: cursor-based  
**File Uploads**: documents (PDF/image), recitation video (via Videos module)  
**Events**: `ApplicationReceived`, `ApplicationUnderReview`, `ApplicationUnderEvaluation`, `ApplicationApproved`, `ApplicationRejected`, `ApplicationNeedsData`, `VideoReUploadRequested`

---

### 10. Judges

**Module**: Judges  
**Surface**: Public Portal (public list) + Admin Dashboard (manage)

#### Public Portal Endpoints

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/judges` | `GET` | No | Public judge panel list (active judges only) |
| `/judges/{id}` | `GET` | No | Judge public profile |

#### Admin Endpoints

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/judges` | `GET` | Admin | `judges.view` | Paginated list |
| `/admin/judges` | `POST` | Admin | `judges.create` | Create judge (links to admin user) |
| `/admin/judges/{id}` | `GET` | Admin | `judges.view` | Full detail |
| `/admin/judges/{id}` | `PUT` | Admin | `judges.update` | Update profile |
| `/admin/judges/{id}` | `DELETE` | Admin | `judges.delete` | Deactivate |
| `/admin/judges/{id}/photo` | `POST` | Admin | `judges.update` | Upload judge photo |
| `/admin/judges/{id}/stages` | `GET` | Admin | `judges.view` | Stages assigned to judge |

**Searchable**: `name`, `country`, `specialization`  
**Filterable**: `country_id`, `is_active`, `stage_id`  
**Sortable**: `name`, `country`  
**Pagination**: cursor-based  
**File Uploads**: judge photo (via Media)  
**Events**: `JudgeAssignedToStage`, `JudgeRemovedFromStage`

---

### 11. Seasons

**Module**: Competition  
**Sub-domain**: Seasons  
**Surface**: Public Portal (archive) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/seasons` | `GET` | No | Public | — | Active + archived seasons |
| `/seasons/{id}` | `GET` | No | Public | — | Season results + stages |
| `/admin/seasons` | `GET` | Admin | Admin | `competition.view` | All seasons |
| `/admin/seasons` | `POST` | Admin | Admin | `competition.seasons` | Create season |
| `/admin/seasons/{id}` | `GET` | Admin | Admin | `competition.view` | Detail |
| `/admin/seasons/{id}` | `PUT` | Admin | Admin | `competition.seasons` | Update |
| `/admin/seasons/{id}/activate` | `PATCH` | Admin | Admin | `competition.seasons` | Set as active season |
| `/admin/seasons/{id}/close` | `PATCH` | Admin | Admin | `competition.seasons` | Close season |
| `/admin/seasons/{id}/archive` | `PATCH` | Admin | Admin | `competition.seasons` | Archive to history |
| `/admin/seasons/{id}/results` | `GET` | Admin | Admin | `competition.results` | Full results for season |

**Pagination**: offset-based (seasons are finite)  
**Sortable**: `year`, `created_at`  
**Events**: `SeasonCreated`, `SeasonActivated`, `SeasonClosed`

---

### 12. Stages

**Module**: Competition  
**Sub-domain**: Stages + Scheduling  
**Surface**: Public Portal (schedule view) + Admin Dashboard (manage)

#### Public Portal

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/seasons/{seasonId}/stages` | `GET` | No | Public stage list with schedule |
| `/seasons/{seasonId}/stages/{id}` | `GET` | No | Stage detail + results (if published) |

#### Admin Dashboard

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/seasons/{seasonId}/stages` | `GET` | Admin | `competition.view` | All stages in season |
| `/admin/seasons/{seasonId}/stages` | `POST` | Admin | `competition.stages` | Create stage |
| `/admin/seasons/{seasonId}/stages/{id}` | `PUT` | Admin | `competition.stages` | Update stage |
| `/admin/seasons/{seasonId}/stages/{id}/start` | `PATCH` | Admin | `competition.stages` | Mark stage as started |
| `/admin/seasons/{seasonId}/stages/{id}/complete` | `PATCH` | Admin | `competition.stages` | Mark stage as complete |
| `/admin/seasons/{seasonId}/stages/{id}/publish-results` | `PATCH` | Admin | `competition.results` | Publish official results |
| `/admin/seasons/{seasonId}/stages/{id}/judges` | `GET` | Admin | `competition.view` | Judge panel for stage |
| `/admin/seasons/{seasonId}/stages/{id}/judges` | `PUT` | Admin | `competition.stages` | Sync judge assignments |
| `/admin/seasons/{seasonId}/stages/{id}/contestants` | `GET` | Admin | `competition.view` | Contestants in stage |

**Pagination**: offset-based  
**Sortable**: `order`, `scheduled_at`  
**Events**: `StageScheduled`, `StageStarted`, `StageCompleted`, `StageResultsPublished`, `JudgeAssignedToStage`

---

### 13. Evaluations

**Module**: Evaluations  
**Surface**: Admin Dashboard — Judge-facing only

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/evaluations` | `GET` | Admin (Judge) | `evaluations.view` | Judge's own evaluation queue |
| `/admin/evaluations/{id}` | `GET` | Admin (Judge) | `evaluations.view` | Single evaluation detail |
| `/admin/evaluations/{id}/scores` | `PUT` | Admin (Judge) | `evaluations.score` | Submit / update criterion scores |
| `/admin/evaluations/{id}/notes` | `PATCH` | Admin (Judge) | `evaluations.score` | Add / update judge notes |
| `/admin/evaluations/{id}/submit` | `PATCH` | Admin (Judge) | `evaluations.score` | Mark evaluation as completed |
| `/admin/evaluations/{id}/approve` | `PATCH` | Admin | `evaluations.approve` | Approve completed evaluation |
| `/admin/evaluations` | `GET` | Admin (Manager) | `evaluations.manage` | All evaluations (all judges) |
| `/admin/scoring-rubric` | `GET` | Admin | `evaluations.view` | Current scoring rubric |
| `/admin/scoring-rubric` | `PUT` | Admin | `evaluations.manage` | Update rubric |

**Filterable**: `stage_id`, `judge_id`, `status` (`pending`, `in_progress`, `completed`, `approved`)  
**Sortable**: `submitted_at`, `score`, `contestant_name`  
**Pagination**: cursor-based  
**Events**: `EvaluationStarted`, `EvaluationCompleted`, `EvaluationApproved`, `ScoreUpdated`

---

### 14. Videos

**Module**: Videos  
**Surface**: Public Portal (gallery) + Contestant Portal (own video) + Admin Dashboard (manage)

#### Public Portal

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/videos` | `GET` | No | Published video gallery (paginated) |
| `/videos/{id}` | `GET` | No | Single video with playback metadata |

#### Contestant Portal

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/contestant/videos` | `GET` | Contestant | Own uploaded videos |
| `/contestant/videos/{id}/status` | `GET` | Contestant | Processing status |

#### Admin Dashboard

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/videos` | `GET` | Admin | `videos.view` | All videos (all statuses) |
| `/admin/videos/{id}` | `GET` | Admin | `videos.view` | Full detail + processing log |
| `/admin/videos/{id}` | `PUT` | Admin | `videos.update` | Update metadata |
| `/admin/videos/{id}/publish` | `PATCH` | Admin | `videos.publish` | Publish to gallery |
| `/admin/videos/{id}/reject` | `PATCH` | Admin | `videos.delete` | Reject / unpublish |
| `/admin/videos/{id}/reprocess` | `PATCH` | Admin | `videos.update` | Trigger FFmpeg reprocessing |
| `/admin/videos/{id}` | `DELETE` | Admin | `videos.delete` | Soft delete |

**Searchable**: `title`, `contestant_name`  
**Filterable**: `status` (`uploaded`, `processing`, `processed`, `published`, `rejected`), `season_id`, `stage_id`  
**Sortable**: `created_at`, `duration`, `contestant_name`  
**Pagination**: cursor-based  
**File Upload**: video file — chunked upload (large files 3–5 min video)  
**Events**: `VideoUploaded`, `VideoProcessingStarted`, `VideoProcessingCompleted`, `VideoProcessingFailed`, `VideoPublished`, `VideoRejected`

---

### 15. Media (Generic Files)

**Module**: Media  
**Surface**: Internal platform service — exposed via other modules  
**Direct Admin Endpoints** (for admin file library)

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/media` | `GET` | Admin | `users.view` | Browse uploaded media |
| `/admin/media` | `POST` | Admin | `content.create` | Upload file |
| `/admin/media/{id}` | `DELETE` | Admin | `content.delete` | Delete file |

**No public direct endpoints** — media is consumed via module-specific endpoints.  
**Pagination**: cursor-based  
**File types**: images (JPEG, PNG, WebP), documents (PDF), videos (delegated to Videos module)  
**Events**: `MediaUploaded`, `MediaDeleted`

---

### 16. Live Stream

**Module**: Streaming  
**Surface**: Public Portal (view) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/stream` | `GET` | No | Public | — | Current stream status + embed data |
| `/admin/stream` | `GET` | Admin | Admin | `streaming.view` | Stream configuration |
| `/admin/stream` | `PUT` | Admin | Admin | `streaming.manage` | Update stream config |
| `/admin/stream/start` | `PATCH` | Admin | Admin | `streaming.manage` | Start stream |
| `/admin/stream/stop` | `PATCH` | Admin | Admin | `streaming.manage` | Stop stream |
| `/admin/stream/source` | `PATCH` | Admin | Admin | `streaming.manage` | Change stream source |

**No pagination**  
**Events**: `StreamStarted`, `StreamStopped`, `StreamSourceChanged`  
**Supported protocols**: RTMP, HLS, DASH

---

### 17. Pages

**Module**: Content  
**Surface**: Public Portal (read) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/pages/{slug}` | `GET` | No | Public | — | Page content by slug |
| `/admin/pages` | `GET` | Admin | Admin | `content.view` | All pages |
| `/admin/pages` | `POST` | Admin | Admin | `content.create` | Create page |
| `/admin/pages/{id}` | `GET` | Admin | Admin | `content.view` | Page detail |
| `/admin/pages/{id}` | `PUT` | Admin | Admin | `content.update` | Update page |
| `/admin/pages/{id}/publish` | `PATCH` | Admin | Admin | `content.publish` | Publish |
| `/admin/pages/{id}/unpublish` | `PATCH` | Admin | Admin | `content.publish` | Unpublish |

**Translatable fields**: `title`, `body`, `meta_description`  
**No public listing** — pages are accessed by known slug  
**Events**: `PageUpdated`

---

### 18. Announcements

**Module**: Content  
**Surface**: Public Portal (read) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/announcements` | `GET` | No | Public | — | Published announcements only |
| `/announcements/{id}` | `GET` | No | Public | — | Single announcement |
| `/admin/announcements` | `GET` | Admin | Admin | `content.view` | All (incl. drafts, scheduled) |
| `/admin/announcements` | `POST` | Admin | Admin | `content.create` | Create |
| `/admin/announcements/{id}` | `PUT` | Admin | Admin | `content.update` | Update |
| `/admin/announcements/{id}/publish` | `PATCH` | Admin | Admin | `content.publish` | Publish immediately |
| `/admin/announcements/{id}/schedule` | `PATCH` | Admin | Admin | `content.publish` | Schedule for future publish |
| `/admin/announcements/{id}/unpublish` | `PATCH` | Admin | Admin | `content.publish` | Unpublish |
| `/admin/announcements/{id}` | `DELETE` | Admin | Admin | `content.delete` | Delete |

**Searchable**: `title`, `body`  
**Filterable**: `status`, `published_at` (date range)  
**Sortable**: `published_at`, `created_at`  
**Pagination**: offset-based  
**Events**: `AnnouncementPublished`

---

### 19. FAQ

**Module**: Content  
**Surface**: Public Portal (read) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/faq` | `GET` | No | Public | — | All active FAQs, ordered |
| `/admin/faq` | `GET` | Admin | Admin | `content.view` | All FAQs |
| `/admin/faq` | `POST` | Admin | Admin | `content.create` | Create |
| `/admin/faq/{id}` | `PUT` | Admin | Admin | `content.update` | Update |
| `/admin/faq/{id}` | `DELETE` | Admin | Admin | `content.delete` | Delete |
| `/admin/faq/reorder` | `PATCH` | Admin | Admin | `content.update` | Bulk reorder |

**Translatable fields**: `question`, `answer`  
**No search / No pagination** — FAQs are a small, ordered list

---

### 20. Sponsors

**Module**: Sponsors  
**Surface**: Public Portal (read) + Admin Dashboard (manage)

| Endpoint | Method | Auth | Surface | Permission | Notes |
|---|---|---|---|---|---|
| `/sponsors` | `GET` | No | Public | — | Active sponsors, ordered by display_order |
| `/admin/sponsors` | `GET` | Admin | Admin | `sponsors.view` | All sponsors |
| `/admin/sponsors` | `POST` | Admin | Admin | `sponsors.create` | Create |
| `/admin/sponsors/{id}` | `PUT` | Admin | Admin | `sponsors.update` | Update |
| `/admin/sponsors/{id}` | `DELETE` | Admin | Admin | `sponsors.delete` | Delete |
| `/admin/sponsors/{id}/activate` | `PATCH` | Admin | Admin | `sponsors.update` | Activate |
| `/admin/sponsors/{id}/deactivate` | `PATCH` | Admin | Admin | `sponsors.update` | Deactivate |
| `/admin/sponsors/reorder` | `PATCH` | Admin | Admin | `sponsors.update` | Bulk reorder |
| `/admin/sponsors/{id}/logo` | `POST` | Admin | Admin | `sponsors.update` | Upload logo |

**No search**  
**Pagination**: none (small dataset with display_order)  
**File Uploads**: logo (image via Media)

---

### 21. Notifications

**Module**: Notifications  
**Surface**: Contestant Portal + Admin Dashboard

#### Contestant Portal

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/contestant/notifications` | `GET` | Contestant | Own notification inbox |
| `/contestant/notifications/{id}/read` | `PATCH` | Contestant | Mark as read |
| `/contestant/notifications/read-all` | `PATCH` | Contestant | Mark all as read |
| `/contestant/notifications/{id}` | `DELETE` | Contestant | Delete notification |

#### Admin Dashboard

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/notifications` | `GET` | Admin | `users.view` | Admin's own notifications |
| `/admin/notifications/{id}/read` | `PATCH` | Admin | — | Mark own as read |
| `/admin/notification-templates` | `GET` | Admin | `settings.view` | All notification templates |
| `/admin/notification-templates/{id}` | `PUT` | Admin | `settings.update` | Update template content |
| `/admin/notifications/broadcast` | `POST` | Admin | `settings.update` | Broadcast manual notification |

**Pagination**: cursor-based (inbox-style, newest first)  
**Filterable**: `is_read`, `type`

---

### 22. Reports & Statistics

**Module**: Reports  
**Surface**: Admin Dashboard only

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/admin/reports/dashboard` | `GET` | Admin | `reports.view` | Aggregated KPI dashboard |
| `/admin/reports/participants` | `GET` | Admin | `reports.view` | Participant statistics |
| `/admin/reports/participants/by-country` | `GET` | Admin | `reports.view` | Country breakdown |
| `/admin/reports/applications` | `GET` | Admin | `reports.view` | Application status breakdown |
| `/admin/reports/judges` | `GET` | Admin | `reports.view` | Judge performance report |
| `/admin/reports/evaluations` | `GET` | Admin | `reports.view` | Evaluation completion + scores |
| `/admin/reports/videos` | `GET` | Admin | `reports.view` | Video processing stats + viewership |
| `/admin/reports/streaming` | `GET` | Admin | `reports.view` | Stream viewership statistics |
| `/admin/reports/seasons/{id}` | `GET` | Admin | `reports.view` | Full historical season archive |
| `/admin/reports/dashboard/export` | `POST` | Admin | `reports.export` | Export dashboard to PDF/CSV |
| `/admin/reports/participants/export` | `POST` | Admin | `reports.export` | Export participant data |

**Filterable**: `season_id`, `stage_id`, `country_id`, `date_from`, `date_to`  
**No pagination** — reports return complete datasets (with export for large data)  
**Events consumed** (read-side only, no events published)

---

## Cross-Cutting API Concerns (Feed into ADR-004)

### Authentication Boundaries

| Token Scope | Issuer | Valid Surfaces | Invalid Surfaces |
|---|---|---|---|
| `type=user` Bearer | Social OAuth callback | `/api/v1/contestant/*`, `/api/v1/` public | `/api/v1/admin/*` |
| `type=admin` Bearer | Admin login | `/api/v1/admin/*` | `/api/v1/contestant/*` |

### Locale Negotiation

All endpoints resolve locale from:
1. `Accept-Language` HTTP header (BCP-47 format)
2. `?lang=ar` query parameter override
3. Default: `ar` (Arabic)

Translatable fields in responses are returned in the resolved locale. RTL flag (`rtl: true/false`) is included in language responses to guide frontend layout.

### File Upload Patterns

| Upload Type | Mechanism | Max Size | Accepted Types | Module |
|---|---|---|---|---|
| Profile photo | Multipart single file | 5 MB | JPEG, PNG, WebP | Contestants → Media |
| Judge photo | Multipart single file | 5 MB | JPEG, PNG, WebP | Judges → Media |
| Passport / Document | Multipart single file | 10 MB | PDF, JPEG, PNG | Applications → Media |
| Recitation video | Chunked multipart | 2 GB | MP4, MOV, AVI | Applications → Videos |
| Page / Announcement image | Multipart single file | 10 MB | JPEG, PNG, WebP | Content → Media |
| Sponsor logo | Multipart single file | 5 MB | JPEG, PNG, WebP, SVG | Sponsors → Media |

### Endpoints Requiring Idempotency

| Endpoint | Reason |
|---|---|
| `POST /contestant/application` | One application per contestant per season — idempotent by business rule |
| `POST /contestant/application/video` | Video re-uploads should not create duplicate processing jobs |
| `PATCH /admin/applications/{id}/approve` | Approving twice must not trigger double notifications |
| `PATCH /admin/applications/{id}/reject` | Same as approve |
| `POST /admin/notifications/broadcast` | Broadcast should not be sent twice on retry |

### Endpoints Requiring Pagination

| Strategy | Applied To |
|---|---|
| **Cursor-based** | Contestants, Applications, Videos, Evaluations, Notifications (high-volume, real-time) |
| **Offset-based** | Users, Countries, Seasons, Stages, Announcements (moderate volume, exportable) |
| **None** | FAQ, Stream, Settings, Permissions, Reports dashboard |

### Search-Enabled Endpoints

| Endpoint | Searchable Fields | Index Owner |
|---|---|---|
| `/admin/users` | name, email | Core → Search |
| `/admin/contestants` | name, email, country | Contestants → Search |
| `/admin/applications` | contestant name, email, country | Applications → Search |
| `/admin/videos` | title, contestant name | Videos → Search |
| `/admin/judges` | name, country, specialization | Judges → Search |
| `/admin/announcements` | title, body | Content → Search |
| `/countries` | name, native_name | Countries → Search |
