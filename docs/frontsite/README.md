# Good Hours public acquisition architecture

Status: Accepted implementation contract for Phase 1.5 (2026-08-16)

This document separates the Good Hours acquisition site from tenant booking,
authentication, and private transactional journeys. It is the durable route,
indexation, linking, content, and conversion contract for Prompts 15–27.

## Route families

| Family | URL policy | Discoverability | Purpose |
| --- | --- | --- | --- |
| Marketing | Stable, lowercase paths on the public Good Hours host | Index only after the page is substantive, truthful, SSR-rendered, internally linked, and present in the curated sitemap | Product discovery and evaluation |
| Tenant booking | `/book/{business-slug}` | `noindex`; excluded from the marketing sitemap and global navigation | A named Business's client booking task |
| Booking entry/recovery | `/book`, `/manage-appointment` | `noindex`; may be linked only from task-specific recovery UI | Privacy-safe instructions when the required Business slug or secure link is missing |
| Secure transactions | `/appointments/secure/{token}`, `/client-forms/secure/{token}`, `/waitlist-offers/{token}`, `/client-files/{token}`, and communication action links | `noindex, nofollow, noarchive`; never linked, canonicalized, logged, or measured with the token | Purpose-bound client actions |
| Authentication | Login, registration, password, verification, and two-factor routes | `noindex`; excluded from the sitemap but valid conversion destinations | Account entry and owner trial signup |
| Application/admin | `/businesses/*`, `/platform/*`, `/admin/*`, profile, downloads, imports, exports | Authenticated and `noindex`; absent from all public discovery surfaces | Product operation |
| Webhooks/assets/utilities | Provider callbacks, Livewire, Sanctum, build assets, health/debug utilities | Non-indexable and not navigational | Machine or local-development behavior |

The preferred production hosts in ADR-011 remain a direction, not evidence of
domain ownership. Application-generated absolute URLs always use the configured
`APP_URL` until OPEN-11 is resolved.

## Approved information architecture

The initial public set is deliberately small. A candidate earns a route only
when it has distinct intent, enough verified evidence, and useful original
content. Prompt ownership is retained for audit traceability.

