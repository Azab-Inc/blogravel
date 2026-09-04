# WordPress Import Job Implementation Plan

## Context
The Blogravel project needs a WordPress WXR import job. A skeleton `ProcessWordPressImport.php` exists but is empty. We need to create the actual import logic and replace the skeleton with a dispatcher wrapper.

## Key Codebase Facts
- All models extend `BaseModel` which uses `HasUuids` (UUID primary keys)
- User has `'password' => 'hashed'` cast — passwords are auto-hashed on set
- Post statuses: `draft`, `scheduled`, `published`
- Comment statuses: `pending`, `approved`, `spam`
- Roles: `super_admin`, `editor`, `author`
- Pivot tables: `post_category`, `post_tag`
- Existing jobs are empty stubs using only `Queueable`

## Files to Create/Modify

### 1. Create `app/Jobs/WordPressImportJob.php`
Full import job implementing `ShouldQueue` with:
- Constructor: `array $items`, `string $tenantId`, `string $userId`
- `handle()` method processing categories, tags, users, posts, pages, comments, and attachments
- Idempotency via `findOrCreate` / skip-if-exists patterns
- Counters: `$imported`, `$skipped`, `$failed`
- `progress()` method returning current counts
- Status mapping for WordPress statuses to Blogravel enums
- Media download via `file_get_contents()` + `Storage::disk('public')->put()`

### 2. Replace `app/Jobs/ProcessWordPressImport.php`
Replace skeleton with a simple wrapper that:
- Accepts same constructor params
- Dispatches `WordPressImportJob` with the items

### 3. Create `tests/Feature/Jobs/WordPressImportJobTest.php`
Test:
- Categories are created and synced to posts
- Tags are created and synced to posts
- Authors are created or found by email
- Posts are created with correct status mapping
- Pages are created
- Comments are created with correct status
- Duplicate slugs are skipped (idempotency)
- Media attachments are downloaded and Media records created

### 4. Formatting
Run `vendor/bin/pint --dirty --format agent` after code changes.

## Verification
- `docker compose exec laravel.test ./vendor/bin/pint app/Jobs/ --format agent`
- `docker compose exec laravel.test php artisan test --compact 2>&1 | tail -5`
