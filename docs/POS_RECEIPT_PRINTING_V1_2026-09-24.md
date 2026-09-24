# POS Receipt Printing V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`  
Verified application head: `bca25c1`  
Hardening CI: `36042936219`

## Goal

Add a professional printable sales receipt on top of the verified POS ledger without rerunning checkout, recalculating commercial data, or claiming fiscal/tax-invoice behavior that has not been configured.

## Implemented

- POS Sale Summary now exposes a dedicated **Print receipt** action.
- Receipt access reuses the existing POS sale ownership boundary: the owning cashier can view it, while broader order reviewers retain their existing access.
- The receipt is rendered from the completed Order, Order Items and Payment records only.
- Browser-print layouts support:
  - 58 mm thermal paper
  - 80 mm thermal paper
  - A4
- Unsupported paper values fall back safely to 80 mm.
- The existing immutable POS order number is used as the V1 receipt reference instead of introducing a second unverified numbering sequence.
- Receipt identity can show the existing store name, address, support phone and support email from Store Settings.
- Receipt details include recorded sale date, cashier reference, payment method/reference, optional customer name, items/variants/SKU, quantities, unit values, subtotal, discount, tax if present, grand total, payment status, cash received/change due, and optional sale notes.
- English and Arabic receipt copy is registered.
- Printing does not create or update orders, payments, inventory movements or stock.

## Integrity model

1. Receipt rendering is read-only and does not call POS checkout or inventory mutation services.
2. The same cashier ownership / broader-order-review authorization used by Sale Summary protects receipt access.
3. Printed values come from the persisted completed sale ledger rather than current catalog price or stock.
4. The receipt reference is the already-recorded POS order number, avoiding a second sequence that could diverge from the sale record.
5. Paper-size selection changes presentation only.

## Deliberate V1 boundaries

- This is a **sales receipt**, not a fiscal or tax invoice.
- No jurisdiction-specific VAT/tax-registration identity, tax invoice wording, fiscal numbering, QR requirement, or legal invoice sequence has been invented.
- No new independent receipt-number counter is introduced in V1.
- No PDF file is generated or stored; V1 uses browser printing.
- Physical printer width, margins, Arabic glyph rendering and cutter behavior still require real-device QAS review.

Formal invoice work should add verified business/legal identity, configured tax rules and jurisdiction-appropriate numbering requirements before any printout is presented as a tax invoice.

## Automated coverage

`PosCashierTest` now also verifies:

1. A completed POS sale can render the receipt.
2. 58 mm paper selection is honored.
3. Unsupported paper input falls back to 80 mm.
4. Rendering the receipt does not change order, payment, inventory-movement or stock state.
5. A different cashier cannot bypass the existing sale ownership boundary by opening receipt mode.

Hardening CI run `36042936219` passed at application head `bca25c1` with **174 tests (983 assertions)**, clean MySQL migration, Laravel boot/routes, Blade/config compilation and frontend production build.

## Consolidated QAS focus

1. Print a representative cash sale on a real 58 mm printer.
2. Print a representative cash/card-terminal sale on 80 mm.
3. Review A4 print/PDF output through the browser print dialog.
4. Compare every printed item, quantity, value, payment reference and total with the stored Sale Summary / Order / Payment.
5. Verify cash received and change due on cash receipts.
6. Verify card-terminal receipts do not show cash-only fields.
7. Review English and Arabic output, RTL/LTR alignment and Arabic glyph rendering.
8. Confirm the owning cashier can print and another cashier remains blocked.
9. Refresh/reprint repeatedly and confirm there are no duplicate orders, payments or stock deductions.
10. Verify store contact details are correct before customer-facing use.

QAS application HEAD remains `0a08253`. Production and `main` remain unchanged.
