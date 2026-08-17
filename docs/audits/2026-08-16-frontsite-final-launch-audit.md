# Prompt 27 final front-site and SEO launch audit

Audit date: 2026-08-16

Environment: local Laravel 13/Inertia 3/Vue 3 production client + SSR build

Scope: Phase 1.5 Prompts 14–27; Prompt 13 remains the product-wide gate

## Decision

- **Front-site technical readiness:** GO for controlled staging and review by
  the named Product, Legal/Privacy, Security, Accessibility, Finance, and
  Operations owners.
- **Phase 1.5 public production launch/indexation:** **NO-GO.** Local technical
  implementation is complete, but legal copy, product identity/domain,
  provider certification, independent accessibility/security, performance at
  target load, and production operations evidence remain external gates.
- **Overall product launch:** **NO-GO.** Prompt 13's critical/high release
  blockers and OPEN-10/OPEN-11 remain unresolved. This audit does not waive or
  downgrade them.

No unresolved in-scope P0 or P1 front-site defect remains after remediation.

## Completion report

- **Files changed:** public route/controller/configuration, one curated
  indexation and schema layer, owner-registration pricing preference, article
  publication fields and sanitization, public Vue pages/layout/components,
  marketing telemetry, global accessibility/performance CSS, tests, two
  additive migrations, prompt/status/decision/front-site documentation, and
  visual evidence. The unused legacy `SchemaOrg` placeholder producer and
  static `public/robots.txt` were removed after reference checks.
- **Routes created:** `/features` plus four details; `/solutions` plus four
  details; `/use-cases` plus four details; `/pricing`; `/company`; `/security`;
  `/resources`; two `/guides/{guide}` pages; `/sitemap.xml`; and generated
  `/robots.txt`. Existing `/blog` and `/blog/{article}` were rebuilt.
- **Routes modified:** `/` now renders Good Hours; `/sitemap` is a 301 to
  `/sitemap.xml`; legal routes use the public shell but remain noindex;
  registration accepts only current allow-listed pricing preferences; error
  responses render branded, noindex Inertia pages. Tenant booking, secure,
  auth, app, provider, and admin families remain separate and non-indexable.
- **Components created:** `MarketingLayout`; responsive header/footer; public
  container, heading, CTA, card, proof, comparison, FAQ/disclosure, breadcrumb,
  and conversion primitives; marketing hub/detail/guide/trust/pricing pages;
  and a vendor-neutral marketing event adapter.
- **Components reused:** Good Hours product mark/tokens/fonts, Inertia `Head`,
  `Link` and routing, Heroicons, server-owned registration/onboarding/billing,
  existing first-party instrumentation, and two synthetic reviewed product
  captures.
- **SEO changes:** query-free absolute canonicals; unique page metadata;
  correct Open Graph properties; `en_IN`; explicit robots meta and response
  headers; curated sitemap and robots; real error status/noindex behavior;
  publishable-article gates; contextual internal links; and no keyword, locale,
  competitor, city, doorway, bot-only, or machine-hidden page.
- **Schema changes:** one centralized factual graph for Organization, WebSite,
  WebPage, SoftwareApplication, eligible Article/Guide, and only currently
  visible pricing offers. Founder, address, contact, award,
  social, review/rating, certification, LocalBusiness, and placeholder schema
  were removed or never emitted.
- **Analytics changes:** bounded `marketing_cta_clicked` and user-initiated
  `marketing_pricing_interval_changed` DOM events have no storage/network
  consumer. `trial.qualified_started` is emitted exactly once only after
  verified Business/trial creation. Query attribution is neither persisted nor
  treated as conversion authority. No vendor, pixel, replay, fingerprinting,
  referrer store, or consent claim was added.
- **Tests run:** complete repository suite: 252 passed, 2,247 assertions, 28
  intentional skips, 18.94 seconds. Marketing tests cover all public page
  families, registration pricing tamper cases, stored XSS, indexation/schema,
  entity-answer consistency, accessibility/performance foundations, and a
  data-driven crawl of all 23 base sitemap URLs. Dedicated destructive MySQL
  tests were not repeated because `BOOKING_MYSQL_INTEGRATION=1` was not enabled;
  Prompt 13 retains their prior evidence and target-topology limits.
