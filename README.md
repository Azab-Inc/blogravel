![Logo](designs/assets/images/banner.png)

# Blogravel

A free, self-hosted, open-source blogging platform built as a lightweight, privacy-first alternative to WordPress. API-first architecture with multi-tenant support, AI-assisted content generation, and one-click WordPress migration.

[Website](https://blogravel.azaber.com/) · [Documentation](https://blogravel.azaber.com/docs) · [GitHub](https://github.com/Azab-Inc/blogravel/)

---

## Why Blogravel?

Blogravel solves the friction of leaving WordPress by giving you a free, self-hosted alternative with a seamless migration path and modern blogging features:

- **Free and open source** — MIT licensed, no vendor lock-in.
- **Privacy-first by design** — Self-host your own data; subscriber emails are only stored after double opt-in.
- **API-first with headless frontend support** — Every frontend, including the built-in starter theme, consumes the same REST API.
- **One-click WordPress migration** — Import posts, pages, media, and categories in minutes.
- **Integrated AI content generation** — Soro (first-class webhook) plus bring-your-own providers (Ollama, OpenAI, Anthropic, custom).
- **Flexible subscription controls** — Granular, per-category mailing list subscriptions.

---

## Features

- **REST API-first** — All frontends consume the same API. Cursor pagination, RFC 9457 error responses.
- **Headless by default** — Connect any frontend (Next.js, Nuxt, Astro, etc.) via API keys generated in the Filament admin.
- **Soro integration** — Webhook receiver for AI-powered SEO content autopublishing. Extensible for other AI content tools.
- **Bring-your-own-AI** — Configure any AI provider (Ollama, OpenAI, Anthropic, custom) for post generation.
- **WordPress import** — One-click migration via WXR file upload or direct MySQL database connection.
- **Mailing lists** — Per-category email subscriptions with double opt-in confirmation.
- **RSS & Atom feeds** — Full RSS 2.0, Atom, and JSON feeds built with Blade views. Per-category and per-author feeds.
- **Multi-tenant** — Subdomain-based tenant isolation. Single deployment serves multiple independent blogs.
- **Custom themes** — Blade component override system. Drop custom components into `resources/themes/{theme-name}/`.
- **Draft previews** — Share draft posts via signed URLs or scoped API keys.
- **Webhooks** — Content change notifications for post and page events. Push to external systems.
- **Stripe billing** — Toggleable via feature flag. Free/Pro/Business tiers for the hosted platform.
- **Role-based access** — Super Admin, Editor, Author roles with per-endpoint enforcement.
- **GDPR-ready** — Double opt-in subscriptions, unsubscribe links, subscriber data deletion.

---

## Tech Stack

**Backend:** PHP 8.3+ (targets 8.5) with Laravel 13, Filament PHP (admin panel), Laravel Sanctum (API auth)

**Frontend (Starter Theme):** Laravel Blade with Tailwind CSS v4, Vite v8

**Database:** PostgreSQL 18 (SQLite for local development)

**Queue:** Laravel Queue with Redis driver

**Cache:** Redis

**Object Storage:** Local by default, S3-compatible (AWS S3, MinIO, Laravel Cloud) optional

**Container Runtime:** Docker Compose (Laravel Sail + FrankenPHP) — app, PostgreSQL, Redis, queue worker, Mailpit

**CI/CD:** GitHub Actions (PHP 8.3/8.4/8.5 matrix)

**Testing:** Pest PHP v4 + PHPUnit v12

**Code Style:** Laravel Pint

**Deployment:** Self-hosted via Docker Compose, or Laravel Cloud for managed hosting

---

## Requirements

- PHP 8.3 or higher
- Docker / Docker Compose
- PostgreSQL 18 (provided via Docker Compose)
- Redis (provided via Docker Compose)

---

## Run Locally

Clone the repository:

```bash
git clone https://github.com/Azab-Inc/blogravel.git
cd blogravel
```

Copy the environment file:

```bash
cp blogravel/.env.example blogravel/.env
```

Start the stack with Docker Compose (app + PostgreSQL + Redis + queue worker + Mailpit):

```bash
docker compose up -d
```

Install dependencies and run migrations:

```bash
docker compose exec laravel.test composer setup
```

Access the application:

- **App:** http://localhost:8000
- **Admin (Filament):** http://localhost:8000/admin
- **Mailpit (email preview):** http://localhost:8025

---

## Running Tests

```bash
docker compose exec laravel.test php artisan test
```

Or with Pest directly:

```bash
docker compose exec laravel.test ./vendor/bin/pest
```

---

## Project Structure

```
blogravel/
├── blogravel/              # Laravel application
│   ├── app/
│   │   ├── Console/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   └── Middleware/
│   │   ├── Models/
│   │   └── Providers/
│   ├── config/
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── resources/
│   │   ├── themes/         # Custom theme overrides
│   │   └── views/          # Starter theme Blade components
│   ├── routes/
│   ├── tests/
│   └── compose.yaml
├── docs/
│   ├── project.md
│   ├── requirements.md
│   └── implementation-plan.md
├── designs/
│   ├── assets/
│   └── logos/
└── README.md
```

---

## Key Capabilities

### WordPress Import

Import all WordPress content via a WXR `.xml` export upload or a direct MySQL connection string. Imports posts, pages, categories, tags, authors, custom fields, and comments — media is downloaded and stored locally. Idempotent by slug, so duplicate imports are safely skipped.

### AI Post Generation

Configure Soro (webhook), an external provider (endpoint + key), or Ollama (local, no key required). Generated content is inserted as a draft — never auto-published.

### Soro Webhook

`POST /api/webhooks/soro` accepts AI-generated content with HMAC-signed authentication (`X-Blogravel-Signature`). Built generically so any AI content tool can use the same endpoint with its own webhook secret.

### Feeds

RSS 2.0 (`/feeds/posts.xml`), Atom (`/feeds/posts.atom`), and JSON (`/feeds/posts.json`), plus per-category and per-author variants. Feed auto-discovery `<link>` tags are injected into the starter theme.

### Mailing Lists

`POST /api/subscribe` with `{ email, categories: [] }` (empty = all). Double opt-in confirmation via email; on publish, matching subscribers are notified with an unsubscribe link.

### Multi-Tenancy

`TENANCY_MODE=multi` enables subdomain-based tenant isolation (e.g. `myblog.blogravel.com`). All content is scoped via a `tenant_id` global scope. Self-hosted instances default to `single` mode.

### Stripe Billing (Hosted)

Toggleable via `BILLING_ENABLED`. Free / Pro / Business tiers with limits on posts, image size, users, and custom domains. Self-hosted instances ignore billing and have no limits.

| Tier     | Posts     | Image Size | Users per Tenant | Custom Domain |
| -------- | --------- | ---------- | ---------------- | ------------- |
| Free     | 50        | 2 MB       | 3                | No            |
| Pro      | Unlimited | 10 MB      | 10               | Yes           |
| Business | Unlimited | 25 MB      | Unlimited        | Yes           |

---

## License

Blogravel is licensed under the **Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)** license. You are free to copy, run, modify, and share it with others at no charge — including internal use within a for-profit business. You may **not** use it for commercial purposes (e.g. selling copies or offering it as a paid/commercial hosting or service product). See [LICENSE](LICENSE) for full terms.

For commercial hosting or resale rights, contact the author: blogravel@azaber.com

---

## Team & Support

**Maintainer:** Alexander Zaborski — Full Stack Developer · info@azaber.com · [LinkedIn](https://www.linkedin.com/in/azaber/)
