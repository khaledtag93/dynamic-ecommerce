# Commerce Settings Workspace V2 — 2026-09-25

## Goal
Improve the operational structure of Payment and Shipping settings while preserving provider configuration, checkout behavior, shipping rules, and server-authoritative validation.

## Payment Settings
- Kept the existing shared Admin page header.
- Split the previously flat settings form into shared section tabs:
  - Payment methods
  - Gateway & stock
  - Paymob setup
  - Bank transfer
- Preserved callback URLs, gateway mode/provider fields, stock-reservation minutes, server-managed credential status, Paymob integration/iframe IDs, and bank-transfer instructions.
- No secret-handling behavior changed; provider secrets remain server-managed.
- Existing PUT route and validation contract are unchanged.

## Shipping Settings
- Shipping was already separated into Methods, Zones & cities, and Rates pages.
- Added one shared shipping navigation partial used by all three pages.
- Removed duplicated cross-links from individual page headers.
- Existing method updates, zone/city integrity rules, rate upsert behavior, threshold basis validation, and checkout semantics are unchanged.

## Notification Center
- Reviewed the Notification Center structure.
- Kept its existing modular Overview / Logs / Templates / Automation / Diagnostics architecture unchanged because it already matches the intended workspace pattern.

## Arabic / RTL
- Added Arabic labels for the new Payment sections and the shared Shipping settings navigation.

## Regression coverage
tests/Feature/CommerceSettingsWorkspaceV2Test.php verifies:
- four focused Payment settings panels;
- Paymob callback configuration remains present;
- shared Shipping navigation on Methods / Zones / Rates;
- Notification Center remains modular;
- Arabic labels for Payment and Shipping workspace navigation.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
