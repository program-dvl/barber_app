# Help Center implementation and coverage audit

Date: 2026-10-24. Requirements: FR-21, documenting existing FR-01–FR-20 behavior.
Decision: ADR-070. This is local implementation and isolated verification evidence;
no production deployment, live provider certification or general launch approval
is claimed. The pre-existing staged `.gitignore` changes were preserved.

## Delivered experience

- Public `/help`, 14 topic pages and 80 task-focused article pages, with customer
  language, prerequisites, real labels, outcomes, troubleshooting and related guides.
- Prominent search, typing suggestions, natural questions, synonyms, partial words,
  bounded typo matching and relevance ranking. Questions and customer data are not
  stored or sent to an external service.
- Shared authenticated Help disclosure with current-page suggestions; guarded
  real-screen links re-evaluate owned active membership, permissions, location
  readiness and plan capabilities. Multiple workspaces receive a choice; denied
  entries explain the restriction without exposing an unprotected destination.
- Genuine expandable screenshots, article contents/breadcrumbs, mobile layout,
  keyboard search and native-dialog Escape/focus return. No generated mockups.
- Yes/no article feedback with revision/session deduplication and an aggregate-only
  operator report. Negative feedback provides related guides, support and search.
- Public titles, descriptions, canonical URLs, TechArticle/breadcrumb data, sitemap
  inclusion and readable initial HTML without the optional SSR service or JavaScript.
  Search query pages, suggestion/feedback endpoints and authenticated handoffs are
  excluded from indexing. Existing booking-host business-slug routing is preserved.

## Audited coverage

| Topic | Guides |
| --- | ---: |
| Getting started and imports | 5 |
| Business, locations, hours, policy and publication | 7 |
| Team, access, services, schedules and activity | 11 |
| Services, categories, prices, add-ons and visibility | 7 |
| Online booking and appointment actions | 7 |
| Calendar and capacity | 3 |
| Walk-in operations | 3 |
| Clients, forms, files, consent, duplicates and privacy | 9 |
| Checkout, received payments, recovery, refunds and receipts | 6 |
| Retail availability and current limitations | 1 |
| Reports, calculations, comparison and exports | 5 |
| Client notifications, delivery and consent | 3 |
| Capacity subscriptions, invoices, credits and recovery | 8 |
| Personal account, sign-in, recovery and security | 5 |
| **Total** | **80** |

The detailed [setup inventory](setup-inventory.md) and
[operations inventory](operations-inventory.md) map real routes, Vue screens,
controllers/services, permissions, dependencies, requirement IDs and article
ownership. [coverage.json](coverage.json) enumerates every published guide,
application action, section count and media reference.

Internal `/admin` and `/platform` tools remain restricted operator surfaces, not
business-owner capabilities. The audit inspected their existing access boundaries
and retained runbooks; no public how-to publishes support impersonation, provider
configuration, private diagnostics, permission grants or financial correction
internals. Public marketing/legal/editorial pages retain their existing ownership;
Help references relevant policies without duplicating their legal content. Legacy
AI, roadmap/changelog writes, Jetstream teams, disabled API-token tools and Phase 2
loyalty/packages/payroll/clinical features are not advertised.

## Real screenshots and remaining visual gaps

The final capture produced **30 unmodified application screenshots** from isolated
synthetic businesses, integrated into **28 guides**. All 30 assets have published
references, alternatives/captions and verified dimensions/SHA-256 in
`content/help-media.json`. They total approximately 1.6 MiB in WebP; each is below
the 550 KiB image budget and loads lazily. Screenshots show actual fields, buttons,
menus and data; they are not illustrations or UI recreations.

Captured families: introduction/readiness; locations/hours/booking preferences/
preview; team directory/add-member/weekly coverage/Schedule/Access; service
catalogue/editor/assignments/rules; dashboard/Calendar/appointment creation; queue/
intake; client directory/profile; checkout/saved-payment controls; notifications/
history; reports; billing overview; public booking services; personal security.
[Capture results](capture-results.json) report no missing controls, page errors,
overflow or broken loaded images. Additional viewport captures and interactive
checks are in [browser evidence](browser/results.json).

Every guide is readable without pictures. Remaining **text-guided visual gaps**:
import preview/duplicates; staff invitation acceptance and access-change confirmation;
schedule impact confirmation/time-off editor; category/add-on detail controls;
client form/file/merge/privacy commands; receipt/refund dialogs; subscription invoice,
provider-return/recovery states; and authenticator/device-management controls.
Screens containing QR secrets, recovery codes, private links or real card/customer
information must never be published. These are explicit enhancement opportunities,
not missing assets disguised as screenshots. The validator currently reports zero
missing or stale published media references.

