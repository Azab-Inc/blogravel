# Auth & RBAC Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a headless API auth flow (Sanctum tokens) and Filament admin auth with MFA (TOTP + email OTP), enforce RBAC on admin/write endpoints, enforce api_keys ability checks, and ship Postman collections and tests.

**Architecture:** API clients authenticate via bearer tokens (no cookies). Admins authenticate via Filament with mandatory MFA for admin roles. RBAC is enforced via middleware/policies on admin/write API routes. API key ability checks are enforced on protected endpoints.

**Tech Stack:** Laravel 13 (PHP 8.5), Laravel Fortify (headless), Laravel Sanctum, Filament (latest), Pest (tests), PostgreSQL, Redis (sessions), Postman (collections)

**Spec:** `docs/superpowers/specs/2026-08-30-auth-rbac-design.md`

## Global Constraints

- API auth is token-based (Sanctum bearer tokens); do not use cookies for API auth.
- Admin auth is Filament-based; admins must complete MFA (TOTP or email OTP).
- RBAC must be enforced on admin routes and write API endpoints.
- API keys must enforce abilities (read/write/draft_read) on protected endpoints.
- TDD: write failing tests first; implement minimal code; verify tests pass.
- Prefer focused files and clear responsibilities.
- Use the app’s existing code style and patterns.
- All new code must pass `vendor/bin/pint` and relevant test suites.

---

### Task 1: Install and configure Fortify (headless)

**Files:**
- Modify: `composer.json`
- Create/modify: `config/fortify.php`
- Create: `app/Providers/FortifyServiceProvider.php` (if not present)

**Interfaces:** None (foundation only).

- [ ] **Step 1: Require Fortify**

```bash
composer require laravel/fortify
```

- [ ] **Step 2: Publish Fortify config**

```bash
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

- [ ] **Step 3: Configure Fortify for headless/API usage**

Edit `config/fortify.php` to disable views and ensure API-friendly routes are registered. Ensure `api` and `web` route registration is configured appropriately for headless usage.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/fortify.php app/Providers
git commit -m "feat(auth): install and configure Fortify (headless)"
```

---

### Task 2: Install and configure Sanctum

**Files:**
- Modify: `composer.json`
- Modify: `app/Models/User.php`
- Create/modify: config/sanctum.php
- Add migrations (publish Sanctum migrations)

**Interfaces:** User model must expose `HasApiTokens` for token creation/revocation.

- [ ] **Step 1: Require Sanctum**

```bash
composer require laravel/sanctum
```

- [ ] **Step 2: Publish Sanctum migrations**

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

- [ ] **Step 3: Add HasApiTokens trait to User model**

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // ...
}
```

- [ ] **Step 4: Run migrations to create personal_access_tokens**

```bash
php artisan migrate
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php config/sanctum.php database migrations composer.json composer.lock
git commit -m "feat(auth): install and configure Sanctum for token auth"
```

---

### Task 3: Implement API login/logout endpoints (bearer tokens)

**Files:**
- Create: `app/Http/Controllers/Api/V1/Auth/LoginController.php`
- Create: `app/Http/Controllers/Api/V1/Auth/LogoutController.php`
- Modify: `routes/api.php` (create if missing)

**Interfaces:**
- POST `/api/v1/login` accepts `{email, password}` and returns `{token, user:{id, role}}`
- POST `/api/v1/logout` revokes current token (requires auth)

- [ ] **Step 1: Write failing test for login**

```php
// tests/Feature/Api/V1/Auth/LoginTest.php
it('returns a sanctum token on valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
});
```

- [ ] **Step 2: Run test to confirm failure**

```bash
php artisan test --filter=LoginTest
```

- [ ] **Step 3: Implement login endpoint**

```php
// app/Http/Controllers/Api/V1/Auth/LoginController.php
public function __invoke(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (! Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'role' => $user->role,
        ],
    ]);
}
```

- [ ] **Step 4: Add route**

```php
// routes/api.php
Route::post('/v1/login', LoginController::class);
Route::post('/v1/logout', LogoutController::class)->middleware('auth:sanctum');
```

- [ ] **Step 5: Implement logout endpoint**

```php
// app/Http/Controllers/Api/V1/Auth/LogoutController.php
public function __invoke(Request $request)
{
    $request->user()->currentAccessToken()->delete();
    return response()->json(null, 204);
}
```

- [ ] **Step 6: Run login test again to verify**

```bash
php artisan test --filter=LoginTest
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/Auth routes/api.php tests
git commit -m "feat(auth): add API login/logout endpoints with Sanctum tokens"
```

---

### Task 4: Add RBAC middleware for admin routes and write endpoints

**Files:**
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Create/modify: route files or middleware kernel registration as needed
- Create: tests for RBAC enforcement

**Interfaces:** Middleware accepts allowed roles and checks `Auth::user()->role`.

- [ ] **Step 1: Write failing tests for role enforcement**

```php
// tests/Feature/Middleware/EnsureUserHasRoleTest.php
it('blocks non-admin from admin-only route', function () {
    $user = User::factory()->create(['role' => Role::Author]);
    $this->actingAs($user)->get('/admin/secret')->assertForbidden();
});

