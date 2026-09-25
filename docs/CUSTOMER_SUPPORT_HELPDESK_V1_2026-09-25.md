# Customer Support / Helpdesk Foundation V1 — 2026-09-25

## Purpose

Dynamic now has a shared customer-service case foundation rather than treating support as a collection of disconnected order notes or channel-specific tools.

The V1 goal is to make customer issues traceable, owned, permission-scoped, bilingual, and linked to commerce context while leaving deeper SLA and omnichannel automation for a later slice.

## Data model

### Support cases
Each case stores:
- human-readable case number;
- optional customer and order linkage;
- assigned staff owner;
- subject and optional category;
- priority: Low, Normal, High, or Urgent;
- status: Open, Waiting for Customer, Waiting for Team, Resolved, or Closed;
- source;
- first staff response timestamp;
- last customer/staff message timestamps;
- resolved and closed timestamps.

### Case messages
Messages store:
- author;
- author type;
- body;
- visibility.

Visibility is explicit:
- **Customer-visible** messages appear in both Admin and the customer's portal.
- **Internal** notes stay staff-only and are excluded from the customer timeline.

## Customer experience

Authenticated customers can:
- open **Help & Support** from account navigation;
- review their own support cases;
- create a support request;
- optionally link one of their own orders;
- open a case timeline;
- add replies while the case is not Closed.

Security boundaries:
- a customer cannot attach another customer's order;
- a customer cannot access another customer's support case;
- internal staff notes are not rendered in the customer timeline;
- Closed cases reject new customer replies.

## Admin experience

Authorized staff get a **Customer Support** workspace under Operations with:
- live search/filter/pagination;
- status, priority and owner filters;
- Open / Urgent / Unassigned / Resolved summary cards;
- new-case creation;
- customer/order context;
- conversation timeline;
- customer-visible reply vs internal-note choice;
- ownership, priority and status controls.

## Permissions

Added:
- `support.view`
- `support.manage`

Default role scope:
- Super Admin: inherited full access.
- Operations Manager: view + manage.
- Support Agent: view + manage.
- Cashier: no support-management access.
- Finance Manager: unchanged.

## Audit and lifecycle rules

Admin activity logging covers:
- case creation;
- workflow update;
- customer-visible staff reply;
- internal note.

First-response timing is deliberately conservative:
- internal notes do **not** count as a customer response;
- the timestamp starts with the first customer-visible staff reply.

A customer reply reopens a Resolved case to Open. A Closed case is terminal for V1 customer replies and requires a new request.

## Arabic / RTL

All copy introduced by this slice was added to `lang/ar.json`, including Admin and customer portal labels, validation feedback, lifecycle labels and navigation.

Layout uses existing shared Admin and storefront primitives so RTL behavior follows the existing platform contracts.

## Regression coverage

`SupportCaseFoundationTest` covers:
- explicit support permission boundaries;
- Cashier denial;
- customer-owned order linkage;
- rejection of cross-customer order linkage;
- cross-customer case isolation;
- internal-note privacy;
- first-response timestamp semantics;
- customer-visible staff reply state;
- resolved-case reopen behavior;
- closed-case reply rejection;
- rejection of staff accounts as support customers;
- support audit records.

The wider CI also continues to run Blade namespace, migration, route, Laravel boot, view compilation and frontend build gates.

## Deferred Helpdesk scope

Still deferred after V2:
- file attachments and malware/type/size policy;
- richer embedded order/payment/delivery/return summaries;
- customer-service analytics;
- business-hours calendars and SLA pause policies;
- email ingestion;
- WhatsApp ingestion;
- live chat ingestion.

SLA targets/breach indicators and reusable bilingual reply templates moved into V2 below. Future channels should feed this same case timeline rather than introduce parallel support records.

## Release state

- Branch: `v42-clean-baseline`
- V1 implementation commits: `b0116d0`, `d342153`, `09da0c0`, `7054729`, `74eba22`, `4c6ab52`, `a1b0ee2`
- Production: unchanged
- QAS: not claimed by this document
- V1 source gate passed before V2 work.
- V2 was merged to `v42-clean-baseline` at `173c14e`; final branch-head CI remains the source gate before QAS.
- Authenticated EN/AR/RTL/responsive Helpdesk acceptance on QAS remains a separate gate.

## V2 — SLA targets and reply templates

This follow-up keeps the existing Helpdesk case model and adds two operational layers without introducing attachments or external-channel ingestion.

### SLA targets
- Cases now store first-response and resolution due timestamps.
- Targets are derived from the case priority at creation time.
- Default hours are configurable through Support Settings and stored in Website Settings.
- Changing the priority of an unresolved case recalculates still-relevant due dates from the original case creation time.
- First-response SLA only completes on a customer-visible staff reply; internal notes do not satisfy it.
- Case detail surfaces first-response and resolution SLA state plus due timestamps.
- The Support overview includes an SLA-breached queue count.
- V2 deliberately does not pause SLA clocks while waiting for the customer. Business-hours calendars and pause policies require merchant policy and are deferred.

### Reply templates
- Added reusable bilingual reply templates with English/Arabic name and body.
- Templates have customer-visible or internal-note default visibility.
- Templates are ordered, can be enabled/disabled, and remain editable.
- The case reply composer can prefill body + visibility from an active template; staff can still edit the text before submitting.
- Templates never bypass the normal support reply endpoint, validation, authorization, audit, or customer/internal visibility rules.

### V2 regression coverage
`SupportSlaAndTemplatesTest` covers:
- SLA deadline creation;
- priority-driven recalculation;
- first-response breach semantics;
- internal-note exclusion from first-response completion;
- SLA settings persistence;
- bilingual template localization;
- active template availability in the case composer;
- cashier denial for Support Settings.

Attachments, malware-safe file handling, business-hours SLA calendars, pause policies, and email/WhatsApp/chat ingestion remain deferred.

