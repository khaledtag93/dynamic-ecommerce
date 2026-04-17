# Payment Stabilization Checklist

## Goal
Reach a reliable, production-ready payment flow before expanding channels in Phase 4.

## Core checks
- Verify callback URL correctness in all environments
- Confirm HMAC validation path for success and failure responses
- Prevent duplicate registration on refresh or repeated return visits
- Ensure declined payments do not remain misleadingly pending
- Confirm order, payment, and provider statuses stay synchronized
- Confirm customer-facing result pages are clear in Arabic and English
- Verify retry flow from failed/declined states
- Confirm admin payment log readability

## Operational checks
- Test with low-value and normal-value orders
- Test user cancellation before payment completion
- Test callback arrival after delayed redirect
- Test refresh on result page
- Test duplicate browser tabs

## Completion gate
Do not call payment fully closed until all scenarios above are manually validated in a public-like environment.
