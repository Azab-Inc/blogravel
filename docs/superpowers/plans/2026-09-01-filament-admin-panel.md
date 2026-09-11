# Phase 3 — Filament Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build complete Filament admin CRUD resources for all models, enforce RBAC at UI and route level, and add dashboard widgets.

**Architecture:** Each model gets a Filament Resource with form, table, and pages. Policies enforce role-based access. Widgets provide dashboard overview. All resources are tenant-scoped.

**Tech Stack:** Filament v5.7.6, Laravel 13, PostgreSQL 18, Pest PHP

**Spec:** `docs/implementation-plan.md` (Phase 3 section)

## Global Constraints
- PHP 8.5+, Laravel 13, Filament v5.7.6
- UUID primary keys on all models (BaseModel with HasUuids)
- Multi-tenant: every query scoped to tenant_id
- Roles: SuperAdmin, Editor, Author (app/Enums/Role.php)
- Use `#[Fillable]` and `#[Hidden]` attribute imports on models
- Filament v5: `$navigationIcon` must be `string|BackedEnum|null`, form components use `Filament\Schemas\Components\Component`
- Run `php artisan octane:reload` after registering new Filament routes
- Run `vendor/bin/pint --format agent` on all changed PHP files
- Run `php artisan test --compact` to verify

---

## Task 1: PostResource

**Files:**
- Create: `app/Filament/Resources/PostResource.php`
- Create: `app/Filament/Resources/PostResource/Pages/ListPosts.php`
- Create: `app/Filament/Resources/PostResource/Pages/CreatePost.php`
- Create: `app/Filament/Resources/PostResource/Pages/EditPost.php`

**Form fields:** title, slug (auto-generated from title), content (rich editor/Tiptap), excerpt, status (PostStatus enum), published_at (date picker), categories (BelongsToMany checkbox), tags (BelongsToMany checkbox)

**Table columns:** title, author.name, status (badge), categories, published_at, created_at

**Relationships to eager load:** author, categories, tags

- [ ] Create PostResource with form and table
- [ ] Create List, Create, Edit page classes
- [ ] Auto-generate slug from title on create
- [ ] Run Pint + reload Octane
- [ ] Verify resource loads in admin panel

---

## Task 2: PageResource

**Files:**
- Create: `app/Filament/Resources/PageResource.php`
- Create: `app/Filament/Resources/PageResource/Pages/ListPages.php`
- Create: `app/Filament/Resources/PageResource/Pages/CreatePage.php`
- Create: `app/Filament/Resources/PageResource/Pages/EditPage.php`

**Form fields:** title, slug, content (rich editor), status (PostStatus enum)

**Table columns:** title, status (badge), created_at, updated_at

- [ ] Create PageResource with form and table
- [ ] Create List, Create, Edit page classes
- [ ] Auto-generate slug from title on create
- [ ] Run Pint + reload Octane
- [ ] Verify resource loads in admin panel

---

## Task 3: CategoryResource + TagResource

**Files:**
- Create: `app/Filament/Resources/CategoryResource.php` + Pages/
- Create: `app/Filament/Resources/TagResource.php` + Pages/

**Category form:** name, slug
**Tag form:** name, slug

**Category table:** name, slug, posts_count, created_at
**Tag table:** name, slug, posts_count, created_at

- [ ] Create CategoryResource with form, table, List/Create/Edit pages
- [ ] Create TagResource with form, table, List/Create/Edit pages
- [ ] Auto-generate slugs from name
- [ ] Add posts_count relation count
- [ ] Run Pint + reload Octane
- [ ] Verify both resources load

---

## Task 4: CommentResource

**Files:**
- Create: `app/Filament/Resources/CommentResource.php`
- Create: `app/Filament/Resources/CommentResource/Pages/ListComments.php`

**Table:** author_name, author_email, post.title, status (badge), content (truncated), created_at
**Filters:** status (Pending/Approved/Spam)
**Actions:** approve, mark spam, delete (inline on table rows)

- [ ] Create CommentResource with table, filters, and inline actions
- [ ] Create List page with bulk actions (approve, spam, delete)
- [ ] Run Pint + reload Octane
- [ ] Verify resource loads

---

## Task 5: MediaResource

