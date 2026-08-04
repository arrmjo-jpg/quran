# ADR-006: Infrastructure Architecture

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-006                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-003 · ADR-004 · ADR-005 · DATABASE-DOMAIN-INVENTORY · ENTITY-RELATIONSHIP-MAP                          |

---

## Status

**Accepted** — This document is the constitutional reference governing all infrastructure, deployment pipelines, caching strategies, CDN rules, storage drivers, background queues, video processing pipelines, search engines, observability tools, backup procedures, performance tuning parameters, environment parity rules, scaling topologies, and package governance for the platform.

---

## Context

### Why This ADR Exists

ADR-001 established the Modular Monolith architecture, API-First contract, and Docker-First deployment goal. ADR-002 defined the 15 module boundaries. ADR-003 specified the dual authentication experience. ADR-004 established the API constitution. ADR-005 established the 21 database architecture decisions.

None of those documents defined the **underlying infrastructure environment** — how the application containers are built and deployed, how Redis caches and queues are segregated, how Cloudflare CDN caches and purges assets, how 2GB recitation videos are stored and processed via FFmpeg, how Meilisearch indexes are synchronized, how Laravel Horizon manages priority queues, how backups and PITR recovery are executed, how performance is tuned, or which third-party Composer packages are permitted vs. forbidden.

Without a single, governing Infrastructure ADR, the following failure modes are inevitable:

- Inconsistent environment configuration causes "works on my machine" bugs between local, staging, and production.
- Unmonitored Redis queue backlogs cause video processing jobs or notification dispatches to hang without alerts.
- Ad-hoc choice of Composer packages introduces banned dependencies (e.g. Passport, JWT, or competing ORMs) that violate ADR-001 and ADR-003.
- Unconstrained FFmpeg video processing starves host CPU resources and causes HTTP API timeouts.
- Direct public exposure of raw video files or contestant passports violates privacy and storage security requirements.
- Lack of CDN cache-invalidation rules leads to stale public content or over-billing from origin hits.
- Ad-hoc backup routines fail when a disaster recovery situation actually occurs.

This ADR eliminates these failure modes by locking in binding infrastructure decisions before module implementation begins.

---

## Architectural Principles

### Principle 1: Infrastructure as Code & Container Parity
Every service required by the platform (PHP-FPM, Nginx, Redis, Meilisearch, Horizon, Worker, FFmpeg) must be defined as containerized infrastructure. Local, testing, staging, and production environments use identical container definitions and PHP configurations.

### Principle 2: Zero Egress Storage & CDN Offloading
Origin servers never serve public media or static assets directly to end users. All public media (processed MP4s, HLS streams, thumbnails, images) is offloaded to Cloudflare R2 object storage and delivered exclusively via Cloudflare CDN edge servers.

### Principle 3: Queue Isolation & Backpressure Management
High-throughput or resource-intensive tasks (FFmpeg transcoding, video assembly, bulk notification dispatch) must run on isolated queue workers with strict process resource limits. Heavy background jobs must never degrade HTTP API response times.

### Principle 4: At-Least-Once Delivery & Failure Observability
Background queue jobs, outbox polling workers, and search index syncers must operate under at-least-once delivery guarantees with explicit retry policies, dead-letter tracking, and real-time alert triggers.

### Principle 5: Package Minimalism & Governance
Third-party packages are admitted only after architectural review against the Approved Packages Registry. Packages that introduce competing paradigms (e.g., GraphQL libraries, alternative ORMs, or legacy auth drivers) are explicitly forbidden.

---

## Formal Decisions

---

### PART I — DEPLOYMENT & CONTAINER ARCHITECTURE

#### Decision 1: Deployment Architecture & Container Topology

##### 1.1 Container Specification
The application runs on Docker containers built from a multi-stage `Dockerfile` based on `php:8.4-fpm-alpine`.

**Included PHP Extensions**: `pdo_mysql`, `redis`, `opcache`, `gd`, `intl`, `zip`, `bcmath`, `pcntl`, `exif`.

##### 1.2 Deployment Platform: Coolify & Docker Compose
- **Platform Manager**: **Coolify** (Self-hosted PaaS) is used to manage container deployments, environment variables, SSL certificates, and webhooks in staging and production.
- **Production Stack (Compose Topology)**:
  - `web`: Nginx 1.26+ reverse proxy (handles SSL termination, static files, HTTP caching, proxying to `app`).
  - `app`: PHP-FPM 8.4 container (executes Laravel code).
  - `horizon`: PHP 8.4 CLI container running `php artisan horizon` (queue worker daemon).
  - `video-worker`: PHP 8.4 CLI container with `ffmpeg` + `ffprobe` installed (isolated queue worker for `video` queue).
  - `outbox-worker`: Lightweight PHP CLI worker running outbox event dispatcher daemon.
  - `meilisearch`: Meilisearch 1.x container.
  - `redis`: Redis 7.x container (configured with dual database logical separation).