- **Build result:** Node v24.19.0 production client and SSR builds pass.
  `npm run check:frontsite-budgets` passes 17 route entries. `git diff --check`
  passes. No repository lint or TypeScript/static-type command is configured.
- **Warnings:** Vite reports the existing DaisyUI `@property` parse warning and
  defers absolute public font URLs to runtime. The 1.79 MB legacy theme CSS is
  quarantined outside public initial-route imports. In-app Chromium was the
  only browser available; final console collection was not exposed by that
  runtime, although sampled navigation produced no visible runtime/hydration
  failure and earlier prompt checks recorded no console warnings. Full
  assistive-technology, 200% zoom, supported-browser, field-performance, and
  production-network reviews remain external.
- **Assumptions:** `APP_URL` is the sole canonical authority until OPEN-11;
  current Paddle-catalog rows are server truth; English for India is the only
  reviewed public locale; no legal/operator/provider fact is inferred; and an
  empty Blog stays useful only while the Resources hub and two reviewed guides
  remain substantive.
- **Deferred items:** OPEN-09 destructive legacy cleanup; OPEN-10 retention and
  privacy executor authority; OPEN-11 trademark/domain/sender identity;
  OPEN-12 analytics/consent/retention; legal approval; live provider and India
  commerce certification; malware scanning and independent security review;
  production monitoring/backup/restore/DR; independent accessibility and
  supported-browser evidence; and target-topology load/field performance.

## Final crawl and page evidence

The deterministic sitemap contains 23 base pages: nine top-level public pages,
four feature details, four solution details, four use-case details, and two
guides. Publishable articles extend that number; draft/incomplete/future
articles never do. The automated crawl requires every listed URL to return
200, emit `index, follow, max-image-preview:large`, and contain its exact
self-canonical. It also proves untrusted query values cannot enter page content
or canonical identity.

Responsive browser sweeps sampled Home, hubs, details, a guide, Pricing,
Resources, Blog, legal pages, and a 404 at 320×720, 360×800, 390×844,
768×1024, and 1440×900. Sampled pages had one `h1`, a main landmark, no empty
links, no horizontal overflow, and no broken loaded image. Annual pricing was
selectable through its semantic label/radio. The controlled mobile navigation
had already proved Escape, outside-close, route-close, focus return, scroll
unlock, and valid destinations in Prompt 15.

Final visual evidence:

- [`../evidence/frontsite-2026-08-16/prompt-27-resources-mobile.png`](../evidence/frontsite-2026-08-16/prompt-27-resources-mobile.png)
- [`../evidence/frontsite-2026-08-16/prompt-27-pricing-desktop.png`](../evidence/frontsite-2026-08-16/prompt-27-pricing-desktop.png)

## Performance and accessibility evidence

| Artifact | Raw size | Gzip | Budget result |
| --- | ---: | ---: | --- |
| Shared app JavaScript | 275.72 kB | 94.86 kB | Pass: under 450 KiB |
| Shared public CSS | 258.85 kB | 39.02 kB | Pass: under 300 KiB |
| Home route JavaScript | 12.78 kB | 5.06 kB | Pass: under 160 KiB |
| Pricing route JavaScript | 9.40 kB | 3.86 kB | Pass: under 160 KiB |
| Home product images | 110.29/52.06 kB | n/a | Pass: each route image under 550 KiB |
| Self-hosted font files | 96–112 kB | n/a | Pass: each under 130 KiB |

The application provides skip navigation, semantic header/main/footer and
breadcrumb landmarks, one page heading, focus-visible styling, reduced-motion
behavior, semantic radio/disclosure/table controls, minimum interaction
targets, responsive wrapping, reserved image dimensions, lazy below-fold
media, and print rules. These are implementation checks, not an independent
WCAG conformance claim.

## Severity-ranked defect register

