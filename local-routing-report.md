# Local Routing Follow-up Report

## Scope

- Centralized local-host recognition behind `TenantHostResolver::isLocalHost()`.
- Preserved IPv4, `localhost`, `lvh.me`, `.localhost`, and IPv6 loopback support.
- Added local-path navigation and POST routes for tenant theme forms.
- Kept platform and non-local host isolation plus bare-local query routing unchanged.

## Evidence

- Focused Pest: `74 passed, 1 skipped` across `TenantHostResolutionTest`, `PlatformEntryTest`, and `SoroWebhookTest`.
- Expanded Pest: `88 passed, 1 skipped` across routing, theme, override, and webhook coverage.
- Subdomain Playwright: `8 passed`.
- `vendor/bin/pint --dirty --format agent`: passed.
- `git diff --check`: passed.

The full Pest suite ran 482 tests and exposed four unrelated existing Filament failures in `GenerateAiPostActionTest` and `SettingsTest`.

The three new local navigation/submission Playwright checks were also run against the existing port-8000 Octane service. That service returned the pre-change root URLs (`/subscribe?tenant=...` and `/subscribe/{id}`), so those checks failed there. The local PHP test server could not be used for browser verification because this checkout's non-test environment resolves the configured Redis hostname as unavailable.
