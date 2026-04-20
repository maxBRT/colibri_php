# Colibri Blueprint (Laravel Monolith)

## 1) Executive Summary

Colibri rewrite moves from distributed Go + RabbitMQ setup to single Laravel application.

- One deployable app for API + scheduled/background work
- No RabbitMQ; use Laravel scheduler + database queue
- Keep PostgreSQL schema compatible for low-risk migration
- Keep S3-compatible logo storage and Gemini enrichment
- Preserve API behavior (`/v1/categories`, `/v1/sources`, `/v1/posts`, `/health`)

Result: lower ops cost, simpler debugging, faster feature work, easier local development.

---

## 2) System Architecture

```text
Clients
  -> HTTP API (Laravel routes/controllers/requests/resources)
  -> Service Layer (business rules + orchestration)
  -> Repository Layer (query isolation)
  -> PostgreSQL (sources/posts/logos + queue tables)

Scheduler (Laravel schedule)
  -> Dispatch Jobs (database queue)
  -> Workers execute jobs:
       - FetchRssJob
       - GenerateDescriptionsJob
       - SyncLogosJob
       - CleanupJob

Infrastructure adapters:
  - HTTP client (RSS/article fetch)
  - AI client (Gemini)
  - Storage adapter (S3/MinIO)
```

### Layers

- HTTP API Layer: validation, auth/rate limits, response shaping
- Job Scheduler Layer: periodic dispatch of background jobs
- Service Layer: use cases (RSS ingestion, enrichment, logo sync)
- Repository Layer: persistence details hidden behind interfaces
- Infrastructure Layer: external APIs, storage, resilient HTTP

---

## 3) Laravel Implementation Details

### Proposed Directory Structure

```text
app/
  Console/
    Commands/
      FetchRssCommand.php
      GenerateDescriptionsCommand.php
      SyncLogosCommand.php
      CleanupPostsCommand.php
  Http/
    Controllers/Api/
      CategoryController.php
      SourceController.php
      PostController.php
      HealthController.php
    Requests/
      ListSourcesRequest.php
      ListPostsRequest.php
    Resources/
      SourceResource.php
      PostResource.php
  Jobs/
    FetchRssJob.php
    GenerateDescriptionsJob.php
    SyncLogosJob.php
    CleanupPostsJob.php
  Models/
    Source.php
    Post.php
    Logo.php
    JobRun.php
  Services/
    SourceService.php
    PostService.php
    RssService.php
    EnrichmentService.php
    LogoService.php
  Repositories/
    Contracts/
      SourceRepositoryInterface.php
      PostRepositoryInterface.php
      LogoRepositoryInterface.php
    Eloquent/
      SourceRepository.php
      PostRepository.php
      LogoRepository.php
  Infrastructure/
    AI/
      GeminiClient.php
    Feed/
      RssParser.php
    Storage/
      LogoStorage.php
  Providers/
    RepositoryServiceProvider.php

config/
  queue.php
  services.php
  rss.php

database/
  migrations/
  factories/
  seeders/

routes/
  api.php

tests/
  Feature/
  Unit/
```

### Built-In Laravel Features to Lean On

- Scheduler (`app/Console/Kernel.php`)
- Queues (`database` driver + workers)
- HTTP client with retry/timeout/circuit-breaker style guards
- Validation via Form Requests
- API resources for response contracts
- Rate limiting via `RateLimiter`
- Logging and failed job tracking

### Essential Packages

- RSS parser package (for robust RSS/Atom parsing), e.g. `simplepie/simplepie`
- No extra queue broker package required
- S3 support via Laravel filesystem + Flysystem S3 adapter

### Service Provider Organization

- `RepositoryServiceProvider`: bind repository interfaces to Eloquent implementations
- `AppServiceProvider`: common macros, strict model settings, default pagination/max values

---

## 4) Component Specifications

### Models

#### `Source`
- Primary key: string `id` (external stable identifier)
- Fields: `id`, `name`, `url`, `category`, timestamps
- Relations:
  - `hasMany(Post::class)`
  - `hasOne(Logo::class)`

#### `Post`
- Primary key: UUID `id`
- Fields: `title`, `description`, `link`, `guid`, `pub_date`, `source_id`, `status`, timestamps
- Status enum: `processing`, `done`
- Relations:
  - `belongsTo(Source::class)`
- Rules:
  - Unique `guid` for dedup

