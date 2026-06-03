
# Blogravel
## Project Requirements Document (PRD)

## 1. Document Control
### 1.1 Document Metadata
- **Project Name:** Blogravel
- **Author(s):** Alexander Zaborski
- **Status:** 🟠Development 
- **Version:** 0.1
- **Date Created:** 31/05/2026
- **Last Updated:** 31/05/2026
- **Repository Link:** https://github.com/Azab-Inc/blogravel/
- **Webapp Link:** https://app.blogravel.azaber.com/
- **Landing page Link:** https://blogravel.azaber.com/

### 1.2 Version History
| Version | Date | Author | Description of Changes |
| ------- | ---- | ------ | ---------------------- |
| 1.0     |   31/05/2026   | Alexander Zaborski | Initial Draft  |

## 2. Executive Summary
### 2.1 Project Overview
- **Purpose:** A free, self-hosted blogging platform built as a lightweight, privacy-first alternative to WordPress.
- **Summary:** Blogravel is a self-hosted blogging platform that makes it easy to migrate from WordPress and run your own blog without relying on third-party services. It offers one-click WordPress import, bring-your-own-API post generation, and granular mailing list subscriptions.
- **Core Function:** Enabling users to self-host a full-featured blog with seamless WordPress migration, optional AI-assisted content generation via a user-supplied API, and advanced category-level email subscriptions.
- **Target Audience:** Existing WordPress users looking for something less bloated, as well as new bloggers who want a lightweight, self-hosted starting point. Budget: free (self-hosted, infrastructure costs only).
- **Value Proposition:** Free to use, privacy-first by design, easy migration from WordPress via one-click import, and more flexible subscription/mailing list controls than WordPress offers out of the box.

### 2.2 Objectives & Goals
- **Primary Goal:** Solve the friction of leaving WordPress by providing a free, self-hosted alternative with a seamless migration path and modern blogging features.
- **Key Features:**
  - One-click WordPress import (posts, pages, media, categories)
  - Bring-your-own-API integration for AI/automated post generation
  - Granular mailing list subscriptions (readers can subscribe per-category)

## 3. Scope 
### 3.1 Scope for first phase
- One-click WordPress import (posts, pages, media, categories)
- Bring-your-own-API integration for AI/automated post generation
- Advanced mailing lists with per-category subscriptions
- Admin panel via Filament PHP
- API-first architecture — all frontends connect via API
- Basic starter theme in Blade templates (toggleable on/off)

### 3.2 Scope for Future Phases
- Frontend template ecosystem — additional pre-built themes beyond the starter
- Blogravel Cloud — managed hosting offering (AWS-based), monthly subscription, no self-hosting required

### 3.3 Assumptions & Constraints
- **Assumptions:** Users are comfortable with self-hosting and have access to a server to deploy on.
- **Constraints:** Admin panel is locked to Filament PHP; starter theme is Blade-based; the platform is API-first so all frontends (including the starter theme) consume the same API; Blogravel Cloud is explicitly out of scope for v1; AI post generation requires users to supply their own API key/endpoint — no vendor is bundled.

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
  - **Description:** Admin users can configure an AI provider and generate post drafts from a text prompt directly within the admin.
  - **Dependencies:** HTTP client for external APIs; Ollama for local model support
  - **Acceptance Criteria:**
    - Admin can add an AI provider via settings: external provider (API endpoint + key) or Ollama (local endpoint + selected model)
    - Multiple providers can be saved; one is set as active
    - User writes a prompt in the post editor and triggers generation
    - Generated content is inserted as a draft — not auto-published
    - Invalid credentials or unreachable endpoints surface a clear error message
    - Ollama integration works without an API key

- **Feature Name:** Mailing Lists with Per-Category Subscriptions
  - **Description:** Readers subscribe via a public API endpoint, specifying which categories they want to follow. Subscription is confirmed via double opt-in email.
  - **Dependencies:** Mail service (SMTP-configurable), queue system for async email sending
  - **Acceptance Criteria:**
    - `POST /subscribe` accepts `{ email, categories: [] }` — empty array subscribes to all
    - Confirmation email is sent immediately on subscription request
    - Subscription is only activated after the reader clicks the confirmation link in their email
    - On post publish, notification emails are sent to all subscribers whose category selection matches
    - Unsubscribe link is included in every notification email

- **Feature Name:** Admin Panel (Filament PHP)
  - **Description:** Full-featured admin dashboard for managing all blog content, settings, users, and integrations.
  - **Dependencies:** Filament PHP, Laravel
  - **Acceptance Criteria:**
    - Three roles are enforced: Super Admin, Editor, Author
    - **Super Admin:** full access — settings, API keys, user management, AI providers, import
    - **Editor:** can create, edit, and publish any post or page
    - **Author:** can create and manage only their own posts; cannot publish without editor approval
    - Role permissions are enforced on all admin routes and API write endpoints