| Route | Purpose and primary intent | Audience | Evidence | Primary CTA | Canonical/indexation | Schema eligibility | Owner prompt |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `/` | Define Good Hours and its booking-to-checkout operating loop | First-time owner | Product brief; FR-01–FR-20 status | Start your trial | Self; index | WebSite, WebPage, SoftwareApplication | 16 |
| `/features` | Explain the connected capability system | Evaluating owner/manager | Implemented Phase 1 modules | Start your trial | Self; index | WebPage, BreadcrumbList | 17 |
| `/features/online-booking` | Mobile booking, availability, holds, secure self-service | Owner protecting booking demand | FR-06, FR-07, FR-09, FR-10 | Start your trial | Self; index | WebPage, BreadcrumbList | 17 |
| `/features/calendar-and-walk-ins` | Calendar, lifecycle, operational recovery, queue | Front desk/manager | FR-06, FR-08 | Start your trial | Self; index | WebPage, BreadcrumbList | 17 |
| `/features/client-management` | Client history, notes, forms, consent and privacy cases | Owner/service professional | FR-11, FR-12 | Start your trial | Self; index | WebPage, BreadcrumbList | 17 |
| `/features/checkout-and-reporting` | Checkout, deposits, receipts, inventory, commission and reports | Owner/accountant | FR-14–FR-18 | Start your trial | Self; index | WebPage, BreadcrumbList | 17 |
| `/solutions` | Help supported business types assess fit | Evaluating owner | Cross-module evidence | Start your trial | Self; index | CollectionPage, BreadcrumbList | 18 |
| `/solutions/barbershops` | Walk-ins, queue, staff calendar, checkout | Barbershop owner | FR-06, FR-08, FR-14 | Start your trial | Self; index | WebPage, BreadcrumbList | 18 |
| `/solutions/salons` | Multi-service scheduling, client context, staff operation | Salon owner | FR-04–FR-08, FR-11–FR-15 | Start your trial | Self; index | WebPage, BreadcrumbList | 18 |
| `/solutions/independent-stylists` | One-owner setup, booking, client record, checkout | Independent professional | FR-01–FR-05, FR-09, FR-11, FR-14 | Start your trial | Self; index | WebPage, BreadcrumbList | 18 |
| `/solutions/spas` | Room/resource capacity, multi-segment services, consultation records | Small spa operator | FR-03–FR-07, FR-11, FR-12 | Start your trial | Self; index with explicit non-medical qualifier | WebPage, BreadcrumbList | 18 |
| `/use-cases` | Frame evaluation by operational problem | Problem-aware owner | Implemented workflows | Explore pricing | Self; index | CollectionPage, BreadcrumbList | 19 |
| `/use-cases/reduce-scheduling-conflicts` | Capacity-aware scheduling and commit-time revalidation | Owner/manager | FR-03–FR-07 | Start your trial | Self; index | WebPage, BreadcrumbList | 19 |
| `/use-cases/manage-walk-ins-and-appointments` | Run mixed booked and walk-in demand | Front desk/owner | FR-06, FR-08 | Start your trial | Self; index | WebPage, BreadcrumbList | 19 |
| `/use-cases/protect-time-with-deposits` | State deposit/cancellation flow without outcome guarantees | Owner | FR-09, FR-10, FR-14 | Explore pricing | Self; index with provider qualifier | WebPage, BreadcrumbList | 19 |
| `/use-cases/keep-client-history-together` | Connect visits, forms, consent, sales and messages | Owner/service professional | FR-11–FR-18 | Start your trial | Self; index | WebPage, BreadcrumbList | 19 |
| `/pricing` | Compare approved Starter/Pro catalogue and trial mechanics | Commercial evaluator | ADR-021/effective price catalogue | Choose a plan | Self; index; incomplete catalog renders a substantive unavailable state and no offers | WebPage, SoftwareApplication offers only when visible prices resolve | 20 |
| `/security` | Explain implemented controls and honest external assurance gaps | Risk evaluator | Architecture, launch evidence | Review product | Self; index | WebPage, BreadcrumbList | 21 |
| `/company` | State product mission, audience, and ownership limits | Visitor/researcher | Product brief, ADR-011 | Explore Good Hours | Self; index | AboutPage, Organization reference | 21 |
| `/terms-of-service` | Counsel-owned terms | Account/legal evaluator | Current Markdown is placeholder | Register only after approval | Self; **noindex until counsel approval** | WebPage only after approval | 21 |
| `/privacy-policy` | Counsel-owned privacy notice | Visitor/data subject | Current Markdown is placeholder; OPEN-10 | Register only after approval | Self; **noindex until counsel approval** | WebPage only after approval | 21 |
| `/resources` | Editorial hub and guide discovery | Owner researching operations | Reviewed static guides and published articles | Explore Good Hours | Self; index | CollectionPage, BreadcrumbList | 22 |
| `/guides/booking-policy-basics` | Educational guide grounded in configurable policy | Owner | FR-02, FR-09, FR-14 | Explore online booking | Self; index | Article, BreadcrumbList | 22 |
| `/guides/salon-opening-checklist` | Operational setup checklist without legal advice | New owner | FR-02–FR-05 readiness | Explore setup features | Self; index | Article, BreadcrumbList | 22 |
| `/blog` | Reviewed article archive | Content reader | Active, publishable articles only | Explore resources | Self; index even when empty only if useful editorial context remains | Blog, BreadcrumbList | 22 |
| `/blog/{slug}` | One reviewed article | Content reader | Sanitized stored article and verified author/date | Contextual product link | Self; index only when active and complete | Article, BreadcrumbList | 22 |

### Consolidated or deferred candidates

- Hair salon and beauty salon are consolidated into `/solutions/salons`; they
  do not have enough unique supported scope for separate pages.
- Nail salon is deferred until distinct product evidence and reviewed audience
  research exist. Current services/forms/resources are not permission to
  publish a keyword-swapped page.
