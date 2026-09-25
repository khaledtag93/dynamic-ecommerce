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

## Deferred Helpdesk V2

Not included in V1:
- file attachments and malware/type/size policy;
- SLA target configuration and breach indicators;
- reusable reply templates/macros;
- richer embedded order/payment/delivery/return summaries;
- customer-service analytics;
- email ingestion;
- WhatsApp ingestion;
- live chat ingestion.

Future channels should feed this same case timeline rather than introduce parallel support records.

## Release state

- Branch: `v42-clean-baseline`
- V1 implementation commits: `b0116d0`, `d342153`, `09da0c0`, `7054729`, `74eba22`, `4c6ab52`, `a1b0ee2`
- Production: unchanged
- QAS: not claimed by this document
- Required next gate: green branch-head CI, then authenticated EN/AR/RTL/responsive Helpdesk acceptance on QAS.
