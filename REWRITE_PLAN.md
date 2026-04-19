# Colibri Rewrite Plan: From Distributed Go to Simplified Monolith

## Executive Summary

**Current State**: Colibri is an over-engineered RSS aggregator using Go with RabbitMQ message queues, 3 separate services, and distributed complexity for simple CRUD operations.

**Proposed State**: A simplified modular monolithic architecture that eliminates RabbitMQ, consolidates services, and uses direct service calls. The system will be easier to deploy, debug, and maintain while preserving all core functionality.

**Key Wins**:
- Eliminate RabbitMQ (operational complexity, single point of failure)
- Single deployable unit (simpler CI/CD, debugging)
- Direct service calls (easier tracing, simpler error handling)
- Maintainable codebase (clear separation of concerns)

---

## Architecture Comparison

### Current Architecture (Distributed)

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Fetcher    │────▶│   RabbitMQ  │────▶│  Consumer   │
│ (Cron Job)  │     │  (3 queues) │     │ (4 replicas)│
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
┌─────────────┐     ┌─────────────┐     ┌──────┴──────┐
│     API     │◀────│  PostgreSQL │◀────│     S3      │
│   Server    │     │   (3 tables)│     │   (Logos)   │
└─────────────┘     └─────────────┘     └─────────────┘
```

**Problems**:
- Message queue overhead for simple DB writes
- Distributed transaction complexity
- 3 separate Docker images to build and deploy
- Hard to trace request flows
- RabbitMQ clustering for HA adds ops burden

### Proposed Architecture (Simplified Monolith)

```
┌─────────────────────────────────────────────────────┐
│                 Colibri Application                  │
│  ┌─────────────┐  ┌─────────────┐  ┌──────────────┐ │
│  │  HTTP API   │  │ Job Runner  │  │   Services   │ │
│  │  (REST)     │  │ (Scheduled) │  │(Business Logic)│
│  └──────┬──────┘  └──────┬──────┘  └──────┬───────┘ │
│         └────────────────┴────────────────┘        │
│                         │                            │
│         ┌───────────────┴───────────────┐            │
│         ▼                               ▼            │
│  ┌─────────────┐                 ┌─────────────┐     │
│  │  PostgreSQL │                 │  S3/Storage │     │
│  │(same schema)│                 │  (Logos)    │     │
│  └─────────────┘                 └─────────────┘     │
└─────────────────────────────────────────────────────┘
```

**Benefits**:
- Single deployable unit
- Direct method calls (no serialization overhead)
- Shared database connection pool
- Simpler error handling and retries
- Easier local development
- Lower infrastructure costs

---

## Component Design

### 1. HTTP API Layer

**Responsibility**: Handle HTTP requests, validation, response formatting

**Endpoints** (identical to current):
```
GET /v1/categories          → List all categories
GET /v1/sources?category=x  → List sources (optional filter)
GET /v1/posts?sources=a,b   → List posts (optional filter)
GET /health                 → Health check
```

**Design Patterns**:
- Controller/Handler pattern
- Request/Response DTOs
- Middleware chain (CORS, rate limiting, logging)
- Content negotiation (JSON)

**Language Agnostic Concepts**:
- Router with parameter extraction
- Query parameter parsing
- Pagination support
- Error response standardization

### 2. Job Scheduler

**Responsibility**: Run background tasks on schedule

**Jobs**:
```
FetchJob:        Every 4 hours  → Fetch RSS feeds
LogoSyncJob:     Every 4 hours  → Sync missing logos
DescriptionJob:  Continuous     → Process pending posts
CleanupJob:      Daily          → Archive old posts
```

**Design Patterns**:
- Cron-like scheduling
- Job queue for async processing (in-memory or simple DB queue)
- Concurrency limiting
- Job status tracking
- Retry with exponential backoff

**Language Agnostic Concepts**:
- Scheduler interface
- Job definition (name, schedule, handler)
- Worker pool pattern
- Graceful shutdown

### 3. Service Layer

**Responsibility**: Business logic, orchestration

**Services**:
```
PostService:
  - ListPosts(filters) → Paginated posts
  - CreatePost(post) → Insert with deduplication
  - UpdateDescription(postID, description) → Update status
  - GetPendingPosts() → Posts needing AI description

SourceService:
  - ListSources(category?) → All sources or filtered
  - UpsertSource(source) → Insert or update
  - GetSourceByID(id) → Single source

LogoService:
  - FetchAndStore(source) → Download and upload to S3
  - GetLogoURL(sourceID) → CDN URL
  - ListMissingLogos() → Sources without logos

