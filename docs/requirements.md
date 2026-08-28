
![Logo](../designs/assets/images/banner.png)
# Blogravel
## Project Requirements Document (PRD)

## 1. Document Control
<a id="point-1-1"></a>

### 1.1 Document Metadata
- **Project Name:** Blogravel
- **Author(s):** Alexander Zaborski
- **Status:** 🟠Development 
- **Version:** 0.2
- **Date Created:** 31/05/2026
- **Last Updated:** 28/08/2026
- **Repository Link:** https://github.com/Azab-Inc/blogravel/
- **Webapp Link:** https://app.blogravel.azaber.com/
- **Landing page Link:** https://blogravel.azaber.com/

### 1.2 Version History
| Version | Date | Author | Description of Changes |
| ------- | ---- | ------ | ---------------------- |
| 0.1     | 31/05/2026 | Alexander Zaborski | Initial Draft |
| 0.2     | 28/08/2026 | Alexander Zaborski | Added Soro integration, RSS/Atom feeds, multi-tenant architecture, Stripe billing, RFC 9457 error format, Blade component theme system, draft previews, webhooks |

## 2. Executive Summary
### 2.1 Project Overview
- **Purpose:** A free, self-hosted, open-source blogging platform built as a lightweight, privacy-first alternative to WordPress, with an optional hosted platform.
- **Summary:** Blogravel is an API-first blogging platform that makes it easy to migrate from WordPress and run your own blog without relying on third-party services. It offers one-click WordPress import, AI-assisted content generation via Soro and other providers, granular mailing list subscriptions, and a multi-tenant architecture for both self-hosted and managed deployments.
- **Core Function:** Enabling users to self-host (or use a managed platform) a full-featured blog with seamless WordPress migration, AI-assisted content generation, and advanced category-level email subscriptions.
- **Target Audience:** Developers and technical users who value self-hosting and API-first architecture. Non-technical users are the long-term audience for the hosted platform.
- **Value Proposition:** Free and open source, privacy-first by design, API-first with headless frontend support, one-click WordPress migration, integrated AI content generation (Soro and others), and flexible subscription/mailing list controls.

### 2.2 Objectives & Goals
- **Primary Goal:** Solve the friction of leaving WordPress by providing a free, self-hosted alternative with a seamless migration path and modern blogging features.
- **Key Features:**
  - One-click WordPress import (posts, pages, media, categories)
  - Bring-your-own-API integration for AI/automated post generation (Soro, Ollama, OpenAI, Anthropic, custom)
  - Granular mailing list subscriptions (readers can subscribe per-category)
  - RSS 2.0, Atom, and JSON feeds
  - Multi-tenant architecture (subdomain-based)
  - Stripe billing with tiered plans (toggleable via feature flag)

## 3. Scope 
### 3.1 Scope for first phase
- One-click WordPress import (posts, pages, media, categories)
- Bring-your-own-API integration for AI/automated post generation (Soro as first-class integration, extensible for other providers)
- Advanced mailing lists with per-category subscriptions
- Admin panel via Filament PHP
- API-first architecture — all frontends connect via REST API
- RSS 2.0, Atom, and JSON feeds (per-site, per-category, per-author)
- Multi-tenant architecture with subdomain routing
- Blade component-based theme system with starter theme
- Stripe billing with three tiers (Free, Pro, Business), toggleable via feature flag
- Draft previews via signed URLs and scoped API keys
- Webhook support for post and page content changes
- RFC 9457 Problem Details error responses
- Cursor pagination on all list endpoints

<a id="point-3-2"></a>

### 3.2 Scope for Future Phases
- Frontend template ecosystem — additional pre-built themes beyond the starter
- Blogravel Cloud — managed hosting offering (Laravel Cloud-based), monthly subscription, no self-hosting required
- GraphQL API endpoint
- Advanced analytics dashboard
- Multi-language content support
- Collaborative editing (real-time)

### 3.3 Assumptions & Constraints
- **Assumptions:** Users are comfortable with self-hosting and have access to a server to deploy on. Hosted platform users expect a SaaS-like onboarding experience.
- **Constraints:** Admin panel is locked to Filament PHP; starter theme is Blade-based; the platform is API-first so all frontends (including the starter theme) consume the same REST API; AI post generation requires users to supply their own API key/endpoint (except Soro which has a dedicated webhook integration); self-hosted and hosted versions have full feature parity (multi-tenancy is the only architectural difference).

## 4. Functional Requirements
### 4.1 Core Features Breakdown & User Flow

- **Feature Name:** WordPress Import
  - **Description:** Allows the admin to import all WordPress content into Blogravel via two methods: uploading a WXR export file, or connecting directly to the WordPress MySQL database via a connection string.
  - **Dependencies:** WXR/XML parser (PHP), local media storage, MySQL PDO driver
  - **Acceptance Criteria:**
    - **Method 1 — WXR File:** Admin can upload a `.xml` WXR export file via the Filament admin panel
      > **How to generate the file:** Run the following command from the WordPress root directory:
      > ```bash
      > wp export --dir=./
      > ```
      > This exports everything (all post types, statuses, authors, categories, tags, comments) into a `.xml` file in the current directory. The file will be named e.g. `yoursite.wordpress.2026-06-03.000.xml`. Upload that file to the import screen.
    - **Method 2 — DB Connection:** Admin can enter a WordPress MySQL connection string; Blogravel connects and reads directly from the WordPress database tables
    - Both methods import all posts (published & draft), pages, categories, tags, authors, custom fields, and comments
    - Media attachments are downloaded and stored locally regardless of import method
    - Import process shows progress and surfaces any errors without silent failures
    - Duplicate imports do not create duplicate entries (idempotent by slug)

