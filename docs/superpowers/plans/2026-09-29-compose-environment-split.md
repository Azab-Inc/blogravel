# Compose Environment Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `compose.yaml` the VPS production stack and `compose.dev.yaml` the local stack, with bind mounts in every Compose file and no Docker-managed volumes.

**Architecture:** The production file builds the existing production image but mounts the checked-out application source at runtime, with PostgreSQL, Redis, web, queue, and scheduler services. The development file keeps the Sail runtime and adds pgAdmin and Mailpit directly, so local `sail up` and `sail down` manage the same services.

**Tech Stack:** Docker Compose v2, Laravel Sail, FrankenPHP, PostgreSQL, Redis, Pest.

**Spec:** Approved conversation design for the production/local Compose split.

## Global Constraints

- `compose.yaml` is for the production VPS.
- `compose.dev.yaml` is for local development.
- All application/runtime data mounts must be bind mounts; no Docker-managed volumes.
- pgAdmin and Mailpit exist only in `compose.dev.yaml`.
- Existing service names and VPS command compatibility should be preserved where practical.
- Compose configuration must be validated with `.env.example` and production-shaped overrides.

---

### Task 1: Add failing Compose contract tests

**Files:**
- Modify: `blogravel/tests/Unit/Deployment/ComposeConfigurationTest.php`
- Test: existing Compose configuration test file

- [x] **Step 1: Add helpers for selecting `compose.yaml` and `compose.dev.yaml`**

Make the Compose test helper accept a file path and continue parsing JSON through `docker compose config`.

- [x] **Step 2: Add failing assertions**

Assert that production has no `pgadmin` or `mailpit`, uses `docker/production/Dockerfile`, uses `.:/app` as a bind mount, and has no top-level `volumes` declarations. Assert that development has Sail bind mounts, Composer bootstrap, pgAdmin, Mailpit, and no top-level Docker-managed volumes.

- [x] **Step 3: Run the focused test**

Run: `php artisan test --compact tests/Unit/Deployment/ComposeConfigurationTest.php`

Expected: FAIL because `compose.dev.yaml` does not exist and the current `compose.yaml` is still the development configuration.

### Task 2: Split the Compose files

**Files:**
- Create: `blogravel/compose.dev.yaml`
- Modify: `blogravel/compose.yaml`
- Modify: `blogravel/.env.example`

- [x] **Step 1: Move the current Sail development services to `compose.dev.yaml`**

Keep the existing local app, PostgreSQL, Redis, queue, and scheduler definitions. Add a bind-mounted Composer bootstrap service. Remove profiles from pgAdmin and Mailpit so they are always part of the local stack. Replace Docker-managed volumes with bind mounts under `docker/volumes/` for PostgreSQL, Redis, and pgAdmin data.

- [x] **Step 2: Define the production VPS stack in `compose.yaml`**

Use `docker/production/Dockerfile` for the web, queue, and scheduler services. Keep the service names `laravel.test`, `pgsql`, `redis`, `queue`, and `scheduler`, plus bind-mounted Composer and frontend preparation services. Mount `.:/app` into application services, mount `./docker/volumes/pgsql:/var/lib/postgresql`, and mount `./docker/volumes/redis:/data`. Do not define pgAdmin, Mailpit, or Docker-managed volumes.

- [x] **Step 3: Keep production roles functional with the mounted checkout**

Set `CONTAINER_ROLE=web` for the web service, `CONTAINER_ROLE=worker` for the queue service, and `CONTAINER_ROLE=scheduler` for the scheduler service. Mount the repository source consistently so deployment commands operate on the same checkout visible to every service.

- [x] **Step 4: Configure Sail to use the development file**

Add `SAIL_FILES=compose.dev.yaml` to `.env.example` so `./vendor/bin/sail up` and `./vendor/bin/sail down` use the local stack rather than the VPS stack.

- [x] **Step 5: Run Compose config validation**

Run:

```bash
docker compose --env-file .env.example -f compose.yaml config
docker compose --env-file .env.example -f compose.dev.yaml config
```

Expected: both commands succeed without unresolved variables or named volume declarations.

### Task 3: Update deployment and developer documentation

**Files:**
- Modify: `README.md`
- Modify: `docs/project.md`
- Modify: `docs/hostinger-vps-deployment.md`
- Modify: `docs/self-hosted-release-process.md`

- [x] **Step 1: Change local commands to use `compose.dev.yaml` or Sail**

Document local startup, exec, testing, and shutdown against `compose.dev.yaml`.

- [x] **Step 2: Keep VPS commands on `compose.yaml`**

Document that the VPS file uses bind-mounted source and bind-mounted database/Redis data. Remove any instruction that enables the development profile or expects pgAdmin/Mailpit in production.

- [x] **Step 3: Document bind-mount ownership and backup requirements**

Explain that `docker/volumes/pgsql`, `docker/volumes/redis`, and any runtime storage directories must be backed up and permissioned for the container user. Explicitly prohibit `docker compose down -v` and deletion of those directories on the VPS.

### Task 4: Verify and commit

**Files:**
- Modify: `blogravel/tests/Unit/Deployment/ComposeConfigurationTest.php`

- [x] **Step 1: Run focused tests and formatting**

Run: `php artisan test --compact tests/Unit/Deployment/ComposeConfigurationTest.php` and `vendor/bin/pint --dirty --format agent`.

- [x] **Step 2: Validate both Compose files and inspect the rendered service graph**

Run both `docker compose ... config` commands, then verify production has seven services, development has eight services, neither file has top-level Docker-managed volumes, and only development includes pgAdmin/Mailpit.

- [x] **Step 3: Check the complete diff**

Run: `git diff --check` and inspect `git status --short`.

- [x] **Step 4: Commit**

```bash
git add blogravel/compose.yaml blogravel/compose.dev.yaml blogravel/.env.example blogravel/tests/Unit/Deployment/ComposeConfigurationTest.php README.md docs/project.md docs/hostinger-vps-deployment.md docs/self-hosted-release-process.md docs/superpowers/plans/2026-09-29-compose-environment-split.md
git commit -m "Feature: split production and development Compose stacks"
```