EnrichmentService:
  - GenerateDescription(url) → AI-generated summary
  - ValidateContent(url) → Check if fetchable

RssService:
  - FetchFeed(url) → Parse RSS/Atom
  - ExtractPosts(feed) → Convert to domain models
```

**Design Patterns**:
- Dependency injection
- Interface-based design (testability)
- Transaction management
- Circuit breaker for external calls

### 4. Repository Layer

**Responsibility**: Data access, database operations

**Repositories**:
```
PostRepository:
  - FindAll(filters, pagination) → Posts
  - FindByID(id) → Post
  - FindByGUID(guid) → Post (for dedup)
  - Insert(post) → ID
  - Update(post) → Success/Failure
  - FindPending(limit) → Posts with status='processing'

SourceRepository:
  - FindAll(category?) → Sources
  - FindByID(id) → Source
  - Upsert(source) → Success/Failure
  - FindAllCategories() → Distinct categories

LogoRepository:
  - FindBySourceID(sourceID) → Logo
  - Upsert(logo) → Success/Failure
  - FindSourcesWithoutLogo() → Source IDs
```

**Design Patterns**:
- Repository pattern
- Unit of Work (transactions)
- Query builders (or ORM)
- Connection pooling

### 5. Infrastructure Layer

**Responsibility**: External integrations, cross-cutting concerns

**Components**:
```
Database:
  - Connection management
  - Migration runner
  - Query execution

Storage (S3-compatible):
  - Upload operations
  - Public URL generation
  - Content-type detection

HTTP Client:
  - RSS fetching
  - Logo downloading
  - Timeout/retry configuration

AI Client:
  - Gemini API integration
  - Prompt templating
  - Response parsing

```

---

## Data Flow

### API Request Flow

```
HTTP Request
    ↓
Router → Parse path/query params
    ↓
Middleware (rate limit, CORS)
    ↓
Controller/Handler
    ↓
Service Layer (business logic)
    ↓
Repository Layer (DB queries)
    ↓
Database
    ↓
Response Serialization → HTTP Response
```

### RSS Fetching Flow (Scheduled Job)

```
Scheduler triggers FetchJob
    ↓
Read sources from configuration
    ↓
For each source (concurrent with limit):
    ├── Fetch RSS feed via HTTP
    ├── Parse XML to domain objects
    ├── Upsert source metadata
    ├── For each post:
    │   ├── Check deduplication (GUID)
    │   ├── Insert new posts (status='processing')
    │   └── Queue for description generation
    └── Fetch and store logo (async)
    ↓
Mark job completion
```

### Description Generation Flow (Background Job)

```
DescriptionJob polls pending posts
    ↓
Fetch post content (or use URL)
    ↓
Call AI service (Gemini)
    ↓
Parse response
    ↓
Update post with description
    ↓
Set status='done'
```

### Logo Fetching Flow

```
LogoJob identifies sources without logos
    ↓
For each source:
    ├── Fetch favicon from website
    ├── Download image
    ├── Validate (size, format)
    ├── Upload to S3
    ├── Generate CDN URL
    └── Upsert logo record
```

---

## Database Schema

**Important**: Keep schema compatible for zero-downtime migration

```sql
-- Sources table
CREATE TABLE sources (
  id VARCHAR PRIMARY KEY,
  name VARCHAR NOT NULL,
  url TEXT NOT NULL,
  category VARCHAR NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Post status enum
CREATE TYPE post_status AS ENUM ('processing', 'done');

-- Posts table
CREATE TABLE posts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title VARCHAR NOT NULL,
  description TEXT,
  link TEXT NOT NULL,
  guid TEXT NOT NULL UNIQUE,
  pub_date TIMESTAMP NOT NULL,
  source_id VARCHAR NOT NULL,
  status post_status NOT NULL DEFAULT 'processing',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  CONSTRAINT fk_sources FOREIGN KEY (source_id)
    REFERENCES sources(id)
);

-- Logos table
CREATE TABLE logos (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  source_id VARCHAR NOT NULL UNIQUE,
  object_key TEXT NOT NULL,
  url TEXT NOT NULL,
  mime_type TEXT,
  size_bytes BIGINT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  CONSTRAINT fk_logos_source FOREIGN KEY (source_id)
    REFERENCES sources(id) ON DELETE CASCADE
);

-- Optional: Job tracking table for observability
CREATE TABLE job_runs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_name VARCHAR NOT NULL,
  started_at TIMESTAMP NOT NULL,
  completed_at TIMESTAMP,
  status VARCHAR NOT NULL, -- 'running', 'success', 'failed'
  items_processed INTEGER DEFAULT 0,
  error_message TEXT
);

