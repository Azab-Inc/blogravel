# Blogravel Implementation and Verification Order

This is the returning-maintainer queue, not a claim that production has been verified.

## 1. Preserve the current worktree

Do not edit or reset these pre-existing WIP files:

- `blogravel/app/Filament/Resources/TagResource.php`
- `blogravel/resources/css/filament/admin/blogravel-theme.css`
- `blogravel/resources/css/tokens.css`

## 2. Manual verification queue

Run the narrowest local checks first, then verify in this order:

1. **#38** — start the local stack and Mailpit; send a test email, then test a broken mail configuration.
2. **#37** — visit public and authenticated API docs; check endpoint examples, authentication, pagination, and RFC 9457 errors.
3. **#28** — exercise async feedback for publish, import, email, errors, and plan-limit upgrade prompts.
4. **#47** — test light default, keyboard-accessible toggle, persistence, no avoidable theme flash, contrast, and representative pages.
5. **#53–56** — review each release-tag pipeline and runbook, then perform provider smoke/rollback checks only with approved credentials and an explicit operator decision.

Record the exact environment, commit/tag, result, and any failed acceptance criterion before changing a ticket to Done or closing an issue.

## 3. Tracker synchronization

After manual evidence is recorded, reconcile `tickets.md`, GitHub issues, and project 17. The board is confirmed as the organization-owned **Blogravel** project. Keep open issues open until their acceptance criteria and required manual checks are complete.

## 4. Future implementation order

After the verification queue: complete #45's full admin-theme scope, then schedule #29–#35 and #39 according to product priorities. Do not treat partial CSS/API-docs work as completion of #45.
