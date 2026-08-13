# Production-only changes before password fix

Backup created before this review:

- `/home/u357586881/backups/clinic/clinic-before-password-fix-20260813-164043.tar.gz`
- SHA-256: `3be7f2a0dfabeb15f67b91272104da038c639a9ef8a1cc967fab8c8b7a4735c3`

The production application tree is not a Git checkout. It was archived and compared file-for-file with local `main`, excluding `.env`, `storage/`, logs, PDFs, uploads, caches, `vendor/`, `node_modules/`, and generated build assets.

| File | Difference in production | Preserve? |
| --- | --- | --- |
| `resources/js/Pages/Admin/Audit/Index.vue` | Replaced the Honduras formatter with browser-local formatting. | No. `main` is correct for the required Honduras timezone. |
| `resources/js/Pages/Admin/Dashboard.vue` | Replaced the Honduras formatter with browser-local formatting. | No. `main` is correct for the required Honduras timezone. |
| `resources/js/Pages/Admin/Documents/Index.vue` | Replaced the Honduras formatter with browser-local formatting. | No. `main` is correct for the required Honduras timezone. |
| `resources/js/Pages/Admin/Documents/Review.vue` | Replaced the Honduras formatter with browser-local formatting. | No. `main` is correct for the required Honduras timezone. |
| `resources/js/Pages/Admin/Invoices/Index.vue` | Replaced the Honduras formatter with browser-local formatting. | No. Outside this fix and superseded by `main`. |
| `resources/js/Pages/Admin/Invoices/Show.vue` | Replaced the Honduras formatter with browser-local formatting. | No. Outside this fix and superseded by `main`. |
| `resources/js/Pages/Admin/Patients/Show.vue` | Replaced the Honduras formatter with browser-local formatting. | No. Outside this fix and superseded by `main`. |

No production-only application source files need to be incorporated into `main`. Production also contains old build backups and generated test reports; they are excluded deployment artifacts, not source changes.