-- Indexes for performance
CREATE INDEX idx_posts_source_id ON posts(source_id);
CREATE INDEX idx_posts_status ON posts(status);
CREATE INDEX idx_posts_pub_date ON posts(pub_date DESC);
CREATE INDEX idx_sources_category ON sources(category);
```

---

## API Contract

### GET /v1/categories

Response:
```json
{
  "categories": ["tech", "news", "science", "business"]
}
```

### GET /v1/sources

Query params:
- `category` (optional, repeatable): Filter by category

Response:
```json
{
  "sources": [
    {
      "id": "hacker-news",
      "name": "Hacker News",
      "url": "https://news.ycombinator.com/rss",
      "category": "tech",
      "logo_url": "https://cdn.example.com/logos/hacker-news.png"
    }
  ]
}
```

### GET /v1/posts

Query params:
- `sources` (optional, repeatable): Filter by source IDs
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20, max: 100)

Response:
```json
{
  "posts": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Article Title",
      "description": "AI-generated summary...",
      "link": "https://example.com/article",
      "guid": "unique-article-id",
      "pub_date": "2024-01-15T10:30:00Z",
      "source_id": "hacker-news",
      "status": "done"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8
  }
}
```

### Error Responses

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid parameter: sources must be valid source IDs"
  }
}
```

---

## Technology Stack Recommendations

### Language Options

**PHP (as suggested by directory name)**:
- Framework: Laravel
- Pros: Mature ecosystem, excellent ORM (Eloquent/Doctrine), built-in scheduler
- Cons: Memory usage, less efficient for high concurrency

**Go (simplification of current)**:
- Framework: Chi (keep) or Gin
- Pros: Performance, existing codebase knowledge, single binary
- Cons: Less built-in functionality (need libraries for ORM, scheduling)

**Python**:
- Framework: FastAPI + Celery (for jobs)
- Pros: Great for AI integration, large ecosystem
- Cons: GIL limitations, deployment complexity

**Node.js**:
- Framework: NestJS or Express
- Pros: JavaScript ecosystem, good for I/O bound work
- Cons: Callback complexity, memory usage

**Recommendation**: PHP with Laravel or Go simplified version

### Database

- **PostgreSQL** (keep): Proven, compatible schema
- Connection pooling: PgBouncer (for PHP) or built-in (Go)
- Migrations: Framework-native or Goose (keep)

### Job Processing

**Option 1: In-Process Scheduler**
- Language-native cron library
- In-memory job queue
- Good for: Simplicity, single-node deployment

**Option 2: Database-Backed Queue**
- Jobs stored in DB table
- Worker processes poll for jobs
- Good for: Durability, multiple workers

**Option 3: External Queue (keep it simple)**
- Redis lists or streams
- Bull (Node), Celery (Python), Sidekiq (Ruby)
- Good for: Job observability, retries

**Recommendation**: Database-backed queue (no new infrastructure)

### Storage

- **S3-compatible** (keep): MinIO, AWS S3, DigitalOcean Spaces
- CDN: CloudFront, Cloudflare, or BunnyCDN

### AI Integration

- **Google Gemini** (keep): Already working integration
- Alternative: OpenAI GPT, Anthropic Claude

### Deployment

**Docker Compose** (single node):
```yaml
services:
  app:
    image: colibri:latest
    ports:
      - "8080:8080"
    environment:
      - DB_HOST=postgres
      - S3_BUCKET=logos
  
  postgres:
    image: postgres:17-alpine
    volumes:
      - pgdata:/var/lib/postgresql/data
  
  # No RabbitMQ needed!
```

**Kubernetes** (if scaling needed):
- Single deployment with multiple replicas
- CronJob for fetcher tasks
- Horizontal Pod Autoscaler for API

---

## Implementation Phases

### Phase 1: Foundation (Week 1-2)

**Goals**: Project setup, database, basic API

**Tasks**:
1. Project scaffolding
2. Database connection and migrations
3. Repository layer implementation
4. Basic API endpoints (GET /v1/sources, /v1/categories)
5. Docker setup

**Deliverables**:
- Working API with read-only endpoints
- Database schema migrated
- CI/CD pipeline

### Phase 2: RSS Integration (Week 3)

**Goals**: RSS fetching, post storage

**Tasks**:
1. RSS client implementation
2. Source upsert logic
3. Post deduplication
4. Job scheduler setup
5. Fetch job implementation

**Deliverables**:
- RSS feeds fetched and stored
- Posts available via API
- Scheduled fetching working

### Phase 3: AI Enrichment (Week 4)

**Goals**: Description generation

