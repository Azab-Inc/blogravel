![Logo](../designs/assets/images/banner.png)
# Blogravel

A free, self-hosted, open-source blogging platform built as a lightweight, privacy-first alternative to WordPress. API-first architecture with multi-tenant support, AI-assisted content generation, and one-click WordPress migration.

## Screenshots

![App Screenshot](https://via.placeholder.com/468x300?text=App+Screenshot+Here)

## Links

- [Website](https://blogravel.azaber.com/)
- [Documentation](https://blogravel.azaber.com/docs)
- [GitHub](https://github.com/Azab-Inc/blogravel/)

## Tech Stack

**Backend:** PHP 8.3+ (targets 8.5) with Laravel 13, Filament PHP (admin panel), Laravel Sanctum (API auth)

**Frontend (Starter Theme):** Laravel Blade with Tailwind CSS v4, Vite v8

**Database:** PostgreSQL 18

**Queue:** Laravel Queue with Redis driver

**Cache:** Redis

**Object Storage:** Local by default, S3-compatible (AWS S3, MinIO, Laravel Cloud) optional

**Container Runtime:** Docker Compose (Laravel Sail + FrankenPHP) — app, PostgreSQL, Redis, queue worker, Mailpit

**CI/CD:** GitHub Actions (PHP 8.3/8.4/8.5 matrix)

**Testing:** Pest PHP v4 + PHPUnit v12

**Code Style:** Laravel Pint

**Deployment:** Self-hosted via Docker Compose, or Laravel Cloud for managed hosting

## Features

- **REST API-first** — All frontends consume the same API. Cursor pagination, RFC 9457 error responses.
- **Headless by default** — Connect any frontend (Next.js, Nuxt, Astro, etc.) via API keys generated in Filament admin.
- **Soro integration** — Webhook receiver for AI-powered SEO content autopublishing. Extensible for other AI content tools.
- **Bring-your-own-AI** — Configure any AI provider (Ollama, OpenAI, Anthropic, custom) for post generation.
- **WordPress import** — One-click migration via WXR file upload or direct MySQL database connection.
- **Mailing lists** — Per-category email subscriptions with double opt-in confirmation.
- **RSS & Atom feeds** — Full RSS 2.0, Atom, and JSON feeds built with Blade views. Per-category and per-author feeds.
- **Multi-tenant** — Subdomain-based tenant isolation. Single deployment serves multiple independent blogs.
- **Custom themes** — Blade component override system. Drop custom components into `resources/themes/{theme-name}/`.
- **Draft previews** — Share draft posts via signed URLs or scoped API keys.
- **Webhooks** — Content change notifications for post and page events. Push to external systems.
- **Stripe billing** — Toggleable via feature flag. Free/Pro/Business tiers for hosted platform.
- **Role-based access** — Super Admin, Editor, Author roles with per-endpoint enforcement.
- **GDPR-ready** — Double opt-in subscriptions, unsubscribe links, subscriber data deletion.

## Run Locally

Clone the repository

```bash
git clone https://github.com/Azab-Inc/blogravel.git
cd blogravel
```

Copy environment file

```bash
cp blogravel/.env.example blogravel/.env
```

Start with Docker Compose (app + PostgreSQL + Redis + queue worker + Mailpit)

```bash
docker compose up -d
```

Install dependencies and run migrations

```bash
docker compose exec laravel.test composer setup
```

Access the application

- **App:** http://localhost:8000
- **Admin (Filament):** http://localhost:8000/admin
- **Mailpit (email preview):** http://localhost:8025

## Running Tests

```bash
docker compose exec laravel.test php artisan test
```

Or with Pest directly:

```bash
docker compose exec laravel.test ./vendor/bin/pest
```

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

## Team

| Person             | Role                 |
| ------------------ | -------------------- |
| Alexander Zaborski | Full Stack Developer |

## Support

| Person             | Role                 | Email           | Contact links                       |
| ------------------ | -------------------- | --------------- | ----------------------------------- |
| Alexander Zaborski | Full Stack Developer | info@azaber.com | https://www.linkedin.com/in/azaber/ |

## License

MIT License. See [LICENSE](../LICENSE) for details.