- **Feature Name:** AI Post Generation
  - **Description:** Admin users can configure an AI provider and generate post drafts from a text prompt directly within the admin. Soro is a first-class integration with dedicated webhook support.
  - **Dependencies:** HTTP client for external APIs; Ollama for local model support
  - **Acceptance Criteria:**
    - Admin can add an AI provider via settings: Soro (webhook-based), external provider (API endpoint + key), or Ollama (local endpoint + selected model)
    - Multiple providers can be saved; one is set as active
    - User writes a prompt in the post editor and triggers generation
    - Generated content is inserted as a draft — not auto-published
    - Invalid credentials or unreachable endpoints surface a clear error message
    - Ollama integration works without an API key

- **Feature Name:** Soro Webhook Integration
  - **Description:** Blogravel exposes a webhook endpoint that Soro (trysoro.com) can POST SEO-optimized content to. The endpoint is generic enough to accept content from any AI publishing tool.
  - **Dependencies:** Webhook authentication (HCMAC shared secret), post creation pipeline
  - **Acceptance Criteria:**
    - `POST /api/webhooks/soro` accepts a rich payload: `{ title, content, excerpt, categories[], tags[], featured_image, metadata{ meta_title, meta_description }, status }`
    - Webhook authentication via HMAC shared secret in `X-Blogravel-Signature` header
    - Webhook secret is configurable in the AI provider settings
    - Incoming content is validated, mapped to Blogravel models, and created as a draft post
    - Invalid signatures return 401; malformed payloads return 422 with RFC 9457 problem details
    - The webhook endpoint is extensible — any AI content tool can use the same endpoint with its own webhook secret

- **Feature Name:** RSS, Atom & JSON Feeds
  - **Description:** Blogravel generates standard RSS 2.0, Atom, and JSON feeds for all published posts, with per-category and per-author feed variants. Built entirely with Laravel Blade views and XML generation — no third-party feed packages.
  - **Dependencies:** None (custom Blade views for XML/JSON rendering)
  - **Acceptance Criteria:**
    - `GET /feeds/posts.xml` — RSS 2.0 feed of all published posts
    - `GET /feeds/posts.atom` — Atom feed of all published posts
    - `GET /feeds/posts.json` — JSON feed of all published posts
    - `GET /feeds/categories/{slug}.xml` — RSS feed for a specific category
    - `GET /feeds/authors/{id}.xml` — RSS feed for a specific author
    - Feed items include title, content, excerpt, author, published date, categories, and featured image
    - Feed auto-discovery `<link>` tags are injected into the Blade starter theme's HTML head
    - Feed format is configurable in admin settings (RSS, Atom, or JSON as default)
    - Feeds respect tenant isolation on multi-tenant deployments

- **Feature Name:** Mailing Lists with Per-Category Subscriptions
  - **Description:** Readers subscribe via a public API endpoint, specifying which categories they want to follow. Subscription is confirmed via double opt-in email.
  - **Dependencies:** Mail service (SMTP-configurable), queue system for async email sending
  - **Acceptance Criteria:**
    - `POST /api/subscribe` accepts `{ email, categories: [] }` — empty array subscribes to all
    - Confirmation email is sent immediately on subscription request
    - Subscription is only activated after the reader clicks the confirmation link in their email
    - On post publish, notification emails are sent to all subscribers whose category selection matches
    - Unsubscribe link is included in every notification email

- **Feature Name:** Admin Panel (Filament PHP)
  - **Description:** Full-featured admin dashboard for managing all blog content, settings, users, and integrations.
  - **Dependencies:** Filament PHP, Laravel
  - **Acceptance Criteria:**
    - Three roles are enforced: Super Admin, Editor, Author
    - **Super Admin:** full access — settings, API keys, user management, AI providers, import, tenant management (on hosted platform)
    - **Editor:** can create, edit, and publish any post or page
    - **Author:** can create and manage only their own posts; cannot publish without editor approval
    - Role permissions are enforced on all admin routes and API write endpoints
    - Upgrade button displayed in top-right when billing feature flag is enabled and user is on Free plan

- **Feature Name:** API-First Architecture
  - **Description:** All blog data (posts, pages, categories, subscribers) is exposed via a REST API consumed by all frontends.
  - **Dependencies:** Laravel Sanctum, rate limiter, RFC 9457 error responses
  - **Acceptance Criteria:**
    - Public read endpoints exist for posts, pages, categories, tags, and individual resources
    - Cursor pagination on all list endpoints via Laravel's built-in `cursorPaginate()`
    - API key generation is available in Filament admin (name + optional rate limit per minute)
    - Rate limiting defaults to 100 requests/minute per key; configurable per key
    - API can be run without any key (open mode) but admin is shown a warning recommending key setup
    - All write/sensitive endpoints require authentication regardless of open mode
    - All error responses follow RFC 9457 Problem Details format (`application/problem+json`)
    - No API versioning for v1 (`/api/posts`); versioning added when breaking changes occur