**Tasks**:
1. AI client integration
2. Description job implementation
3. Background job processing
4. Rate limiting for AI API

**Deliverables**:
- Posts have AI-generated descriptions
- Job queue processing

### Phase 4: Logo Handling (Week 5)

**Goals**: Logo fetching and storage

**Tasks**:
1. S3 client setup
2. Favicon fetching logic
3. Logo upload and CDN URL generation
4. Logo sync job

**Deliverables**:
- Source logos visible in API
- S3 storage working

### Phase 5: Polish & Migration (Week 6)

**Goals**: Production readiness

**Tasks**:
1. Rate limiting and middleware
2. Error handling and logging
3. Monitoring and alerting
4. Data migration from old system
5. Performance optimization
6. Documentation

**Deliverables**:
- Production deployment
- Old system deprecated
- Documentation complete

---

## Migration Strategy

### Data Migration

**Approach**: Parallel running with gradual cutover

1. **Schema Compatibility**: New system uses same schema
2. **Dual Write** (optional): Write to both systems during transition
3. **Data Sync**: One-time migration of historical data
4. **Verification**: Compare API responses between systems

**Migration Steps**:
```bash
# 1. Export data from old system
pg_dump colibri_db > colibri_backup.sql

# 2. Import to new system
psql new_colibri_db < colibri_backup.sql

# 3. Run new system in parallel (different port)
docker-compose -f docker-compose.new.yml up

# 4. Verify data consistency
# Compare API responses: /v1/posts, /v1/sources

# 5. Switch traffic (DNS or load balancer)

# 6. Decommission old system
```

### Feature Parity Checklist

- [ ] RSS feed fetching (every 4 hours)
- [ ] Post deduplication by GUID
- [ ] AI description generation
- [ ] Logo fetching and S3 storage
- [ ] REST API endpoints (categories, sources, posts)
- [ ] Rate limiting (100 req/min)
- [ ] CORS headers
- [ ] Health check endpoint
- [ ] Database migrations
- [ ] Docker containerization

### Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Data loss | Full backup before migration, parallel running |
| Performance degradation | Load testing, connection pooling, caching |
| AI API failures | Circuit breaker, fallback to empty description |
| RSS parsing errors | Try-catch per feed, don't fail entire batch |
| S3 upload failures | Retry logic, queue for later processing |

---

## Directory Structure (Example: PHP/Laravel)

```
colibri/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── FetchRssCommand.php
│   │   │   ├── GenerateDescriptionsCommand.php
│   │   │   └── SyncLogosCommand.php
│   │   └── Kernel.php              # Schedule definitions
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CategoryController.php
│   │   │   ├── SourceController.php
│   │   │   └── PostController.php
│   │   ├── Middleware/
│   │   │   ├── RateLimitMiddleware.php
│   │   │   └── SecurityHeadersMiddleware.php
│   │   └── Requests/
│   │       ├── ListSourcesRequest.php
│   │       └── ListPostsRequest.php
│   ├── Models/
│   │   ├── Source.php
│   │   ├── Post.php
│   │   └── Logo.php
│   ├── Services/
│   │   ├── RssService.php
│   │   ├── EnrichmentService.php
│   │   ├── LogoService.php
│   │   └── Jobs/
│   │       ├── FetchJob.php
│   │       ├── DescriptionJob.php
│   │       └── LogoJob.php
│   ├── Repositories/
│   │   ├── SourceRepository.php
│   │   ├── PostRepository.php
│   │   └── LogoRepository.php
│   └── Infrastructure/
│       ├── S3Client.php
│       ├── GeminiClient.php
│       └── RssParser.php
├── config/
│   ├── app.php
│   ├── database.php
│   └── services.php
├── database/
│   ├── migrations/                 # Same schema as current
│   └── seeders/
├── routes/
│   └── api.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── docker/
│   ├── Dockerfile
│   └── nginx.conf
└── docker-compose.yml
```

---

## Conclusion

This rewrite plan eliminates the unnecessary complexity of RabbitMQ and distributed services while preserving all functionality. The simplified monolithic architecture will be:

1. **Easier to develop**: Single codebase, direct method calls
2. **Easier to deploy**: One container instead of three
3. **Easier to debug**: No distributed tracing needed
4. **Easier to maintain**: Clear separation of concerns
5. **More cost-effective**: Less infrastructure

The key insight is that RabbitMQ was solving a problem that didn't exist for this scale. Direct service calls with proper error handling and retries are simpler and more reliable for an RSS aggregator.

**Next Steps**:
1. Review and approve this plan
2. Choose implementation language
3. Set up new repository
4. Begin Phase 1 implementation
