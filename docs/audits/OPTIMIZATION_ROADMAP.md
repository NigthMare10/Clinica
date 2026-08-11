# Optimization Roadmap

## Scoring

Impact, effort and risk describe the proposed implementation, not this read-only audit.

## Phase A - Quick Wins

| Recommendation | Problem / evidence | Impact | Effort | Risk |
| --- | --- | --- | --- | --- |
| Scope or reduce Vite prefetch | Home prefetches unrelated admin chunks; `AppServiceProvider.php:42` | High | Low | Low |
| Fix specialty `srcset` | Invalid same-source 640w/1280w generation | High | Medium | Low |
| Add sitemap and robots Sitemap directive | Production sitemap 404 | High | Medium | Low |
| Use editorial specialty SEO fields | Fields exist but are unused | Medium | Low | Low |
| Complete verification tab ARIA | Incomplete `tablist` pattern | Medium | Low | Low |
| Add admin skip link/main landmark | Admin keyboard navigation repeats sidebar | Medium | Low | Low |
| Make map marker hit areas accessible | 14-16px visual targets | Medium | Low | Low |
| Aggregate dashboard/verifications in SQL | Multiple count queries and PHP scans | High | Medium | Low |
| Fix invoice-item loaded-relation N+1 | One parent query per item mutation | Medium | Small | Medium |
| Add queue timeout/overlap plan | OCR duration exceeds queue defaults | High | Medium | Medium |

## Phase B - Core Web Vitals

1. Build a Vite treemap and route-level CSS coverage report. Do not assume package removal before evidence.
2. Split/admin-gate broad shared code where the treemap proves public routes carry it.
3. Generate real image derivatives (360/640/960/1280) and enforce mobile hero/card budgets.
4. Test reduced decorative blur/shadow under a mobile media query and compare screenshots, LCP, TBT, and perceived quality.
5. Add three-to-five-run Lighthouse CI budgets and real-user monitoring for LCP/INP/CLS.

Impact: High. Effort: Medium-High. Risk: Medium.

## Phase C - SEO

1. Publish verified sitemap and precise robots policy.
2. Add complete social metadata and a verified institutional social card.
3. Use managed SEO title/description fields with fallbacks.
4. Add SSR or prerendering for public route metadata and JSON-LD.
5. Audit specialty content for genuine clinical uniqueness before expanding location pages.

Impact: High. Effort: Medium-High. Risk: Low-Medium.

## Phase D - Backend and Database

1. Replace all-record patient/document selects with authorized search endpoints.
2. Limit extraction/verification history fields returned to document review pages.
3. Convert dashboard/verification metrics to conditional SQL aggregation.
4. Capture `EXPLAIN` plans at real volume before targeted composite indexes.
5. Design asynchronous PDF issuance carefully around immutable audit/NCF allocation; do not move fiscal allocation out of its atomic lock without a concurrency design.

Impact: High. Effort: Medium-High. Risk: Medium.

## Phase E - Storage and Infrastructure

1. Define approved retention periods for originals, issued PDFs, revisions, logs and audit trails.
2. Add a dry-run, logged reconciliation for stale temp folders/orphans and schedule it only after review.
3. Assess Redis only with persistence, worker supervision, eviction policy, and recovery plan; do not change cache/session/queue stores blindly.
4. Configure immutable cache headers for fingerprinted `/build/assets/*` at server/CDN level and verify in browser.
5. Consider safe short caching for truly anonymous public pages only after session/Inertia variation testing.

Impact: Medium-High. Effort: Medium-High. Risk: Medium.

## Phase F - Security Polish

1. Enable HSTS after domain/subdomain readiness review.
2. Design nonce/hash CSP migration away from unsafe-inline.
3. Evaluate COOP/CORP against third-party map tiles before adoption.

Impact: Medium. Effort: Medium-High. Risk: Medium-High.

## Top 10 Quick Wins

1. Measure and reduce global Vite prefetch concurrency.
2. Correct specialty image `srcset` generation.
3. Add `/sitemap.xml` and reference it from `robots.txt`.
4. Apply stored specialty SEO title/description.
5. Use one SQL aggregate for verification metrics/trend.
6. Use conditional aggregates for dashboard cards.
7. Replace all-patient selectors with capped search endpoints.
8. Complete QR verification tab accessibility semantics.
9. Add skip link/main landmark to admin.
10. Set queue timeout/retry/overlap policy before document volume grows.

## High-Impact Changes Requiring More Work

- Public SSR/prerendering for reliable crawler metadata.
- True responsive image generation pipeline and CMS metadata.
- Admin/public bundle architecture splitting.
- Durable asynchronous document/fiscal PDF workflow that preserves NCF transaction integrity.
- Retention/reconciliation policy and scheduled cleanup.
- Redis/worker/CDN architecture only after operational readiness review.

## Recommended First Phase

Approve only Phase A items 1-6 first: they are measurable, reversible, have low blast radius, and do not alter medical/fiscal business rules. Run local tests and a staging Lighthouse comparison before requesting a separate deployment approval.
