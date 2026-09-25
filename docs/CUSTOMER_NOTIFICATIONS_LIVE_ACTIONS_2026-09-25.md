# Customer Notifications Live Actions — 2026-09-25

## Goal

Close the remaining customer-notification reload gap while preserving authorization and the existing no-JavaScript behavior.

## Implemented

- `Mark all as read` now supports a progressive JSON response and updates the Notifications page without a full reload.
- Individual unread notifications now support progressive read-state updates.
- Notifications with an `action_url` are marked read first, then navigate to the existing destination.
- Notifications without a destination are marked read in place and their action is removed.
- Unread count updates immediately in the page action.
- `Mark all as read` updates the current page controls in place and removes the global unread action when the count reaches zero.
- The normal PATCH form behavior remains available when JavaScript is unavailable.
- `markAllRead` now performs a direct relationship update instead of loading every unread notification model into memory.
- Existing per-notification ownership enforcement remains server-side.
- Added Arabic success messages for single/all read actions.

## Regression coverage

`tests/Feature/CustomerNotificationLiveActionsTest.php` covers:

- live read of an owned notification;
- returned unread count and action destination;
- cross-customer denial;
- mark-all isolation to the signed-in customer;
- progressive-enhancement hooks and no-JS forms;
- Arabic success copy.

## Source commits

- `cee6eb5` — live notification JSON state
- `813b189` / `d07ba73` — progressive customer notification controls
- `235ba6a` — controller cleanup
- `8241808` — Arabic feedback
- `877eee4` — regression coverage

## Release status

- Source: implemented on `v42-clean-baseline`.
- CI: pending branch-head verification.
- QAS: not yet claimed for this slice.
- Production: unchanged.

## QAS acceptance

- Verify single read with and without an action destination.
- Verify mark-all on a multi-page inbox.
- Verify unread count reaches zero and controls update without reload.
- Verify EN/AR and RTL behavior on desktop/mobile.
- Refresh the page after actions and confirm persisted read state matches the live UI.
