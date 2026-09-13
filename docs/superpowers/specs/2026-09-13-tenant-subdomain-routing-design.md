# Tenant Subdomain Routing & Root-Domain Entrypoint

## Goal

Serve each tenant from a generated subdomain while reserving the platform root domain for authentication and administration.

## Requirements

- A tenant slug such as `acme` resolves from `acme.<platform-domain>` and renders the enabled public theme.
- The platform root redirects guests to `/admin/login` and authenticated users to the Filament dashboard.
- Unknown, reserved, and malformed subdomains return a clear 404 without exposing another tenant.
- Tenants receive a unique generated slug and retain a separate nullable custom-domain field.
- Existing `domain`-based API, webhook, and local-test behavior remains compatible during this migration.
- Session cookies can be shared between the platform root and tenant subdomains through deployment configuration.
- Documentation covers platform domain, wildcard DNS/TLS, and local wildcard host setup.
- Feature tests and Playwright tests cover root redirects, host resolution, tenant isolation, invalid hosts, and cross-host authentication behavior.

## Design

Add `slug` and nullable `custom_domain` columns to `tenants`. Generate a unique slug from the tenant name when a tenant is created, while leaving the existing `domain` column available for current integrations and compatibility. Tenant public host resolution matches an exact custom domain first, then a single-label slug under the configured platform domain.

Introduce a focused host resolver/middleware boundary. It determines whether the request targets the platform root, a valid tenant host, or an invalid host; valid tenant requests receive the tenant in request attributes before the existing theme middleware runs. Root requests use a small controller to redirect based on authentication state. Reserved labels and malformed/multi-level tenant hosts are rejected.

Configure the platform domain and reserved labels through environment-backed config. Keep session-cookie behavior environment-controlled via `SESSION_DOMAIN`, documenting `.blogravel.com` for hosted deployments and a local wildcard domain such as `.lvh.me` when cross-host browser sessions are needed.

## Verification

Use Pest feature tests for resolver and HTTP behavior. Use Playwright CLI tests against the running Laravel server for browser-visible root redirects, tenant rendering, invalid-host 404s, and an authenticated session crossing from the root admin host to a tenant host.