##### 1.3 Reverse Proxy & Web Server: Nginx
Nginx acts as the edge reverse proxy on the host:
- Proxies requests to `app:9000` via FastCGI socket.
- Handles static asset serving (`/storage`, `/build`) directly with long cache headers.
- Restricts upload body size (`client_max_body_size 100M` for standard routes, `2000M` for chunked upload session endpoint).

##### 1.4 Automated CI/CD Pipeline
- **CI Tool**: GitHub Actions.
- **Pipeline Workflow**:
  1. `lint`: Runs `vendor/bin/pint --test` (Pint code style check).
  2. `analyze`: Runs `vendor/bin/phpstan analyse` (Larastan Level 8 check).
  3. `test`: Runs `vendor/bin/pest` (Pest test suite against MySQL/Redis test containers).
  4. `build`: Builds production Docker image and pushes to private container registry.
  5. `deploy`: Triggers Coolify webhook to deploy new image tag with zero-downtime container replacement (`docker rollout`).

---

### PART II — CACHING & CDN STRATEGY

#### Decision 2: Multi-Tier Caching Architecture

##### 2.1 Storage Layer: Redis
Redis is the platform's central in-memory store, logically partitioned by database index:
- `DB 0`: Application Cache (`Cache::class`).
- `DB 1`: Queue & Horizon (`Queue::class`, `Horizon::class`).
- `DB 2`: Session Store (`type=admin` session tokens / rate limiting states).

##### 2.2 Cache Tagging & Namespacing
All cached items must use namespaced keys following the format:
`{module}:{entity}:{id}:{variant}`

Example: `contestants:profile:01927f3a-abc1:full`

Modules use Redis Cache Tags for group invalidation:
```php
Cache::tags(['competition', 'seasons'])->put('season:current', $seasonData, 86400);
```

##### 2.3 Cache Invalidation Policy
- **Event-Driven Invalidation**: Domain events trigger cache purge listeners. (Example: `SeasonActivated` event purges `Cache::tags(['seasons'])->flush()`).
- **No Infinite TTL**: Every cached item must specify an explicit TTL. Default TTL: 3600 seconds (1 hour). Static reference data TTL: 86400 seconds (24 hours).

