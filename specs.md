# Blogravel Current Status Specification

**Snapshot:** `518aba328078ea2fad818115e0263d004c549ba6` (2026-10-04), branch `dev`.

## Status vocabulary

- **Done:** implementation and available automated evidence are complete; no known manual gate remains in this snapshot.
- **To Verify:** implementation evidence exists, but the acceptance criteria still require a manual/browser/provider/production check. This does not mean the GitHub issue is closed.
- **In Progress:** meaningful implementation exists, but the issue acceptance criteria are not complete.
- **Todo (future):** deliberately deferred scope; no current implementation claim.

## Implemented, awaiting manual verification

| Issue | Evidence in this repository | Manual gate |
|---|---|---|
| [#28](https://github.com/Azab-Inc/blogravel/issues/28) | `ad10c4f`, `6247108`, `3a14cfe`; `PostFeedbackTest.php`, `theme-pages.spec.ts` | Exercise publish/import/email/error feedback and plan-limit upgrade prompts in admin and public theme. |
| [#37](https://github.com/Azab-Inc/blogravel/issues/37) | `1576990`, `1b7252f`; `blogravel/docs/api/*.md`, API documentation feature/E2E tests | Open public and authenticated docs through the deployed/served docs entrypoint and compare examples with live responses. |
| [#38](https://github.com/Azab-Inc/blogravel/issues/38) | `820d03d`, `68178b2`; `TestMail.php`, settings feature/E2E coverage | Send a test email with Mailpit and a configured provider; confirm a clear misconfiguration failure. |
| [#47](https://github.com/Azab-Inc/blogravel/issues/47) | `c69e9e1`, `731a6cc`, `c93c904`; `theme-pages.spec.ts`, theme feature tests | Check default light mode, keyboard toggle, persistence, all representative pages, and contrast/responsive behavior. |
| [#53](https://github.com/Azab-Inc/blogravel/issues/53) | `2b6aa88`, `69990a9`, `8ba2e28`, `5707573`; deployment design and validation docs | Review release-tag gate and provider runbooks end-to-end without performing live deployment. |
| [#54](https://github.com/Azab-Inc/blogravel/issues/54) | `20d8a97`, `b105adc`, `3a25277`; `Jenkinsfile.hostinger`, Hostinger runbook | Operator verifies credentials, tagged upgrade, `/up`, services, backup/restore, and rollback procedure. |
| [#55](https://github.com/Azab-Inc/blogravel/issues/55) | `7804bad`, `3c84f92`; `Jenkinsfile.laravel-cloud`, Laravel Cloud runbook | Operator verifies resolved-commit deployment, queue/storage configuration, secrets, smoke checks, and rollback. |
| [#56](https://github.com/Azab-Inc/blogravel/issues/56) | `f871f8a`; `Jenkinsfile.gcp`, Terraform, GCP runbook, deployment configuration tests | Operator verifies immutable image digest, private services, scheduler/job, secrets, smoke checks, and rollback. |

## Deferred work

Issues **#29–#35, #39, and #45** remain future/deferred scope. The current Filament CSS and API-docs styling are supporting work, not evidence that the complete #45 custom admin-theme acceptance criteria (logos, favicon, colors, font, and E2E coverage) are complete.

## Known tracker uncertainty

The local tracker links project 17 as the Blogravel board. GitHub confirms that project 17 is the organization-owned **“Blogravel”** project with 53 items. Issues #28, #37, #38, #47, and #53–56 are open and correctly represented on the board as **To Verify**. Issues #23, #41, and #42 are intentionally marked *(not in project)* in `tickets.md`.