- Comparison/competitor, city, country, directory, marketplace, AI, franchise,
  medical-compliance, demo, contact, newsletter, integration, testimonial, and
  customer-story pages are not approved.
- A changelog may later return as a reviewed resource type. The current generic
  surface is noindex and absent from navigation.

## Legacy route disposition

| Current surface | Disposition | Reason / owner |
| --- | --- | --- |
| `/roadmap` and authenticated voting | Quarantine, noindex, remove from public navigation; destructive removal still depends on OPEN-09 | Exposes unsupported future promises and boilerplate community behavior |
| `/coming-soon` and email collection | Quarantine, noindex, remove from navigation; do not collect email | No owned consent, retention, or follow-up workflow |
| `/changelog` | Quarantine/noindex until a reviewed release-content process exists | Generic Larafast content and unsafe rendered Markdown |
| `/sitemap` | Redirect to `/sitemap.xml` | One canonical machine URL |
| Runtime crawler sitemap | Replace with a curated, data-driven sitemap | A request-triggered crawler can discover tokens, auth, utilities, or noncanonical URLs |
| `/og-image/{title?}/{description?}` and `/og-image-testing` | Restrict/remove from production public routing | User-controlled image copy and a test template are not indexable content |
| Generic integrations/assets | Remove from visible bundles and new pages; retain files until OPEN-09 cleanup | They imply unsupported partnerships |
| Generic blog articles | Only active records passing the Prompt 22 publication contract may be indexable | Existing records are not assumed to be Good Hours editorial content |

## Indexation rules

1. A page is indexable only if it returns 200, has a unique title, description
   and `h1`, emits an absolute query-free canonical, is SSR-readable, is linked
   by at least one approved crawl path, and is included in `/sitemap.xml`.
2. Query parameters never alter canonical identity. UTM and attribution input
   is bounded, expired, and removed from canonical/schema URLs.
3. Authentication, tenant booking, secure-token, admin, API/webhook, download,
   preview, debug, error, and unfinished legal pages emit `noindex` and stay out
   of sitemap/internal discovery.
4. Unknown routes return a real 404 with `noindex`; there are no soft 404s.
5. Pagination and article eligibility are explicit. Draft/inactive content is
   a 404, not an indexable preview.
6. `robots.txt` advertises only the curated sitemap and does not pretend that a
   `Disallow` rule protects private content.

## Global and contextual linking

```text
Home
├── Features ── four verified feature details
├── Solutions ── four differentiated business-type pages
├── Use cases ── four problem/workflow pages
├── Pricing ── Register (selected plan/interval only)
├── Resources ── guides + eligible blog articles
└── Company / Security / approved legal pages

Every detail page
├── parent hub
├── one or two adjacent, relevant pages
├── Pricing when commercial evaluation is useful
└── Register trial, or Dashboard when authenticated
```

Marketing pages never link to a guessed tenant slug or secure action. Booking
pages never acquire the marketing navigation; they retain task-focused recovery
and tenant context.

## Conversion contract

- Anonymous primary label: **Start your trial** → `register`.
- Pricing label: **Choose Starter** / **Choose Pro** → `register` with only an
  allow-listed plan code and interval.
- Secondary labels describe the destination: **Explore features**, **View
  pricing**, **Read the guide**, or **Log in**.
- Authenticated users receive **Open dashboard** rather than another signup
  loop. A user without an active Business still enters the application-owned
  dashboard/onboarding resolver.
- No demo, contact, chat, newsletter, migration consultation, or sales callback
  CTA exists until an owned receiving and consent workflow is accepted.
- A registration click is not a trial conversion. `signup_completed` is owned
  by the verified, exactly-once Business/trial creation path.

## Content and proof policy

All claims are mapped in
[`content-and-claims.md`](content-and-claims.md). Screenshots use only synthetic
demo data, name the captured surface, reserve dimensions, and never imply a
customer relationship. Provider-dependent claims carry a sandbox/live
qualification. Legal and security copy distinguishes implemented controls from
independent certification.
