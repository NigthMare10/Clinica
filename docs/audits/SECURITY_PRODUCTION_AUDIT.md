# Security Production Audit

## Confirmed Production Controls

| Control | Result |
| --- | --- |
| HTTPS redirect | HTTP resolves to HTTPS |
| Transport compression | Brotli for HTML/CSS/JS |
| CSP | Present; `default-src 'self'`, frame ancestors denied by application policy |
| X-Content-Type-Options | `nosniff` |
| X-Frame-Options | `DENY` |
| Referrer Policy | `strict-origin-when-cross-origin`; private verify flows use `no-referrer` |
| Permissions Policy | camera only for verification, microphone/geolocation/payment denied |
| Session cookie | Secure, HttpOnly, SameSite=Lax |
| XSRF cookie | Secure, SameSite=Lax; intentionally readable by client-side XSRF handling |
| Private routes | NoIndex middleware uses `no-store, private`, `noindex,nofollow,noarchive` |

## P2 - HSTS is absent

- **Problem:** production responses do not include `Strict-Transport-Security`.
- **Impact:** first-visit downgrade protection is unavailable.
- **Evidence:** browser response header audit.
- **Solution proposed:** enable HSTS only after confirming all subdomains are HTTPS-ready; start with an appropriate max-age and includeSubDomains decision.
- **Risk:** Medium because an incorrect policy can make a hostname inaccessible. **Effort:** Low. **Priority:** P2.

## P2 - CSP currently allows inline scripts/styles

- **Problem:** application CSP contains `'unsafe-inline'` for scripts and styles.
- **Impact:** CSP provides weaker XSS defense than nonce/hash-based policy.
- **Evidence:** `app/Http/Middleware/SecurityHeaders.php:14-16`.
- **Solution proposed:** inventory framework/Vite inline requirements, move to per-response nonces or hashes, and test Inertia/Vite/analytics behavior. Do not remove `unsafe-inline` without that validation.
- **Risk:** High. **Effort:** High. **Priority:** P2.

## P3 - Defense-in-depth headers

- **Problem:** no observed HSTS, COOP, or explicit cross-origin resource policy response headers.
- **Impact:** hardening opportunity, not an identified compromise.
- **Solution proposed:** assess COOP/CORP compatibility with OpenStreetMap tiles and public PDFs before adding headers. Keep X-Frame-Options/CSP frame-ancestors.
- **Risk:** Medium. **Effort:** Low-Medium. **Priority:** P3.

## Security Constraints for Optimization

- Never cache private PDF/download responses, `/admin`, verification tokens, invoice verification, patient data, or authenticated Inertia responses at CDN/application edge.
- Preserve `no-store`, referrer suppression and robot exclusion for private/verification flows.
- Do not expose admin credentials, session cookies, RTN/CAI secrets beyond approved fiscal documents, or production logs in public reports.
- Do not move document/PDF storage to S3-compatible remote storage until the local-path-dependent OCR/PDF pipeline has secure staging support.