it('allows super_admin to admin-only route', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);
    $this->actingAs($user)->get('/admin/secret')->assertOk();
});
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
php artisan test --filter=EnsureUserHasRoleTest
```

- [ ] **Step 3: Implement middleware**

```php
// app/Http/Middleware/EnsureUserHasRole.php
public function handle(Request $request, Closure $next, string ...$roles)
{
    if (! $request->user() || ! in_array($request->user()->role, $roles)) {
        abort(403);
    }
    return $next($request);
}
```

- [ ] **Step 4: Wire middleware to test route**

Register a test route (or use an existing admin route) and attach `ensure.user.has.role:super_admin,editor`.

- [ ] **Step 5: Run RBAC tests**

```bash
php artisan test --filter=EnsureUserHasRoleTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureUserHasRole.php tests
git commit -m "feat(auth): add RBAC middleware and tests"
```

---

### Task 5: Enforce api_keys ability checks on protected endpoints

**Files:**
- Create/modify: middleware or policy logic for API key ability checks
- Create: tests for ability enforcement

**Interfaces:** Ensure API requests carrying an `X-Api-Key` header are validated against `api_keys.abilities`.

- [ ] **Step 1: Write failing tests**

```php
// tests/Feature/ApiKeyAbilityTest.php
it('rejects write request with read-only api key', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Read];
    $key->save();

    $this->withHeader('X-Api-Key', $key->key_hash)
        ->postJson('/api/v1/posts', ['title' => 'x'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
php artisan test --filter=ApiKeyAbilityTest
```

- [ ] **Step 3: Implement API key ability middleware/policy**

- [ ] **Step 4: Run ability tests again**

```bash
php artisan test --filter=ApiKeyAbilityTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware tests
git commit -m "feat(auth): enforce api_keys ability checks on protected endpoints"
```

---

### Task 6: Set up Filament admin auth with MFA (TOTP + email OTP)

**Files:**
- Create/modify: Filament panel provider(s)
- Add Filament MFA plugin and configuration
- Add tests for MFA flows (admin login requires MFA)

**Interfaces:** Admin login requires MFA (TOTP or email OTP) before granting access.

- [ ] **Step 1: Install required Filament MFA package (e.g., filament-fortify-mfa or similar)**

- [ ] **Step 2: Configure Filament panel to require MFA for admin roles**

- [ ] **Step 3: Write failing test for MFA requirement**

```php
// tests/Feature/Admin/MfaTest.php
it('requires MFA for super_admin login', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);
    // Attempt login; assert MFA challenge presented
});
```

- [ ] **Step 4: Implement MFA enrollment/verification flows**

- [ ] **Step 5: Run MFA tests**

```bash
php artisan test --filter=MfaTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Providers/Filament tests
git commit -m "feat(auth): add Filament MFA (TOTP + email OTP) for admin auth"
```

---

### Task 7: Create Postman collections for API testing

**Files:**
- Create: `postman/Blogravel API Auth.postman_collection.json`
- Include variables for token and base URL

**Interfaces:** Collection includes requests for:
- POST /api/v1/login
- POST /api/v1/logout
- Sample protected requests using Bearer token

- [ ] **Step 1: Create Postman collection JSON**

- [ ] **Step 2: Add collection variables and examples**

- [ ] **Step 3: Commit**

```bash
git add postman
git commit -m "docs(api): add Postman collection for auth and protected endpoints"
```

---

### Task 8: Add integration tests for role boundaries and api_key abilities

**Files:**
- Create/modify: feature tests in `tests/Feature`

**Interfaces:** End-to-end tests that:
- Use Sanctum tokens for authenticated API requests
- Enforce roles on admin routes
- Enforce api_keys abilities on endpoints

- [ ] **Step 1: Write role boundary integration tests**

- [ ] **Step 2: Write api_key ability integration tests**

- [ ] **Step 3: Run full relevant test suite**

```bash
php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add tests
git commit -m "tests(auth): add integration tests for RBAC and api_key abilities"
```

---

### Task 9: Final validation and cleanup

**Files:** none (validation only)

**Interfaces:** All tests pass; code formatted; no regressions.

- [ ] **Step 1: Run Pint**

```bash
vendor/bin/pint
```

- [ ] **Step 2: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 3: Commit any fixes**

```bash
git add -A
git commit -m "fix(auth): minor fixes and formatting after final validation"
```
