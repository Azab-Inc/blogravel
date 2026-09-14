# Account Recovery and GDPR Retention Design

## Context

Blogravel already has nullable `deleted_at` columns and Laravel's `SoftDeletes`
trait on users and tenants. Account closure currently soft-deletes the user and,
when the user is the last administrator, the tenant as well. The UI promises a
30-day recovery period, but there is no recovery workflow, deletion provenance,
safe export, or automatic permanent deletion.

This design completes ticket #44 and extends it with the approved account
recovery, GDPR export, and retention requirements.

## Decisions

- Recovery is available from the web/Filament login flow only. API login keeps
  returning invalid credentials for soft-deleted users.
- Recovery requires the original email and password. It uses a separate strict
  rate limit. A successful recovery redirects to normal login so existing MFA
  behavior remains in force.
- Tenant ownership remains role-based. `admin` and `super_admin` roles are the
  administrator set; no `owner_id` is introduced.
- An administrator deleting another user permanently blocks that user's
  self-service recovery. The removed user may register a new account with the
  same email for another tenant.
- A self-closed account can recover for 30 days. A last administrator's
  self-closure restores the user and tenant atomically when both are within
  their recovery windows.
- If the user's window remains open after the tenant window has expired, the
  user is restored without the tenant and is required to create a new tenant.
- A self-closed non-administrator whose tenant is gone receives a specific
  blocked explanation and is not restored.
- A self-closed super administrator can recover. An administrator-removed or
  tenant-closed super administrator cannot self-recover.
- Active emails are unique, but soft-deleted historical rows may share an email.
  If an active account now owns the email, recovery of the old account is
  blocked.
- User and tenant deadlines are calculated independently from their own
  `deleted_at` values.
- Expired data is permanently deleted for GDPR. Tenant-owned content is removed
  at the tenant boundary; detached users remain until their own deadline.
- Tenant and user purge work is daily, idempotent, and retryable. A failed
  external cleanup leaves the database records pending for a later retry.

## Data Model

Add deletion provenance to `users`:

- `deleted_by`: nullable UUID actor identifier without a foreign key. Historical
  metadata must survive if the actor is later deleted.
- `deletion_reason`: controlled value of `self_closed`, `admin_removed`, or
  `tenant_closed`.
- Existing `deleted_at` remains the retention clock.

Replace the database-wide unique email constraint with a PostgreSQL partial
unique index on `email` where `deleted_at IS NULL`. This allows a new active
identity to reuse the email of a soft-deleted identity while preserving normal
login uniqueness and avoiding application-level race conditions.

The existing soft-delete migrations and model traits remain in place. The
tenant scope must continue excluding soft-deleted tenants, including when
queries are made through `BelongsToTenant`.

## Account Lifecycle

An account lifecycle service is the only application-level entry point for
user closure and administrator removal.

### Self-closure

1. Start a database transaction.
2. Mark the user as soft-deleted with reason `self_closed` and no actor.
3. If the user is the last administrator for the tenant, soft-delete the
   tenant in the same transaction.
4. Log the user out and redirect to login.

The existing Settings and Edit Profile closure actions call this service rather
than duplicating deletion logic. Closure of a tenant requires typed confirmation
of the tenant name or slug. Export is offered before closure but is optional.

### Administrator removal

User resource edit and bulk-delete actions call the lifecycle service with the
authenticated actor and reason `admin_removed`. The deletion confirmation
states that the user cannot self-recover and may register again only as a new
account. The original email and provenance remain on the soft-deleted row.

### Recovery

The recovery service queries `User::withTrashed()` and validates the password.
After credentials are valid, it evaluates the newest matching eligible record
and returns a specific result:

- `self_closed`, user and tenant active: restore the user.
- `self_closed`, tenant soft-deleted and within its window: restore both in one
  transaction.
- `self_closed`, tenant already permanently deleted: restore the user with
  `tenant_id = null`; the next authenticated request opens new-tenant setup.
