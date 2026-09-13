# Task 3 Report

## Files

- `blogravel/playwright.config.ts`
- `blogravel/tests/e2e/helpers.ts`
- `blogravel/tests/e2e/login.setup.ts`
- `blogravel/tests/e2e/subdomain-routing.spec.ts`

## Original Red

Command:

```text
npx playwright test tests/e2e/subdomain-routing.spec.ts --project=chromium
```

Initial result after writing the original spec:

```text
6 tests: 2 passed, 4 failed
```

The expected routing assertions failed because the browser host strategy had not yet been configured. The invalid-host 404 assertion passed. The first run also exposed an invalid `test.use()` placement; that was corrected before the clean red run.

The app container database was then migrated and seeded so tenant requests could exercise the existing Task 1 schema:

```text
docker compose exec -T laravel.test php artisan migrate --force
docker compose exec -T laravel.test php artisan db:seed --force
```

Both commands completed successfully.

## Green

Command:

```text
npx playwright test tests/e2e/subdomain-routing.spec.ts --project=chromium
```

Output:

```text
Running 6 tests using 1 worker
6 passed (6.5s)
```

Coverage includes guest root redirect, authenticated root redirect, tenant rendering and isolation, invalid-host 404, and authentication continuity from root to tenant host.

## Changes

- Configured Chromium to map `blogravel.com` and wildcard subdomains to `127.0.0.1` while using real host URLs.
- Added a test-only login helper to share the session cookie across `.blogravel.com` subdomains.
- Added the focused Playwright routing spec with status, URL, content, isolation, and authentication assertions.

## Commit

`Tests: cover tenant subdomain routing in Playwright`

## Concerns

- The browser suite depends on the local Laravel server and seeded tenants `acme.io` and `globex.net`.
- The local container database required applying the already-committed tenant migration before the green run; that environment change is not part of the commit.

## Review Fix

### Red

The revised final spec was run before restoring the cross-subdomain cookie-sharing support:

```text
npx playwright test tests/e2e/subdomain-routing.spec.ts --project=chromium
```

Output:

```text
Running 6 tests using 1 worker
5 passed, 1 failed
```

The failure was intentional and specific: after the authenticated root dashboard, the tenant-host `/admin` navigation resolved to `/admin/login` instead of the authenticated dashboard. The other five routing tests passed. This run used explicit `http://blogravel.com:8000` and wildcard subdomain URLs; it did not rely on a localhost host claim.

### Green

After restoring the existing `shareAuthAcrossSubdomains` setup used by the original commit:

```text
npx playwright test tests/e2e/subdomain-routing.spec.ts --project=chromium
```

Output:

```text
Running 6 tests using 1 worker
6 passed (8.2s)
```

The continuity test now requires and verifies the authenticated `/admin` dashboard on `acmeio.blogravel.com`; the root redirect, tenant rendering/isolation, and invalid-host tests remain unchanged.

### Fix Commit

This commit: `Tests: strengthen tenant subdomain authentication coverage and correct report`.

### Fix Concerns

- No production code or new configuration was required for this review fix; the existing cookie-sharing helper is now exercised by a protected tenant route.

## Final Whole-Branch Findings

### Feed URL Hosts

#### Red

Added a Pest regression covering generated tenant, custom, legacy, and local hosts. Before the fix, tenant-host resolution was valid but `home_page_url`, `feed_url`, and item URLs still used `blogravel.com` through `route('home')`; custom, legacy, and local cases failed their host assertions.

#### Green

```text
php artisan test --compact tests/Feature/FeedsTest.php --filter='uses the current tenant host'
4 passed (16 assertions)
```

Feed canonical and item links now use named routes with relative paths joined to the request scheme/host, while retaining the tenant query needed by local compatibility.

### Reserved Slugs

#### Red

Added a Pest regression for `admin`, `API`, and `www`. Before the fix, those names generated reserved slugs directly.

#### Green

```text
php artisan test --compact tests/Feature/TenantHostResolutionTest.php --filter='reserved tenant names'
3 passed (6 assertions)
```

Reserved labels are normalized from configuration and receive deterministic `tenant-{uuid}` fallback slugs.