**Files:**
- Create: `app/Filament/Resources/MediaResource.php`
- Create: `app/Filament/Resources/MediaResource/Pages/ListMedia.php`
- Create: `app/Filament/Resources/MediaResource/Pages/CreateMedia.php`

**Form:** file upload, name
**Table:** name, file_path, mime_type, size (formatted), created_at
**Storage:** local disk (storage/app/public)

- [ ] Create MediaResource with file upload form
- [ ] Store files to storage/app/public
- [ ] Create List and Create pages
- [ ] Run Pint + reload Octane
- [ ] Verify upload works

---

## Task 6: UserResource

**Files:**
- Create: `app/Filament/Resources/UserResource.php`
- Create: `app/Filament/Resources/UserResource/Pages/ListUsers.php`
- Create: `app/Filament/Resources/UserResource/Pages/CreateUser.php`
- Create: `app/Filament/Resources/UserResource/Pages/EditUser.php`

**Form:** first_name, last_name, email, password (only on create), role (select), tenant_id (disabled/auto-assigned)
**Table:** name, email, role (badge), created_at
**Relationships:** tenant

- [ ] Create UserResource with form and table
- [ ] Create List, Create, Edit pages
- [ ] Hash password on create, ignore on edit
- [ ] Run Pint + reload Octane
- [ ] Verify resource loads

---

## Task 7: SettingResource

**Files:**
- Create: `app/Filament/Resources/SettingResource.php`
- Create: `app/Filament/Resources/SettingResource/Pages/ListSettings.php`
- Create: `app/Filament/Resources/SettingResource/Pages/CreateSetting.php`
- Create: `app/Filament/Resources/SettingResource/Pages/EditSetting.php`

**Form:** key (text), value (textarea)
**Table:** key, value, updated_at

This is a simple key-value store per tenant.

- [x] Create SettingResource with form and table
- [x] Create List, Create, Edit pages
- [x] Run Pint + reload Octane
- [x] Verify resource loads

---

## Task 8: Role-Based Policies

**Files:**
- Create: `app/Policies/PostPolicy.php`
- Create: `app/Policies/PagePolicy.php`
- Create: `app/Policies/UserPolicy.php`
- Create: `app/Policies/SettingPolicy.php`
- Create: `app/Policies/CommentPolicy.php`
- Create: `app/Policies/CategoryPolicy.php`
- Create: `app/Policies/TagPolicy.php`
- Create: `app/Policies/MediaPolicy.php`

**Rules:**
- SuperAdmin: full access to everything
- Editor: create/edit/publish any post/page; manage comments and media; no user management or settings
- Author: create/edit own posts only; cannot publish; cannot manage other users' content

- [ ] Create PostPolicy (viewAny, view, create, update, delete, restore)
- [ ] Create PagePolicy (same as PostPolicy)
- [ ] Create UserPolicy (SuperAdmin only)
- [ ] Create SettingPolicy (SuperAdmin only)
- [ ] Create CommentPolicy (Editor+ can manage)
- [ ] Create CategoryPolicy, TagPolicy (Editor+ can manage)
- [ ] Create MediaPolicy (Editor+ can manage)
- [ ] Register policies in AuthServiceProvider or auto-discover
- [ ] Run tests

---

## Task 9: Dashboard Widgets

**Files:**
- Create: `app/Filament/Widgets/RecentPostsOverview.php`
- Create: `app/Filament/Widgets/CommentModerationCount.php`
- Create: `app/Filament/Widgets/SubscriberCount.php`
- Create: `app/Filament/Widgets/LatestPosts.php`

**Widgets:**
- RecentPostsOverview: StatsOverview showing posts count (last 7 days), total posts, total pages
- CommentModerationCount: Stat showing pending comments count with link to comment list
- SubscriberCount: Stat showing total active subscribers
- LatestPosts: TableWidget showing 5 most recent posts with edit links

- [ ] Create StatsOverview widget (posts, pages, comments counts)
- [ ] Create CommentModerationCount widget
- [ ] Create SubscriberCount widget
- [ ] Create LatestPosts table widget
- [ ] Register all widgets in AdminPanelProvider
- [ ] Run Pint + reload Octane
- [ ] Verify dashboard loads with all widgets

---

## Task 10: Verification & Tests