- `admin_removed`: deny self-service recovery.
- `tenant_closed`: deny self-service recovery.
- User deadline expired: deny recovery; purge owns permanent deletion.
- An active user already owns the email: deny recovery to preserve the active
  identity.

Successful recovery queues an email notification and redirects to normal login.
Existing MFA settings are retained and must be satisfied by normal login.

## Recovery and Tenant Setup UI

Add a public Filament auth page for account recovery and a link from the login
page. The page collects email and password and displays the recovery service's
specific authenticated result. Recovery has its own strict per-email/IP
throttle.

Add an authenticated tenant setup page for restored administrators whose old
tenant is gone. It asks for tenant name only, creates a tenant using existing
slug generation and reserved-label rules, assigns the restored user as its
administrator, and redirects to the normal admin panel. This is a new flow; the
current guest registration page cannot be reused by an authenticated user.

## GDPR Export

Tenant admins may export their tenant. Super administrators may select active or
recoverable soft-deleted tenants. A self-closed administrator must recover and
complete normal login before exporting during the retention window. Export is
also available before closure and is never a closure prerequisite.

The export action lets the administrator choose CSV or XLSX and always returns a
private ZIP archive:

- CSV: one safe CSV file per dataset plus uploaded media files.
- XLSX: one workbook with a worksheet per dataset plus uploaded media files.

The export includes safe allowlisted fields from tenant metadata, active and
soft-deleted users, posts, pages, categories, tags, comments, subscribers,
invitations, billing metadata, settings, media metadata/files, API and webhook
metadata, and backup metadata.

It excludes passwords, remember tokens, MFA secrets and recovery codes,
passkeys, API-key values and hashes, invitation and subscriber tokens, webhook,
FTP, and AI secrets, access tokens, sessions, credential-bearing notifications,
and backup archives.

Generation runs in a queued job using direct `openspout/openspout` support for
XLSX output. The ZIP is stored on private storage for 24 hours. The admin
receives a notification containing an authenticated expiring download link, and
expired files are removed by cleanup.

## Permanent Purge

Add a daily command and Laravel schedule, plus a Compose scheduler runner for
the existing deployment setup.

For a due tenant, the purge service:

1. Enumerates and deletes tenant media files and backup archives.
2. Removes tenant tokens, sessions, notifications, secrets, and queued
   references using idempotent cleanup operations.
3. Marks still-attached users as `tenant_closed` when needed, records the purge
   timestamp as their `deleted_at`, and detaches every user from the tenant.
4. Force-deletes the tenant after external cleanup succeeds, allowing database
   cascades to remove tenant-owned records.

Detached users are not deleted as a side effect of tenant force deletion. Each
user remains until their own deadline, then user cleanup removes personal
tokens, sessions, notifications, passkeys, and other non-cascading records
before force-deleting the user. Existing foreign keys remove authored posts
and dependent rows when a user is force-deleted.

If any external or queued-reference cleanup fails, the service throws a
retryable exception and leaves the database record pending. Repeated runs are
safe because file and reference deletion operations tolerate already-removed
items. Purge does not include backup archives in exports, but it does delete
them during retention purge.

## Testing

Add Pest coverage for:

- Soft-delete columns, indexes, model behavior, and tenant scoping.
- Self-closure, last-admin tenant closure, administrator removal provenance,
  and email reuse.
- Every recovery authorization branch, deadlines, tenantless recovery setup,
  active-email conflict, rate limiting, and normal MFA handoff.
- Export role authorization, cross-tenant isolation, active and trashed data,
  CSV/XLSX ZIP contents, media files, and absence of all excluded secrets.
- Tenant and user purge boundaries, detached-user retention, database cascades,
  external cleanup retries, and schedule registration.

Add Playwright coverage for the login recovery link, recovery outcomes, tenant
setup redirect, typed closure confirmation, export request/download
authorization, and responsive auth-page rendering.

## Dependencies

Declare `openspout/openspout` directly in Composer. It is currently installed
transitively through Filament, but the export feature is application behavior
and should retain an explicit, stable runtime dependency.
