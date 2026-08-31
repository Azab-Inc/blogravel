# Auth & RBAC Foundation Design (issue #2)

This document defines the design for Blogravel’s authentication and role-based access control foundation, aligned with:

- API auth via bearer tokens (no cookies)
- Admin auth via Filament (latest) with MFA required
- MFA support: TOTP authenticator app + email OTP

## Goals

1. Provide a headless/API-friendly auth flow for API clients and Postman testing.
2. Provide a Filament-based admin auth flow with mandatory MFA for admin users.
3. Enforce RBAC on admin routes and write API endpoints.
4. Enforce api_keys + ability checks (read/write/draft_read) on relevant API endpoints.
5. Ship tests that prove role boundaries and api_key ability enforcement.

## Scope

### In scope

- Install and configure Laravel Fortify (headless)
- Install and configure Laravel Sanctum
- Create API login flow that returns a Sanctum bearer token
- Create API logout / token revocation
- Create middleware to enforce `users.role` (super_admin/editor/author) on admin routes and write API endpoints
- Create middleware/policy enforcement for `api_keys` abilities on protected endpoints
- Filament auth with MFA requirement for admin users
- MFA enrollment/verification for TOTP and email OTP
- Postman collection for API auth + protected endpoint testing
- Tests for role boundaries and api_key ability enforcement

### Out of scope (for this issue)

- Full admin UI beyond auth/MFA flows
- Public read API implementation (issue #8)
- API token introspection/debug endpoints
- Full password reset email flows beyond standard Fortify behavior

## Architecture

### Authentication layers

1. **API layer (Sanctum)**
   - API clients authenticate via `POST /api/v1/login` and receive a Sanctum bearer token.
   - All protected API requests use `Authorization: Bearer <token>`.
   - No cookies.

2. **Admin layer (Filament)**
   - Admins log in via Filament login.
   - Filament enforces MFA after login for admin users.
   - MFA methods: TOTP and email OTP.

### Data model (existing + additions)

Existing (already built in current repo state):

- `users.role` enum (via `App\Enums\Role`):
  - super_admin
  - editor
  - author

- `api_keys` table and `App\Models\ApiKey` (with ability checks via `App\Enums\ApiKeyAbility`)

Additions/changes:

- Keep Filament native auth (no Fortify plugin) for admin login + MFA.
- Add Filament MFA plugin + email MFA transport (via Symfony Mailer / Laravel mail).
- Use Sanctum for token-based API auth.

## Auth flows

### API login (headless)

1. Client sends:
   - POST `/api/v1/login`
   - JSON: `email`, `password`

2. Backend:
   - Validates credentials
   - Checks user status (active/not blocked)
   - Issues Sanctum token (bearer)
   - Returns token + basic user metadata (id, role)

3. Client:
   - Stores token securely
   - Uses `Authorization: Bearer <token>` on protected API calls

### API logout

- POST `/api/v1/logout` (authenticated via Bearer token)
- Revoke current token

### Admin login (Filament)

1. Admin navigates to Filament login
2. Authenticates with email + password
3. Filament forces MFA enrollment/verification
4. After MFA success, admin session is fully authenticated

## RBAC design

### Role semantics

- **super_admin**
  - Full access to admin resources
  - Can manage users/roles/settings
- **editor**
  - Can manage content (posts/pages/comments/tags/categories) across assigned scope
  - Can access admin panels allowed by editor policy
- **author**
  - Can create/manage own content only
  - Restricted admin access

### Middleware design

Create role middleware (e.g., `role:super_admin,editor`) to protect admin routes and write API endpoints.

Implementation approach:

- Add middleware that reads authenticated user role
- Compare against allowed roles
- Return 403 if not authorized

## API key ability design

### Abilities

- `read` (post/tag/page/category read endpoints)
- `write` (create/update/delete protected endpoints)
- `draft_read` (access draft content where applicable)

### Enforcement approach

Add middleware/policy logic to inspect request API key (if present) and ensure required ability exists before allowing access.

## Filament MFA design

### MFA requirements

- Required for admin users (super_admin/editor)
- Author role may not require MFA unless explicitly enabled later

### MFA methods

1. **TOTP**
   - Standard authenticator app flow
   - Secret stored encrypted
   - QR code provisioning

2. **Email OTP**
   - One-time code sent via email
   - Short expiration window
   - Rate limiting on code generation

### MFA UX

- On first admin login, prompt to enroll in MFA
- Allow choosing TOTP or email OTP
- On subsequent logins, require MFA verification
- Provide recovery/backup considerations (can be added later if needed)

## Testing strategy

### Auth tests

- Test successful API login returns Sanctum token
- Test invalid credentials return 401
- Test protected API endpoints reject unauthenticated requests

### Role boundary tests

- Test super_admin can access admin-only routes
- Test editor can access editor-allowed routes and cannot access super_admin-only routes
- Test author cannot access editor/super_admin-only routes
- Test write API endpoints enforce role correctly

### API key ability tests

- Test `read`-only key cannot write
- Test `write`-allowed key can access write endpoints
- Test `draft_read` access logic where implemented
- Test requests with missing/invalid API key are rejected appropriately

## Delivery artifacts

1. Code changes:
   - Fortify installed + configured (headless)
   - Sanctum installed + configured
   - API auth routes/controllers
   - Role middleware
   - API key ability middleware/policy logic
   - Filament MFA setup

2. Documentation:
   - Postman collection for API auth and protected endpoints

3. Tests:
   - Role boundary tests
   - API key ability enforcement tests
   - Basic auth flow tests

## Acceptance mapping (to issue #2)

- Fortify installed; login/registration/password reset/email verification/2FA scaffolded
- `users.role` enum enforced
- Middleware enforces role on admin routes and write API endpoints
- Sanctum token auth for API; api_keys with ability checks enforced
- Tests cover role boundaries and API key ability enforcement

## Implementation notes

- Keep API auth stateless (bearer tokens).
- Avoid cookies for API auth.
- Use Filament’s latest patterns for admin auth + MFA.
- Avoid overengineering recovery flows in first iteration; keep scope tight.
- Add Postman collection only for verified endpoints (not speculative routes).
