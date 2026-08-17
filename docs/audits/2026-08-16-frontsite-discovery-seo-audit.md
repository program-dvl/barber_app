# Prompt 14 front-site discovery and SEO audit

Audit date: 2026-08-16  
Environment: local Laravel 13/Inertia 3/Vue 3 production client + SSR build  
Decision: implementation may proceed to Prompt 15; this does not change the
Prompt 13 overall launch NO-GO.

## Executive result

The existing root page is Larafast marketing boilerplate inside a repository
whose product application is already Good Hours. At 360px it had no horizontal
overflow, but exposed fabricated counts, uptime/support statements, fake
ratings/user activity, unsupported integrations and partner logos, unresolved
translation keys, a dead demo link, numerous `#` footer links, a nonfunctional
newsletter, wrong product/audience copy and `Barber_app` environment branding.
The public sitemap crawled the running application dynamically and the schema
service could fabricate organization/review data. Legal Markdown was an
explicit placeholder. These are publication blockers, not copy polish.

The accepted IA, indexation and CTA contract is
[`../frontsite/README.md`](../frontsite/README.md); the claim/entity ledger is
[`../frontsite/content-and-claims.md`](../frontsite/content-and-claims.md).

## Verification baseline

- Prompt 13 evidence exists and says NO-GO. OPEN-10/OPEN-11 plus provider,
  legal, security, accessibility, topology and operations certification remain
  unresolved.
- Focused local suite: 30 passed, 309 assertions, 1 intentional skip
  (`ProductShellTest`, authentication, registration and public booking/
  waitlist).
- Production client + SSR build passed with Node v24.19.0. Warnings: DaisyUI
  `@property`; public self-hosted fonts remain runtime URLs. Baseline Home
  route chunk: 96.31 kB client (20.12 kB gzip); shared app chunk 272.41 kB
  (93.82 kB gzip); CSS 253.30 kB (37.71 kB gzip) plus a 1.79 MB theme CSS
  asset (150.29 kB gzip).
- Browser baseline at 360×800: one `h1`, 360px document width (no horizontal
  overflow), but 40+ public links including 10 dead/`#` destinations and a
  hidden dropdown that was not a controlled, focus-returning mobile menu.
- Screenshot: [`../evidence/frontsite-2026-08-16/prompt-14-home-mobile-before.png`](../evidence/frontsite-2026-08-16/prompt-14-home-mobile-before.png).

## Anonymous route inventory

The executable `php artisan route:list --json` was the source. Framework asset
routes are grouped rather than repeated.

| Surface | Class | Current behavior | Disposition |
| --- | --- | --- | --- |
| `/` | Marketing | Generic Larafast Home, no route name, default SEO | Replace in P15/P16; index only after completion |
| `/blog`, `/blog/{slug}` | Content | Generic copy; active DB rows; raw stored HTML rendered with `v-html`; article schema | Rebuild publication/sanitization contract in P22; eligible records only |
| `/roadmap` + authenticated mutations | Marketing/community | Generic voting and future-feature exposure | Quarantine/noindex; remove from nav; OPEN-09 owns deletion |
| `/coming-soon` + POST | Marketing/form | Generic email collector with no consent/retention/follow-up ownership | Quarantine/noindex and disable collection in P21/P23 |
| `/changelog` | Content | Generic Markdown rendered to HTML, public | Quarantine/noindex until reviewed content process |
| `/book`, `/book/{slug}` | Booking | Privacy-safe entry plus tenant booking | Keep; noindex; not in marketing nav/sitemap |
| `/manage-appointment` | Booking recovery | Requires secure link | Keep; noindex |
| Appointment/client-form/waitlist secure-token URLs | Transactional | Expiring, purpose-bound public task routes | Keep; strict noindex/nofollow/noarchive, no sitemap/canonical token leakage |
| `/client-forms/completed` | Transactional status | Generic completion | Keep; noindex |
| `/client-files/{token}` | Transactional download | Expiring private file access | Keep; noindex/noarchive |
| Communication action link | Transactional | GET/POST action token | Keep; noindex/noarchive |
| Login/register/password/verification/2FA | Auth | Valid owner/staff identity flow | Keep; noindex; register/login may receive CTA links |
| Terms/privacy Jetstream routes | Legal | Markdown says “Edit this file…” | Keep route names; replace UI; noindex until counsel-owned copy exists |
| `/sitemap` | Utility/SEO | Dynamic Spatie crawler rooted at `APP_URL` | Replace with curated `/sitemap.xml`; redirect legacy path |
| `/og-image/{...}`, `/og-image-testing` | Utility/test | User-controlled image text and public test template | Restrict/remove from public production routing in P23 |
| Provider webhooks | Webhook | Signed provider callbacks | Keep; never index/link |
| Sanctum/Livewire/build routes | Framework utility | Machine assets/actions | Keep; never index/link |
| Filament/admin/platform/shop/profile/download routes | Private app | Auth/policy protected | Keep; noindex and absent from marketing discovery |
| Unknown URL | Error | Framework 404 | Preserve real 404; add noindex/error recovery in P23/P27 |