### Custom-Domain Uniqueness

#### Red

Added a raw-query regression that bypasses the model mutator. Before the fix, SQLite accepted a mixed-case duplicate custom domain.

#### Green

```text
php artisan test --compact tests/Feature/TenantHostResolutionTest.php --filter='raw inserts'
1 passed (2 assertions)
```

The normalization migration now replaces the case-sensitive index with a portable PostgreSQL/SQLite functional partial unique index on `LOWER(custom_domain)`.

## Verification

```text
php artisan test --compact tests/Feature/FeedsTest.php tests/Feature/TenantHostResolutionTest.php
49 passed (106 assertions)

npx playwright test tests/e2e/subdomain-routing.spec.ts tests/e2e/theme-pages.spec.ts --project=chromium
22 passed

vendor/bin/pint --dirty --format agent
git diff --check
```

The full Laravel suite ran with `416 passed` and `4 failed` in unrelated existing Filament action/settings coverage. Issue #46 remains `Todo` and was not marked complete because the full suite is not fully green.

## Final Concerns

- Full-suite failures remain in `GenerateAiPostActionTest` and `SettingsTest`; they need separate investigation before closing #46.
- The focused tenant routing and theme browser suites are fully green.

## Remaining Review Findings

### Feed Serialization Coverage

Added a single Pest matrix covering RSS and Atom responses on generated, custom, legacy, and local tenant hosts. Each response is parsed with SimpleXML and asserts the canonical home link, feed self link, and post item link preserve the request host, paths, tenant query, and post fragment.

### Custom-Domain Migration Coverage

Retained the existing SQLite raw-insert regression and added a `pgsql`-grouped test that skips on other drivers. On PostgreSQL it verifies the `tenants_custom_domain_lower_unique` functional index exists and rejects a raw mixed-case duplicate. The test requires the `pgsql` driver and a configured PostgreSQL test database.

### Verification

```text
php artisan test --compact tests/Feature/FeedsTest.php tests/Feature/TenantHostResolutionTest.php
51 tests: 50 passed, 1 skipped, 178 assertions (SQLite; PostgreSQL test skipped)

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=testing DB_USERNAME=sail DB_PASSWORD=password php artisan test --compact tests/Feature/TenantHostResolutionTest.php --filter='PostgreSQL enforces'
1 passed, 2 assertions (PostgreSQL)

npx playwright test tests/e2e/subdomain-routing.spec.ts tests/e2e/theme-pages.spec.ts --project=chromium
22 passed

vendor/bin/pint --dirty --format agent
git diff --check
```

Issue #46 remains `Todo` because the full suite still has the unrelated existing Filament failures documented above.

The full combined focused fileset under PostgreSQL is not clean because the existing unknown-tenant feed test passes the non-UUID literal `nonexistent` into a UUID column and returns 500; that failure aborts the test transaction for subsequent tests. The migration backfill, collision, and new PostgreSQL index tests pass when run independently against the PostgreSQL `testing` database.

## Final Whole-Branch Findings

### Web Host Validation

Added Pest regressions for unknown `/admin` and `/admin/secret` requests and reserved/malformed `/debug/session-check` requests. Web host validation now runs as a prepended application middleware, before route authentication/controller middleware, while explicitly bypassing API and health requests. Platform, valid tenant, and established local hosts retain their existing behavior; duplicate route and Filament registrations are removed.

### Authentication Continuity

Removed the Playwright manual cookie rewrite. The setup now asserts that the login response emits the configured `.blogravel.com` session cookie, and the routing spec verifies authenticated access to the protected tenant-host `/admin` route.

### Verification

```text
php artisan test --compact tests/Feature/FeedsTest.php tests/Feature/TenantHostResolutionTest.php
65 tests: 64 passed, 1 skipped, 205 assertions

npx playwright test tests/e2e/subdomain-routing.spec.ts tests/e2e/theme-pages.spec.ts --project=chromium
22 passed

php artisan test --compact
437 tests: 419 passed, 4 failed (unrelated existing Filament failures)

GET /up: 200
GET /api/v1/posts without authentication: 401
```

Issue #46 remains `Todo` because the full suite still has the unrelated existing Filament failures.