- **Feature Name:** API-First Architecture
  - **Description:** All blog data (posts, pages, categories, subscribers) is exposed via a REST API consumed by all frontends.
  - **Dependencies:** Laravel Sanctum and other middleware, rate limiter
  - **Acceptance Criteria:**
    - Public read endpoints exist for posts, pages, categories, tags, and individual resources
    - API key generation is available in admin settings (name + optional rate limit per minute)
    - Rate limiting is configurable per key (requests/minute); defaults to unlimited
    - API can be run without any key (open mode) but admin is shown a warning recommending key setup
    - All write/sensitive endpoints require authentication regardless of open mode

- **Feature Name:** Starter Theme (Blade)
  - **Description:** A minimal, toggleable Blade-based starter theme for users who want a working frontend out of the box. Consumes the internal API.
  - **Dependencies:** Laravel Blade, Tailwind CSS
  - **Acceptance Criteria:**
    - Includes pages: post list (home), single post, category page, subscribe form, contact page
    - Theme can be toggled off in admin settings, leaving only the API active
    - All pages are responsive across mobile, tablet, and desktop
    - Contact page submits to a configurable email address

### 4.2 User Flow & Navigation Map
- **Admin — Publish a Post:**
  Login → Dashboard → Posts → New Post → Write / Generate via AI → Assign Categories and Tags → Publish → Subscribers notified by email

- **Admin — WordPress Import:**
  Settings → Import → Upload WXR file or SQL connection string → Review summary → Confirm → Import runs → Success/error report

- **Admin — Configure AI Provider:**
  Settings → AI Providers → Add Provider → Select type (External / Ollama) → Enter endpoint + key → Save → Set as active

- **Reader — Subscribe:**
  Reader calls `POST /subscribe` with email + categories → Confirmation email sent → Reader clicks confirm link → Subscription activated

- **Reader — Browse (Starter Theme):**
  Home (post list) → Click post → Read post → Subscribe form / Contact page

- _**Flowchart / Wireframe Link:**_ [_To be added_]

### 4.3 Data Requirements & Schema
- **Key Entities:**

  | Table | Key Fields |
  |---|---|
  | `users` | id, name, email, password, role (`super_admin` \| `editor` \| `author`) |
  | `posts` | id, title, slug, content, excerpt, status (`draft` \| `published`), author_id, published_at |
  | `pages` | id, title, slug, content, status |
  | `categories` | id, name, slug, description |
  | `tags` | id, name, slug |
  | `post_category` | post_id, category_id |
  | `post_tag` | post_id, tag_id |
  | `comments` | id, post_id, author_name, author_email, body, status (`pending` \| `approved` \| `spam`) |
  | `media` | id, filename, path, mime_type, size, post_id (nullable) |
  | `subscribers` | id, first_name, last_name. email, confirmed, confirmation_token, created_at |
  | `subscriber_category` | subscriber_id, category_id — empty = subscribed to all |
  | `ai_providers` | id, name, type (`openai` \| `ollama` \| `custom`), endpoint, api_key (encrypted), is_active |
  | `api_keys` | id, name, key_hash, rate_limit_per_minute (nullable), last_used_at |
  | `settings` | key, value — global config (site name, theme toggle, contact email, etc.) |

- **Data Relations:**
  - `Post` BelongsTo `User` (author)
  - `Post` HasMany `Comments`
  - `Post` BelongsToMany `Categories`
  - `Post` BelongsToMany `Tags`
  - `Post` HasMany `Media`
  - `Subscriber` BelongsToMany `Categories` (empty pivot = all categories)

- **UML:** _[To be added]_

## 5. Non-Functional Requirements
### 5.1 Performance & Scalability
- **Performance Targets:** No hard targets defined for v1 — performance is largely dependent on the user's hosting environment. The application must not block on slow operations (emails, imports); all such tasks must be offloaded to queues.
- **Queue System:** All async operations (email sending, subscription confirmations, post notifications, WordPress import jobs) must run through a queue worker (e.g. Laravel Queue with database or Redis driver) to ensure no hanging requests and no lost messages.
- _**Scalability:**_ _Out of scope for v1 (self-hosted). Caching, CDN, and horizontal scaling will be addressed in Blogravel Cloud (v2)._

### 5.2 Security & Privacy
- **Authentication & Authorization:**
  - Admin panel: session-based authentication via Filament's built-in login (email + password)
  - Public API: authenticated via Laravel Sanctum tokens (Bearer token on protected endpoints); open read mode available with admin warning
  - Role-based access (Super Admin, Editor, Author) enforced on all admin routes and write API endpoints
- **Data Protection:** Passwords hashed (bcrypt via Laravel default); AI provider API keys encrypted at rest; HTTPS enforced (responsibility of the hosting environment); subscriber emails stored only after double opt-in confirmation
- _**Compliance Requirements:**_ _GDPR applies due to collection of subscriber email addresses. Requirements: double opt-in before storing email, unsubscribe link in every notification email, ability for subscribers to request deletion._

### 5.3 Reliability & Availability
- **Uptime:** Out of scope for v1 — uptime and crash recovery are the responsibility of the user's hosting environment. _(Blogravel Cloud in v2 will define SLA targets.)_
- **Backups:** Optional — admin can toggle automated database backups on/off in settings. When enabled, backups run on a configurable schedule and are stored locally (or to a path the admin specifies).

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