## Severity-ranked findings

| Severity | Finding/evidence | Recommendation | Owner |
| --- | --- | --- | --- |
| P0 | Placeholder legal terms/privacy are publicly reachable | Noindex and visibly mark unavailable; counsel/DPO approval required | P21/P23; OPEN-10 |
| P0 | Organization/review schema producers contain placeholder founders, address, awards, offers, sameAs, reviewer and rating data | Remove all unsupported producers; centralize verified graph | P23 |
| P0 | Dynamic sitemap crawler can traverse auth, booking tokens, utilities, redirects and parameter variants | Replace with an explicit indexable-route registry | P23 |
| P1 | Home is entirely the wrong product with fabricated proof and Phase 2/integration implications | Replace shell/home from verified claim ledger | P15/P16 |
| P1 | Generic article/changelog HTML is rendered with `v-html` without an application publication sanitization boundary | Sanitize server-side and test stored-XSS payloads | P22 |
| P1 | Canonical defaults to request URL and therefore permits query/host duplication; metadata lacks standard OG properties/type/site name/robots | Central SEO service and configured base URL validation | P23 |
| P1 | Secure/auth/tenant booking routes do not have a documented, enforced noindex policy | Add route-family robots middleware/meta/headers | P23 |
| P1 | Dead demo/footer/social/service/cookie/newsletter destinations | Remove; never replace with empty actions | P15/P16/P21 |
| P1 | Pricing shown in frontend components is static and conflicts with effective-dated catalog truth | Render only active catalog plans/prices with unavailable state | P20 |
| P2 | Header mobile dropdown has weak state/focus/escape/route-change behavior and app name/logo mismatch | Rebuild semantic controlled navigation | P15 |
| P2 | 1.1 MB baseline hero and broad integration imagery; 1.79 MB theme CSS; home sends 96 kB page JS | Remove assets from render path, reserve dimensions, set budgets | P16/P25 |
| P2 | Generic empty blog is a dead end and moment adds ~60 kB client JS | Build useful empty state and native/date-server formatting | P22/P25 |
| P2 | No application-owned marketing event/attribution/consent contract | Add bounded first-party adapter and keep vendor unresolved | P26 |
| P2 | No global crawl graph, breadcrumb contract or related-content ownership | Implement from accepted IA | P15/P17–P26 |

## Component and asset disposition

