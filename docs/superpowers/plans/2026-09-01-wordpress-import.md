# Phase 4 — WordPress Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import WordPress content (posts, pages, categories, tags, comments, media) via WXR XML file or direct database connection.

**Architecture:** Two import methods (WXR file upload, MySQL DB connection) feed into a shared processing pipeline. A Filament page handles upload/config, a queued job processes the import, and progress is tracked via a DB-backed status.

**Tech Stack:** Laravel 13, Filament v5, Pest PHP, SimpleXML/PDO

**Spec:** `docs/implementation-plan.md` (Phase 4 section)

## Global Constraints
- PHP 8.5+, Laravel 13, Filament v5.7.6
- UUID primary keys on all models
- Multi-tenant: every import scoped to current user's tenant_id
- Run `vendor/bin/pint --format agent` on changed files
- Run `php artisan test --compact` to verify
- Run `php artisan octane:reload` after Filament changes

---

## Task 1: WXR XML Parser

**Files:**
- Create: `app/Services/WordPress/WxrParser.php`
- Create: `tests/Unit/WxrParserTest.php`

**Responsibilities:** Parse WXR 1.0/1.1 XML, extract posts/pages/categories/tags/comments/media attachments, normalize statuses.

- [ ] Create WxrParser class that accepts XML string or file path
- [ ] Parse channel metadata (title, link, description)
- [ ] Parse items (posts, pages) with title, content, excerpt, slug, status, date, author, categories, tags
- [ ] Map WordPress statuses: publish→published, draft→draft, pending→draft, private→published, trash→draft
- [ ] Parse wp:category and wp:tag taxonomies
- [ ] Parse comments per post
- [ ] Parse attachment items (media)
- [ ] Write unit tests with a sample WXR fixture
- [ ] Run tests, run Pint

---

## Task 2: Import Job

**Files:**
- Create: `app/Jobs/WordPressImportJob.php`
- Modify: `app/Jobs/ProcessWordPressImport.php` (if skeleton exists, replace it)

**Responsibilities:** Process parsed WXR data idempotently — create/skip posts, pages, categories, tags, comments; download media; map authors.

- [ ] Create WordPressImportJob implementing ShouldQueue
- [ ] Accept WxrParser output (array of items) + tenant_id + user_id
- [ ] Idempotency: skip records where slug already exists for tenant
- [ ] Create categories, link to posts via pivot
- [ ] Create tags, link to posts via pivot
- [ ] Create/skip posts with author mapping (create User if not exists)
- [ ] Create/skip comments
- [ ] Download media attachments to storage/app/public/media, create Media records
- [ ] Update post content URLs to point to local media paths
- [ ] Track progress: store imported/skipped/failed counts
- [ ] Write tests with factories

---

## Task 3: Filament Import Page (WXR Upload)

**Files:**
- Create: `app/Filament/Pages/ImportWordPress.php`
- Create: `resources/views/filament/pages/import-wordpress.blade.php`

**Responsibilities:** Upload WXR file, dispatch import job, show progress.

- [ ] Create Filament Page with file upload component (accepts .xml)
- [ ] Validate uploaded file is valid WXR
- [ ] Parse with WxrParser, dispatch WordPressImportJob
- [ ] Show job status: pending/processing/completed/failed with counts
- [ ] Add to navigation under Content group or new Import group
- [ ] Run Pint, reload Octane

---

## Task 4: Database Connection Import

**Files:**
- Create: `app/Services/WordPress/DatabaseImporter.php`
- Modify: `app/Filament/Pages/ImportWordPress.php` (add DB connection form)

**Responsibilities:** Connect to remote MySQL DB, read WordPress tables, feed into same import pipeline.

- [ ] Create DatabaseImporter class that accepts MySQL connection string
- [ ] PDO connect to remote WordPress database
- [ ] Read wp_posts, wp_postmeta, wp_terms, wp_term_taxonomy, wp_term_relationships, wp_comments, wp_users tables
- [ ] Convert to same format as WxrParser output
- [ ] Reuse WordPressImportJob for processing
- [ ] Add DB connection form to ImportWordPress page (host, port, database, username, password)
- [ ] Test connection button with inline feedback
- [ ] Write tests

---

## Task 5: Verification

- [ ] Run `php artisan test --compact` — all tests pass
- [ ] Run `vendor/bin/pint --format agent` — no changes
- [ ] Manual: upload a WXR file, verify content appears in admin
- [ ] Commit all Phase 4 work
