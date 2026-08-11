# Backend, Database, Storage and PDF Audit

## Production State (Read Only)

- MariaDB 11.8.8, 33 tables, total 2.70 MB.
- Largest table: `medical_documents`, 7 rows / 608 KiB. Current volume is small; recommendations are preventative scalability work.
- `jobs`: 0. `failed_jobs`: 0.
- Storage: 1.9 MB total; private documents 1.3 MB; temporary files 108 KiB; Laravel log 268 KiB.
- Config and views are cached. Routes/events are not cached. PHP OPcache is enabled with 128 MB, timestamp validation on, `revalidate_freq=2`.
- No scheduled tasks are defined.

## P0 - Queue timeout and failure-observability risk

- **Problem:** document processing can run external PDF/OCR subprocesses longer than the database queue visibility timeout; job exceptions are caught without rethrowing.
- **Impact:** duplicate processing and missing failed-job visibility under slow/multi-page workloads.
- **Evidence:** `app/Jobs/ProcessMedicalDocument.php:25-84`; `config/queue.php:38-45`; PDF/OCR process timeouts can be 120 seconds.
- **Cause probable:** job timeout/backoff/overlap policy was not aligned with subprocess timeout.
- **Solution proposed:** define explicit job timeout/backoff, set `retry_after` above worst-case runtime, ensure worker timeout stays below retry_after, add per-document overlap lock, and report unexpected failures to Laravel failed-job handling.
- **Risk:** Medium. **Effort:** Medium. **Priority:** P0.

## P1 - Unbounded Inertia payloads and repeated query counts

- **Problem:** creation/review routes load all accessible patients and documents; verification/dashboard metrics use PHP-side aggregation and repeated counts.
- **Impact:** response payload, memory, and query count increase with clinic activity.
- **Evidence:** `GeneratedMedicalDocumentController.php:42,66-68`; `InvoiceController.php:53-56`; `MedicalDocumentController.php:55-57,126`; `DashboardController.php:23-37`; `VerificationController.php:34-57`.
- **Solution proposed:** introduce authorized debounced search endpoints (minimum 2-3 chars, 20-50 capped results), load documents after patient/clinic selection, use SQL conditional aggregation/date ranges and group-by for analytics, and short-cache non-sensitive aggregate metrics by scope.
- **Risk:** Low. **Effort:** Medium. **Priority:** P1.

## P1 - Confirmed invoice-item N+1

- **Problem:** each invoice-item mutation queries its parent invoice status.
- **Impact:** one extra query per item inside fiscal draft/issue transactions.
- **Evidence:** `app/Models/InvoiceItem.php:22-38`; loops in `InvoiceDraftService.php:30-39` and `InvoiceIssueService.php:51-55`.
- **Solution proposed:** use a loaded parent relation for controlled bulk operations while retaining database fallback for isolated writes.
- **Risk:** Medium. **Effort:** Small. **Priority:** P1.

## P1 - PDF work occurs while records are locked

- **Problem:** rendering, encryption, storage and PDF verification occur within medical/fiscal database transactions and locks.
- **Impact:** longer lock hold time and contention during concurrent issuance.
- **Evidence:** `MedicalDocumentIssueService.php:24-100`; `InvoiceIssueService.php:30-83`; `InvoicePdfService.php:38-60`.
- **Solution proposed:** measure real generation and lock waits first. A larger change can use a durable processing state and controlled worker concurrency; preserve atomic NCF allocation.
- **Risk:** High. **Effort:** High. **Priority:** P1, design phase only.

## P1 - Query/index opportunities

- **Problem:** search uses leading-wildcard `LIKE`; current document lookup combines `public_code` and `is_current_revision` without a composite index.
- **Impact:** scans grow with patient/document/invoice volume.
- **Evidence:** `GlobalSearchController.php:22-31`, `MedicalDocumentController.php:42-44`, `InvoiceController.php:35`, verification service lookup; production index inventory lacks `(public_code, is_current_revision)`.
- **Solution proposed:** capture `EXPLAIN` against production-scale data before adding indexes. Likely candidates: `medical_documents(public_code, is_current_revision)`, `(public_code, revision_number)`, `patient_clinic(patient_id, clinic_id)`, and a global audit `created_at` index. Do not add all speculatively.
- **Risk:** Low. **Effort:** Small-Medium. **Priority:** P1.

## Storage and Cleanup

- Temporary directories are normally removed in `finally` blocks, but a process crash can leave them behind.
- Original uploads and assets may be written before database completion, leaving possible orphan objects after failures.
- There is no `clinic:cleanup-temporary-files` command in the current deployed application, despite the requested audit name; no cleanup command was executed.
- No scheduler is defined, so no automatic temporary/orphan reconciliation exists.
- **Proposed solution:** design a dry-run-first scheduled reconciliation only for stale temporary folders and unowned objects. Define retention/legal policy before considering issued PDFs, originals, versions, or audit logs.
- **Risk:** Medium. **Effort:** Medium. **Priority:** P2.

## PDF Pipeline

- Files created per issue: temporary QR/working PDFs, generated/encrypted issued PDF, hashes, and audit records. Temporary paths use `finally` cleanup in normal completion.
- Ghostscript is available in production. Earlier production validation confirms it is usable for page-count/render fallbacks.
- No production PDF benchmark was run because generating a production medical/fiscal document would be a write action. Measure renderer, QR, Ghostscript, encryption, hashing, and storage timing in staging with representative one- and multi-page samples before optimizing DPI or subprocess layout.
- Do not reduce QR error correction, quiet zone, or validation DPI without QR decode regression tests.