#### `Logo`
- Primary key: UUID `id`
- Fields: `source_id`, `object_key`, `url`, `mime_type`, `size_bytes`, timestamps
- Relation:
  - `belongsTo(Source::class)`
- Rule:
  - Unique `source_id` (one logo per source)

### Controllers

#### `CategoryController@index`
- Returns distinct categories

#### `SourceController@index`
- Optional `category[]` filter
- Returns sources + `logo_url`

#### `PostController@index`
- Optional `sources[]` filter
- Pagination (`page`, `per_page`, max 100)

#### `HealthController@show`
- Lightweight health status (app + DB connectivity)

### Services

#### `RssService`
- Fetch feed URL
- Parse feed items
- Normalize into post DTOs
- Upsert source metadata when needed

#### `PostService`
- Deduplicate by GUID
- Persist new posts as `processing`
- Query paginated posts

#### `EnrichmentService`
- Generate article summary with Gemini
- Handle failures gracefully (fallback to empty description)

#### `LogoService`
- Discover favicon/logo candidate
- Validate media type/size
- Upload to S3/MinIO
- Upsert `logos` record

### Repositories

- `SourceRepository`
  - `list(?array $categories)`
  - `upsert(array $payload)`
  - `listCategories()`
- `PostRepository`
  - `paginate(array $filters, int $page, int $perPage)`
  - `findByGuid(string $guid)`
  - `insert(array $payload)`
  - `listPending(int $limit)`
  - `markDone(string $id, ?string $description)`
- `LogoRepository`
  - `findBySourceId(string $sourceId)`
  - `upsert(array $payload)`
  - `listSourcesWithoutLogos()`

### Jobs

- `FetchRssJob`: fetch configured sources, write new posts, dispatch enrichment
- `GenerateDescriptionsJob`: process pending posts in batches
- `SyncLogosJob`: find sources missing logo, fetch/upload/upsert
- `CleanupPostsJob`: archive/remove old posts per retention policy

### Artisan Commands

- `rss:fetch {--source=*}`
- `posts:describe {--limit=50}`
- `logos:sync {--source=*}`
- `posts:cleanup {--days=90}`

Commands dispatch jobs; heavy work stays async.

---

## 5) Database Design

Goal: keep compatibility with existing schema for safer cutover.

### Tables

- `sources`
  - `id` varchar PK
  - `name` varchar not null
  - `url` text not null
  - `category` varchar not null
  - timestamps

- `posts`
  - `id` uuid PK default `gen_random_uuid()`
  - `title` varchar not null
  - `description` text nullable
  - `link` text not null
  - `guid` text unique not null
  - `pub_date` timestamp not null
  - `source_id` varchar FK -> `sources.id`
  - `status` enum (`processing`, `done`) default `processing`
  - timestamps

- `logos`
  - `id` uuid PK default `gen_random_uuid()`
  - `source_id` varchar unique FK -> `sources.id` on delete cascade
  - `object_key` text not null
  - `url` text not null
  - `mime_type` text nullable
  - `size_bytes` bigint nullable
  - timestamps

- Optional `job_runs`
  - track status, duration, counts, error text for visibility

### Indexing Strategy

- `posts(source_id)`
- `posts(status)`
- `posts(pub_date desc)`
- `sources(category)`
- `posts(guid unique)`
- `logos(source_id unique)`

---

## 6) API Design

### Routes

```php
Route::prefix('v1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/sources', [SourceController::class, 'index']);
    Route::get('/posts', [PostController::class, 'index']);
});

Route::get('/health', [HealthController::class, 'show']);
```

### Validation Rules

- `/v1/sources`
  - `category` optional; string or repeatable array
- `/v1/posts`
  - `sources` optional; repeatable array of existing source ids
  - `page` integer >= 1 (default 1)
  - `per_page` integer 1..100 (default 20)

### Response Contracts

- Categories:
  - `{ "categories": ["tech", "news"] }`
- Sources:
  - `{ "sources": [{ "id": "...", "name": "...", "url": "...", "category": "...", "logo_url": "..." }] }`
- Posts:
  - `{ "posts": [...], "pagination": { "page": 1, "per_page": 20, "total": 150, "total_pages": 8 } }`

### Error Handling

- Unified error envelope:
  - `{ "error": { "code": "VALIDATION_ERROR", "message": "..." } }`
