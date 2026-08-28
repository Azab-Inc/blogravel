
# Blogravel
## Implementation Plan

Based on the [Project Requirements Document](./requirements.md), broken into phases with clear deliverables and dependencies.

---

## Phase 1 — Foundation & Data Layer

**Dependencies:** None (starting point)

### Deliverables

#### 1.1 Project Bootstrap
- Initialize Laravel project with PHP 8.3+
- Configure PostgreSQL connection and Redis driver
- Set up Docker Compose with three services: Laravel app, PostgreSQL, Redis
- Create `example.env` with all required variables (database, mail, app key, etc.)
- Verify `docker compose up` boots all three containers

#### 1.2 Database Schema
- Generate migrations for all tables defined in [point 4.3](requirements.md#point-4-3):
  - `users` with role enum (`super_admin`, `editor`, `author`)
  - `posts`, `pages`, `categories`, `tags`, `comments`, `media`
  - Pivot tables: `post_category`, `post_tag`
  - `subscribers`, `subscriber_category`
  - `ai_providers`, `api_keys`, `settings`
- Define Eloquent models with all relationships (BelongsTo, HasMany, BelongsToMany)
- Seed a default Super Admin user and sample data

#### 1.3 Authentication & Authorization
- **Admin panel:** Filament's built-in session-based login (email + password)
- **Public API:** Laravel Sanctum token-based auth (Bearer token on protected endpoints)
- **Roles middleware:** Enforce Super Admin, Editor, and Author boundaries on admin routes and write API endpoints (see [point 4.1 — Admin Panel](requirements.md#point-4-1))
- Store AI provider API keys encrypted at rest (Laravel's encryption)

#### 1.4 Queue Infrastructure
- Configure Laravel Queue with Redis driver
- Create a dedicated queue worker container in Docker Compose
- Define base Job classes for async operations

### Acceptance Criteria
- `docker compose up` starts all services
- `/login` serves Filament's login page
- `POST /api/login` returns a Sanctum token for valid credentials
- Queue worker processes jobs without errors
- Database migrations run clean on `php artisan migrate`

---

## Phase 2 — API-First Core

**Dependencies:** Phase 1 complete (database, auth, queue)

### Deliverables

#### 2.1 Public Read Endpoints
- `GET /api/posts` — paginated post list with category/tag filters
- `GET /api/posts/{slug}` — single post
- `GET /api/pages` — page list
- `GET /api/pages/{slug}` — single page
- `GET /api/categories` — category list
- `GET /api/categories/{slug}` — single category with posts
- `GET /api/tags` — tag list

All return JSON; support open (unauthenticated) read mode.

#### 2.2 API Key Management
- Admin UI (Filament resource) to generate API keys: name + optional rate limit per minute
- Store key hashes, never plaintext
- Laravel Sanctum token-based auth on write and sensitive endpoints
- Apply `RateLimiter` middleware per key; default unlimited when `rate_limit_per_minute` is null

#### 2.3 Open Mode Warning
- When no API keys exist, display a persistent warning in the admin panel recommending key setup
- All write/sensitive endpoints require authentication regardless of open mode

#### 2.4 Write Endpoints (Protected)
- `POST/PUT/DELETE /api/posts`
- `POST/PUT/DELETE /api/pages`
- `POST/PUT/DELETE /api/categories`
- `POST/PUT/DELETE /api/tags`
- Role enforcement on all write operations (Editor+ can write any; Author can write own posts only)

### Acceptance Criteria
- Anonymous GET requests return posts, pages, categories, tags
- Anonymous POST/PUT/DELETE requests return 401
- Authenticated requests with valid API keys respect rate limits
- Admin can generate, view, and revoke API keys in settings
- Warning banner appears when no API keys exist

---

## Phase 3 — Admin Panel (Filament PHP)

**Dependencies:** Phases 1 and 2 complete (models, auth, API)

### Deliverables

#### 3.1 Filament Resources
Build admin CRUD interfaces for:

- **Posts** — title, slug (auto-generated), content (rich editor), excerpt, featured image, categories, tags, status (`draft` / `published`), author, published_at
- **Pages** — title, slug, content, status
- **Categories** — name, slug, description
- **Tags** — name, slug
- **Comments** — list with status filter (`pending` / `approved` / `spam`); inline approve/spam/delete actions
- **Media** — upload, grid/list view, attach to posts
- **Users** — CRUD, role assignment, password management
- **Settings** — single-page form for site name, theme toggle, contact email, backup schedule, etc.

#### 3.2 Role Enforcement
- **Super Admin:** full access to all resources and settings
- **Editor:** create, edit, publish any post/page; manage comments and media; no user management or settings
- **Author:** create and edit own drafts only; cannot publish; cannot manage other users' content

#### 3.3 Dashboard Widgets
- Recent posts (last 7 days)
- Comment moderation queue count
- Subscriber count
- Quick-publish action

### Acceptance Criteria
- Each resource listed above is manageable through the Filament UI
- Role restrictions block unauthorized actions at both the UI and route level
- Super Admin can toggle starter theme on/off via settings
- Media uploads work and store to local storage

---

## Phase 4 — WordPress Import

**Dependencies:** Phase 3 complete (admin panel, media handling)

### Deliverables

#### 4.1 WXR File Import
- Filament import screen with file upload (`.xml`)
- WXR/XML parser that extracts: posts, pages, categories, tags, authors, custom fields, comments, media attachments
- Run via queued job to avoid request timeouts
- Show progress bar or status updates in the admin UI

#### 4.2 Database Connection Import
- Filament import screen with MySQL connection string input
- PDO-based direct read from WordPress database tables
- Use the same queued job pattern and processing pipeline as WXR import

#### 4.3 Import Processing Pipeline
- **Slug normalisation:** sanitise and deduplicate slugs
- **Idempotency:** skip records where the slug already exists in Blogravel
- **Media handling:** download all referenced media files, store locally, update content URLs
- **Author mapping:** create Blogravel users for WordPress authors (or map to existing)
- **Error reporting:** surface per-item failures (invalid dates, missing fields) without aborting the entire import

### Acceptance Criteria
- Upload a real WXR export → all content appears in Blogravel admin
- Duplicate import of the same file produces no duplicates
- Media files are present in the media library after import
- DB connection method produces identical results to WXR method
- Errors are displayed per-item without silent failures

---

## Phase 5 — AI Post Generation

**Dependencies:** Phase 3 complete (admin panel, post editor)

### Deliverables

#### 5.1 AI Provider Management
- Filament resource for AI providers: name, type (`openai` / `ollama` / `custom`), endpoint URL, API key (encrypted)
- Test connection button with inline success/failure feedback
- Multiple providers can be saved; one is set as active
- Ollama does not require an API key

#### 5.2 Prompt-to-Draft Generation
- Text prompt input in the post editor
- Trigger generation → HTTP call to active provider's endpoint
- On success: insert generated content as a draft post (never auto-publish)
- On failure: surface clear error (invalid credentials, unreachable endpoint, timeout)

#### 5.3 Provider-Specific Formatting
- **OpenAI-compatible:** POST `{model, messages, max_tokens}` to `/chat/completions`
- **Ollama:** POST `{model, prompt}` to `/api/generate`
- **Custom:** user-configurable request template (future enhancement; v1 uses OpenAI-compatible payload)

### Acceptance Criteria
- Admin can add, edit, test, and delete AI providers
- Invalid credentials return a readable error, not a 500
- Generated content appears in the editor as a draft
- Ollama works without an API key

---

## Phase 6 — Mailing Lists

**Dependencies:** Phases 1 and 2 complete (queue, API)

### Deliverables

#### 6.1 Public Subscribe Endpoint
- `POST /api/subscribe` accepts `{ email, categories: [] }`
- Empty categories array = subscribed to all
- No auth required (public endpoint)
- Validate email format; return 422 on invalid input

#### 6.2 Double Opt-In Flow
- On subscribe request: generate unique `confirmation_token`, store in `subscribers.confirmation_token`, queue confirmation email
- Confirmation email: includes link with token (e.g. `GET /api/subscribe/confirm?token=...`)
- On confirm: set `confirmed = true`, clear token
- Subscription is inactive until confirmed (do not send notifications to unconfirmed addresses)

#### 6.3 Post-Publish Notifications
- On post publish event: query subscribers whose category selection matches the post's categories (or all-category subscribers)
- Queue notification email per subscriber batch
- Each notification email includes post title, excerpt, link, and unsubscribe link

#### 6.4 Unsubscribe Handling
- `GET /api/unsubscribe?token=...` — unsubscribe from all (delete subscriber)
- `POST /api/unsubscribe` accepts `{ email, categories: [] }` — unsubscribe from specific categories
- Unsubscribe link in every notification email

### Acceptance Criteria
- `POST /api/subscribe` queues a confirmation email
- Subscription is only active after link click
- Publishing a post triggers notifications only to matching subscribers
- Unsubscribe link works and prevents future notifications

---

## Phase 7 — Starter Theme (Blade)

**Dependencies:** Phase 2 complete (public API)

### Deliverables

#### 7.1 Theme Pages
- **Home:** paginated post list with category filter
- **Single Post:** full content with author, date, categories, tags, comments section
- **Category Page:** category description + paginated post list
- **Subscribe:** form with email input and category checkboxes; posts to `POST /api/subscribe`
- **Contact:** form with name, email, message fields; submits to configurable email address

#### 7.2 API Consumption
- All pages fetch data from the internal REST API (same as external frontends would)
- Use Laravel HTTP client to call internal API endpoints

#### 7.3 Design & Responsiveness
- Tailwind CSS, matching the design aesthetic from [point 6.3](requirements.md#point-6-3) (sleek, minimal, polished, snappy)
- Responsive: mobile, tablet, desktop
- WCAG 2.1 AA compliant: semantic HTML, sufficient colour contrast, keyboard navigable, screen reader labels on forms

#### 7.4 Theme Toggle
- Admin setting: enable/disable starter theme
- When disabled: only API is active; Blade routes return 404 or redirect
- When enabled: Blade routes serve the theme

### Acceptance Criteria
- All five pages render correctly and match the design aesthetic
- Theme toggle in admin settings enables/disables the frontend
- Pages pass manual responsive and accessibility spot-checks
- Contact form sends email to the configured address

---

## Phase 8 — Testing, CI & Release

**Dependencies:** All Phase 1–7 complete

### Deliverables

#### 8.1 Automated Tests
- **Pest PHP unit tests** for isolated logic (see [point 9.1](requirements.md#point-9-1)):
  - WXR parsing, slug normalisation, idempotent duplicate detection
  - Subscription category matching (empty array = all)
  - API key rate-limit calculation
  - Role/permission helpers
  - AI provider key encryption/decryption
- **Pest feature tests** for HTTP routes and API endpoints (see [point 9.2](requirements.md#point-9-2)):
  - All public read endpoints
  - All write endpoints with auth and role enforcement
  - Subscribe/confirm/unsubscribe flow
  - Post-publish notification dispatching
  - AI generation endpoint (with HTTP fakes)
  - WordPress import (with WXR fixture)
- **Pest browser tests** for starter theme smoke coverage

#### 8.2 CI Pipeline
- GitHub Actions workflow (see [point 8.2](requirements.md#point-8-2)):
  - Run `pest` on PHP 8.3, 8.4, 8.5 matrix
  - Run `./vendor/bin/pint --test` for code style
  - Block merge on failure

#### 8.3 Docker Compose & Documentation
- Finalise `docker-compose.yml` with all four services: app, postgres, redis, queue worker
- Write README with quick-start instructions
- Write upgrade/migration notes for future releases
- Publish API docs on the landing site (see [point 7.1](requirements.md#point-7-1))

#### 8.4 Manual QA Pass
Execute the manual test cases from [point 9.2](requirements.md#point-9-2):
- Full WordPress import against a real WXR export
- Send test email from admin with production-like SMTP
- Complete double opt-in subscription (confirm + unsubscribe)
- Publish a post and verify notification content and category filtering
- Toggle theme on/off and spot-check responsive layout

### Acceptance Criteria
- `pest` passes locally and in CI
- `pint --test` passes
- `docker compose up` works on a fresh clone with only `example.env`
- Manual QA checklist signed off

---

## Phase 9 — Future Phases (v2+)

**Dependencies:** v1 released

### 9.1 Frontend Template Ecosystem
- Additional pre-built themes beyond the starter
- Theme marketplace or download system
- Theme configuration options in admin

### 9.2 Blogravel Cloud
- Managed AWS-based hosting with monthly subscription
- Automated deployment pipeline
- Centralised monitoring and crash recovery
- SLA targets for uptime

For detailed scope, see [point 3.2](requirements.md#point-3-2).
