# Lighthouse Audit - Production

Audit date: 2026-08-11. Target: `https://clinicaprivadasanta-ana.com`.

## Method

- Lighthouse 13.4.1 against production, unauthenticated, one cold run per route and device profile.
- Desktop uses Lighthouse desktop preset; mobile uses Lighthouse mobile throttling.
- A browser warm-load pass reused the same browser cache. Lighthouse itself clears origin data, so it cannot represent a warm-cache pass.
- Values are lab measurements, not field Core Web Vitals. Repeat three to five times after every optimization.

## Desktop Cold Results

| Route | Perf | A11y | Best | SEO | FCP | LCP | TBT | CLS | Speed Index | TTFB |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| `/` | 95 | 95 | 100 | 100 | 0.68 s | 0.76 s | 14 ms | 0.00 | 2.13 s | 589 ms |
| `/especialidades` | 92 | 95 | 96 | 100 | 0.67 s | 0.97 s | 91 ms | 0.00 | 2.46 s | 562 ms |
| `/clinica` | 93 | 100 | 100 | 100 | 0.67 s | 0.89 s | 18 ms | 0.00 | 2.68 s | 615 ms |
| `/clinicas` | 90 | 92 | 96 | 100 | 0.90 s | 1.10 s | 78 ms | 0.00 | 2.69 s | 577 ms |
| `/contacto` | 94 | 100 | 100 | 100 | 0.67 s | 0.91 s | 5 ms | 0.00 | 2.33 s | 576 ms |
| `/verificar` | 93 | 91 | 100 | 69 | 0.68 s | 0.92 s | 68 ms | 0.00 | 2.56 s | 566 ms |
| `/login` | 94 | 93 | 96 | 58 | 0.67 s | 0.79 s | 0 ms | 0.00 | 2.37 s | 561 ms |

## Mobile Cold Results

| Route | Perf | A11y | Best | SEO | FCP | LCP | TBT | CLS | Speed Index | TTFB |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| `/` | 82 | 96 | 96 | 100 | 2.07 s | 2.89 s | 322 ms | 0.00 | 5.26 s | 601 ms |
| `/especialidades` | 83 | 95 | 96 | 100 | 1.80 s | 3.50 s | 210 ms | 0.00 | 5.30 s | 610 ms |
| `/clinica` | 81 | 100 | 96 | 100 | 2.03 s | 3.61 s | 103 ms | 0.00 | 6.87 s | 656 ms |
| `/clinicas` | 85 | 96 | 100 | 100 | 1.81 s | 3.01 s | 181 ms | 0.00 | 5.84 s | 578 ms |
| `/contacto` | 81 | 100 | 96 | 100 | 2.05 s | 3.85 s | 61 ms | 0.00 | 6.07 s | 651 ms |
| `/verificar` | 87 | 91 | 96 | 69 | 1.81 s | 3.16 s | 51 ms | 0.00 | 6.25 s | 598 ms |
| `/login` | 90 | 93 | 96 | 54 | 2.04 s | 2.34 s | 92 ms | 0.00 | 5.88 s | 651 ms |

## Core Web Vitals Interpretation

- CLS is excellent across all audited routes: `0.00`.
- Desktop is healthy. The priority is mobile LCP and Speed Index on `/clinica`, `/contacto`, and `/especialidades`.
- Mobile home LCP element: `/images/photography/female-doctor-consultation-1280.webp`.
- Page heroes are the LCP candidates on the remaining visual routes. Their markup correctly uses dimensions, `decoding="async"`, `loading="eager"`, and `fetchpriority="high"` only for hero imagery.
- TTFB is consistently about `0.56-0.66 s`. It is not critical, but it limits mobile LCP improvement once images and JavaScript are optimized.
- Lighthouse did not provide field INP. Mobile TBT peaks at `322 ms` on home and `210 ms` on specialties, indicating an interaction-risk area to validate later with real-user monitoring.

## Cold vs Warm Browser Pass

Browser warm navigation reused JavaScript and image resources, reducing DCL after the first visit to roughly `0.61-0.65 s`. HTML TTFB remained about `0.56-0.60 s` because HTML is `no-cache, private`.

- This is correct for authenticated, verification, and private routes.
- Public routes should be evaluated separately for safe edge/application caching after explicit cookie and Inertia variation rules are designed.
- Fingerprinted Vite assets were reused by the browser, but Lighthouse reports no long-lived cache policy for cold assets. Verify server/CDN static headers before claiming an asset-cache improvement.

## Lighthouse Findings

### P1 - Mobile main-thread and initial JS cost

- **Problem:** Mobile home has `322 ms` TBT; Lighthouse estimates `59 KiB` unused JavaScript in the `101.6 KiB` transferred shared app chunk.
- **Evidence:** `app-Bm5HbX2-.js` is 101.6 KiB transferred on mobile home; script evaluation is about 441 ms and style/layout about 576 ms.
- **Cause probable:** the shared entry includes broad application code and Vite link prefetch starts route chunks after page load.
- **Proposed solution:** measure a build treemap, then isolate public/admin code and test reducing `Vite::prefetch(concurrency: 3)`.
- **Risk:** Low if tested across Inertia navigation; do not remove required module preloads.
- **Effort:** Medium. **Priority:** P1.

### P1 - Mobile hero/image LCP

- **Problem:** Mobile LCP is `2.89-3.85 s` on visual public pages.
- **Evidence:** hero images are correctly prioritized but are still large enough to dominate mobile transfer/decode.
- **Cause probable:** LCP assets use only 640/1280 variants, and some cards advertise an invalid responsive set for images without a `-1280.webp` source.
- **Proposed solution:** create real 360/640/960/1280 WebP or AVIF derivatives and store explicit URLs/widths. Keep one hero as high priority; keep below-fold imagery lazy.
- **Risk:** Low after visual regression testing. **Effort:** Medium. **Priority:** P1.

### P2 - CSS transfer and unused rules

- **Problem:** shared CSS transfers 28.8 KiB compressed; Lighthouse estimates 24 KiB unused on mobile home.
- **Evidence:** `app-sT4VkuZW.css` contains public, admin, auth, and operational styles in one entry.
- **Proposed solution:** verify CSS coverage across key routes, then split page/admin CSS or remove genuinely dead rules. Do not blindly purge dynamic classes.
- **Risk:** Medium. **Effort:** Medium. **Priority:** P2.

### P2 - SEO score is deliberately reduced on private flows

- **Problem:** `/verificar` and `/login` score 69 and 54 in Lighthouse SEO.
- **Evidence:** both correctly declare `noindex,nofollow`.
- **Cause:** Lighthouse evaluates indexability, but these routes must not be indexed.
- **Proposed solution:** no change needed for those two scores; document them as intentional exclusions.
- **Risk:** None. **Effort:** None. **Priority:** Informational.
