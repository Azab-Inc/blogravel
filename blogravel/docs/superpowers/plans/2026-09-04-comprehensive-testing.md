# Test Plan — Blogravel Comprehensive Testing

## Context
Blogravel needs a layered test suite: Pest unit/feature tests for API, auth, AI generation, WordPress import, and policies; Playwright E2E tests for all 12 Filament admin pages plus 4 critical user flows.

## Tasks

### Task 1: API CRUD Feature Tests (Pest)
Write Pest feature tests for `/api/v1/` endpoints:
- **Posts**: index, store, show, update, destroy, validation errors (missing title/content), authorization (unauthenticated 401)
- **Pages**: same CRUD + validation
- **Categories**: same CRUD + validation
- **Tags**: same CRUD + validation
- Each test creates its own data via factories (isolated, RefreshDatabase)
- Use `actingAs` or Sanctum tokens for auth
- Assert JSON structure, status codes, DB state

### Task 2: Auth Feature Tests (Pest)
Write Pest feature tests for auth flows:
- **Login**: valid credentials → token, invalid → 401, missing fields → 422
- **Register**: valid data → user created, duplicate email → 422, missing fields → 422
- **MFA**: challenge flow (if enabled), recovery codes
- **Logout**: invalidates token

### Task 3: AI Generation + WordPress Import Tests (Pest)
Write Pest feature tests:
- **AI Generation**: dispatch GenerateAiPostJob, verify draft created, verify notification sent, verify content populated
- **WordPress Import**: upload WXR file, verify categories/tags/posts created, verify idempotency (duplicate import skips)
- **Policies**: author can edit own posts, cannot edit others'; editor can edit any; super_admin can do anything

### Task 4: Playwright E2E Tests
Write Playwright test files:
- **All 12 Filament pages**: navigate, verify renders, CRUD via forms (create/edit/delete a Post, Category, Tag, Page, User, etc.)
- **Register flow**: /admin/register → fill → submit → redirect
- **Login + MFA flow**: /admin/login → credentials → MFA → dashboard
- **AI Post Generation**: Posts → "Generate with AI" → modal → submit → draft created
- **WordPress Import**: Import page → upload WXR → import starts
- Config: playwright.config.ts pointing to localhost:8000

### Task 5: Bash Script + Final Verification
- Create `full-test.sh` that runs `php artisan test` then `npx playwright test`
- Run full suite, verify all pass
- Commit everything

## Conventions
- Pest tests with `RefreshDatabase`
- Factories for all models
- `php artisan make:test --pest` for creating test files
- No comments in code unless asked
- Commit format: "Tests: ..."