| Severity | Defect and evidence | Disposition | Owner |
| --- | --- | --- | --- |
| P0 | Public legal Markdown was placeholder content without counsel/DPO ownership | Fixed safely: explicit review draft, visible missing-owner state, stable noindex routes; approval remains external | Prompt 21 / OPEN-10 |
| P0 | Legacy schema producer could fabricate founders, address, awards, offers, reviews, reviewer and rating | Fixed: centralized factual graph; unused legacy producer deleted; schema regression excludes these types/fields | Prompt 23 |
| P0 | Request-time crawler could discover private/token/auth/utility routes | Fixed: deterministic 23-page base registry plus eligible-article query | Prompt 23 |
| P1 | Generic Larafast proposition, fake proof, partner/integration claims, dead links and newsletter action | Fixed: Good Hours shell/Home, synthetic evidence, executable destinations only | Prompts 15–16 |
| P1 | Stored article content rendered without an application sanitization/publication boundary | Fixed: server Markdown sanitizer, lifecycle gates, 404 quarantine and stored-XSS tests | Prompt 22 |
| P1 | Static/tamperable pricing could drift from effective billing catalog | Fixed: server catalog presenter, complete/unavailable states, registration revalidation and tamper tests | Prompt 20 |
| P1 | Canonical, robots, Open Graph, schema, sitemap and error policies conflicted or failed open | Fixed centrally and covered by route-family/crawl tests | Prompt 23 |
| P1 | Resources/Blog initially returned 500 in final local browser sweep because new additive migrations had not been applied | Fixed: migrations applied locally; both pages rechecked; deployment must run normal migrations before traffic | Prompt 27 / Operations |
| P2 | Mobile navigation lacked deterministic keyboard/focus/scroll behavior | Fixed in shared shell and browser-tested | Prompt 15 |
| P2 | Large generic Home/assets and missing public budgets | Fixed render path; explicit JS/CSS/font/image budgets now pass | Prompts 16/25 |
| P2 | No owned public event/link contract | Fixed locally with bounded vendor-neutral events and exact-once server authority; vendor remains intentionally absent | Prompt 26 / OPEN-12 |
| External | Legal, identity/domain, providers, assurance, operations and field/accessibility gates | Open; unsafe publication is prevented or explicitly qualified where possible | Named owners below |

## External unblock evidence and owners

| Gate | Accountable owner(s) | Evidence required before production public launch |
| --- | --- | --- |
| Terms, privacy, retention, destructive privacy processing (OPEN-10) | Named Indian counsel, Privacy/DPO, Product | Approved versioned terms/privacy notice; operator/controller/contact; purposes/recipients/transfers; consent/cookie position; record-specific retention schedule; deletion/anonymisation executor authorization and tests; acceptance/version records |
| Product identity, domain, and senders (OPEN-11) | Counsel, Product, Operations | Trademark clearance; acquired/configured canonical domain; redirect/proxy validation; defensive-domain decision; SPF, DKIM, DMARC, return path, reply handling, and provider sender verification |
| Marketing measurement (OPEN-12) | Product, Privacy/DPO, Engineering | Named provider/purpose; allowed event properties; consent class/UI; retention/expiry/deletion; access and incident owner; test proving no PII/token/private URL leakage |
| SaaS billing, appointment payments, and India commerce | Finance, Engineering, Operations, counsel/tax reviewer | Paddle and Stripe target-environment approval; signed webhook, checkout, renewal/failure/refund/reconciliation/settlement evidence; final tax/GST/receipt/legal review per tenant |
| Email and WhatsApp | Operations, Privacy, Engineering | Resend domain/sender verification; Twilio sender and Content SID approval; production callback secrets; delivery/suppression/opt-out evidence |
| Security and uploads | Security, Engineering | Upload malware scanning/quarantine; independent penetration test and remediation; production secrets/header/proxy review; continuous dependency/vulnerability control |
| Reliability and recovery | Operations/SRE, Engineering | Monitoring and alert routing; named on-call; truthful status communication; production backup configuration; exercised restore, DR, rollback, and incident runbooks |
| Accessibility and browser support | Accessibility/Design, Engineering, Product | Independent WCAG 2.2 AA audit; keyboard/screen-reader/200% zoom/reduced-motion evidence; Chrome, Safari, Edge, Firefox and representative real-device matrix; remediated findings |
| Capacity and field performance | Engineering, Operations | Target database/queue/storage/CDN topology load tests for availability, checkout, webhooks and public pages; cache/query evidence; production RUM/Core Web Vitals thresholds and owner |

OPEN-09 remains a medium Product/Engineering cleanup decision and does not
authorize deleting unknown legacy records or routes during launch remediation.
