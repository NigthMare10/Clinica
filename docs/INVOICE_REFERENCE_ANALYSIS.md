# Invoice Reference Analysis

## Scope

The authorized reference is a single-page cash invoice. It is used as a visual and field-structure reference only. Its original remains unchanged.

## Observed Layout

- Institutional identity and the `FACTURA CONTADO` heading lead the page.
- The fiscal header groups NCF, emission deadline, authorized range, RTN, and CAI.
- Order, date/time, and invoice number precede client and professional details.
- A line-item table contains code, quantity, description, price, discount, and payment/copayment columns.
- Totals distinguish discounts, exempt/exonerated amounts, taxable bases, ISV, paid amount, and balance.
- The lower area includes the amount in words, status, recipient/emitter copies, and signature/stamp area.

## Implementation Constraints

- Fiscal values from the reference are never seeded as active values.
- The invoice NCF is allocated exclusively from a fiscal authorization during issuance; it is never derived from a medical-document code.
- Issued invoices are immutable. Void invoices retain their NCF and history.
- The QR contains only an opaque verification token and resolves through `/verificar/factura/{token}`.
- The production invoice renderer must keep ordinary invoices to one page and validate that condition before storage.

## Privacy

No reference client, staff, financial, fiscal, CAI, RTN, range, or order values are reproduced here.
