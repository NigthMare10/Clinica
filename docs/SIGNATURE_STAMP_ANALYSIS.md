# Signature And Stamp Reference Analysis

## Scope

The authorized medical reference is a one-page institutional certificate. The original is not modified and is not a source of fixture data.

## Observed Layout

- The institutional header and patient band occupy the upper page.
- The clinical narrative occupies the center.
- The closing identifies the professional on the lower-left area.
- A handwritten-signature area and a circular institutional/professional stamp appear in the lower-right area.
- Verification elements must remain separate from the signature/stamp area to avoid overlap.

## Implementation Constraints

- Signature and stamp are independent private assets, not public URLs.
- The import assistant renders only the selected authorized PDF locally, crops selected regions, and removes temporary artifacts.
- The operator prepares a crop; an authorized administrator must explicitly confirm authorization before persistence.
- The generated PDF stores asset hashes and positions in its issuance snapshot. An image signature is a visual institutional mark, not a cryptographic/PAdES signature.
- Coordinates are template-controlled in millimeters: `signature_x`, `signature_y`, `signature_width`, `stamp_x`, `stamp_y`, and `stamp_width`.

## Privacy

No patient, clinical narrative, identity, credential, phone, fiscal, or source-document values are reproduced here.