- Map Laravel exceptions in exception handler:
  - validation -> `422`
  - not found -> `404`
  - rate limit -> `429`
  - unexpected -> `500` + internal log context id

---

## 7) Job Scheduling and Queue Strategy

### Scheduler (`Kernel::schedule`)

- Every 4 hours: dispatch `FetchRssJob`
- Every 4 hours: dispatch `SyncLogosJob`
- Every minute: dispatch `GenerateDescriptionsJob` (batch limited)
- Daily: dispatch `CleanupPostsJob`

Use overlap protection + single-server lock when deployed multi-instance:
- `withoutOverlapping()`
- `onOneServer()` (if cache lock backend configured)

### Queue Configuration

- `QUEUE_CONNECTION=database`
- Run migrations for `jobs`, `failed_jobs`
- Separate queues by concern:
  - `rss`, `enrichment`, `logos`, `default`

### Concurrency and Retry

- Worker processes tuned per queue priority
- Job-level:
  - `tries`
  - `backoff` (exponential)
  - `timeout`
- Idempotency:
  - dedup by `guid`
  - logo upsert by `source_id`

---

## 8) External Integrations

### RSS Fetching

- HTTP client defaults:
  - short connect timeout, bounded total timeout
  - retries with jitter
  - custom user agent
- Parse RSS/Atom safely; ignore malformed entries instead of failing whole batch

### Gemini Integration

- Laravel AI SDK
- Config in `services.php`:
  - API key, model name, timeout, max tokens, rate limit
- Prompt template in service class
- Fail-soft behavior:
  - if generation fails, mark post done with null description
- Add per-provider rate limiting middleware for jobs

### S3/MinIO Integration

- Use Laravel `Storage::disk('s3')`
- Validate content type and max size before upload
- Store object key and public URL in `logos`
- Keep naming deterministic enough for overwrite or unique keys per source version

---

## 9) Testing Strategy (Pest)

### Feature Tests

- API contracts:
  - `GET /v1/categories`
  - `GET /v1/sources` with and without category filters
  - `GET /v1/posts` pagination and filtering
- Validation errors and response shapes
- Health endpoint

### Unit Tests

- Repository query behavior
- Service logic:
  - dedup rules
  - enrichment fallback paths
  - logo validation rules

### Job Tests

- `FetchRssJob` inserts new posts, skips duplicate GUIDs
- `GenerateDescriptionsJob` updates status/description correctly
- `SyncLogosJob` only processes missing logos

### Mocking

- `Http::fake()` for RSS and Gemini
- `Storage::fake('s3')` for logo uploads
- Queue assertions with `Queue::fake()` where useful

---

## 10) Deployment Considerations

### Docker

- App container (PHP-FPM + app code)
- Web container (Nginx)
- Postgres container
- No RabbitMQ container

### Runtime Processes

- Web/API
- Queue workers (`php artisan queue:work --queue=rss,enrichment,logos,default`)
- Scheduler (`php artisan schedule:work` or cron + `schedule:run`)

### Environment Configuration

Required env groups:
- App: `APP_ENV`, `APP_URL`, `APP_KEY`
- DB: `DB_*`
- Queue: `QUEUE_CONNECTION=database`
- Storage: `FILESYSTEM_DISK=s3`, `AWS_*`/MinIO keys
- Gemini: `GEMINI_API_KEY`, `GEMINI_MODEL`
- Operational: timeouts, batch sizes, retention days

### Operational Baseline

- Configure health checks for web and workers
- Collect logs centrally
- Enable failed job monitoring and replay (`queue:retry`)
- Use rolling deployment; run migrations before traffic switch

---

## Implementation Phases (Recommended)

1. Foundation: models, migrations, repositories, read-only APIs
2. RSS ingestion: parser + fetch job + dedup flow
3. AI enrichment: Gemini integration + description job
4. Logo sync: logo discovery + S3 upload + API exposure
5. Hardening: rate limits, observability, migration cutover, perf tuning

---

## Parity Checklist

- [ ] RSS fetch every 4 hours
- [ ] Dedup by GUID
- [ ] AI descriptions
- [ ] Logo fetch + S3 storage
- [ ] API endpoints parity
- [ ] Rate limiting (100 req/min baseline)
- [ ] CORS and security headers
- [ ] Health endpoint
- [ ] DB-backed queue and worker setup
- [ ] Dockerized deployment

This blueprint is implementation-ready and aligned to Laravel monolith best practices while preserving existing product behavior.
