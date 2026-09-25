# Customer Support Live UX — 2026-09-25

## Goal

Close customer-account consistency gaps in Help & Support without changing the Helpdesk business model.

## Live case list

- Customer support pagination now uses the shared progressive live-list pattern already used by Orders, Returns and Notifications.
- Live fragments remain scoped by `customer_id`; no customer can receive another customer's support cases.
- Normal links and full-page pagination remain the no-JavaScript fallback.
- `CustomerSupportLiveListTest` covers full/live page parity, pagination and customer isolation.
- Hardening CI #1400 passed at `058cd0a` with 377 tests / 2555 assertions plus clean MySQL migration, Laravel routes, Blade compilation and frontend production build.

## Live customer reply

- Customer replies can return JSON for enhanced web requests while preserving the existing redirect/flash fallback.
- The existing `SupportCaseService::addCustomerReply()` remains the only business mutation path.
- The browser appends the server-confirmed message safely using `textContent`; reply text is never injected as raw HTML.
- A reply updates the displayed case status and last-updated timestamp from server values.
- Resolved cases still reopen through the existing service rule; Closed remains terminal.
- Ownership remains server-enforced.
- Validation errors remain server-generated and are shown in the live status region.
- Added Arabic failure feedback.
- `CustomerSupportLiveReplyTest` covers successful live reply, resolved-case reopen, cross-customer denial, closed-case rejection, progressive hooks and Arabic copy.

## Release status

- Source: implemented on `v42-clean-baseline`.
- Live-list portion: CI-verified by Hardening CI #1400 at `058cd0a`.
- Live-reply portion: Hardening CI #1401 passed at `799ae2d` with 380 tests / 2576 assertions plus clean migration, routes, Blade compilation and frontend production build.
- QAS: not yet claimed for this customer UX follow-up.
- Production: unchanged.

## QAS acceptance

- Paginate through more than 20 cases without full reload and verify Back/Forward behavior.
- Reply to Open / Waiting / Resolved cases and confirm the new message, status and timestamp update in place.
- Verify Closed cases have no reply composer.
- Verify EN/AR, RTL, long messages and mobile layout.
- Refresh after a live reply and confirm persisted state matches the in-page state.
