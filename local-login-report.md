# Local Login Report

## Root Cause

The local `.env` sets `SESSION_DOMAIN=.blogravel.com`. Laravel therefore emitted the session and CSRF cookies for `.blogravel.com` during `http://localhost:8000/admin/login`; the browser rejected them for localhost, leaving `document.cookie` empty and losing the login on refresh.

## Change

Added `UseLocalSessionCookies` as global middleware. It detects `localhost`, loopback hosts, and `*.localhost` through the existing host resolver, then rewrites only the configured session cookie and `XSRF-TOKEN` to host-only cookies in that response. It does not mutate the config repository, so request-specific cookie state cannot leak between Octane requests. Hosted responses retain `SESSION_DOMAIN` unchanged.

## Verification

- Red Pest regression before implementation: failed because the cookie domain was `.blogravel.test`, not `null`.
- Focused Pest regression after implementation: 1 passed, 3 assertions.
- Focused platform entry Pest file: 12 passed, 28 assertions.
- Focused auth/platform/local-routing Pest suites: 92 tests, 87 passed, 5 skipped.
- Focused Playwright auth/local-login/subdomain suites: 15 passed, including the setup test.
- Pint: passed with no formatting changes.
- `git diff --check`: passed.

## Concerns

- Host-only cookies intentionally do not cross from bare `localhost` to a `*.localhost` tenant host; browsers do not provide a reliable shared-cookie scope for localhost. The local path routing mode remains same-host and preserves the session.
- The ignored local `.env` remains `SESSION_DOMAIN=.blogravel.com`; no override is required.
