# Task 3 Report

## Files

- `blogravel/playwright.config.ts`
- `blogravel/tests/e2e/helpers.ts`
- `blogravel/tests/e2e/login.setup.ts`
- `blogravel/tests/e2e/subdomain-routing.spec.ts`

## Red

Command:

```text
npx playwright test tests/e2e/subdomain-routing.spec.ts --project=chromium
```

Initial result after writing the spec:

```text
6 tests: 2 passed, 4 failed
```

The expected routing assertions failed because browser requests were still using the fixed localhost host: root and valid tenant requests returned 404. The invalid-host 404 assertion passed. The first run also exposed an invalid `test.use()` placement; that was corrected before the clean red run.

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
