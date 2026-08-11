# Hostinger Storage Audit

Date: 2026-08-11

## Site boundaries

- Clinic application root: `/home/u357586881/domains/clinicaprivadasanta-ana.com/app`
- Clinic webroot: `/home/u357586881/domains/clinicaprivadasanta-ana.com/public_html`
- Studio Lemus root: `/home/u357586881/domains/violet-crow-104407.hostingersite.com/studio-lemus`

## Findings

- Clinic was initially an empty webroot containing only `default.php` (16 KB).
- The deployed Clinic domain uses 136 MB after production dependencies were installed.
- The Studio Lemus domain uses 2.4 GB and was not modified.
- The hosting filesystem reports 1.2 TB available space.
- Clinic private documents are stored outside the webroot in `app/storage/app/private`.
- No Clinic-specific temporary files existed before deployment, so no deletion was required.

## PDF/OCR tools

The server does not provide `qpdf`, `pdftotext`, `pdftoppm`, `pdfinfo`, or `tesseract`. Advanced PDF encryption, text extraction, preview rendering, and OCR require these tools to be made available by the hosting environment.