- **Feature Name:** Draft Previews
  - **Description:** Unpublished draft posts can be shared and previewed by headless frontends without making content public.
  - **Dependencies:** Signed URL generation, scoped API keys
  - **Acceptance Criteria:**
    - **Signed URLs:** Admin can generate a time-limited, HMAC-signed URL for a draft post (e.g. `/api/posts/{slug}?token=abc123&expires=1735689600`). No auth required to view. Shareable with editors or clients.
    - **Scoped API keys:** Certain API keys have `draft_read` permission. Frontend includes the key and can fetch drafts via the normal API. Used for live preview mode in the connected frontend.
    - Signed URLs expire after a configurable duration (default: 24 hours)
    - Draft previews respect tenant isolation

- **Feature Name:** Webhooks
  - **Description:** Blogravel can POST content change notifications to external URLs when posts or pages are published, updated, or deleted.
  - **Dependencies:** Queue system for async delivery
  - **Acceptance Criteria:**
    - Admin can configure webhook URLs in Filament settings
    - Supported events: `post.published`, `post.updated`, `post.deleted`, `page.published`, `page.updated`, `page.deleted`
    - Webhook payload includes event type, timestamp, and the full resource data
    - Webhook delivery is retried on failure (3 retries with exponential backoff)
    - Webhook delivery logs are viewable in the admin panel

- **Feature Name:** Multi-Tenant Architecture
  - **Description:** A single Blogravel deployment serves multiple independent blogs (tenants). Each tenant is isolated by subdomain. Self-hosted instances run in single-tenant mode by default.
  - **Dependencies:** Subdomain routing middleware, `tenant_id` column on all content tables
  - **Acceptance Criteria:**
    - `TENANCY_MODE=multi` enables multi-tenant mode; `TENANCY_MODE=single` (default) runs single-tenant
    - Tenant is resolved from subdomain (e.g. `myblog.blogravel.com`)
    - All content tables have a `tenant_id` foreign key; queries are scoped via global scope
    - Tenant admin can invite users by email (invite link with token) or generate shareable invite codes
    - Invited users are scoped to the inviting tenant
    - API keys are scoped per tenant
    - Media uploads are stored in tenant-prefixed paths

- **Feature Name:** Stripe Billing
  - **Description:** Optional Stripe integration for the hosted platform. Toggleable via feature flag. Three subscription tiers with usage limits.
  - **Dependencies:** Laravel Cashier, Stripe API, feature flag (`BILLING_ENABLED`)
  - **Acceptance Criteria:**
    - Billing is disabled by default (`BILLING_ENABLED=false` in `.env`)
    - When enabled, Filament admin shows an "Upgrade" button for Free plan users
    - Three tiers:

      | Tier | Posts | Image Size | Users per Tenant | Custom Domain |
      |------|-------|------------|------------------|---------------|
      | Free | 50    | 2 MB       | 3                | No            |
      | Pro  | Unlimited | 10 MB  | 10               | Yes           |
      | Business | Unlimited | 25 MB | Unlimited     | Yes           |

    - Tenant limits are enforced via middleware and model observers (backend) and Filament UI feedback (frontend)
    - When a limit is reached, the API returns `403` with RFC 9457 problem details: `{ "type": "plan_limit", "title": "Plan limit reached", "detail": "Free plan allows 50 posts. Upgrade to Pro for unlimited." }`
    - Stripe webhook handles subscription creation, updates, cancellation, and failed payments
    - Self-hosted instances ignore the billing feature flag and have no limits