##### 2.4 Production Framework Caching
Production deployment scripts **must** execute:
```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

#### Decision 3: Cloudflare CDN & Edge Caching Strategy

##### 3.1 CDN Provider & Zone Strategy
All domain traffic is proxied through **Cloudflare CDN** (Proxy Mode `orange-clouded`).

##### 3.2 Cache Rules per Route Surface

| Route Pattern | Cache Rule | Cache-Control Header | Edge TTL |
|---|---|---|---|
| `/api/v1/contestant/*` | Bypass Cache | `no-cache, no-store, private` | 0s |
| `/api/v1/admin/*` | Bypass Cache | `no-cache, no-store, private` | 0s |
| `/api/v1/stream` | Edge Cache | `public, max-age=15, s-maxage=30` | 30s |
| `/api/v1/pages/*` | Edge Cache | `public, max-age=3600, s-maxage=86400` | 24h |
| `/api/v1/announcements/*` | Edge Cache | `public, max-age=1800, s-maxage=7200` | 2h |
| `/api/v1/faqs` | Edge Cache | `public, max-age=86400, s-maxage=604800` | 7d |
| `/media/public/*` (R2) | Aggressive Cache | `public, max-age=31536000, immutable` | 1 year |

##### 3.3 CDN Cache Purge Automation
When public CMS content is published or updated (e.g. `AnnouncementPublished`, `PageUpdated`), the backend dispatches a background job that invokes the Cloudflare Purge API to clear the specific URL tag from edge cache instantly.

---

### PART III — MEDIA & STORAGE ARCHITECTURE

#### Decision 4: Media Storage Architecture & Cloudflare R2

##### 4.1 Storage Abstraction
File storage is managed through Laravel's `Filesystem` abstraction using the `league/flysystem-aws-s3-v3` driver.

##### 4.2 Bucket Topology (Cloudflare R2)
Cloudflare R2 is chosen as the cloud object storage provider due to **zero egress fees** for high-volume video delivery.

```
quran-media-private/         ← Private Bucket (No Public Access)
├── contestants/documents/
└── videos/originals/

quran-media-public/          ← Public Bucket (CDN Proxied via Custom Domain)
├── media/photos/
├── media/logos/
├── videos/processed/
├── videos/thumbnails/
└── videos/hls/
```

##### 4.3 Storage Location by Environment

| Environment | Private Storage Driver | Public Storage Driver |
|---|---|---|
| `local` | `local` (`storage/app/private`) | `public` (`storage/app/public`) |
| `testing` | `array` (in-memory mock) | `array` (in-memory mock) |
| `staging` | Cloudflare R2 (staging buckets) | Cloudflare R2 (staging buckets) |
| `production` | Cloudflare R2 (`quran-media-private`) | Cloudflare R2 (`quran-media-public`) |

##### 4.4 Signed URL Security Protocol
Original video files and contestant identity documents in `quran-media-private` are accessible **only** via temporary time-limited HMAC Signed URLs generated by `Storage::temporaryUrl()`.
- Maximum Signed URL TTL: **60 minutes**.
- Direct public HTTP access to the private bucket is disabled at the Cloudflare R2 bucket policy level.

---

### PART IV — QUEUE & WORKER ARCHITECTURE

#### Decision 5: Queue Architecture, Priorities & Laravel Horizon

##### 5.1 Queue Driver & Monitoring
Background queue operations use Redis (`DB 1`) as the transport layer and **Laravel Horizon** for process management, queue balancing, failure metrics, and dashboard monitoring.

##### 5.2 Queue Taxonomy & Priority Registry

| Queue Name | Responsibilities | Priority | Min Workers | Max Workers | Timeout |
|---|---|---|:---:|:---:|:---:|
| `high` | Outbox event dispatch, urgent state-change notifications, auth emails | 1 (Highest) | 3 | 10 | 30s |
| `default` | General background jobs, audit logging, analytics aggregation | 2 | 2 | 6 | 60s |
| `notifications` | In-app notifications, transactional emails, SMS dispatches | 3 | 2 | 8 | 60s |
| `search` | Meilisearch index imports and record updates | 4 | 1 | 4 | 120s |
| `emails` | Bulk announcement broadcast emails, marketing lists | 5 | 1 | 4 | 300s |
| `video` | Video chunk assembly, FFmpeg transcoding, HLS generation | Isolated | 1 | 2 | 1800s (30m) |

##### 5.3 Horizon Balance Strategy
In staging and production, Horizon uses `balance: auto` with `autoScaleMaxProcs: 20`. Horizon automatically allocates worker processes to queues experiencing backlog growth.

##### 5.4 Failed Job Policy & Dead Letter Queue (DLQ)
- Failed jobs are written to the `failed_jobs` table in MySQL (`CHAR(36)` UUID PK).
- Default retry attempts: `3` (except `video` queue which permits `2` retries).
- Backoff strategy: Exponential backoff `[10, 60, 300]` seconds.
- Notifications: Any job failing permanently triggers a notification to the engineering Slack/alert channel via Monolog.

---

### PART V — VIDEO PROCESSING & SEARCH ARCHITECTURE

#### Decision 6: Video Processing Architecture (FFmpeg / HLS)

##### 6.1 Container Isolation
FFmpeg operations execute inside an **isolated container worker** (`video-worker`). The main HTTP API container (`app`) never invokes FFmpeg directly.

##### 6.2 Transcoding Specifications

```
Original Video Upload (MP4 / MOV / AVI up to 2GB)
       │
       ▼
FFmpeg Transcoding Pipeline
       ├── 360p Variant  (600 kbps video, 96 kbps audio, AAC)
       ├── 720p Variant  (1500 kbps video, 128 kbps audio, AAC)
       ├── 1080p Variant (3000 kbps video, 192 kbps audio, AAC)
       ├── Poster Frame   (Extraction at 00:00:05 → 480x270 JPEG & 1280x720 JPEG)
       └── HLS Generation (Master m3u8 + variant playlists + 6s TS segments)
```

##### 6.3 Process Protection & CPU Throttling
- FFmpeg commands run with `nice -n 19` (lowest CPU scheduling priority).
- Threads restricted to `-threads 2` per FFmpeg execution to prevent CPU starvation.
- Video worker container memory limit: `4GB RAM`.

#### Decision 7: Search Architecture (Laravel Scout + Meilisearch)

##### 7.1 Search Stack
Full-text search is powered by **Laravel Scout** with the **Meilisearch** driver (`meilisearch/meilisearch-php`).

##### 7.2 Index Registry & Searchable Models

| Index Name | Model | Searchable Attributes | Filterable Attributes | Sortable Attributes |
|---|---|---|---|---|
| `users_index` | `User` | `name`, `email` | `type`, `is_active` | `created_at` |
| `contestants_index` | `Contestant` | `name`, `email`, `phone` | `country_id`, `gender`, `is_active` | `created_at` |
| `applications_index` | `Application` | `contestant_name`, `contestant_email` | `status`, `season_id`, `country_id` | `submitted_at` |
| `judges_index` | `Judge` | `name`, `specialization` | `country_id`, `is_active` | `display_order` |
| `videos_index` | `Video` | `contestant_name` | `status`, `published_at` | `published_at` |
| `announcements_index` | `Announcement` | `title`, `body` | `status`, `published_at` | `published_at` |
| `countries_index` | `Country` | `name`, `native_name`, `iso_code` | `is_active` | `display_order` |

##### 7.3 Synchronization Policy
- Scout indexing operates in **asynchronous queue mode** (`SCOUT_QUEUE=true`), utilizing the `search` queue.
- Reindex Policy: Full index rebuild executed via `php artisan scout:import "Modules\..."` during major deployments or index schema changes.

---

### PART VI — OBSERVABILITY, SECURITY & BACKUPS

#### Decision 8: Observability, Logging & Monitoring

##### 8.1 Logging Infrastructure
- **Format**: Structured JSON logging in production.
- **Log Channels**:
  - `single`: Standard daily file log.
  - `stderr`: Structured JSON log to container stdout/stderr for log aggregators.
  - `slack`: Triggers on `CRITICAL` or `EMERGENCY` log levels to alert on-call engineering.

##### 8.2 Local Debugging vs. Production Rule
- **Laravel Telescope**: Permitted **only** in `local` and `testing` environments (`TELESCOPE_ENABLED=false` in staging and production). Telescope package is installed as a dev dependency (`--dev`).

##### 8.3 Health Check Endpoint
A public health check endpoint is exposed at `/api/v1/health`:
- Performs lightweight assertions: MySQL connection, Redis connection, Meilisearch connection, Storage write test.
- Returns `HTTP 200` with JSON status breakdown when healthy; `HTTP 503` when a core dependency fails.

#### Decision 9: Security Infrastructure & Headers

##### 9.1 Rate Limiting Architecture
Rate limiting is backed by Redis (`DB 2`) via Laravel's `RateLimiter` facade. (Limits defined per ADR-004: Public 60/m, Contestant 300/m, Admin 600/m, Auth 10/m).

##### 9.2 Security Headers (Enforced at Nginx Layer)
```nginx
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; media-src 'self' https:;" always;
```

#### Decision 10: Backup, Retention & Disaster Recovery Strategy

##### 10.1 MySQL Backup Schedule & PITR
- **Daily Full Backup**: Automated `mysqldump` script runs daily at 02:00 UTC, compresses output, and uploads to an offsite isolated Cloudflare R2 backup vault (`quran-backups-vault`).
- **Point-In-Time Recovery (PITR)**: MySQL binary logging (`log_bin`) is enabled with 7-day retention. Binary logs are pushed to object storage hourly.

##### 10.2 Retention Schedule
- Daily Backups: Retained for **30 days**.
- Weekly Backups: Retained for **12 weeks**.
- Monthly Backups: Retained for **12 months**.

##### 10.3 Disaster Recovery RPO / RTO Targets
- **Recovery Point Objective (RPO)**: < 1 hour (via binary log PITR).
- **Recovery Time Objective (RTO)**: < 2 hours (full container stack rebuild + DB restore).
- **Automated Restore Testing**: An automated test restore is executed on the 1st of every month in a dedicated staging container sandbox to verify backup integrity.

---

### PART VII — PERFORMANCE, ENVIRONMENT & SCALING

#### Decision 11: Performance Tuning Parameters

##### 11.1 PHP-FPM Configuration
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000
```

##### 11.2 OPcache Production Configuration
```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

##### 11.3 MySQL InnoDB Buffer Pool
MySQL `innodb_buffer_pool_size` is allocated **65% of total dedicated database server RAM**.

#### Decision 12: Environment Parity Strategy

The system recognizes four strict environments: `local`, `testing`, `staging`, `production`.

**Parity Rule**: The Docker base images, PHP version, Nginx configurations, OPcache settings, and service binaries (Redis, Meilisearch) are **identical** across staging and production. Zero custom server modifications are permitted outside container definitions.

#### Decision 13: Horizontal Scaling Topology

##### Phase 1: Single-Node Container Topology (Launch)
Single host server running Coolify orchestrating Nginx + App + Horizon + Video Worker + Redis + Meilisearch + Managed MySQL + Cloudflare R2 + Cloudflare CDN Edge.

##### Phase 2: Multi-Node Distributed Scaling Topology (High Load)

```
                       Cloudflare CDN / WAF Edge
                                   │
                                   ▼
                Load Balancer (Nginx / Cloudflare ALB)
                      ┌────────────┴────────────┐
                      ▼                         ▼
            App Node 1 (FPM/Nginx)    App Node 2 (FPM/Nginx)
                      │                         │
                      └────────────┬────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
  Managed MySQL Cluster     Redis Cluster            Horizon Worker Cluster
 (Primary + Read Replica)  (Cache & Queue)           (Dedicated Video Nodes)
                                   │
                                   ▼
                         Meilisearch Search Node
                                   │
                                   ▼
                         Cloudflare R2 Storage
```

---

### PART VIII — PACKAGES GOVERNANCE

#### Decision 14: Approved & Forbidden Packages Registry

To maintain architectural integrity, prevent paradigm conflicts, and ensure compliance with ADR-001 through ADR-005, third-party Composer and NPM packages are strictly governed.

##### Approved Required Packages Registry

| Package Name | Purpose | Approved Version | Module / Scope |
|---|---|---|---|
| `php` | Language Runtime | `^8.4` | Global |
| `laravel/framework` | Application Framework | `^12.0` / `^13.0` | Global |
| `laravel/sanctum` | Admin Token Authentication | Latest stable | Core / Auth |
| `spatie/laravel-permission` | Role & Permission Management | Latest stable | Core / Auth |
| `astrotomic/laravel-translatable` | Translation Table ORM Helper | Latest stable | Content, Countries, etc. |
| `league/flysystem-aws-s3-v3` | Cloud Storage Adapter (R2) | Latest stable | Media |
| `laravel/horizon` | Queue Worker Management | Latest stable | Infrastructure |
| `laravel/scout` | Search Abstraction Layer | Latest stable | Search |
| `meilisearch/meilisearch-php` | Meilisearch Driver | Latest stable | Search |
| `laravel/socialite` | Contestant Social OAuth | Latest stable | Auth |
| `intervention/image` | Image Resizing & Processing | `^3.0` | Media |
| `ramsey/uuid` | UUID v7 Generation | Latest stable | Core |
| `predis/predis` / `ext-redis` | Redis Client Driver | Latest stable | Infrastructure |
| `zircote/swagger-php` | Code-First OpenAPI Annotation | Latest stable | API / Dev |
| `pestphp/pest` | Testing Framework | `^3.0` | Dev / Testing |
| `nunomaduro/collision` | Test Reporting | Latest stable | Dev |
| `nunomaduro/larastan` | Static Analysis (Level 8+) | `^3.0` | Dev |
| `laravel/pint` | Code Style Enforcement | Latest stable | Dev |
| `rector/rector` | Automated Refactoring | Latest stable | Dev |

##### Forbidden Packages Registry

| Package Name | Status | Rationale for Ban | Alternative |
|---|---|---|---|
| `laravel/passport` | ❌ BANNED | Introduces OAuth2 server complexity. Sanctum handles admin tokens; Socialite handles contestant social auth (ADR-003). | `laravel/sanctum` + `laravel/socialite` |
| `tymon/jwt-auth` | ❌ BANNED | Unmaintained legacy JWT package. Conflicts with Sanctum token management. | `laravel/sanctum` |
| `doctrine/orm` | ❌ BANNED | Second ORM engine. Conflicts with Eloquent models, migrations, and database conventions (ADR-005). | Eloquent ORM |
| `spatie/laravel-medialibrary` | ❌ BANNED | Implements its own media table schema and storage conventions that conflict with Decision 14 (Media Ownership Convention) and `media_assets` schema. | Custom `MediaAsset` Platform Service |
| `spatie/laravel-settings` | ❌ BANNED | Uses custom DTO storage that conflicts with the simple `settings` table schema defined in DATABASE-DOMAIN-INVENTORY. | Native Core `Setting` Model |
| `graphql-laravel` / `lighthouse` | ❌ BANNED | Violates ADR-001 & ADR-004 REST API-First specification. | REST API v1 |
| `barryvdh/laravel-debugbar` | ❌ BANNED in prod | Injects DOM payloads and leaks memory in API responses. Permitted only in local dev if non-intrusive. | Laravel Telescope (Local) |
| `nwidart/laravel-modules` | ❌ BANNED | Introduces aggressive runtime package loading that conflicts with native Modular Monolith ServiceProvider architecture (ADR-002). | Native ServiceProvider Modular Architecture |

---

## Cross-Decision Matrix

| Infrastructure Area | Governed By | Inter-Dependency |
|---|---|---|
| Deployment & Containers | Decision 1 | Feeds into Performance (Decision 11) & Scaling (Decision 13) |
| Cache & Redis | Decision 2 | Feeds into Rate Limiting (Decision 9) & Queue (Decision 5) |
| Cloudflare CDN | Decision 3 | Feeds into Media Storage (Decision 4) & Security (Decision 9) |
| Media Storage & R2 | Decision 4 | Governed by ADR-005 Decision 14 (Media Ownership) |
| Horizon & Queues | Decision 5 | Transport for Video Processing (Decision 6) & Search Indexing (Decision 7) |
| Video Transcoding | Decision 6 | Requires Isolated Worker Container (Decision 1) |
| Search (Meilisearch) | Decision 7 | Receives Sync Jobs from Queue (Decision 5) |
| Backups & PITR | Decision 10 | Uses Cloudflare R2 Backup Vault (Decision 4) |
| Package Governance | Decision 14 | Enforces ADR-001, ADR-003, ADR-004, ADR-005 compliance |

---

## Infrastructure Anti-Patterns (Prohibited Practices)

1. ❌ **No Direct Public Serving of Private Storage**: Original video uploads or contestant documents must never be served through public HTTP URLs. Signed URLs are mandatory.
2. ❌ **No Heavy Processing in HTTP Thread**: FFmpeg, image conversions, or bulk emails inside an HTTP controller are strictly prohibited. Always dispatch to queues.
3. ❌ **No Un-monitored Queues**: Adding a new background queue without registering it in Horizon and setting a balance policy is prohibited.
4. ❌ **No Direct Host Dependencies**: Installing PHP, Redis, FFmpeg, or Nginx directly on the host OS without containerization is prohibited.
5. ❌ **No Banned Packages**: Installing any package listed in the Forbidden Packages Registry is an automatic PR failure.
6. ❌ **No Wildcard CORS or Missing Security Headers**: Staging and production Nginx configs must enforce security headers and strict CORS origins.
7. ❌ **No Telescope in Production**: `TELESCOPE_ENABLED` must be `false` in production to prevent DB bloat and memory leaks.

---

## Consequences

### Positive Consequences
- **Complete Environment Determinism**: Containerized configuration eliminates environment discrepancies across all deployment targets.
- **Zero Egress Media Delivery**: Using Cloudflare R2 + CDN eliminates bandwidth costs for video streaming and thumbnail distribution.
- **Resilient Background Processing**: Horizon priorities and isolated FFmpeg workers guarantee fast HTTP API response times even during heavy video transcoding.
- **Strict Package Quality**: Eliminating redundant or conflicting third-party packages keeps vendor size minimal, security footprint low, and architecture compliant.
- **Disaster Recovery Readiness**: Automated backups with PITR binary logs provide guaranteed recovery points (<1h RPO).

### Negative Consequences / Trade-offs
- **Infrastructure Complexity**: Running Horizon, isolated video workers, Meilisearch, and Redis requires more container management overhead than a simple monolithic VM.
- **Coolify / Host Memory Requirements**: Initial single-node deployment requires a server with at least 8GB RAM to comfortably run all containers alongside FFmpeg transcoding.

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-003: Authentication & Identity Architecture](./ADR-003-authentication-identity.md)
- [ADR-004: API Standards & Conventions](./ADR-004-api-standards-conventions.md)
- [ADR-005: Database Architecture](./ADR-005-database-architecture.md)
- [ADR-ROADMAP.md](../ADR-ROADMAP.md)
- [Laravel Horizon Documentation](https://laravel.com/docs/horizon)
- [Cloudflare R2 Documentation](https://developers.cloudflare.com/r2/)
- [Meilisearch Documentation](https://www.meilisearch.com/docs)
- [FFmpeg Documentation](https://ffmpeg.org/documentation.html)
