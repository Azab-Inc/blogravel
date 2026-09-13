# Local Tenant Routing Report

## Red Evidence

- `php artisan test --compact tests/Feature/TenantHostResolutionTest.php`: 5 new routing cases failed with 404/302 before implementation.
- `npx playwright test tests/e2e/subdomain-routing.spec.ts tests/e2e/theme-pages.spec.ts --project=chromium`: 5 new local-routing cases failed; 22 existing cases passed.

## Green Evidence

- `php artisan test --compact tests/Feature/TenantHostResolutionTest.php tests/Feature/ThemeTest.php`: 51 passed, 1 skipped.
- `npx playwright test tests/e2e/subdomain-routing.spec.ts tests/e2e/theme-pages.spec.ts --project=chromium`: 27 passed.
- `vendor/bin/pint --dirty --format agent`: passed and formatted changed PHP files.
- `git diff --check`: passed.

## Files

- Added shared local host and slug resolution in `TenantHostResolver`.
- Added local subdomain/path resolution and non-local path guards in tenant middleware.
- Added local path theme routes for home, posts, categories, subscribe, and contact.
- Added Pest and Playwright coverage for local routes, isolation, invalid slugs, and query compatibility.
- Updated the root `README.md` with local routing URLs and production behavior.

## Tests

- Focused Pest tests: passed.
- Required Playwright specs on Chromium: passed.
- Pint and diff check: passed.

## Commit

- `Fix - Backend: support local tenant routing`

## Concerns

- Local path routes use dedicated route names; existing theme links continue to preserve bare-local query compatibility rather than changing production URL generation.
- One existing Pest test remains skipped because it requires PostgreSQL.
