# WhatsApp workspace UI/UX hardening — 2026-09-24

Working branch: `v42-clean-baseline`

## Goal

Make the long WhatsApp administration page easier to scan and operate while preserving provider configuration, queue controls, template fields, test-send/resend behavior, retry safety, filters, logs, and server-managed secret handling.

## Implemented

- Added a compact section navigator for:
  - Overview
  - Channel & queue
  - Provider & templates
  - Test tools
  - Logs
- Removed the visual effect of one oversized outer white panel; the page now reads as separate focused workspaces.
- Converted the five channel/queue switches into aligned toggle cards using the shared admin switch sizing and spacing.
- Standardized the five delivery-log summary cards and three message-type summary cards on the shared admin metric-card language.
- Converted inner configuration, provider, template, test, resend, and logs containers to theme-aware section cards.
- Kept sensitive Meta credentials server-managed and unchanged; no secret values or credential persistence behavior was added.
- Kept all existing routes and business actions unchanged:
  - settings update
  - controlled test send
  - manual order-event resend
  - log retry
  - log filtering/pagination
- Added English/Arabic labels for the new workspace navigation and provider guidance.
- Added `AdminWhatsAppExperienceTest` to protect the section structure, switch-card count/alignment, and headline metric-card consistency.

## Validation state

- Source implementation: complete for this UI iteration.
- Automated regression coverage: added.
- Branch-head CI: pending.
- Authenticated English/Arabic desktop/mobile QAS review: pending.
- Production: unchanged.

## QAS focus

1. Open the page in English and Arabic and use every section anchor.
2. Check all five switches at desktop and narrow widths; control and label must stay vertically centered and visually separated.
3. Save channel/queue settings and verify the same values reload.
4. Confirm provider identifiers and template values are unchanged by the UI restructuring.
5. Run a controlled test send only in the approved QAS setup and confirm the result appears in Logs.
6. Check manual order-event resend and retry confirmation flows without changing their safety behavior.
7. Filter/paginate logs and expand debug details.
8. Verify no access token, app secret, or verify token value is rendered or persisted through the page.
