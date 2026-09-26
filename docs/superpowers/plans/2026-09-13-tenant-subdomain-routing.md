# Tenant Subdomain Routing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve tenants from generated subdomains, reserve the platform root for admin entry, and verify the behavior with Pest and Playwright.

**Architecture:** Add tenant identity fields without removing the legacy `domain` field. Centralize host classification and tenant lookup in a request middleware/service boundary, then use a root entrypoint controller for auth-aware redirects and keep the existing theme middleware focused on theme setup. Configure platform/session behavior through environment-backed config.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent migrations/models, Pest 4, Playwright CLI.

**Spec:** `docs/superpowers/specs/2026-09-13-tenant-subdomain-routing-design.md`

## Global Constraints

- Do not remove or repurpose the existing `tenants.domain` column; existing API, webhook, and local-test behavior must remain compatible.
- Do not add dependencies.
- Production code must be preceded by a failing Pest or Playwright test for the behavior it implements.
- Use named routes and existing Laravel/Filament conventions.
- Run PHP formatting with `vendor/bin/pint --dirty --format agent` after PHP changes.
- Include both feature tests and Playwright browser tests.

---

### Task 1: Tenant Identity Fields and Host Resolver

**Files:**
- Create: `blogravel/database/migrations/<timestamp>_add_slug_and_custom_domain_to_tenants_table.php`
- Modify: `blogravel/app/Models/Tenant.php`
- Modify: `blogravel/database/factories/TenantFactory.php`
- Create: `blogravel/app/Services/TenantHostResolver.php`
- Create: `blogravel/app/Http/Middleware/ResolveTenantHost.php`
- Modify: `blogravel/config/tenancy.php`
- Test: `blogravel/tests/Feature/TenantHostResolutionTest.php`

**Interfaces:**
- `TenantHostResolver::resolve(string $host): ?Tenant` returns only a tenant matched by exact `custom_domain` or by one slug label under the configured platform domain.
- `ResolveTenantHost::handle(Request $request, Closure $next): Response` sets request attribute `tenant` for valid tenant hosts and aborts with a clear 404 for invalid tenant hosts; platform-root requests pass through.

- [ ] Write failing Pest tests for unique slug generation, generated-host resolution, custom-domain resolution, unknown/reserved/malformed host rejection, and tenant isolation.
- [ ] Run `php artisan test --compact tests/Feature/TenantHostResolutionTest.php` and verify the new tests fail for the missing fields/resolver.
- [ ] Add the migration, model fillable field, factory defaults, tenancy config, resolver, and middleware with the smallest implementation that satisfies the tests.
- [ ] Run the focused Pest file and `vendor/bin/pint --dirty --format agent`.
- [ ] Commit with `Feature: add tenant host resolution foundation`.

### Task 2: Root-Domain Entrypoint and Session Configuration

**Files:**
- Create: `blogravel/app/Http/Controllers/PlatformEntryController.php`
- Modify: `blogravel/routes/web.php`
- Modify: `blogravel/bootstrap/app.php`
- Modify: `blogravel/config/session.php`
- Modify: `blogravel/.env.example`
- Test: `blogravel/tests/Feature/PlatformEntryTest.php`

**Interfaces:**
- `PlatformEntryController::__invoke(Request $request): RedirectResponse` redirects unauthenticated root requests to the Filament login route and authenticated root requests to the Filament dashboard route.
- Root-domain requests are identified from `config('tenancy.platform_domain')`; tenant public routes use `ResolveTenantHost` before `theme.resolve`.

- [ ] Write failing feature tests for guest root redirect, authenticated root redirect, tenant theme rendering, and session cookie domain configuration.
- [ ] Run the focused tests and verify failure for the missing controller/host route boundary.
- [ ] Add the root controller, route grouping/middleware ordering, config wiring, and documented environment defaults.
- [ ] Run the focused tests and `vendor/bin/pint --dirty --format agent`.
- [ ] Commit with `Feature: add platform root entrypoint`.

### Task 3: Browser Coverage

**Files:**
- Modify: `blogravel/playwright.config.ts`
- Create: `blogravel/tests/e2e/subdomain-routing.spec.ts`
- Modify: `blogravel/tests/e2e/helpers.ts` if host-aware login helpers are required

**Interfaces:**
- Playwright tests run through the existing `npx playwright test` CLI and use the configured Laravel server base URL.
- Host-specific navigation uses request `Host` headers or an equivalent browser-safe local wildcard host strategy without weakening assertions.

- [ ] Write the Playwright tests first for root guest redirect, authenticated root redirect, tenant rendering/isolation, invalid host 404, and authentication continuity between root and tenant hosts.
- [ ] Run the new spec with `npx playwright test tests/e2e/subdomain-routing.spec.ts --project=chromium` and verify it fails for the missing host routing.
- [ ] Implement only the configuration/helper changes required for the browser tests to exercise host-specific requests.
- [ ] Run the focused Playwright spec and capture the result.
- [ ] Commit with `Tests: cover tenant subdomain routing in Playwright`.

### Task 4: Documentation, Full Verification, and Ticket Sync

**Files:**
- Modify: `blogravel/README.md`
- Modify: `blogravel/tickets.md`

**Interfaces:**
- Documentation names the platform-domain, wildcard DNS, wildcard TLS, `SESSION_DOMAIN`, and local wildcard-host settings introduced by Tasks 1-2.
- Ticket #46 is marked `Done` in `tickets.md` and synchronized to the GitHub project only after all verification passes.

- [ ] Add concise deployment and local-development instructions without changing unrelated documentation.
- [ ] Run `php artisan test --compact` and `npx playwright test --project=chromium` against the running application.
- [ ] Inspect `git diff`, `git status`, and the relevant GitHub project item before syncing status.
- [ ] Update the local ticket and GitHub project item to `Done`.
- [ ] Run the final verification commands again after ticket/documentation edits.
- [ ] Commit with `Feature: complete tenant subdomain routing`.