## Accuracy findings and preserved limits

- New purchases use one capacity subscription, with actual calculator/catalogue
  values; help does not offer retired Starter/Pro selection or duplicate prices.
- Checkout records amounts already received. Recording an external card/link
  payment does not charge a card or create a payment link. Unknown outcomes recover
  the same request, preserving immutable financial history.
- Public deposit-required booking still lacks the completed online payment UI;
  instructions disclose that it cannot confirm and direct clients to the business.
- Products can be imported through Business setup. A complete inventory editor,
  stock receipt/adjustment interface, standalone commission editor and cash-close
  mutation interface are not currently supported self-service screens.
- Team professional title, working location and login permissions are independent.
  Split shifts and dated exceptions are supported; overnight/travel/future-variant
  editing and advanced service-resource editing remain existing open decisions.
- Notifications use managed email/SMS preferences/history, not retired template
  builders, WhatsApp inboxes, branded-sender setup or marketing campaigns. Simulated
  outcomes and provider acceptance are distinct from confirmed client delivery.
- Privacy cases do not promise unapproved destructive retention execution. Real
  tax/region, payment/refund, delivery and operational launch gates remain open.
- Existing branding copy still refers to a legacy tier on one setup screen; articles
  describe the actual entitlement instead. No unrelated billing/UI behavior changed.

Independent review corrected a product-import contradiction, the New password
label, suggestion Retry-After behavior and no-JavaScript Help navigation. No
cross-tenant deep-link bypass or unsafe Markdown rendering was identified.

## Verification

[Recorded results](verification.txt): **822 backend tests passed / 8,847 assertions**,
30 existing intentional skips; **104 frontend tests passed**. The focused Help suite
has **37 tests / 323 assertions**, including 14 real customer-question relevance
cases, publication/media validation, sanitization, drafts/future exclusion, public
HTML, feedback and access denial/multiple-workspace tests. Production client/SSR
builds, scoped Pint, whitespace and **21 public route budgets** pass. A final
Help/SEO/crawl/privacy regression after the last content/fallback edits passes
**72 tests / 2,237 assertions**.

Browser checks: **42 responsive/journey records** at 320/390/768/1024/1440 widths,
plus **3 additional recovery checks**. Sampled index/category/article/search/billing
recovery pages have one heading/main, one canonical, no overflow, no broken images
and labelled buttons. Keyboard suggestions navigate to an article; expanded images
close on Escape and restore focus; contextual Help works on desktop/phone; an
owned-workspace choice is available; no-JavaScript content remains readable and
numbered. Real local feedback saves successfully. Injected 429s preserve work,
show countdowns, disable duplicate votes and stop suggestion requests while waiting.
A persistent suggestion failure stops repeated requests while full Search still
advances the user. Negative feedback offers a useful support path.
[Recovery records](browser/recovery-results.json) and [screens](browser/) retain proof.

[Search benchmark](search-benchmark.json): 13 fresh-catalogue searches across 80
articles measured approximately **9–13 ms locally** in one PHP process. This is not
an HTTP/load/production-topology certificate. Search is bounded by query/token/body
limits; no new dependency or external search cost was introduced. Reassess an index
when measured corpus growth or request latency warrants it.

Build retains existing DaisyUI `@property` optimization and font-reference warnings.
Browser verification is local Chromium, not independent WCAG certification or a
Safari/Firefox/Edge/physical-device matrix. No real payment, customer notification,
primary database change, provider call or production recovery was executed.

## Maintenance and rollout

The [maintenance runbook](maintenance.md) covers content review, schema, draft versus
published states, related/action keys, screenshots, validation, feedback and future
UI changes. `php artisan help:validate` is the publication gate; `help:feedback`
exposes only aggregate counts. Commit feature behavior, guides, media and tests in
the same change and review the real affected journey before publishing.

Deployment must include source content, manifest/images, backend/views and built
assets together, and apply the additive `help_article_feedback` migration before
feedback is enabled. No migration was applied to the primary application database
in this task. Verify deployed `/help`, metadata/sitemap, feedback and signed-out/
role-restricted handoffs after normal rollout. Only deployed evidence can establish
that the deployed experience works. General launch/provider/security gates remain.
