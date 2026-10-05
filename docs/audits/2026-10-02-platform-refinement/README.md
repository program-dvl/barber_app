# ClipperDesk platform refinement — 2 October 2026

This is a presentation refinement of existing capabilities under FR-01–FR-20,
ADR-042 and [the product experience contract](../../modules/product-experience.md).
It does not waive production release gates or enable deferred modules.

## Inventory and implementation

Reviewed 76 Vue page sources, 103 shared Vue layouts/components and 20 Blade
sources. [Page inventory](page-inventory.md) identifies rendered versus source-only
pages. [Route inventory](route-inventory.json) records all 294 registered routes,
including APIs, commands and vendor routes; those are not all user-facing pages.
The active route families were grouped by task and permission before refinement.

The shared system now uses semantic type and density tokens: 14px operational
body text, 12–14px metadata, 17px section headings, 24px page headings and 40px
desktop controls, increasing to at least 44px for touch. Public form inputs remain
16px. Root rem sizing remains 16px. No browser zoom, scale transform or blanket
font-size reduction is used. Compact operational pages and more spacious public
pages share controls, focus, states, colours and terminology.

Shared work covers page headers, proportional empty states, buttons/loading,
select placement and keyboard behaviour, labels/required fields, errors and
accessible descriptions, phone controls, modal focus/Escape/return, sidebar
scrolling, mobile menus, long names and avatar fallback. The Filament content
panel uses the local font, compact controls and a readable dark canvas without
the conflicting DaisyUI component defaults.

Appointment means the scheduled visit; booking means reserving it or the booking
page. Dashboard views are Appointment list, By staff and Now & next. Existing
stored preference keys and user-entered data retain their original values.
Payment recording, paid deposits, simulation, provider acceptance/delivery and
publication remain distinguishable. Audit changes are a scoped, read-only
whitelist of readable field names and meaningful values; immutable evidence is
not rewritten. Reports and receipts show currency and business-local times;
source references remain available through disclosure/exports.

## Browser coverage

The review used the in-app browser at normal zoom against an isolated SQLite
fixture with synthetic business/client/contact data and fake delivery providers.
No real business data, live payments, messages, account credentials or legally
binding submissions were changed. All four onboarding steps were inspected;
the final publish action was not executed. The synthetic database, credentials,
temporary text-size rule and review server were removed after verification.

| Surface | Rendered states and checks |
| --- | --- |
| Dashboard | Populated schedule, long client name, metrics, all three views, empty schedule, role filtering and compact metric rows |
| Calendar and appointments | Populated and empty days, staff/date views, filters, appointment creation dialog and details drawer; schedule dominates the page |
| Walk-in queue | Two waiting clients, compact rows, actions and reason dialogs |
| Clients | Directory, search, long names, add/edit drawer, server validation, first-invalid focus; profile tabs for history, preferences, forms, consent, notes, files, privacy and duplicate review |
| Services | Catalogue, service form and compact grouped fields |
| Team and availability | Staff listing, invitation/role fields, staff and availability drawers; no permission changes submitted |
| Checkout and sales | Empty states, appointment selection, manual payment review, completed synthetic cash sale and receipt; no external transaction |
| Reports | Empty and populated sale results, filters, totals, readable names/currency/local times and final printed summary |
| Activity | Search/filter/event presentation and scoped readable changes |
| Client notifications | Email/SMS forms, simulation status, sender limitations and essential policy guidance |
| Business setup | Business details, locations/hours, staff/services, booking rules, import controls, preview/readiness/publication states; four guided steps and field validation |
| Subscription and billing | Current trial, plan choices, payment/portal availability and truthful limitations |
| Account | Sign-in and error, registration display, password-recovery display, profile/avatar fallback, security controls and sign-out-other-devices dialog/Escape/focus return |
| Public booking | Service selection, times, client details, non-deposit review, expiry guidance; deposit-required review accurately blocked when payment UI is unavailable; tablet and mobile |
| Public self-service | Issued manage/reschedule link display, waitlist offer display, non-legal two-field intake submission and completed state, invalid/expired link states |
| Platform operations | All 11 navigation modules: overview, businesses, subscriptions, plans, payments, coupons, support access, failures, health, flags and audit events; existing placeholders remain explicit |
| Content administration | Article, changelog, launch-subscriber and roadmap lists; article form on mobile/tablet and dark-mode canvas |
| Public acquisition and legal | Home; ten feature/solution/use-case/resource/pricing/security/company/legal hubs; all 21 detail pages; two guides and article index; responsive headings/assets |

