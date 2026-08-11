# Production Performance Audit

## Scope and Guardrails

Read-only audit of production on 2026-08-11. No deployment, migration, environment change, database mutation, file deletion, or server configuration change was performed.

## Network and Largest Assets

Home mobile Lighthouse cold load: 89 requests, 569 KiB transferred. The request count is inflated after initial render by Vite prefetching route chunks; it remains an optimization opportunity because it competes with lower-end mobile networking.

| Asset | Type | Transfer | Finding |
| --- | --- | ---: | --- |
| `/images/photography/dentistry-1280.webp` | Image | 134 KiB | Below-fold lazy image; downloaded during Lighthouse scroll/settle. Use a true smaller card source. |
| `/images/photography/female-doctor-consultation-1280.webp` | Image | 104 KiB | Correct home LCP image and high priority. |
| `/build/assets/app-Bm5HbX2-.js` | JS | 102 KiB | Largest initial script; 59 KiB unused estimate on home. |
| `/build/assets/leaflet-src-D_ayM1Fk.js` | JS | 42 KiB | Deferred map chunk; it loads only near the mini-map/interaction, not at initial FCP. |
| `/build/assets/app-sT4VkuZW.css` | CSS | 29 KiB | Shared global stylesheet; 24 KiB unused estimate on home. |
| `/` HTML | Document | 18 KiB | Brotli compressed. |

## JavaScript

### P1 - Public home prefetches unrelated route chunks

- **Problem:** after the initial home resources, the waterfall includes admin/document/fiscal chunks such as `Review`, `Generate`, `AdminLayout`, `Signature`, and `Lookup`.
- **Impact:** additional network contention and CPU work on mobile, even for visitors who never enter administration.
- **Evidence:** production Lighthouse network list; `app/Providers/AppServiceProvider.php:42` calls `Vite::prefetch(concurrency: 3)` globally.
- **Cause probable:** global Inertia/Vite prefetch policy has no public/admin boundary.
- **Solution proposed:** benchmark `concurrency: 0/1` or conditionally prefetch only after first interaction on public routes. Preserve modulepreload for the current page.
- **Risk:** Low. **Effort:** Low. **Priority:** P1.

### P2 - Shared app chunk carries code not needed by every public route

- **Problem:** the shared application chunk is 297 KiB uncompressed/102 KiB transferred; 59% is unused in home lab coverage.
- **Impact:** mobile TBT and LCP.
- **Evidence:** Lighthouse unused-JS audit; Vite manifest generated locally.
- **Solution proposed:** produce a treemap before editing imports, then separate admin-only shell/components from public entry dependencies where the graph permits.
- **Risk:** Medium. **Effort:** Medium. **Priority:** P2.

### GSAP and QR

- No GSAP source import exists. `gsap@3.15.0` is installed but extraneous (`npm ls`); it is not in `package.json` or the lockfile.
- Current reveal animation uses `IntersectionObserver` and `requestAnimationFrame`, with reduced-motion support. Keep it; do not add GSAP.
- QR camera scanning uses native `BarcodeDetector` and `getUserMedia` only after selecting the QR tab. It is not loaded on home and no camera permission is requested on page load.
- Safari/unsupported-browser fallback is text/PDF only. A decoder fallback is a UX decision, not an immediate performance change.

## CSS and Rendering

- CSS has deliberate blur/backdrop-filter effects in navigation, cards, hero overlays, and map labels (`resources/css/redesign.css`). They are visually contained, but several large fixed/blurred layers can increase mobile compositing cost.
- Avoid a broad visual rewrite. First test a mobile media-query reduction for decorative blur/shadows and compare LCP/TBT/screenshots.
- Public images consistently reserve width/height, explaining the excellent CLS result.

## Images

### P1 - Specialty responsive-source bug

- **Problem:** `responsiveSet()` transforms any path as though it ends in `-1280.webp`.
- **Evidence:** `resources/js/Pages/Public/Home.vue:17-19` and `Specialties/Index.vue`; CMS/seed specialty paths are single `/images/specialties/*.webp` files.
- **Impact:** the same image can be emitted as both `640w` and `1280w`, which misrepresents source widths and can download a larger file than necessary.
- **Solution proposed:** store actual derivatives and descriptors; emit no `srcset` when variants do not exist.
- **Risk:** Low. **Effort:** Medium. **Priority:** P1.

### P2 - Asset inventory and image budget

- Production public images total 3.5 MB; build output totals 7.4 MB. Individual served images are WebP and currently modest, but visual pages need smaller mobile derivatives.
- Suggested future budget: LCP image <= 150 KiB transferred on mobile, card image <= 80 KiB, and never load a 1280px source for a 360px card.
- ImageMagick is not available locally, so intrinsic dimensions for every repository image were not generated in this audit. Production browser measurements confirm the hero/card rendering patterns and should be paired with an image-derivative pipeline before an implementation.

## Map

- Leaflet is dynamically imported only by the home mini-map after intersection/interaction (`Home.vue:22-56`); it is not initial critical-path JS.
- The home map requests OpenStreetMap tiles and creates 18 lightweight circle markers. Keep the deferred behavior; assess privacy/third-party tile policy separately.
- The `/clinicas` page intentionally uses the SVG fallback map. It does not load Leaflet, so Leaflet is not unnecessary initial bundle cost on that page.
- The SVG marker buttons are 14-16px and should receive a 44px hit target for mobile accessibility.

## Fonts

- No external font request appears in Lighthouse or browser resource timing; pages rely on system/UI and serif stacks.
- This is positive for performance. No font preload is recommended.

## Backend TTFB and Compression

- Browser production TTFB: `~0.56-0.66 s` across public routes.
- HTML, CSS, and JS use Brotli (`content-encoding: br`); HTTP/2 and HTTP/3 are available.
- Hostinger CDN reports public HTML as dynamic (`x-hcdn-cache-status: DYNAMIC`), and HTML uses `no-cache, private`.
- Recommendation: cache only explicitly cookie-safe anonymous public responses, after validating `Vary`, Inertia headers, session/flash behavior, and invalidation. Never cache `/admin`, `/verificar*`, invoice verification, or private downloads.

## Mobile UX

- Tested viewport: 390x844; Lighthouse mobile profile also used.
- No measured horizontal overflow or CLS failure on audited public routes.
- Improve small operational text (many 8.5-12.5px labels), map hit targets, complete mobile-menu focus management, and QR tab semantics.
- Login artwork is hidden below 1000px but still has an image source; conditionally render or use an appropriate media source to avoid unnecessary mobile work.

## Performance Budget Proposal

| Metric | Current mobile worst | Proposed CI budget |
| --- | ---: | ---: |
| Performance score | 81 | >= 90 for public routes |
| LCP | 3.85 s | <= 2.5 s lab target |
| TBT | 322 ms | <= 200 ms |
| CLS | 0.00 | <= 0.05 |
| Initial JS transfer | 110 KiB home | <= 90 KiB gzip/Brotli-equivalent |
| Initial CSS transfer | 29 KiB | <= 35 KiB, route-aware |
| Initial requests | 89 Lighthouse settled | <= 35 critical-before-LCP |
