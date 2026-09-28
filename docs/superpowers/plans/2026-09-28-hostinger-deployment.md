# Hostinger VPS Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy validated immutable tags to the existing Hostinger VPS Compose installation from Jenkins.

**Architecture:** A dedicated declarative Jenkinsfile calls the shared release gate, binds password-based SSH credentials, and runs the existing documented Compose upgrade sequence remotely. The VPS remains outside Terraform and retains PostgreSQL, Redis, queue, and scheduler containers.

**Tech Stack:** Jenkins Declarative Pipeline, Bash, SSH, `sshpass`, Docker Compose, Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-28-multi-platform-deployment-design.md`

## Global Constraints

- Implement `docs/superpowers/plans/2026-09-28-deployment-foundation.md` first.
- Use the existing password-based SSH/`sshpass` mechanism; bind its secret from Jenkins.
- Use a validated release tag and the existing Compose upgrade commands.
- Do not provision the VPS with Terraform or copy production secrets from the repository.

---

## File Structure

- Create `Jenkinsfile.hostinger`: parameterized manual Hostinger deployment pipeline.
- Create `blogravel/tests/Unit/Deployment/HostingerDeploymentPipelineTest.php`: static safety checks for the pipeline.
- Create `docs/hostinger-vps-deployment.md`: operator runbook for setup, deploy, verify, backup, and rollback.

### Task 1: Add the Hostinger deployment pipeline

**Files:**
- Create: `Jenkinsfile.hostinger`
- Test: `blogravel/tests/Unit/Deployment/HostingerDeploymentPipelineTest.php`

**Interfaces:**
- Consumes: Jenkins `RELEASE_TAG`, `HOSTINGER_HOST`, `HOSTINGER_DEPLOY_PATH`, `HOSTINGER_SSH_CREDENTIALS_ID` parameters.
- Produces: an upgraded Compose stack and a successful remote `/up` check.

- [ ] **Step 1: Write the failing Pest configuration test**

Read `Jenkinsfile.hostinger` and assert it declares `RELEASE_TAG`, calls `ci/validate-release.sh`, uses `withCredentials([usernamePassword(...)])`, runs `sshpass`, uses `git checkout`, runs `docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build`, `php artisan migrate --force`, `php artisan optimize:clear`, restarts `laravel.test queue scheduler`, and curls `/up` with `--fail`.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `cd blogravel && php artisan test --compact tests/Unit/Deployment/HostingerDeploymentPipelineTest.php`

Expected: FAIL because the pipeline file is absent.

- [ ] **Step 3: Implement the Jenkins pipeline**

Use the existing `php85` agent and `disableConcurrentBuilds()`. Run `RELEASE_TAG="${params.RELEASE_TAG}" ./ci/validate-release.sh`, then bind a Jenkins username/password credential. Remote commands must be one quoted SSH command that changes to `HOSTINGER_DEPLOY_PATH`, fetches tags, checks out the requested tag, runs the documented upgrade sequence, and ends with:

```bash
curl --fail --silent --show-error http://127.0.0.1:${APP_PORT:-8080}/up
```

Never interpolate the password into logs or accept an unvalidated arbitrary shell command parameter.

- [ ] **Step 4: Run focused validation**

Run:

```bash
cd blogravel
php artisan test --compact tests/Unit/Deployment/HostingerDeploymentPipelineTest.php
```

Expected: PASS. Jenkins runs the pipeline only after its three Jenkins credential/host parameters are configured.

- [ ] **Step 5: Commit the pipeline and test**

```bash
git add Jenkinsfile.hostinger blogravel/tests/Unit/Deployment/HostingerDeploymentPipelineTest.php
git commit -m "Feature: add Hostinger tagged deployment pipeline"
```

### Task 2: Document Hostinger operations

**Files:**
- Create: `docs/hostinger-vps-deployment.md`

**Interfaces:**
- Consumes: the parameter and credential contract from `Jenkinsfile.hostinger`.
- Produces: an operator checklist for provisioning, deployment, verification, backup, and rollback.

- [ ] **Step 1: Write the runbook**

Document Docker Engine/Compose, a tagged repository checkout, production `.env` stored only on the VPS, Jenkins credential setup, wildcard DNS/TLS, and private internal services. Link `self-hosted-deployment.md` for environment values and `self-hosted-release-process.md` for tag creation.

- [ ] **Step 2: Document exact operational commands**

Include the existing upgrade commands, `docker compose ... ps`, service log commands, `php artisan queue:failed`, and a rollback to the previous tag. State that schema/data rollback requires migration review and a verified backup.

- [ ] **Step 3: Review documentation accuracy and commit**

Compare every runbook command to `blogravel/compose.yaml` and `Jenkinsfile.hostinger`, then commit:

```bash
git add docs/hostinger-vps-deployment.md
git commit -m "Docs: add Hostinger deployment runbook"
```
