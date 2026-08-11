# Invoice Numbering Architecture

## Separate Sequences

Medical documents use their existing clinical public code. Invoices use an NCF created only from a fiscal authorization. The two identifiers are never interchangeable.

`Patient -> Medical document -> Invoice` is a database relationship. It does not alter either sequence.

## Allocation

1. Draft creation does not allocate an NCF.
2. Issue opens a database transaction and locks the selected authorization row.
3. The service validates status, CAI, RTN, range, and emission deadline.
4. It reads the current number, formats the NCF, increments atomically, and creates the immutable issued invoice.
5. A void invoice retains its NCF. The next issue receives the following number.

## Authorization Lifecycle

Authorizations are historical records with `ACTIVE`, `EXHAUSTED`, `EXPIRED`, or `DISABLED` status. A new authorization is added; old ones are never overwritten. Exhaustion and expiry block issuance.

## Verification

Issued PDFs store source and final SHA-256 hashes. QR verification resolves an opaque token and clearly states that institutional verification does not replace the official fiscal authority validator.
