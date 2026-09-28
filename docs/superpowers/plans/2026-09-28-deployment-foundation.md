# Deployment Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish immutable release validation and track the three provider deployments as linked work.

**Architecture:** The root `Jenkinsfile` remains CI-only. A single shell release gate resolves an annotated SemVer tag and runs the existing quality checks; platform Jenkinsfiles call it before any provider action. GitHub issue tracking is synchronized with Project #17 and `tickets.md`.

**Tech Stack:** Jenkins Declarative Pipeline, Bash, Git, Pest 4, GitHub CLI.

**Spec:** `docs/superpowers/specs/2026-09-28-multi-platform-deployment-design.md`

## Global Constraints

- Deploy only validated annotated `vMAJOR.MINOR.PATCH` tags.
- Preserve the existing Compose deployment and root CI pipeline.
- Never commit credentials, production URLs, IP addresses, `.env` files, or Terraform state.
- Keep provider credentials isolated in Jenkins credentials bindings.
- Link existing issue #29 rather than replacing it.

---

## File Structure

- Create `ci/validate-release.sh`: validates and checks out a release tag, then runs the shared quality gate.
- Create `blogravel/tests/Unit/Deployment/ReleaseValidationScriptTest.php`: verifies the executable's required tag and validation behavior through a temporary Git fixture.
- Modify `tickets.md`: mirrors the parent and three child GitHub issues and their Project #17 statuses.

### Task 1: Add the release-validation executable

**Files:**
- Create: `ci/validate-release.sh`
- Test: `blogravel/tests/Unit/Deployment/ReleaseValidationScriptTest.php`

**Interfaces:**
- Consumes: `RELEASE_TAG`, an annotated Git tag available in the Jenkins checkout.
- Produces: a checked-out release commit on success; non-zero exit with an actionable message for a missing, lightweight, or non-SemVer tag.

- [ ] **Step 1: Write the failing Pest test**

Create a temporary repository in the test, make both lightweight and annotated tags, execute the script with `RELEASE_TAG`, and assert that only an annotated `v1.2.3` tag reaches its success marker. Assert the script source invokes these existing commands:

```php
expect($script)
    ->toContain("git cat-file -t \"\$RELEASE_TAG\"")
    ->toContain('composer lint:check')
    ->toContain('php artisan test --compact')
    ->toContain('npm run build')
    ->toContain('docker compose --env-file .env.example config --quiet');
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `php artisan test --compact tests/Unit/Deployment/ReleaseValidationScriptTest.php`

Expected: FAIL because `ci/validate-release.sh` does not exist.

- [ ] **Step 3: Implement `ci/validate-release.sh`**

Use strict Bash mode and reject unset input before accessing it:

```bash
#!/usr/bin/env bash
set -euo pipefail

: "${RELEASE_TAG:?RELEASE_TAG must name an annotated vMAJOR.MINOR.PATCH tag}"
[[ "$RELEASE_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
    printf '%s\n' 'RELEASE_TAG must match vMAJOR.MINOR.PATCH' >&2
    exit 1
}
[[ "$(git cat-file -t "$RELEASE_TAG")" == 'tag' ]] || {
    printf '%s\n' 'RELEASE_TAG must be an annotated tag' >&2
    exit 1
}
git checkout --detach "$RELEASE_TAG^{commit}"
```

After changing into `blogravel/`, run `composer install --no-interaction --prefer-dist --no-progress`, `npm ci`, `composer lint:check`, `php artisan test --compact`, `npm run build`, and `docker compose --env-file .env.example config --quiet` in that order. Do not add deployment credentials or platform commands.

- [ ] **Step 4: Run focused and regression validation**

Run:

```bash
chmod +x ci/validate-release.sh
cd blogravel
php artisan test --compact tests/Unit/Deployment/ReleaseValidationScriptTest.php
docker compose --env-file .env.example config --quiet
```

Expected: PASS and no unresolved Compose variables.

- [ ] **Step 5: Commit the release foundation**

```bash
git add ci/validate-release.sh blogravel/tests/Unit/Deployment/ReleaseValidationScriptTest.php
git commit -m "Feature: add immutable release validation"
```

### Task 2: Create and synchronize deployment tickets

**Files:**
- Modify: `tickets.md`

**Interfaces:**
- Consumes: GitHub repository `Azab-Inc/blogravel`, GitHub Project #17, existing issue #29.
- Produces: one parent issue and three child issues, all in Project #17 with `Todo` status and mirrored in `tickets.md`.

- [ ] **Step 1: Create the parent issue**

Create `Multi-platform production deployment` through `gh issue create`. Its body must link #29 and define the shared immutable-tag, provider-isolation, secret-management, DNS, validation, and rollback acceptance criteria from the spec.

- [ ] **Step 2: Create the three child issues**

Create these exact titles and link each body to the parent issue:

```text
Hostinger VPS tagged deployment
Laravel Cloud tagged deployment
GCP Cloud Run tagged deployment
```

The issue bodies must copy the platform-specific acceptance criteria from the spec, including Redis/Compose for Hostinger, managed queues/Object Storage for Laravel Cloud, and Cloud Run/Cloud SQL/GCS/database queue for GCP.

- [ ] **Step 3: Add issues to Project #17**

Capture each URL returned by `gh issue create`, then pass that captured URL to `gh project item-add 17 --owner Azab-Inc --url "$ISSUE_URL"`. Set each Project item Status to `Todo` with the existing project field and option IDs discovered through `gh project field-list 17 --owner Azab-Inc`.

- [ ] **Step 4: Mirror the issue URLs and titles in `tickets.md`**

Add the four issues as `Todo` entries. Preserve every existing row and leave issue #29 as its existing `Todo` entry.

- [ ] **Step 5: Verify synchronization and commit**

Run `gh project item-list 17 --owner Azab-Inc --limit 100` and confirm all four issue titles are `Todo`. Review `tickets.md`, then commit:

```bash
git add tickets.md
git commit -m "Docs: track multi-platform deployment work"
```
