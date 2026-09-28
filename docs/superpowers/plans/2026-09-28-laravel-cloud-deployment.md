# Laravel Cloud Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy validated tagged commits to Laravel Cloud with platform-native queues, storage, and secrets.

**Architecture:** Jenkins validates the tag and POSTs its resolved commit SHA to a Laravel Cloud deploy hook. Laravel Cloud owns the application runtime, managed queue, Object Storage, and secret configuration; no Terraform is added for this target.

**Tech Stack:** Jenkins Declarative Pipeline, Bash, curl, Laravel Cloud managed queues and Object Storage, Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-28-multi-platform-deployment-design.md`

## Global Constraints

- Implement `docs/superpowers/plans/2026-09-28-deployment-foundation.md` first.
- Require a Laravel version supported by Laravel Cloud managed queues; the repository's Laravel `^13.7` satisfies the documented floor.
- Use Laravel Cloud managed queues; do not run Horizon for this target.
- Store the deploy hook and application credentials in Jenkins/Laravel Cloud only.
- Do not create Terraform resources for Laravel Cloud.

---

## File Structure

- Create `Jenkinsfile.laravel-cloud`: manual tagged deployment-hook pipeline.
- Create `blogravel/tests/Unit/Deployment/LaravelCloudDeploymentPipelineTest.php`: validates immutable-commit hook usage.
- Create `docs/laravel-cloud-deployment.md`: setup and operator runbook.

### Task 1: Add the Laravel Cloud deployment pipeline

**Files:**
- Create: `Jenkinsfile.laravel-cloud`
- Test: `blogravel/tests/Unit/Deployment/LaravelCloudDeploymentPipelineTest.php`

**Interfaces:**
- Consumes: `RELEASE_TAG` parameter and `LARAVEL_CLOUD_DEPLOY_HOOK` secret text credential.
- Produces: an authenticated POST to the deploy hook using the resolved immutable tag commit.

- [ ] **Step 1: Write the failing Pest configuration test**

Assert the pipeline calls `ci/validate-release.sh`, binds `LARAVEL_CLOUD_DEPLOY_HOOK` through `withCredentials([string(...)])`, resolves `git rev-parse "${RELEASE_TAG}^{commit}"`, and invokes:

```bash
curl --fail --silent --show-error --request POST "${LARAVEL_CLOUD_DEPLOY_HOOK}?commit_hash=${RELEASE_COMMIT}"
```

Assert it contains no `php artisan horizon`, `terraform`, or literal hook URL.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `cd blogravel && php artisan test --compact tests/Unit/Deployment/LaravelCloudDeploymentPipelineTest.php`

Expected: FAIL because the pipeline is absent.

- [ ] **Step 3: Implement the declarative pipeline**

Use `php85`, a required string `RELEASE_TAG` parameter, `disableConcurrentBuilds()`, the shared validation script, and one deploy-hook stage. Store the resolved commit only in a local shell variable and use curl failure flags so a non-2xx hook response fails Jenkins.

- [ ] **Step 4: Run focused validation and commit**

Run the focused Pest test. After it passes:

```bash
git add Jenkinsfile.laravel-cloud blogravel/tests/Unit/Deployment/LaravelCloudDeploymentPipelineTest.php
git commit -m "Feature: add Laravel Cloud tagged deployment pipeline"
```

### Task 2: Document Laravel Cloud configuration and operations

**Files:**
- Create: `docs/laravel-cloud-deployment.md`

**Interfaces:**
- Consumes: Jenkins deploy-hook contract and Laravel Cloud environment configuration.
- Produces: reproducible dashboard setup and deployment/rollback instructions.

- [ ] **Step 1: Document environment provisioning**

Specify connecting the tracked repository branch, enabling the deploy hook, setting the root and wildcard custom hostnames, and configuring `APP_URL`, `TENANCY_PLATFORM_DOMAIN`, `SESSION_DOMAIN`, production database/cache settings, mail credentials, and Laravel Cloud Object Storage disk settings as Laravel Cloud secrets.

- [ ] **Step 2: Document managed queues**

Require one standard Flex managed queue as default, set `QUEUE_CONNECTION=cloud`, and explain that Laravel Cloud's Queues dashboard handles failed-job inspection/retry/delete. State that no Horizon process or Redis queue is configured for this target.

- [ ] **Step 3: Document validation and rollback**

List deploy-hook Jenkins execution, Laravel Cloud deployment logs, queue dashboard, `/up`, root-domain admin login, and tenant-host smoke checks. Rollback is a prior validated tag's commit hash through the same Jenkinsfile; migration rollback remains manual.

- [ ] **Step 4: Commit the runbook**

```bash
git add docs/laravel-cloud-deployment.md
git commit -m "Docs: add Laravel Cloud deployment runbook"
```