| Existing item | Disposition | Reason |
| --- | --- | --- |
| Good Hours semantic tokens, fonts, `ProductMark`, focus/reduced-motion base | Reuse | Accepted brand/design foundation |
| Low-level Heroicons and Inertia `Link` | Reuse | Existing dependencies and semantic navigation |
| `HomeLayout`, Header, Footer, Copyright | Replace/adapt in P15 | Generic links, theme behavior, copy and inaccessible disclosure |
| Product `PageHeader`, `SurfaceCard`, buttons/state panels | Reference/reuse low-level semantics only | Marketing needs its own namespace and density |
| Hero, Partners, Integrations, FeaturesFlow, ContentWithImage, Problem, old Plans/FAQ/CTA | Quarantine then remove from Home imports | Unsupported/generic claims and visuals |
| `hero.jpg`, `content.jpg`, `cta.png`, integration logos, old logo assets | Do not reuse | Generic/unlicensed or unsupported implication |
| Existing verified product evidence screenshots | Reuse selectively | Synthetic and already reviewed; crop/label without changing meaning |
| Blog Article card/page | Replace/adapt | Generic copy, unsafe rich HTML, fabricated sharing assumptions |

## Metadata, indexation, schema and crawl baseline

- The default title rendered as `- Good Hours`; Home did not supply page SEO.
- Defaults supplied one description and request-derived canonical. There was no
  `og:type`, standard `property="og:*"`, site name, locale, robots directive or
  safe route-family policy.
- `/sitemap` was HTML-route named but returned a request-time crawl result;
  `/sitemap.xml` was not an application route or generated artifact at audit
  time.
- `robots.txt` permitted everything and did not advertise a sitemap.
- Schema was injected through a global Blade include and was not attached to a
  single verified entity registry. The Organization and Reviews producers were
  unsafe even if not currently shared on Home.
- Marketing pages had no accepted breadcrumb or entity graph. Tenant booking
  and secure routes were not intentionally separated in the sitemap producer.

## Journey findings

| Person | Baseline problem | Intended path |
| --- | --- | --- |
| First-time owner | Wrong developer-starter proposition and fake proof | Home → relevant feature/solution/use case → Pricing → Register |
| Evaluating owner | Static generic pricing and no limitations | Features/solutions → Pricing with catalog truth → Register |
| Returning visitor | No consistent current location or useful resources | Global navigation + relevant related links |
| Existing customer | Sign-in competes with generic CTA | Login, or Dashboard when authenticated |
| Trial signup | “Get started/free” labels are inconsistent | “Start your trial” → Register; verified completion remains server-authoritative |
| Content reader | Generic build-products blog and empty dead end | Resources/guide/article → related product → relevant action |
| Booking client | Marketing and tenant booking coexist without an explicit URL policy | Direct `/book/{slug}` task shell; no marketing menu or guessed Business |

## Accessibility and responsive baseline

Verified locally: base focus-visible and reduced-motion CSS exists; the Home
document stayed within 360px. Not verified: WCAG conformance, screen-reader
behavior, full keyboard order, contrast across generic components, menu focus
return/Escape/outside-click, 200% zoom and supported browser matrix. Many icon-
only social links were dead; the mobile menu used a focus/dropdown convention
without deterministic state; headings and buttons belonged to unrelated
interactive sections. Prompts 15/16 own remediation; P25/P27 own cross-site
evidence. Independent WCAG certification remains a Prompt 13 blocker.

## Performance baseline and budgets handed to P25

The local production build is a lab artifact, not customer-facing performance
evidence. Primary risks are the broad shared application bundle, DaisyUI theme
CSS, large generic images, self-hosted TTF fonts and page-specific Home code.
P25 must budget the public critical path, subset/preload only required fonts,
remove generic image requests, preserve image dimensions, avoid new third-party
scripts and distinguish lab results from field data.

## Analytics and privacy baseline

`InstrumentationService` is allow-listed, append-only and tenant-safe for
product metrics; `PublicBookingEvent` records privacy-minimized booking flow
milestones. There is no marketing attribution/consent/vendor implementation.
The current Home includes no approved tracker, which is safer than installing
one without authority. P26 must add a bounded application adapter, allowed
properties, expiry/deduplication, server-authoritative signup completion and a
future-provider seam. OPEN-10 remains the retention/consent authority blocker.

## Prompt 15 gate

Unblocked. Navigation labels, route hierarchy, CTA semantics, route-family
indexation, component dispositions and truthful global language are now
explicit. Prompt 15 may build the shared shell without inventing destination
pages or resolving Prompt 13 blockers.