All 12 shop navigation modules were inspected at 1280×800 and 390×844. The
second responsive pass inspected all 12 at 768×1024 and 360×800 using rendered
DOM dimensions plus representative screenshots. No document-level horizontal
overflow or broken loaded images remained in those checks. Wide report tables
and calendars retain intentional labelled horizontal scrolling inside their
containers. All 12 modules were also checked at 360px with temporary 200% root
text enlargement; fixes covered minimum widths, labels, headers and grid children.
The temporary enlargement was removed. Public booking was also checked with
200% text, including the reflowing brand/header. The final metric-row adjustment
was checked on desktop and mobile after that pass.

Role fixtures covered owner, receptionist, stylist and accountant, each assigned
to the synthetic location. Stylist schedule/client/report scope and hidden
financial controls were inspected. Accountant finance/report/activity views
render without appointment controls. Receptionist navigation was rechecked after
the settings-link correction; it contains only permitted operational modules.
This visual coverage complements, and does not replace, server authorization and
tenant-isolation tests.

Keyboard checks covered select navigation, disabled options, viewport placement,
mobile navigation focus/Escape, appointment/client dialogs, first-invalid focus
and profile dialog focus restoration. Focus states, text contrast and touch sizes
were reviewed visually. This is not independent WCAG or screen-reader certification.

## Automated evidence

- Complete existing PHP suite: **321 passed, 28 intentional skips, 3,394 assertions**.
  Skips cover intentionally superseded boilerplate and environment-dependent
  checks; this is not live MySQL concurrency or provider certification.
- Frontend tests: **9 passed** (report time-zone/currency presentation and select
  keyboard/placement behaviour).
- Final client and SSR production builds pass on the installed Node 24 runtime.
- Frontsite asset budgets pass for **17 route entries**.
- Added/extended focused coverage protects audit payload privacy and tenant
  scoping, readable role/plan values, client-visible visit time versus cleanup
  capacity, printed report names/money, and receipt local time without changing
  the receipt snapshot/hash.
- Final whitespace and targeted PHP formatting checks were run. The full suite
  includes communications/account-email work already present in the checkout;
  unrelated existing changes were preserved.

## Second pass and evidence files

The second pass searched for the original opaque vocabulary and repeated status
copy, checked all route families, revisited role-specific links and enlarged-text
layouts, and corrected onboarding density/labels, misleading calendar-link action
names, notification simulation visibility, report references, print formatting,
profile labels/avatar fallback, and the dashboard metric layout.

Representative screenshots are saved alongside this audit. Start with
[desktop dashboard](dashboard-desktop.jpg), [mobile calendar](mobile-calendar.jpg),
[client validation](mobile-client-validation.jpg), [guided setup](mobile-onboarding-review.jpg),
[booking payment limitation](mobile-booking-deposit-limit.jpg),
[tablet booking](tablet-booking.jpg), [profile dialog](mobile-profile-dialog.jpg),
[printed report](report-print-desktop.jpg) and [receipt](receipt-desktop.jpg).
These contain synthetic data. Some earlier screenshots precede small later copy
refinements; the dashboard and printed report captures show the final versions.

## Specific remaining gaps

1. **OPEN-16: public deposit payment UI.** The API creates Stripe PaymentIntents,
   but the public client has no complete payment element/return/status recovery.
   The server correctly requires payment evidence. The UI now shows the amount
   due and a clear contact-business limitation, without a false confirmation or
   pay-later bypass. Engineering/Finance must complete and certify this journey
   before enabling deposit-required self-service booking in production.
2. No live Stripe subscription/deposit/refund, SMS, email-provider delivery,
   public HTTPS callback or actual-device notification was exercised. Existing
   regionalisation, legal and production gates remain open.
3. Password reset, email verification, two-factor challenge/recovery, staff
   invitation acceptance, first-location activation and billing-provider return
   states were source-reviewed but not rendered with live valid tokens/provider
   sessions. Account security changes and destructive actions were not executed.
4. Published article detail and the other three content create/edit forms were
   source-reviewed; no publication-ready content fixture was available. Legacy
   Jetstream teams/API keys/roadmap/changelog/coming-soon pages, inventory and
   deferred shop modules stay quarantined/source-only. Invoice PDF source was
   reviewed; generated PDF pagination was not visually certified.
5. The representative states above do not cover every populated data combination,
   every validation message or every dialog branch. Full Chrome/Safari/Edge/Firefox,
   physical-device, screen-reader and independent WCAG checks remain release work.
   OPEN-14 missing domain-module specifications remain unresolved.