## 7. Technical Architecture & Tech Stack
### 7.1 Technical Overview
- **Application Type:** [e.g. REST api, Web app]
- **Hosting solution:** [e.g VPS, Cloud provider (AWS, Azure)]
- **Third-Party Services:** [e.g. SendGrid for emails, Hostinger VPS for server hosting]
- _**API Docs Link:**_ [_[Link to Swagger UI or Postman Collection]_]
### 7.2 Tech Stack
- **Frontend:** [e.g. Blazor, Angular]
  - Libraries: [e.g. Tailwind CSS]
- **Backend:** [e.g. .NET Core, PHP with Symfony]
  - Libraries: [e.g. Entity Framework]
- **Database:** [e.g. SQLite, MySQL, PostgreSQL]
- _**External APIs/SDKs:**_ 
  |API/SDK|Purpose|Relation|
  |-------|-------|--------|
  |e.g Stripe|e.g Payments|e.g CashoutService|

### 7.3 System Architecture Diagram
- _**Diagram Link:**_ [_[Link to architecture flow chart or Miro board]_]
- **Architecture Overview:** [Brief description of how frontend, backend, database, and third-party systems interact]

## 8. Deployment & Infrastructure
### 8.1 Hosting & Environment Strategy
- **Hosting Provider:** [e.g. Hostinger VPS, Cloudflare Pages, AWS]
- **Operating System / Containerization:** [e.g. Debian Linux, Docker container setup]
- **Environments:** [e.g. Development (local), Test/Staging, Production]


### 8.2 CI/CD Pipeline & DevOps
- **CI/CD Platform:** [e.g. GitHub Actions, Jenkins, none (manual deploy)]
- _**Pipelines:**_
  |Pipeline|Platform|Purpose|Order|
  |--------|--------|-------|-----|
  |[e.g Deploy to prod]|[e.g jenkins]|[e.g Upload main to prod]|[e.g Last]|

- **Deployment Process:** [e.g. Automatic on merge to main, or manual run script]

### 8.3 Domain & DNS Configuration
- **Domain Name:** [e.g. azaber.com, profitwithcode.com]
- **Registrar / DNS Manager:** [e.g. Cloudflare, Namecheap]
- **SSL / Security:** [e.g. Let's Encrypt SSL certificates, Cloudflare Proxy]

## 9. Testing & Quality Assurance Plan
### 9.1 Unit Testing Strategy
- **Testing Framework(s):** [e.g. xUnit, Jest, MSTest]
- **Target Areas:** [e.g. Calculation helpers, core utility classes]
- _**Coverage Target:**_ [_[e.g. Minimum 80% coverage on backend logic]_]

### 9.2 Functional Testing
- **Unit Testing:** [e.g. Frameworks used, target areas, mock functions]
- **Integration Testing:** [e.g. Testing database connections, API endpoint integrations]
- **Automated E2E/API Tool:** [e.g. Playwright, Cypress, Postman, none]
- **Critical Paths to Test:** [e.g. Entire checkout flow, invoice generation, user login]
- **Manual Test Cases:** [e.g. Submit form with invalid inputs and verify error validation displays]

### 9.3 Non Functional Testing
- **Performance & Load Testing:** [e.g. Run Lighthouse audit on build, verify page loads in < 1.5s under throttling]
- **Responsiveness Verification:** [e.g. Test interface on mobile, tablet, and desktop viewports]
- _**Accessibility Check:**_ [_[e.g. Validate keyboard navigation and screen reader contrast compliance]_]

## 10. Project Milestones & Timeline
### 10.1 Key Milestones
- **Project Kickoff:** [Date]
- **Core MVP Development:** [Date]
- **Testing & QA:** [Date]
- **Launch Date (v1.0):** [Date]

### 10.2 Release & Rollout Plan
- **Beta Testing:** [e.g. Invite-only beta testing with select users]
- **Production Rollout Strategy:** [e.g. Direct overwrite, zero-downtime blue-green deployment]
- **Post-Launch Monitoring:** [e.g. Verify logs and error reports daily for the first week]

### _10.3 Future Phases_
- _**Phase 2 Features:**_ [_[e.g. Add user accounts, cloud database sync, invoice template customization]_]
- _**Long-term Roadmap:**_ [_[e.g. Native desktop and mobile application versions]_]

## 11. Appendix
### 11.1 Glossary of Terms
- **Term 1:** [Definition of abbreviations or technical terms, e.g. MVP - Minimum Viable Product]
- _**Term 2:**_ [_[Definition of optional/secondary terms]_]

### 11.2 References & External Links
- **Git Standards:** [Git standards guide](file:///home/swagoverlord/repos/wiki/guidelines/git-standards.md)
- _**Third-Party Documentation:**_ [_[Link to external service docs, e.g. Stripe API developer portal]_]
- _**Competitor/Inspiration Links:**_ [_[Link to similar apps or products being used as inspiration]_]