- [ ] Run `php artisan test --compact` — all tests pass
- [ ] Manual check: each resource loads in admin panel
- [ ] Manual check: role restrictions work (login as Author, verify cannot access User/Settings resources)
- [ ] Run `vendor/bin/pint --format agent` — no changes needed
- [ ] Commit all Phase 3 work

---

## Task 11: User Profile Settings Page

**Goal:** Allow users to click their name in the Filament top-right user menu to access a profile settings page where they can edit their name, email, change password, and close their account.

**Files:**
- Create: `app/Filament/Pages/Auth/EditProfile.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (register page, configure user menu link)

**Context:** The Filament top-right user menu currently shows the user's name (e.g., "Ada Minner") but clicking it does nothing. The goal is to link this to a new Filament page.

### Sub-task 11a: Create ProfileSettings Filament Page

- [x] Create `app/Filament/Pages/Auth/EditProfile.php` extending `Filament\Auth\Pages\EditProfile`
- [x] Add form with: first_name, last_name, email fields (pre-filled from auth user)
- [x] Form uses `filament()->getUser()` to load current user data via base class
- [x] On save, update the authenticated user's profile (validate email uniqueness excluding self)
- [x] Add success notification after save (via base class)
- [x] Set page title to "Profile Settings"
- [x] Register in `AdminPanelProvider.php` via `->profile(EditProfile::class)`

### Sub-task 11b: Add Password Change to Profile Page

- [x] Add password section to profile form: password, password_confirmation, currentPassword
- [x] Validate current_password matches before allowing change (via base class `currentPassword()` rule)
- [x] Hash new password via base class `dehydrateStateUsing(fn ($state) => Hash::make($state))`
- [x] Clear password fields after successful update (via base class)
- [x] Password confirmation field always visible, required when password filled

### Sub-task 11c: Add Account Closure with Admin Protection

- [x] Add "Danger Zone" section at bottom of profile page
- [x] Add "Close Account" button with confirmation modal
- [x] On confirm: soft-delete the user (set `deleted_at` timestamp)
- [x] If user is the ONLY admin/super_admin in their tenant: soft-delete tenant too
- [x] Log the user out after account closure
- [x] Redirect to login page with success message

### Sub-task 11d: Migration for Soft Delete Columns

- [x] Create migration: `add_deleted_at_to_users_table`
- [x] Create migration: `add_deleted_at_to_tenants_table`
- [x] Add `SoftDeletes` trait to User model
- [x] Add `SoftDeletes` trait to Tenant model
- [x] Scope queries to exclude soft-deleted users/tenants

### Sub-task 11e: Configure User Menu Link

- [x] In `AdminPanelProvider.php`, use `->profile(EditProfile::class)` to wire the menu item
- [x] Clicking the user's name navigates to `/admin/profile`

### Sub-task 11f: Tests

- [x] Test: authenticated user can access profile settings page
- [x] Test: user can update first_name, last_name, email
- [x] Test: user can change password with valid currentPassword
- [x] Test: password change fails with wrong currentPassword
- [x] Test: user can close their own account (soft delete)
- [x] Test: last admin closure also soft-deletes tenant
- [x] Test: non-last-admin closure does NOT delete tenant

---

## Task 12: Database Migrations for Soft Delete Support

**Files:**
- Create: `database/migrations/2026_09_10_000000_add_deleted_at_to_users_table.php`
- Create: `database/migrations/2026_09_10_000001_add_deleted_at_to_tenants_table.php`
- Modify: `app/Models/User.php` — add `SoftDeletes` trait
- Modify: `app/Models/Tenant.php` — add `SoftDeletes` trait

**Details:**
- [x] Add `deleted_at` column to `users` table (nullable timestamp, index)
- [x] Add `deleted_at` column to `tenants` table (nullable timestamp, index)
- [x] Add `use Illuminate\Database\Eloquent\SoftDeletes;` and `use SoftDeletes;` to User model
- [x] Add `use SoftDeletes;` to Tenant model
- [x] Ensure tenant scoping in `BelongsToTenant` trait excludes soft-deleted tenants
- [x] Run `php artisan migrate`
- [x] Run `vendor/bin/pint --format agent`
- [x] Run `php artisan test --compact`
