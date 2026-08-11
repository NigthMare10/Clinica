# SEO Audit - Production

## Production Checks

- HTTPS redirect works: `http://` resolves to `https://`.
- Public routes have unique client-rendered titles, descriptions, canonical URLs, H1s, and Spanish HTML language.
- `/verificar` and `/login` correctly use `noindex,nofollow`; their low Lighthouse SEO scores are intentional.
- `/robots.txt` returns only `User-agent: *` and `Disallow:`.
- `/sitemap.xml` returns 404.

## P1 - Missing sitemap and incomplete robots policy

- **Problem:** no sitemap exists and robots has no sitemap reference or route exclusions.
- **Impact:** slower discovery/recrawl and unnecessary crawler access to account paths.
- **Evidence:** production `/sitemap.xml` returned 404; `public/robots.txt` has two lines only.
- **Cause probable:** sitemap generation was not added.
- **Solution proposed:** add an XML sitemap for `/`, `/especialidades`, active public specialty detail pages, `/clinica`, `/clinicas`, and `/contacto`. Add `Sitemap:` and disallow `/admin`, `/login`, and account/auth endpoints while keeping CSS/JS crawlable.
- **Risk:** Low. **Effort:** Medium. **Priority:** P1.

## P1 - Metadata is client-rendered only

- **Problem:** the initial Laravel HTML shell has a generic title; page title/description/canonical/OG fields are injected by Vue after mount.
- **Impact:** social crawlers and non-JS crawlers may not see route-specific metadata reliably.
- **Evidence:** `resources/views/app.blade.php:1-16`; `resources/js/Components/PageMeta.vue:1-7`.
- **Solution proposed:** add Inertia SSR or prerender the small public route set. Verify response source, not DevTools, contains final metadata.
- **Risk:** Medium. **Effort:** High. **Priority:** P1.

## P2 - Social metadata lacks preview image and Twitter fields

- **Problem:** PageMeta outputs title, description, type, URL, and canonical only.
- **Impact:** weak or inconsistent social previews.
- **Evidence:** `PageMeta.vue:7`.
- **Solution proposed:** add absolute `og:image`, dimensions, `og:site_name`, `og:locale`, `twitter:card`, and a versioned institutional social image. Do not use patient, document, or unverified location imagery.
- **Risk:** Low. **Effort:** Low. **Priority:** P2.

## P2 - Editorial SEO fields are unused

- **Problem:** specialty `seo_title` and `seo_description` exist but public pages derive metadata from generic title/content.
- **Evidence:** specialty schema and `resources/js/Pages/Public/Specialties/Show.vue`.
- **Solution proposed:** serialize and use editorial fields with safe fallback. Enforce title/description length in the admin content workflow.
- **Risk:** Low. **Effort:** Low. **Priority:** P2.

## P2 - Canonical pagination handling

- **Problem:** fallback canonical strips query parameters.
- **Impact:** a future paginated public listing may canonicalize page two to page one.
- **Evidence:** `PageMeta.vue:5`.
- **Solution proposed:** preserve recognized pagination parameters or set explicit canonical/prev/next on paginated public pages.
- **Risk:** Low. **Effort:** Low. **Priority:** P2.

## Structured Data

- Existing client-rendered JSON-LD uses `MedicalClinic` in `PublicLayout.vue`.
- Improve only after SSR/prerender makes it discoverable: provide `@id`, verified logo/image, telephone, `PostalAddress`, geo coordinates only for verified locations, and stable sameAs links.
- Do not add ratings, reviews, medical staff, hours, or physical locations without confirmed source data.

## Specialty and Location Content

- Specialty pages need an editorial review for unique title, H1, description, clinical scope, and CTA. Avoid template-copy repetition.
- Future departmental URLs can help only for true, independently useful clinics/coverage pages with confirmed contact/location/service data. Do not create pages for fictitious local presence or thin duplicated departmental content.
- A Google Business Profile is recommended only for verified physical locations. Use an accurate primary organization profile and location profiles only where each location can receive patients and has valid address/phone data.

## Search Console Preparation

Before onboarding Search Console: deploy a valid sitemap, verify server-rendered metadata/canonicals, preserve `noindex` on protected routes, and submit the canonical HTTPS property.
