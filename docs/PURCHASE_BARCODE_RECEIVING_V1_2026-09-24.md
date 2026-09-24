# Purchase Barcode Receiving V1

Date: 2026-09-24
Branch: `v42-clean-baseline`
Verified application head: `0a2ceb9`
Hardening CI: `36038635115` — 163 tests, 889 assertions, frontend build passed.

## Implemented
- Persistent per-line receiving verification.
- One accepted scan equals one verified physical unit; scans never mutate inventory.
- Exact product/variant matching through the shared barcode resolver.
- Parent product barcodes for variant products are rejected.
- Unknown, out-of-purchase and ambiguous identifiers are rejected.
- Duplicate purchase lines require explicit cost/expiry line selection.
- Undo-one correction and over-scan prevention.
- Final verified receipt requires every line's verified quantity to equal ordered quantity under database locks.
- Final stock mutation remains all-or-nothing and replay-safe through the existing purchase receipt transaction.
- Existing manual receipt remains available for operational compatibility.
- EN/AR workspace and regression coverage included.

## QAS focus
Use real labels to scan simple products and variants, test duplicate lines, undo an accidental scan, block incomplete final receipt, then complete receipt and confirm stock/movements are written exactly once. Review EN/AR desktop/mobile layout and autofocus.

QAS application head remains `0a08253`. Production and `main` are unchanged.