- **Feature Name:** Custom Theme System
  - **Description:** A Blade component-based theme override system. Users customize the starter theme by publishing and overriding individual Blade components.
  - **Dependencies:** Laravel Blade components, theme resolution middleware
  - **Acceptance Criteria:**
    - Active theme is set in admin settings (defaults to `starter`)
    - Theme directory: `resources/themes/{theme-name}/`
    - Themes override individual Blade components by matching the component name (e.g. `resources/themes/my-theme/components/post-card.blade.php` overrides the starter theme's `<x-post-card>`)
    - Theme can register additional CSS/JS via a `theme.json` manifest
    - If a component is not found in the active theme, the starter theme's version is used as fallback
    - Theme can be toggled off in admin settings, leaving only the API active (headless mode)

- **Feature Name:** Starter Theme (Blade)
  - **Description:** A minimal, toggleable Blade-based starter theme for users who want a working frontend out of the box. Consumes the internal API.
  - **Dependencies:** Laravel Blade, Tailwind CSS
  - **Acceptance Criteria:**
    - Includes pages: post list (home), single post, category page, subscribe form, contact page
    - Theme can be toggled off in admin settings, leaving only the API active
    - All pages are responsive across mobile, tablet, and desktop
    - Contact page submits to a configurable email address
    - RSS/Atom feed auto-discovery `<link>` tags in HTML head

### 4.2 User Flow & Navigation Map
- **Admin — Publish a Post:**
  Login → Dashboard → Posts → New Post → Write / Generate via AI → Assign Categories and Tags → Publish → Subscribers notified by email

- **Admin — WordPress Import:**
  Settings → Import → Upload WXR file or SQL connection string → Review summary → Confirm → Import runs → Success/error report

- **Admin — Configure AI Provider:**
  Settings → AI Providers → Add Provider → Select type (Soro / External / Ollama) → Enter endpoint + key → Save → Set as active

- **Admin — Configure Webhook:**
  Settings → Webhooks → Add webhook URL → Select events (post.published, etc.) → Save

- **Admin — Invite User (Multi-Tenant):**
  Settings → Users → Invite by email → User receives invite link → Creates account → Scoped to tenant

- **Reader — Subscribe:**
  Reader calls `POST /subscribe` with email + categories → Confirmation email sent → Reader clicks confirm link → Subscription activated

- **Reader — Browse (Starter Theme):**
  Home (post list) → Click post → Read post → Subscribe form / Contact page

- **AI Content — Soro Webhook:**
  Soro generates content → POSTs to `/api/webhooks/soro` → Blogravel validates signature → Creates draft post → Admin reviews and publishes

- _**Flowchart / Wireframe Link:**_ [_To be added_]

<a id="point-4-3"></a>

### 4.3 Data Requirements & Schema
- **Key Entities:**

  | Table | Key Fields |
  |---|---|
  | `tenants` | id, name, slug (subdomain), domain (nullable), plan (`free` \| `pro` \| `business`), billing_enabled, settings, created_at |
  | `users` | id, tenant_id (nullable — null for super admins), name, email, password, role (`super_admin` \| `editor` \| `author`), email_verified_at |
  | `posts` | id, tenant_id, title, slug, content, excerpt, status (`draft` \| `published`), author_id, featured_image (nullable), meta_title (nullable), meta_description (nullable), published_at |
  | `pages` | id, tenant_id, title, slug, content, status (`draft` \| `published`), meta_title (nullable), meta_description (nullable), published_at |
  | `categories` | id, tenant_id, name, slug, description |
  | `tags` | id, tenant_id, name, slug |
  | `post_category` | post_id, category_id |
  | `post_tag` | post_id, tag_id |
  | `comments` | id, tenant_id, post_id, author_name, author_email, body, status (`pending` \| `approved` \| `spam`) |
  | `media` | id, tenant_id, filename, path, mime_type, size, post_id (nullable) |
  | `subscribers` | id, tenant_id, first_name, last_name, email, confirmed, confirmation_token, created_at |
  | `subscriber_category` | subscriber_id, category_id — empty = subscribed to all |
  | `ai_providers` | id, tenant_id, name, type (`soro` \| `openai` \| `ollama` \| `custom`), endpoint (nullable), api_key (nullable, encrypted), webhook_secret (nullable), is_active |
  | `api_keys` | id, tenant_id, name, key_hash, abilities (JSON — e.g. `["read", "draft_read"]`), rate_limit_per_minute (nullable — default 100), last_used_at |
  | `settings` | id, tenant_id, key, value — global config (site name, theme toggle, contact email, billing, etc.) |
  | `webhooks` | id, tenant_id, url, events (JSON array), secret, is_active, last_triggered_at |
  | `invitations` | id, tenant_id, email, token, role, expires_at, accepted_at (nullable) |
  | `subscriptions` | id, tenant_id, stripe_id, stripe_status, stripe_plan, trial_ends_at (nullable), ends_at (nullable) |

- **Data Relations:**
  - `Tenant` HasMany `Users`, `Posts`, `Pages`, `Categories`, `Tags`, `Subscribers`, `ApiKeys`, `Webhooks`, `AiProviders`
  - `User` BelongsTo `Tenant` (nullable for super admins)
  - `Post` BelongsTo `User` (author), BelongsTo `Tenant`
  - `Post` HasMany `Comments`
  - `Post` BelongsToMany `Categories`
  - `Post` BelongsToMany `Tags`
  - `Post` HasMany `Media`
  - `Subscriber` BelongsToMany `Categories` (empty pivot = all categories)
  - `ApiKey` BelongsTo `Tenant`, has JSON `abilities` field
  - `Webhook` BelongsTo `Tenant`, has JSON `events` field
  - `Invitation` BelongsTo `Tenant`, BelongsTo `User` (who sent it)
  - `Subscription` BelongsTo `Tenant`

- **UML:** [Database UML diagram](../designs/database-uml.drawio)

## 5. Non-Functional Requirements
<a id="point-5-1"></a>

### 5.1 Performance & Scalability
- **Performance Targets:** No hard targets defined for v1 — performance is largely dependent on the user's hosting environment. The application must not block on slow operations (emails, imports); all such tasks must be offloaded to queues.
- **Queue System:** All async operations (email sending, subscription confirmations, post notifications, WordPress import jobs, webhook delivery) must run through a queue worker (e.g. Laravel Queue with database or Redis driver) to ensure no hanging requests and no lost messages.
- _**Scalability:**_ _Out of scope for v1 (self-hosted). Caching, CDN, and horizontal scaling will be addressed in Blogravel Cloud (v2)._

<a id="point-5-2"></a>

### 5.2 Security & Privacy
- **Authentication & Authorization:**
  - Admin panel: session-based authentication via Filament's built-in login (email + password)
  - Public API: authenticated via Laravel Sanctum tokens (Bearer token on protected endpoints); open read mode available with admin warning
  - Role-based access (Super Admin, Editor, Author) enforced on all admin routes and write API endpoints
  - API key abilities field controls scope (e.g. `read`, `write`, `draft_read`)
- **Data Protection:** Passwords hashed (bcrypt via Laravel default); AI provider API keys encrypted at rest; HTTPS enforced (responsibility of the hosting environment); subscriber emails stored only after double opt-in confirmation
- **Webhook Security:** HMAC shared secret authentication for incoming webhooks (Soro and others). Signature verified via `X-Blogravel-Signature` header.
- **Tenant Isolation:** All queries scoped via `tenant_id` global scope. Middleware resolves tenant from subdomain and injects into request context.
- _**Compliance Requirements:**_ _GDPR applies due to collection of subscriber email addresses. Requirements: double opt-in before storing email, unsubscribe link in every notification email, ability for subscribers to request deletion._

### 5.3 Reliability & Availability
- **Uptime:** Out of scope for v1 — uptime and crash recovery are the responsibility of the user's hosting environment. _(Blogravel Cloud in v2 will define SLA targets.)_
- **Backups:** Optional — admin can toggle automated database backups on/off in settings. When enabled, backups run on a configurable schedule and are stored locally (or to a path the admin specifies).

<a id="point-5-4"></a>

### 5.4 Compatibility & Accessibility
- **Supported Browsers/OS:** Starter theme must support all major modern browsers — Chrome, Firefox, Safari, Edge — across mobile, tablet, and desktop viewports. Admin panel (Filament) inherits Filament's own browser support.
- _**Accessibility Standards:**_ _Starter theme must follow WCAG 2.1 AA guidelines — semantic HTML, sufficient colour contrast, keyboard navigability, and screen reader compatibility._

## 6. User Interface & Wireframes
### 6.1 Design tools
- **UI Design Tool:** draw.io
- **Styling System:** Tailwind CSS and filament theme for custom admin panel styles.

### 6.2 Designs & Wireframes
- _**Wireframes:**_ _To be created in draw.io and saved to the [`designs`](../designs) 
- _**Mockups:**_ _To be added once designs are complete_

### 6.3 Interactive States & Feedback
- **Design Aesthetic:** Sleek and minimal but not bare-bones; should feel polished and intentional without heavy decoration or animations.
- **Hover & Active States:** Subtle transitions on interactive elements (buttons, links, cards) — e.g. slight brightness shift or underline on hover, gentle scale-down on click.
- **Transitions & Animations:** Smooth, short transitions (100–150ms) where used; nothing flashy — motion should feel purposeful and quick with a focus on a "snappy" feel.
- **Feedback Alerts:**
  - Toast notifications for all async actions (publish success, import complete, email sent, errors)
  - Inline validation errors on forms (shown on submit and on blur where appropriate)
  - Filament's default feedback behaviours retained as the baseline for the admin panel
  - Upgrade prompts when tenant limits are reached (Free plan users)

## 7. Technical Architecture & Tech Stack
### 7.1 Technical Overview
- **Application Type:** REST API + Web app (API-first; Blade starter theme consumes the same API as external frontends)
- **Hosting Solutions:**
  - **Self-hosted:** Docker Compose. A `docker-compose.yml` and `example.env` will be provided for users to deploy on their own server.
  - **Hosted (Blogravel Cloud):** Laravel Cloud. Multi-tenant deployment with subdomain routing. Media stored in S3-compatible object storage.
- **Third-Party Services:** Mail is user-configurable — supports any SMTP server or major providers (e.g. Mailgun, Resend, SendGrid) via environment variables. No provider is bundled or required.
- _**API Docs Link:**_ _To be published on the public Blogravel landing/docs site ([blogravel.azaber.com](https://blogravel.azaber.com))_

### 7.2 Tech Stack
- **Backend:** PHP 8.3+ (targets 8.5) with Laravel 13
  - **Packages (first-party Laravel):** Filament PHP (admin panel), Livewire (reactive UI within Filament), Laravel Sanctum (API token auth), Laravel Queue (async jobs via Redis driver), Laravel Cashier (Stripe billing, feature-flagged), Laravel Fortify (auth scaffolding)
  - **Dev packages:** Pest PHP v4 (testing), Laravel Pint (code style), Laravel Sail (Docker dev environment), Laravel Octane + FrankenPHP (HTTP server), Laravel Boost (MCP agent tooling), Collision (error display), Mockery (test mocking), Faker (test data generation)
  - **No third-party packages** — all dependencies are first-party Laravel or standard Laravel dev tooling
- **Frontend (Starter Theme):** Laravel Blade
  - **Libraries:** Tailwind CSS v4
- **Database:** PostgreSQL 18 (SQLite for local development)
- **Queue Driver:** Redis
- **Media Storage:** Local filesystem by default, S3-compatible object storage optional (AWS S3, MinIO, Laravel Cloud storage)
- _**External APIs/SDKs:**_

  | API/SDK | Purpose | Notes |
  |---------|---------|-------|
  | Soro (trysoro.com) | AI-powered SEO content autopublishing | Webhook integration with HMAC auth |
  | User-supplied AI provider (OpenAI, Anthropic, Ollama, etc.) | AI post generation | Admin configures endpoint + key; no vendor bundled |
  | Stripe | Subscription billing (hosted platform) | Feature-flagged via `BILLING_ENABLED` env var |
  | User-configured SMTP / Mailgun / Resend / SendGrid | Transactional email (subscription confirmations, post notifications) | Configured via `.env` and can be tested in admin panel with "send test email" option |

### 7.3 System Architecture Diagram
- _**Diagram Link:**_ _To be added to the `/designs` folder_
- **Architecture Overview:** Users deploy via Docker Compose (Laravel app + PostgreSQL + Redis + queue worker containers) for self-hosted, or Laravel Cloud for the hosted platform. The Laravel backend exposes a REST API consumed by all frontends. The Blade starter theme is an optional built-in frontend that hits the same API. The Filament admin panel runs on the same Laravel instance. Queue workers (Redis-backed) handle all async jobs — emails, import processing, notifications, webhook delivery. Mailpit is included in the Docker stack for local email preview. Mail is routed through the user's chosen SMTP/provider configured in `.env`. On multi-tenant deployments, tenant is resolved from subdomain and all queries are scoped via `tenant_id`.

## 8. Deployment & Infrastructure

### 8.1 Hosting & Environment Strategy
- **Hosting Model (v1):**
  - **Self-hosted:** Users deploy on their own server using the provided `docker-compose.yml` and `example.env` (see [point 7.1](#point-7-1)). `TENANCY_MODE=single`. No billing. No limits.
  - **Hosted (Blogravel Cloud):** Managed via Laravel Cloud. `TENANCY_MODE=multi`. Stripe billing enabled. S3 media storage. Subdomain routing.
- **Container Stack (self-hosted):** Laravel app, PostgreSQL, Redis, queue worker, and Mailpit run as separate Docker Compose services, matching the architecture in [point 7.3](#point-7-3).
- **Environments:** Local development mirrors production as closely as possible — same Docker Compose stack (app, PostgreSQL, Redis, queue worker, Mailpit), same environment variables (PostgreSQL as DB, Redis for queue/cache/session, Mailpit for mail). Production is the user's self-hosted instance or Laravel Cloud, configured through `.env` (mail, database, API keys, theme toggle, tenancy mode, billing, etc.).
- _**Blogravel Cloud:**_ _Managed via Laravel Cloud. Multi-tenant deployment with subdomain routing. See [point 8.1](#point-8-1)._

### 8.2 CI/CD Pipeline & DevOps
- **CI/CD Platform:** GitHub Actions — automated checks on pull requests and merges to the main branches.
- **Pipelines (v1):**

  | Pipeline | Platform | Purpose |
  | -------- | -------- | ------- |
  | Quality checks | GitHub Actions | Run tests, static analysis, and other checks before changes are merged |

- **Deployment Process (v1):** Users pull or build from a release tag and run `docker compose up` (or equivalent) on their server. Repository docs will cover env setup, migrations, and queue workers. Hosted platform deploys via Laravel Cloud.
- _**Blogravel Cloud deploy pipeline:**_ _Managed via Laravel Cloud. See [point 8.1](#point-8-1)._

### 8.3 Domain & DNS Configuration
- **Public URLs (reference deployment):** Landing/docs at [blogravel.azaber.com](https://blogravel.azaber.com); app at [app.blogravel.azaber.com](https://app.blogravel.azaber.com/) (see [point 1.1](#point-1-1)).
- **Self-hosted users:** Bring their own domain; point DNS at the server running Docker Compose. HTTPS is the user's responsibility (reverse proxy or TLS termination at the host — see [point 5.2](#point-5-2)).
- **Hosted platform:** Each tenant gets a subdomain (e.g. `myblog.blogravel.com`). Wildcard SSL certificate managed by Laravel Cloud.

## 9. Testing & Quality Assurance Plan
### 9.1 Unit Testing Strategy
- **Testing Framework:** [Pest PHP](https://pestphp.com/) (v4) with the Laravel plugin — all tests live under `tests/Unit` and `tests/Feature`. Run locally via `php artisan test` or `./vendor/bin/pest`.
- **Code Style:** [Laravel Pint](https://laravel.com/docs/pint) — enforced in CI alongside tests (see [point 8.2](#point-8-2)).
- **Target Areas:** Isolated logic that should not require HTTP or a full stack, including:
  - WXR/XML parsing and import mapping (slug normalisation, idempotent duplicate detection)
  - Subscription matching (category filters, empty array = all)
  - API key rate-limit calculation
  - Role/permission helpers (Super Admin, Editor, Author)
  - Encryption/decryption of stored AI provider keys
  - Tenant scope isolation (queries scoped by `tenant_id`)
  - RFC 9457 problem detail response formatting
  - HMAC webhook signature verification
  - Subscription limit enforcement (50/Unlimited posts, 2/10/25 MB image size, 3/10/Unlimited users)
- **Factories & Fakes:** Use Laravel model factories and `Mail::fake()`, `Queue::fake()`, and HTTP fakes for external AI/SMTP calls — no live third-party services in automated tests.
- _**Coverage Target:**_ _No fixed percentage for v1 — priority is meaningful tests on the critical paths in [point 9.2](#point-9-2), not blanket line coverage._

<a id="point-9-2"></a>

### 9.2 Functional Testing
- **Feature Tests (HTTP / API):** Pest feature tests against Laravel routes — primary test layer for v1. Use `RefreshDatabase` (or equivalent) for tests that touch the schema. Cover request/response shape, status codes, auth middleware, and validation errors.
- **Integration Testing:** Database persistence, Eloquent relationships (see [point 4.3](#point-4-3)), queued jobs (import, confirmation email, post notifications, webhook delivery), and Filament admin actions where they map to the same underlying models/API.
- **Automated Browser / E2E:** Pest browser tests for smoke coverage of the starter theme (home, single post, category, subscribe form, contact) — confirm pages render and core interactions work. Full Filament UI E2E is not required for v1; admin flows are covered by feature tests plus targeted manual QA.
- **Critical Paths to Test (automated where practical):**

  | Area | What to verify |
  | ---- | -------------- |
  | WordPress import | WXR upload and DB-connection paths import content; duplicates skipped by slug; errors surfaced |
  | AI post generation | Provider config saved; generation returns draft content; invalid endpoint/key returns clear error |
  | Soro webhook | Valid HMAC signature creates draft; invalid signature returns 401; malformed payload returns 422 |
  | Mailing lists | `POST /subscribe` → confirmation queued; activation only after confirm link; publish triggers matching notifications; unsubscribe link present |
  | RSS/Atom feeds | Feeds render correctly; per-category and per-author feeds work; auto-discovery tags present in HTML |
  | Roles & auth | Super Admin / Editor / Author boundaries on admin and write API routes |
  | Public API | Read endpoints for posts, pages, categories, tags; cursor pagination; rate limiting per API key; write endpoints require auth |
  | Draft previews | Signed URLs work and expire; scoped API keys can read drafts; unsigned requests cannot |
  | Webhooks | Post/page events trigger webhook delivery; retry on failure; delivery logs visible |
  | Multi-tenant | Tenant isolation (queries scoped by tenant_id); subdomain routing; invitation flow |
  | Billing | Limit enforcement (posts, image size, users); upgrade prompts; Stripe webhook handling |
  | Starter theme | Theme toggle off leaves API-only mode; contact form validation and submission |
  | Error responses | All errors follow RFC 9457 format with correct status codes |

- **Manual Test Cases (before release):**
  - Run a full WordPress import against a real WXR export and confirm media, comments, and categories in the admin UI
  - Send test email from admin settings — verify in Mailpit dashboard at `http://localhost:8025`
  - Complete double opt-in subscription in a real mailbox (confirm + unsubscribe)
  - Publish a post and confirm notification email content and category filtering
  - Toggle starter theme on/off and spot-check responsive layout on mobile, tablet, and desktop
  - Test Soro webhook with a real payload (or simulated payload with correct HMAC signature)
  - Verify RSS feed renders in a feed reader (e.g. Feedly)
  - Test tenant invitation flow (email + invite code)
  - Test subscription limit enforcement on Free plan

### 9.3 Non-Functional Testing
- **Performance & Load Testing:** No formal load targets for v1 (see [point 5.1](#point-5-1)). Sanity-check that publish, subscribe, and import endpoints return without blocking on queue/mail work. Optional Lighthouse run on the starter theme home page before release — no hard score gate.
- **Responsiveness Verification:** Manual pass on starter theme pages across Chrome, Firefox, Safari, and Edge at mobile, tablet, and desktop widths (see [point 5.4](#point-5-4)).
- **Accessibility Check:** Manual WCAG 2.1 AA spot-check on the starter theme — keyboard navigation, focus order, colour contrast, and screen reader labels on forms (subscribe, contact). Filament inherits its own accessibility baseline.
- **CI Enforcement:** GitHub Actions runs the test suite (PHP 8.3–8.5 matrix) and Pint on pull requests and merges to `main` / `develop` (see [point 8.2](#point-8-2)). Changes must pass CI before merge.

## 10. Project Milestones & Timeline
### 10.1 Key Milestones
- **Project Kickoff:** 30/05/2026
- **Core MVP Development:** TBD
- **Testing & QA:** TBD
- **Launch Date (v1.0):** TBD

### 10.2 Release & Rollout Plan
- **Beta Testing:** Feedback collected via GitHub issues on the public repository.
- **Production Rollout Strategy:** Tagged GitHub releases. Users pull or build from a release tag and run `docker compose up` on their own server — see [point 8.2](#point-8-2). Migration and upgrade notes published with each release.
- **Post-Launch Monitoring:** No centralized monitoring for v1 (self-hosted, see [point 5.3](#point-5-3)). Feedback and issue reports collected via GitHub. Release announcements posted on the landing page at [blogravel.azaber.com](https://blogravel.azaber.com).

### 10.3 Future Phases
- **Phase 2 Features:** Frontend template ecosystem (additional pre-built themes beyond the starter) and Blogravel Cloud (managed Laravel Cloud-based hosting with monthly subscription, no self-hosting required) — see [point 3.2](#point-3-2).
- **Long-term Roadmap:** GraphQL API endpoint, advanced analytics dashboard, multi-language content support, collaborative editing — see [point 3.2](#point-3-2).

## 11. Appendix
### 11.1 Glossary of Terms
- **API-first:** Architecture approach where the API is the primary consumer-facing interface; all frontends (including the starter theme) consume the same API.
- **Blade:** Laravel's templating engine used to build the starter theme and custom theme components.
- **Cursor Pagination:** A pagination method that uses a cursor (encoded string) instead of page numbers, providing better performance on large datasets. Implemented via Laravel's built-in `cursorPaginate()`.
- **Docker Compose:** Tool for defining and running multi-container Docker applications; used for Blogravel's self-hosted deployment stack (Laravel app, PostgreSQL, Redis, queue worker).
- **Double opt-in:** Email verification process requiring the subscriber to click a confirmation link before their subscription is activated.
- **Filament PHP:** Admin panel framework for Laravel used as Blogravel's admin interface.
- **GitHub Actions:** CI/CD platform for running automated tests and quality checks on pull requests and merges.
- **HMAC:** Hash-based Message Authentication Code — used to verify webhook signatures for secure content delivery.
- **Idempotent:** Property where repeated operations produce the same result; WordPress imports are idempotent by slug.
- **Laravel:** PHP web application framework powering Blogravel's backend, admin panel, and API.
- **Mailpit:** Local email testing tool included in the Docker development stack. Intercepts all outgoing mail and provides a web interface at `http://localhost:8025` for previewing emails.
- **Laravel Cashier:** First-party Laravel package for Stripe subscription billing integration.
- **Laravel Cloud:** Managed hosting platform for Laravel applications, used for the hosted Blogravel platform.
- **Laravel Fortify:** First-party Laravel package for authentication scaffolding (login, registration, password reset, email verification, two-factor authentication).
- **Laravel Octane:** First-party Laravel package for serving applications via high-performance servers (FrankenPHP).
- **Laravel Pint:** First-party Laravel PHP code style fixer, enforced in CI alongside tests.
- **Laravel Sanctum:** First-party Laravel lightweight API token authentication system used for API key management.
- **Laravel Queue:** First-party Laravel unified API for queueing async jobs (emails, imports, notifications, webhook delivery) backed by Redis.
- **Laravel Sail:** First-party Laravel lightweight Docker development environment.
- **Livewire:** Full-stack framework for building reactive UI components within Filament and Laravel.
- **MVP:** Minimum Viable Product — the initial feature set defined in v1 scope.
- **Ollama:** Local AI model runner for on-device LLM inference without requiring a third-party API key.
- **Pest PHP:** PHP testing framework (v4) with Laravel plugin used for unit, feature, and browser tests.
- **PDO:** PHP Data Objects — database access abstraction layer used for WordPress MySQL connection imports.
- **PostgreSQL:** Open-source relational database used as Blogravel's primary data store.
- **Problem Details (RFC 9457):** IETF standard for machine-readable error responses in HTTP APIs. Uses `application/problem+json` content type with `type`, `title`, `status`, and `detail` fields.
- **Queue worker:** Background process that dequeues and processes async jobs (email sending, import processing, notifications, webhook delivery).
- **Rate limiting:** Restricting the number of API requests a client can make within a per-minute window, configurable per API key. Default: 100 req/min.
- **Redis:** In-memory data store used as the queue driver and cache backend.
- **REST API:** Representational State Transfer API — Blogravel exposes RESTful endpoints consumed by all frontends.
- **RBAC / Role-based access:** Permission system with three tiers — Super Admin (full access), Editor (publish any post), Author (own posts only).
- **RFC 9457:** See Problem Details.
- **Soro:** AI-powered SEO content autopublishing service (trysoro.com). Integrates with Blogravel via webhook for automated content publishing.
- **SMTP:** Simple Mail Transfer Protocol — configurable mail driver for sending transactional emails (confirmations, notifications).
- **Subdomain Routing:** Multi-tenant URL strategy where each tenant is identified by a unique subdomain (e.g. `myblog.blogravel.com`).
- **Tailwind CSS:** Utility-first CSS framework used to style the starter theme.
- **Tenant:** An independent blog within a multi-tenant Blogravel deployment. Each tenant has isolated content, users, and settings.
- **Tenant Isolation:** Data separation strategy ensuring one tenant cannot access another tenant's data. Implemented via `tenant_id` column and global query scopes.
- **WCAG 2.1 AA:** Web Content Accessibility Guidelines 2.1, Level AA — the accessibility standard the starter theme must meet.
- **Webhook:** A mechanism for one application to send real-time HTTP POST notifications to another application when events occur.
- **WXR:** WordPress eXtended RSS — XML export format containing posts, pages, media, categories, and comments for import into Blogravel.

### 11.2 References & External Links
**Frameworks & Languages:**
  - [PHP](https://www.php.net/)
  - [Laravel](https://laravel.com/) ([Blade docs](https://laravel.com/docs/blade), [Sanctum docs](https://laravel.com/docs/sanctum), [Queue docs](https://laravel.com/docs/queues), [Pint docs](https://laravel.com/docs/pint), [Cashier docs](https://laravel.com/docs/billing))
  - [Filament PHP](https://filamentphp.com/)
  - [Livewire](https://livewire.laravel.com/)
  - [Tailwind CSS](https://tailwindcss.com/)

**Infrastructure & Tools:**
  - [Docker Compose](https://docs.docker.com/compose/)
  - [PostgreSQL](https://www.postgresql.org/)
  - [Redis](https://redis.io/)
  - [GitHub Actions](https://github.com/features/actions)
  - [Laravel Cloud](https://cloud.laravel.com/)
  - [Ollama](https://ollama.ai/)

**Testing:**
  - [Pest PHP](https://pestphp.com/)

**Standards:**
  - [RFC 9457 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc9457.html)

**Mail Providers (user-configurable):**
  - [Mailgun](https://www.mailgun.com/)
  - [Resend](https://resend.com/)
  - [SendGrid](https://sendgrid.com/)

**AI Providers (user-supplied):**
  - [Soro](https://trysoro.com/)
  - [OpenAI](https://openai.com/)
  - [Anthropic](https://www.anthropic.com/)

**Billing:**
  - [Stripe](https://stripe.com/)
  - [Laravel Cashier](https://laravel.com/docs/billing)

**Feeds:**
  - Custom Blade views for RSS 2.0, Atom, and JSON feed generation — no third-party packages
