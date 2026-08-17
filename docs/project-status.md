# Project status

Status date: 2026-08-16

## Current state

The repository is a Larafast boilerplate being adopted as the base for Good
Hours, a barber shop and salon management SaaS. The Phase 1 product is
documented and its reusable product-shell, permanent identity, and tenant/access
foundation plus FR-01 subscription/entitlement, FR-02 through FR-05 business
configuration, the FR-06/FR-07 availability/hold/booking-commit core, and the
FR-06/FR-08 operational calendar, Appointment lifecycle, and walk-in queue are
implemented. FR-09/FR-10 public booking, secure self-service, and waitlist plus
FR-11/FR-12 Client CRM, protected context, forms, consent, attachments,
duplicates, and privacy cases are also implemented. FR-13 application-owned
email/WhatsApp communications, local-time reminders, consent/suppression,
delivery evidence, callbacks, diagnostics, and safe replay are implemented.
FR-14/FR-15 commerce and FR-16 through FR-18 Inventory, Commission/Tip,
dashboard, reporting, export, and Phase 1 metric instrumentation are implemented
and locally verified. FR-20 platform administration and the support-grant
workflow are implemented and locally verified.

## Prompt 13 launch-readiness decision

**NO-GO for limited paid launch and general availability.** The complete
versioned evidence is in
[`release/2026-08-16-limited-paid-launch/`](release/2026-08-16-limited-paid-launch/README.md).
Local regression, dedicated MySQL concurrency, production builds, dependency
advisory remediation, a production-mode Chromium smoke pass, and a synthetic
MySQL dump/restore pass. They do not replace production provider, topology,
legal, accessibility, browser-matrix, monitoring, or operational sign-off.

Prompt 13 hardening added HTTP correlation/security/no-store controls, updated
the PHP/frontend dependency locks to zero known advisories at the dated lookup,
made the demo tenant's trial/subscription journey complete, and restored the
Checkout & Sales screen to the authenticated application shell. Final local
suite: **201 passed, 1,450 assertions, 28 intentional skips in 17.27 seconds**. Dedicated MySQL:
**12 passed, 48 assertions**; availability search 212.75 ms/786 queries,
booking commit 20.79 ms/63 queries, and 32-event calendar 9.52 ms/9 queries.

Critical launch blockers are live Paddle and Stripe certification, OPEN-10
retention/privacy authority, India legal/accounting review, and production
backup/RPO/RTO/DR/rollback evidence. High blockers are external penetration
testing plus upload malware scanning, target-topology load/burst evidence,
production observability/on-call/status operation, Resend/Twilio certification,
and independent WCAG/supported-browser verification. There are no approved
risk waivers and no named human sign-offs.

Documentation foundation:

- [x] Complete Word PRD reviewed, including all 32 rendered pages
- [x] Complete PRD transcribed to canonical Markdown
- [x] Product, architecture, domain, quality, module, and roadmap documents
  initialized
- [x] Dependency-aware implementation prompts prepared
- [x] Good Hours name, mark, voice, palette, typography, domain direction, and
  outbound-message identity accepted in ADR-011
- [ ] Launch country, currency, tax posture, and privacy regime confirmed
- [x] Communications locale and mobile channel resolved by ADR-019 as
  India/`en-IN`, Resend email, and Twilio WhatsApp; broader India tax/privacy/
  receipt approval remains open
- [x] Larafast reuse and removal audit completed; recommendations await approval
- [x] Phase 1 implementation started with the product-shell foundation

Implemented product-shell foundation:

- [x] Authenticated responsive shop shell with every PRD Section 10 destination
- [x] Separate public booking and secure self-service shell
- [x] Separate, role-gated, visibly marked platform-administration shell
- [x] Semantic tokens and reusable page, action, form, table, card, dialog,
  toast, skeleton, empty, loading, error, success, and destructive patterns
- [x] Focus-visible, reduced-motion, labelled landmarks, mobile focus-managed
  navigation, and 44px touch-target conventions
- [x] Honest requirement-linked placeholders with no fake salon workflow data
- [x] Good Hours mark, Manrope/Newsreader type system, pine/oat/poppy palette,
  product voice, auth identity, shell branding, and public booking language
  integrated without changing domain workflow scope
- [x] Prompt 01–09 UI/UX adoption review completed against the canonical design
  guide: all existing user-facing Vue pages were reviewed; shared shells,
  dashboard, calendar, queue, billing, configuration, booking/self-service,
  Client CRM/forms/privacy, and platform-readiness surfaces were updated and
  browser-verified at desktop and representative mobile widths
- [x] Dashboard offers Command desk, Rhythm board, and Guided front desk views
  over the same tenant-scoped operational data, with a per-user device-local
  preference and no change to authorization or scheduling truth
- [ ] Prompt 09 has no dedicated business-facing communications settings or
  delivery-log Vue page; its implemented HTTP diagnostics and provider behavior
  remain backend/operations capabilities until a requirement promotes that UI
- [x] Preferred `getgoodhours.com` domain and sender-address system selected;
  acquisition, trademark clearance, and authenticated sending remain OPEN-11

Implemented tenancy, identity, access, and audit foundation:

- [x] Explicit Business tenant context and Business-owned Location lineage;
  shop URLs carry a Business public ULID and resolve active Membership before
  child bindings/policies
- [x] User, Membership, and StaffProfile separation with unambiguous factories
  and cross-Business/location database constraints
- [x] Owner, Manager, Receptionist, Barber/Stylist, and Accountant starter
  roles plus exact custom Business roles and direct Membership grants
- [x] Policy permissions for calendar all/own, Client view/contact/edit/note,
  sensitive notes, attachments, forms, merge, privacy, appointment deletion,
  discounts, refunds, revenue, commissions, inventory, settings, billing,
  staff, audit, and exports
- [x] Expiring, revocable, single-use, token-hashed invitations bound to email,
  Business, role, optional StaffProfile, and validated Locations
- [x] Membership removal denies the next request, deletes database sessions and
  applicable Sanctum tokens, rotates remember tokens, and writes audit evidence
- [x] Append-only application audit skeleton with actor/tenant/target/source,
  correlation, reason, before/after, and sensitive-key redaction
- [x] Tenant-aware job payload/middleware, private-file namespace, cache-key,
  search-envelope, and export-name conventions
- [x] Separate expiring platform roles; platform administration requires
  verified email plus confirmed TOTP and cannot enter a tenant without
  Membership
- [x] Magic-link and unrestricted social-login routes disabled; verified
  password identities, reset, session management, TOTP, and recovery codes
  retained
- [x] Plain test execution isolated to SQLite `:memory:`; legacy disabled Team
  tests now skip instead of failing
- [x] Tenant-isolation evidence matrix recorded in
  [`tenant-isolation-matrix.md`](tenant-isolation-matrix.md)

Implemented FR-01 subscription and entitlement foundation:

- [x] Paddle Billing is selectable for Good Hours SaaS subscriptions through
  the application-owned provider contract (ADR-021); Stripe historical
  evidence and adapter remain retained, not deleted
- [x] Product-approved Paddle sandbox catalog maps Starter (USD 50 monthly /
  USD 500 annual) and Pro (USD 100 monthly / USD 1,000 annual) to active
  provider prices and effective-dated, server-enforced entitlements
- [x] `billing:sync-paddle-catalog` keeps the two Paddle products and four
  recurring prices synchronized from one reviewed catalog definition: it is
  dry-run by default, safely creates effective-dated replacement prices when
  an amount changes, and retains prior provider/local evidence
- [x] Verified owner registration creates one locked registration intent and,
  only after verification, exactly one Business, Owner Membership, and dated
  trial even when the verification event is replayed
- [x] Business-owned normalized plans, monthly/annual effective prices,
  subscription state, append-only changes, invoices, payments, coupons,
  provider events, retry notices, usage, and entitlement overrides
- [x] Responsive owner billing surface for dated trial/renewal state, plan and
  interval selection, premium in-app review plus embedded Paddle inline
  checkout (no popup or hosted-page redirect), portal, saved-method evidence,
  invoices/payment history, cancellation, and reactivation; redesigned plan
  comparison makes price, annual savings, entitlements, renewal, and payment
  controls clear before checkout. The 2026-08-16 follow-up removed a duplicated
  legacy plan grid, corrected Paddle.js decimal totals (`100.00` no longer
  renders as `$1.00`), and retains an explicit completion result without using
  the browser result as paid-access evidence. Billing-capable owners now see
  plan/status in the application header; immediate prorated upgrades,
  same-interval renewal downgrades, dated period-end cancellation, cancellation
  reason capture, and undo-cancellation are presented as explicit confirmed
  actions. Paddle undo correctly clears `scheduled_change`
- [x] Paddle checkout detects placeholder credentials before any provider call,
  scopes the owner lookup to the correct Business, and presents a selected-plan
  loading/error state without exposing raw provider responses
- [x] Shared Paddle-account traffic is application-marked; signed events for a
  different SaaS are discarded before payload persistence, while Good Hours
  renewal events retain Business correlation through copied custom data.
  Paddle's QRxpress seller text is confirmed as account-level hosted checkout
  identity, not Good Hours application copy; changing it to Stylnexa requires
  an account-wide Paddle change or a separate seller account
- [x] Failed-renewal warning, retry/grace, read-only restriction, safe payment
  recovery, termination, and dated export-availability behavior
- [x] Feature and numeric entitlement catalog with server checks reusable by
  HTTP actions/APIs, domain services, jobs, and imports
- [x] Over-limit downgrades schedule for period end, snapshot usage/limits,
  preserve existing records, and deny only increasing operations after effect
- [x] Stripe and Paddle webhook signature verification, event ID/payload
  deduplication, provider-time ordering, failed-event replay, and scheduled
  reconciliation contracts
- [x] Existing Business roles are backfilled when a permission is introduced;
  missing permissions fail closed rather than raising a framework exception
- [x] Staff scheduling policy failures are returned as in-app form feedback;
  dashboard actions are permission-aware and the account profile renders
  without a tenant context
- [x] Legacy User/Cashier, Lemon Squeezy, and Paddle production routes/listeners
  disabled without deleting unknown legacy data
- [x] Provider audit recorded in
  [`audits/2026-08-11-subscription-provider-audit.md`](audits/2026-08-11-subscription-provider-audit.md)

Implemented FR-02 through FR-05 business configuration:

- [x] Resumable business-details, hours, services, staff, availability,
  booking-rules, import, preview, and publish workflow
- [x] Explicit readiness blockers and optional improvements, with no vague
  completion percentage
- [x] Locale, currency, IANA time zone, week start, tax posture, cancellation,
  terms/privacy, private branding, and globally unique booking-slug aliases
- [x] Location normal/special hours, holidays, closures, reusable resource
  quantities/hours/maintenance, and persisted affected-appointment previews
- [x] Service/add-on categories, active/processing/cleanup segments, price,
  tax, deposit, notice, visibility, eligibility, qualification, and resource
  rules
- [x] Staff profiles/invitations foundation, Location/service assignments,
  staff variants, split schedules, breaks, leave, sickness, temporary changes,
  and overlap/travel-impossible schedule rejection
- [x] Effective service and capacity resolvers plus immutable historical
  configuration snapshots
- [x] Tenant-aware CSV import jobs for clients, staff, services, and products
  with templates, mappings, validation/error preview, duplicate review,
  progress/count summaries, replay safety, and private error exports; reviewed
  Client rows now project into the authoritative CRM aggregate
- [x] Production-like Pine & Palm Studio demo reaches publishable readiness in
  a recorded 24 minutes through `GoodHoursDemoSeeder`
- [x] Booking-engine boundary frozen as the read-only
  `AvailabilityConfiguration` and `AppointmentImpactSource` contracts; no
  search, capacity hold, commit, or calendar behavior was introduced by Prompt
  04

Implemented FR-06/FR-07 availability and booking core:

- [x] One shared rule engine for Location/special hours/closures, interval,
  notice/advance, Service eligibility and immutable effective values, Staff
  qualification/schedule/break/leave/personal block/Location/travel overlap,
  Resource hours/maintenance/quantity/segment occupancy, Appointments, and
  unexpired Holds
- [x] Preferred Staff with explicit any-qualified fallback plus per-segment
  qualified-provider handoff and staff release during processing
- [x] Advisory bounded availability query and commit-time revalidation through
  the same engine
- [x] Stable replay-safe capacity Holds with deterministic expiry and
  stale-snapshot rejection at confirmation
- [x] Direct and Hold-based atomic Appointment commit including every Service
  Line, Segment, Resource Claim, immutable configuration snapshot, and initial
  status-history record
- [x] Deterministic MySQL Staff/Resource root locking, pooled quantity checks,
  half-open UTC overlap rules, five-attempt deadlock retry, and Business-scoped
  command idempotency
- [x] Scheduling-backed Appointment impact source for Location, Staff, Service,
  and Physical Resource changes
- [x] Domain error codes/messages do not disclose private break, leave,
  maintenance, Appointment, or schedule details
- [x] Architecture guard prevents current controllers, Filament, and Vue source
  from importing Appointment persistence instead of use-case commands

Implemented FR-06/FR-08 calendar and walk-in operations:

- [x] Authenticated today/day/week/staff calendar with Location, Staff, Service,
  and status filters plus printable daily schedule
- [x] Accessible text/border/icon cues for Appointment state, walk-ins, blocks,
  late work, and work in service; no status depends on color alone
- [x] Create/multi-service, move/drag confirmation, resize, reassign,
  add/remove service, duplicate, rebook, internal-note, block, cancel/no-show,
  and controlled lifecycle actions
- [x] Optimistic version checks and Business-scoped operation keys for replay
  and concurrent stale-edit rejection
- [x] Linked replacement Appointments preserve the terminal original and run
  every new time/provider/service claim through commit revalidation
- [x] Manager override is limited to notice/advance policy warnings and requires
  permission, acknowledgement, reason, append-only change evidence, and audit;
  capacity/integrity conflicts remain non-overridable
- [x] Evidence-based walk-in estimates, queue position/reasoned reorder,
  assignment, notification intent, safe Appointment conversion/service start,
  abandonment, and actual-wait history
- [x] Late arrival, service overrun, staff-unavailable, and unexpected-closure
  impact/recovery records with internal notification events
- [x] Every local front-desk time is parsed in the selected Location IANA time
  zone and stored/compared in UTC
- [x] Idempotent `FrontDeskDaySeeder` simulates 8 appointments, 2 waiting
  walk-ins, and 1 block across realistic statuses

Implemented FR-09/FR-10 public booking and waitlist:

- [x] Passwordless mobile Location, Service/add-on, any/preferred Staff,
  local-date/time, details, policy, consent, and confirmation journey
- [x] One authoritative availability/Hold/commit path with policy-version and
  commit-time capacity revalidation; no duplicate public booking rules
- [x] Correct effective price/duration/deposit presentation, explicit add-on
  lines, cancellation/terms/privacy snapshot, and fail-closed unconnected
  deposit state pending Prompt 10
- [x] Booking reference, downloadable calendar event, refresh-safe flow state,
  privacy-safe conversion events, and public rate limits
- [x] Hashed, expiring, purpose-bound Appointment links for view, reschedule,
  cancellation, rebook, contact, waitlist join/leave, and payment status
- [x] Shop controls for online availability, Staff-selection mode, exact/from
  pricing, new-client eligibility, cancellation cutoff, offer batch size, and
  link lifetime with versioned policy snapshots
- [x] Tenant-scoped waitlist preferences, active duplicate prevention,
  availability-backed matching, controlled batches, expiry, retained offer
  history, versioned leave, and atomic first-valid claim
- [x] Contact/cancel/reschedule changes revoke older secure links and return a
  fresh view link; references are never an authentication mechanism

Implemented FR-11/FR-12 Client CRM and consent:

- [x] Booking, walk-in conversion, and reviewed Client CSV import create or
  conservatively match a tenant-owned Client with searchable normalized
  identity and retained display values
- [x] Same-contact spelling variations create review candidates, never fuzzy
  automatic merges; exact same identity safely reuses one profile
- [x] Responsive role-filtered Client directory/profile with birthday,
  encrypted preferences, communication choices, preferred Staff/Services,
  referral, tenant-scoped tags, consent state, visit summary,
  Appointment/service/performer history, and explicit unavailable financial
  history until checkout owns it
- [x] Encrypted authored allergy, sensitivity, formula, hair/skin/treatment,
  patch-test, preference, warning, and general Notes with narrower sensitive
  permission and preserved deactivated-Staff attribution
- [x] Optimistic Client versions reject stale concurrent edits; contact changes
  preserve history and revoke future vulnerable Appointment links
- [x] Permissioned, previewed, reasoned Client merge locks both profiles,
  selects one survivor, preserves every current relationship/tag and immutable
  submission snapshot, retains the losing identity, and writes safe audit
  evidence
- [x] Staff-facing form builder, six published starter forms, Service
  association, immutable template versions, pre-Appointment request/due state,
  Calendar/profile completion cues, and hashed expiring one-use public links
- [x] Immutable exact form wording/fields, encrypted answers/signature/identity,
  Client, Appointment, version, and submission-time evidence; later publishing
  cannot rewrite prior submissions
- [x] Tenant-private JPEG/PNG/PDF attachments and before/after images with MIME,
  size, SHA-256, visibility, retention class, and revocable expiring access
- [x] Tracked export, correction, Consent withdrawal, and
  deletion/anonymisation review cases with deadlines, reviewer/result/audit,
  private minimized export artifacts, and link rotation
- [x] ADR-018 safely bounds unresolved OPEN-02/OPEN-10: destructive processing
  enters `blocked_policy`, records retained-data evidence, and applies no
  deletion/anonymisation until launch policy authorizes an executor

Implemented FR-13 reliable communications:

- [x] ADR-019 resolves OPEN-06 and the communications portion of OPEN-02:
  India/`en-IN` is the launch fallback, email uses Resend, and the approved
  mobile channel is Twilio WhatsApp behind application-owned contracts
- [x] Versioned tenant templates cover confirmation, pending, approval,
  rejection, reminders, changes, cancellation, deposits, receipts, waitlist,
  queue, feedback, and rebooking with allow-listed variables, validation,
  preview, fallbacks, locale fallback, and an approved WhatsApp Content SID gate
- [x] Operational events converge on one immutable intent and at most one
  encrypted-recipient message per event/channel/recipient; bounded attempts
  reuse the stable provider idempotency key
- [x] Reminder offsets and overnight quiet hours resolve in the governing IANA
  time zone before UTC persistence and never push a reminder past its
  Appointment; cancellation/reschedule and consent changes are checked
  immediately before provider access
- [x] Transactional/legal basis remains distinct from explicit marketing
  consent; WhatsApp always requires channel opt-in; unsubscribe, marketing/all
  suppression, invalid destination, bounce, and complaint paths are durable
- [x] Tenant-aware queued jobs carry Business and correlation UUID; provider
  callbacks verify signatures, deduplicate event IDs/payload hashes, and ignore
  older states that would rewind delivery
- [x] Client/source history, privacy export, and merge registries include
  content-minimized communication evidence; support diagnostics omit destination
  and content and replay only through a bounded, reasoned, audited idempotent
  tenant-settings path without bypassing the pending ADR-009 support grant
- [x] Purpose-bound short-lived action records are revocable/consumable and
  reconstruct signed URLs without storing raw bearer tokens

Implemented FR-16 through FR-18 management foundation:

- [x] Tenant Product Category/Product catalog with SKU/barcode, integer-minor
  sale/cost/tax inputs, status, aggregate and Location stock, low-stock
  threshold, exact version-1 CSV import/export, and row-level rollback/errors
- [x] Locked, replay-safe stock receipt, reasoned manual adjustment, completed
  Sale deduction, and refund/void `restock`/`write_off`/`customer_keeps`
  disposition through an append-only before/after movement ledger
- [x] Effective-dated Service percentage, Product percentage, and fixed-Service
  rules plus discounted-line calculation, immutable earned/reversal/adjustment
  Commission and Tip entries, Staff statement, and queued payroll export
- [x] Permission/location/staff-filtered today dashboard with every visible
  count/value linked to its source report and explicit UTC freshness/Location
  IANA time zone
- [x] Complete Phase 1 report catalog with shared date/Location/Staff/Service/
  status filters, stable prior-period comparison, source IDs/drill links,
  queued CSV, printable summaries, and payment/Sale reconciliation
- [x] Central metric version `1.0.0` plus allow-listed idempotent acquisition,
  activation, booking, reliability, revenue-protection, operations, retention,
  usage, and support event definitions that reject direct contact values
- [x] Tenant-aware export jobs persist and re-authorize the requesting
  Membership, Location/Staff scope, and normalized filters before writing a
  private hashed artifact

## Verified repository baseline

- Backend manifest targets PHP 8.3 and Laravel 13.
- `composer.lock` currently records Laravel 13.25.0, Filament 5.7.6, Inertia
  Laravel 2.0.25, Livewire 4.4.0, Cashier 16.7.0, and Ziggy 2.6.3.
- Frontend lock data records Vue 3.5.34, Inertia Vue 3.6.1, Tailwind CSS
  4.1.14, DaisyUI 5.1.27, and Vite 8.2.1. Frontend tooling requires Node
  22.12 or newer; `.nvmrc` pins the verified Node 24.19.0 baseline.
- Existing boilerplate includes Jetstream Team code, but Team/invitation and API
  token features are disabled. Registration no longer creates a personal Team;
  retained Team tests skip explicitly while the disabled legacy schema awaits a
  separately reviewed cleanup/backfill decision.
- Authentication, social login, magic links, multiple billing integrations,
  invoices, a Filament admin panel, global roles/permissions, marketing pages,
  blog, roadmap, and changelog are present but not approved as salon behavior.
- Booted route evidence reports 202 routes. The repository has 38 migration
  files and 35 feature-test files. Counts include the FR-13 provider callbacks,
  template/settings, support diagnostic/replay, and action-link surfaces.
- The configured local environment uses MySQL, sync queues, database sessions,
  file cache, and UTC. The public storage link and Sentry DSN are absent.
- `User` implements `MustVerifyEmail`; Spatie Business scoping is enabled on
  Membership-backed roles; Business, Location, Membership, StaffProfile,
  StaffInvitation, AuditEvent, and legacy Invoice policies are registered.
  Normalized Business billing is active. Unapproved billing/provider resources
  and tables remain quarantined legacy boilerplate.
- The real `.env` is ignored and untracked. The tracked examples contain empty,
  null, variable, or obvious provider placeholders, and a tracked-source scan
  found no recognized live token pattern.
- The repository's `CLAUDE.md` package-version summary is stale and must not be
  used as version authority.

## Verification baseline (2026-08-10)

- `php artisan about`: application boots on Laravel 13.8.0 and PHP 8.4.8.
- Safe isolated test command using in-memory SQLite: 41 passed, 10 failed, 6
  skipped, 117 assertions in 5.50 seconds. All failures are Team tests while
  Team support is disabled. API-token and Team-invitation tests skip because
  those features are disabled.
- Plain `php artisan test` was not run because `.env.testing` is absent,
  `phpunit.xml` does not override the database, local configuration points to
  MySQL, and most feature tests use `RefreshDatabase`. This is a data-loss risk
  until test isolation is enforced.
- `vendor/bin/pint --test` fails with 19 files requiring formatting. The audit
  did not rewrite them.
- `npm run build` passes both client and SSR builds (1,194 client modules and 98
  SSR modules). CSS optimization emits two warnings for the `@property` rule.
- `composer audit --locked --no-dev --format=plain` reports 45 advisories
  affecting 19 packages, including high-severity findings.
- `npm audit --json` reports 19 vulnerable packages: 2 critical, 14 high, and 3
  moderate. Fixes are reported available.
- Full evidence and classifications are in
  [`audits/2026-08-10-larafast-adoption-audit.md`](audits/2026-08-10-larafast-adoption-audit.md).

### Product-shell verification (2026-08-10)

- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
  tests/Feature/ProductShellTest.php`: 17 passed, 194 assertions in 0.63s.
  Evidence covers public pages, authenticated route boundaries, all ten shop
  placeholders, unknown-route denial, ordinary-user platform denial,
  platform-admin access, the reusable pattern reference, semantic focus tokens,
  and reduced-motion CSS.
- `npm run build`: client and SSR builds passed; client transformed 1,205
  modules and SSR transformed 112 modules. DaisyUI still emits the two known
  CSS optimizer warnings for its `@property --radialprogress` rule.
- Targeted Pint run over the changed PHP middleware, routes, and test passed
  after formatting `routes/web.php`.
- Browser checks passed at 360x800, 768x900, and 1440x950. Shop and public
  pages reported no horizontal overflow at 360px. No visible action in the
  360px shop/public checks was smaller than 44x44; the corrected platform menu
  trigger measured 44x44.
- Both shop and platform mobile drawers moved focus to the first destination,
  exposed dialog/modal semantics, closed on Escape, and returned focus to the
  trigger. Keyboard focus rendered a 3px `#1677cc` solid outline with 3px
  offset. The loaded stylesheet exposed the reduced-motion media rule.
- Browser-computed contrast samples were 15.52:1 for strong heading text,
  5.95:1 for primary semantic text, and 4.8:1 for muted footer text. Public
  controls had accessible names, main/header/footer landmarks were present,
  and the final browser run reported zero console errors.
- The interface-pattern browser check verified destructive-dialog labelling,
  initial focus on Cancel, Escape close, and a polite success toast with a
  labelled dismiss button. The local PHP 8.4 preview server emitted the known
  vendor deprecation for `Tightenco\Ziggy\Ziggy::__construct()` nullable typing;
  it did not affect route rendering.
- Screenshot evidence: [`evidence/product-shell/`](evidence/product-shell/)
  contains public booking at 360px, shop dashboard at 360px, 768px, and 1440px,
  platform administration at 1440px, and the interface-pattern reference at
  1440px.

### Good Hours identity verification (2026-08-11)

- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
  tests/Feature/ProductShellTest.php`: 18 passed, 207 assertions in 0.88s.
  Coverage now includes the Good Hours name, tagline, real mark, public and
  authenticated identity, platform distinction, and permanent brand tokens.
- `npm run build`: client and SSR builds passed after the brand integration;
  client transformed 1,210 modules and SSR transformed 113 modules. The build
  retains public font URLs for runtime resolution and emits the two existing
  DaisyUI optimizer warnings for `@property --radialprogress`.
- Browser checks passed for public booking at 360x800, the shop shell at
  360x800, 768x900, and 1488x1058, and platform administration at 1440x950.
  No checked viewport had page-level horizontal overflow. Mobile actions met
  the 44px target, and the desktop identity capture ran at device-pixel ratio
  1.
- The shop navigation drawer moved focus into the dialog, closed on Escape,
  and returned focus to its trigger. Visible keyboard focus measured a 3px
  solid `rgb(11, 110, 173)` outline with a 3px offset. The reduced-motion rule
  remained present in the loaded stylesheet.
- Public-shell computed contrast was 14.72:1 for the strong heading, 5.01:1
  for muted footer text, and 5.24:1 for white primary-action text. The checked
  public state exposed header, main, and footer landmarks, no unnamed controls,
  and zero browser-console errors.
- Side-by-side full-view and focused-region comparisons passed. Exact source,
  implementation, viewport, interaction, responsive, accessibility, and
  comparison-history evidence is recorded in the project-root
  [`design-qa.md`](../design-qa.md). Supporting captures are under
  [`evidence/product-shell/`](evidence/product-shell/).

### Tenancy, identity, access, and audit verification (2026-08-11)

- `php artisan test`: 78 passed, 16 skipped, 410 assertions in 6.33 seconds.
  The skips are explicit disabled-feature coverage for Jetstream Teams/team
  invitations, user-created Sanctum tokens, and configuration-dependent public
  registration.
- Platform-access tests cover the five starter-role matrices, different roles
  for one User in two Businesses, exact custom roles, direct grants, assigned
  Locations, same-tenant access, unauthorized roles, cross-tenant identifiers,
  invitation expiry/revocation/replay, former-employee sessions and tokens,
  private files, jobs, cache keys, search envelopes, exports, platform access,
  and append-only/redacted audit events.
- `vendor/bin/pint --test` passes for the full repository.
- `npm run build` passes client and SSR production builds (1,204 client modules
  and 111 SSR modules). The two known DaisyUI `@property --radialprogress`
  optimizer warnings remain non-fatal.
- Fresh migrations execute in the isolated SQLite `:memory:` suite. A separate
  file-backed SQLite check confirmed the platform-access migration rolls back
  cleanly; continuing the repository-wide rollback then reaches an unrelated
  pre-existing Cashier migration whose indexed `stripe_id` column cannot be
  dropped in its current order. MySQL 8 foundation migration compatibility is
  now verified below; concurrency/locking verification remains required before
  any scheduling or payment concurrency claim.
- The full control-to-test mapping is recorded in
  [`tenant-isolation-matrix.md`](tenant-isolation-matrix.md).

### Subscription and entitlement verification (2026-08-11)

- `php artisan test`: 95 passed, 16 skipped, 530 assertions in 7.67 seconds.
  Skips remain the explicit disabled Jetstream Team, Team invitation,
  user-created API-token, and configuration-branch coverage.
- `php artisan test tests/Feature/Billing/SubscriptionLifecycleTest.php`: 15
  passed, 88 assertions in 0.82 seconds. Coverage includes verified exactly-once
  onboarding, monthly/annual conversion, saved-method evidence, cancellation,
  support cancellation, reactivation, retry/grace/restriction/recovery,
  over-limit downgrade, HTTP/job/import checks, effective dating/audit,
  duplicate/out-of-order events, installed Stripe SDK signature verification,
  invoices/payments, cross-tenant denial, coupons, export window, notices, and
  reconciliation replay.
- `php artisan test tests/Feature/RegistrationTest.php
  tests/Feature/Database/FoundationMigrationRecoveryTest.php`: 4 passed, 1
  skipped, 36 assertions in 1.00 seconds. This covers the complete register,
  pending-intent, signed-verification, exactly-once Business/Owner/trial,
  logout, and credential sign-in flow plus resumable partial-DDL recovery.
- `vendor/bin/pint --test` passes for the full repository.
- `npm run build` passes client and SSR production builds (1,205 client modules
  and 112 SSR modules). The existing DaisyUI `@property --radialprogress`
  optimizer warning and runtime-resolved self-hosted font URLs remain non-fatal.
- Fresh SQLite `:memory:` migrations pass with the normalized billing schema and
  seeded trial entitlement definitions. On MySQL 8.0.42, the repaired
  foundation migrations resumed the existing partially applied schema without
  dropping data: platform access completed in 187.58 ms and subscription
  entitlements completed in 165.09 ms after a MySQL-safe coupon index-name fix.
  Both migrations now report `Ran`.
- A rollback-only MySQL smoke test returned `intent=yes`, `business=yes`,
  `subscription=trialing`, and `login=yes`; no smoke-test tenant was retained.
  Concurrency/locking verification remains required before a production
  scheduling or payment concurrency claim.
- No Stripe sandbox credentials exist locally. Evidence therefore covers the
  installed SDK signature contract and deterministic application contract, not
  live Checkout, billing portal, subscription schedule, test-clock, mail, tax,
  or settlement behavior. Those remain paid-launch gates.

### Business-configuration verification (2026-08-11)

- `php artisan test`: 110 passed, 16 skipped, 612 assertions in 8.40 seconds.
  Skips remain the explicit disabled Jetstream Team, Team invitation,
  user-created API-token, and configuration-branch coverage.
- `php artisan test
  tests/Feature/BusinessConfiguration/ConfigurationFoundationTest.php`: 15
  passed, 82 assertions. Evidence covers explicit readiness, the 24-minute
  demo, and the guided first-bookable-path interface; permission and tenant denial; slug uniqueness/aliases; local/DST hours,
  closures, and resource maintenance; split and impossible schedules;
  staff-specific effective values and immutable snapshots; add-on resource
  quantity; persisted Appointment-impact adapter results and published-change
  gating; staff and resource window/exception resolution; import replay, malformed files, private errors, duplicates, and
  entitlement denial; and the exact booking-facing contract.
- Fresh SQLite `:memory:` migration passed for the configuration schema,
  including composite tenant foreign keys. No MySQL booking-concurrency claim
  is made because this prompt creates no capacity-hold or Appointment commit
  tables; Prompt 05 must supply MySQL 8 locking evidence for those operations.
- `vendor/bin/pint --test` passes for the full repository.
- `npm run build` passes client and SSR builds (1,209 client modules and 113 SSR
  modules). The known DaisyUI `@property --radialprogress` warning and
  runtime-resolved self-hosted font URLs remain non-fatal.
- `GoodHoursDemoSeeder` is idempotent and supplies one active Location, six
  local opening days, three barber-chair units, a segmented/deposited/taxed
  Service, a qualified scheduled Staff Profile, reviewed preview evidence, and
  a 24-minute publish record. Its owner password is random and not a reusable
  shared credential.

### Availability and booking-core verification (2026-08-11)

- `php artisan test
  tests/Feature/SchedulingOperations/BookingEngineTest.php`: 12 passed, 69
  assertions in 1.00 seconds on isolated SQLite. Coverage includes shared
  search/commit policy, preferred/any Staff, notice/advance, replay, expiry,
  Hold confirmation/staleness, Resource quantity, staff-free/resource-busy,
  processing occupancy, segment provider handoff, all-or-none rollback,
  closure/break/maintenance, private error content, travel overlap, stale
  search, explicit add-on lines, segmented Staff duration variants,
  spring and repeated-hour fall daylight-saving changes, and local midnight.
- `php artisan test
  tests/Architecture/SchedulingWriteBoundaryTest.php`: 1 passed, 1,368
  assertions in 0.06 seconds across current controller, Filament, and Vue
  source files.
- MySQL concurrency evidence ran on local MySQL 8.0.42/InnoDB in the dedicated
  `barber_app_booking_test` database, never the development `barber_app`
  database. Seven test cases with 29 assertions passed. Independent forked PHP
  workers purged inherited connections, opened separate PDO connections, and
  waited behind a pipe start barrier. Scenarios cover online-online,
  online-reception, staff-free/resource-busy, quantity two with three
  contenders, shared multi-segment handoff, Hold expiry versus commit, exact
  duplicate replay, and stale search.
- Representative local MySQL measurements: a one-day any-qualified query
  returned 20 slots in 210.52 ms using 746 statements; a direct atomic
  Staff/Resource commit completed in 15.53 ms using 55 statements. Both meet
  provisional response targets locally. The search statement count is explicit
  read-amplification debt, not launch load evidence.
- Fresh MySQL migrations exposed and corrected MySQL's 64-character identifier
  limit in one platform invitation foreign key and business-configuration
  import/foreign-key names. All scheduling foreign keys/indexes use explicit
  short names. Fresh migration then completed through all 34 migrations.
- `php artisan test`: 122 passed, 23 skipped, 681 assertions in 9.21 seconds.
  The seven MySQL cases skip in the ordinary isolated suite and were run
  separately with the explicit dedicated-database gate above. Existing skips
  remain disabled Jetstream Team/invitation/API-token and configuration-branch
  coverage.
- `vendor/bin/pint --test` passes for the full repository.
- `npm run build` passes client and SSR builds (1,210 client modules and 113 SSR
  modules). The existing DaisyUI `@property --radialprogress` warning and
  runtime-resolved self-hosted font URLs remain non-fatal.

### Calendar and walk-in verification (2026-08-12)

- `php artisan test
  tests/Feature/SchedulingOperations/CalendarWalkInOperationsTest.php`: 7
  passed, 93 assertions. Coverage includes controlled lifecycle/replay/stale
  editing, linked replacement/override, blocks/calendar cues/performance,
  walk-in estimate/reorder/notify/collision/start, operational exceptions and
  closure recovery, role/cross-tenant HTTP denial, Location-time conversion,
  and an idempotent production-like front-desk day.
- The dedicated MySQL 8.0.42/InnoDB suite now passes 9 tests and 38 assertions,
  including two independent processes racing a stale lifecycle version and a
  32-Appointment calendar projection. One edit succeeds and one fails with
  `STALE_APPOINTMENT`; the projection measured 7.08 ms and 9 SQL statements.
  Availability measured 204.68 ms/786 statements and direct commit measured
  15.86 ms/57 statements in that run.
- Responsive browser checks used the seeded 8-appointment, 2-walk-in, 1-block
  day at 1440x950, 768x900, and 360x800. Calendar and queue had no page-level
  horizontal overflow and no visible control under 44px. Dialogs initially
  focus Cancel, close on Escape, and return focus to their opener. Non-color
  status cues, representative 5.01:1-or-better text contrast, and an empty
  browser error/warning log were verified.
- `npm run build` passes client and SSR production builds (1,211 client modules
  and 115 SSR modules). The existing DaisyUI `@property --radialprogress`
  warning and runtime-resolved self-hosted font URLs remain non-fatal.
- `php artisan test`: 127 passed, 25 explicitly skipped, 746 assertions in
  11.58 seconds. `vendor/bin/pint --test` passes for the full repository.
  Later prompt evidence must not weaken these operational tests.

### Public booking and waitlist verification (2026-08-12)

- `tests/Feature/PublicBooking/PublicBookingWaitlistTest.php`: 9 tests cover
  passwordless Hold/confirmation and replay, policy drift, elapsed observation
  time, required-deposit fail-closed behavior, expiring/revoked/purpose-bound
  links, contact/cancel versioning, waitlist deduplication/batching/claim,
  secure join/leave, tenant/identifier tampering, shop policy controls, and
  offer expiry/requeue, and public rate limiting.
- Public plus booking/calendar regressions pass 28 tests and 252 assertions.
  The production build passes client and SSR compilation with only the existing
  DaisyUI `@property` and runtime font-resolution warnings.
- The dedicated MySQL 8.0.42/InnoDB suite passes 11 tests and 45 assertions.
  It proves MySQL JSON key-order normalization cannot falsely stale an unchanged
  Hold and two parallel waitlist claimants produce exactly one confirmed
  Appointment. The latest local run measured availability at 231.09 ms/786
  statements, direct commit at 17.75 ms/57 statements, and a 32-event calendar
  at 9.00 ms/9 statements.
- In-app browser QA completed a real Pine & Palm journey from Service selection
  through confirmation and secure self-service. The final page exposed the
  booking reference, calendar link, and every purpose-bound action; the checked
  1280x720 surface had no horizontal overflow, unnamed visible controls, or
  target under 44px, and no console error/warning. Existing product-shell and
  calendar evidence cover the shared responsive shell at 360px and 768px;
  external Safari/Firefox device-lab certification remains a launch-hardening
  gate.
- Browser testing discovered and fixed snapshot comparison that treated
  observation time and MySQL JSON object key order as policy changes. Regression
  evidence now covers elapsed confirmation time and MySQL-native JSON storage.
- `php artisan test`: 136 passed, 27 explicitly skipped, 836 assertions in
  12.79 seconds. The skipped MySQL cases passed separately above; remaining
  skips are deliberate disabled-feature/configuration branches.

### Client CRM and consent verification (2026-08-12)

- `tests/Feature/ClientRecords/ClientCrmConsentTest.php`: 8 tests and 106
  assertions pass. Coverage includes exact matching and same-mobile spelling
  candidates; encrypted, versioned preference edits and Appointment-link
  revocation; tenant-scoped tags/preferred Staff/Services; merge preview and
  preservation of Appointment, Consent, Note, Form Request, immutable
  Submission, Attachment, Privacy Request, tag, and preferred-Service
  relationships; authenticated form-builder publishing, template changes after
  submission, Calendar completion state, and one-use form links;
  sensitive-role/access-audit and cross-tenant denial; private and expired file
  links; and all four privacy workflows with export-content
  inspection/minimisation.
- Client-import, public-booking, scheduling, calendar/walk-in, platform-access,
  and architecture regressions pass together with the new Client suite. Public
  booking assertions prove Appointment→Client creation and contact update of
  the same identity; configuration assertions prove reviewed imports create
  authoritative CRM profiles.
- The dedicated MySQL 8.0.42/InnoDB suite passes 12 cases and 48 assertions:
  all established booking/calendar/waitlist races remain safe, and two
  independent Client writers starting at version 1 yield exactly one version-2
  update while the other receives the stale-edit result. Fresh MySQL migration
  includes every Client composite foreign key, preferred-Service pivot, and six
  starter forms. The latest representative run measured search at 247.50 ms /
  786 statements, commit at 21.51 ms / 61 statements, and a 32-event calendar
  at 10.82 ms / 9 statements after adding form-completion projection.
- In-app browser QA completed the authenticated directory/profile, stored a
  synthetic protected allergy note, created an Appointment-bound secure form
  request, and completed its one-use public form. The 390×844 profile measured
  390px content/viewport width with no horizontal overflow; its back action was
  44px, changed selects/file inputs were named, and the responsive shop
  navigation remained usable. Browser QA discovered and fixed runtime page
  context and starter-publish default issues before completion. The final
  builder pass loaded the Allergy declaration’s two fields, exposed every
  supported answer type and named control, and retained a 1280px/1280px
  viewport/content width without horizontal overflow.
- `php artisan test`: 143 passed, 28 explicitly skipped, 932 assertions in
  14.48 seconds. Every MySQL-marked case passed separately above; all remaining
  skips are deliberate disabled-feature/configuration branches.
- `npm run build` passes client and SSR production builds. Only the existing
  non-fatal DaisyUI `@property --radialprogress` optimizer and runtime font URL
  warnings remain. Formatter, route, migration-status, and final diff checks
  also pass in the final Prompt 08 verification pass.

### Reliable communications verification (2026-08-14)

- `tests/Feature/Communications/ReliableCommunicationsTest.php`: 10 tests and
  83 assertions pass. Coverage includes duplicate event convergence across two
  channels, allow-listed/missing variables and safe fallbacks, cross-tenant
  template denial, quiet-hour boundaries, spring/fall DST, transactional/
  marketing/WhatsApp consent, unsubscribe/suppression, invalid destinations,
  consent change after queueing, cancelled reminders, bounded retry/outage
  recovery, terminal ambiguous WhatsApp handling, signed/duplicate/out-of-order
  callbacks, content-minimized
  diagnostics, bounded safe replay, revocable action links, and tenant/
  correlation jobs.
- The complete SQLite suite passes: 153 passed, 28 explicitly skipped, 1,015
  assertions in 14.32 seconds. Skips remain deliberate disabled legacy features
  and separately proven MySQL booking/concurrency cases.
- Resend/Twilio production credentials were not used. Provider sandbox sender,
  template, signature, callback-burst, and deliverability certification remain
  external Prompt 13 launch evidence, not claims made by local contract fakes.

### Money and commerce verification (2026-08-15)

- FR-14 and FR-15 now have an application-owned, append-only commerce ledger:
  deposit policy/snapshot, pending online payment intent, verified provider
  event inbox, immutable payment/refund/correction records, sale and source
  line snapshots, receipt reproduction, reconciliation tasks, and daily cash
  close. Appointment capacity remains a Hold until verified payment success;
  finalization failure creates a visible reconciliation task.
- `tests/Feature/MoneyCommerce/MoneyCommerceTest.php`: 5 tests prove
  integer-minor-unit inclusive-tax calculation, deposit plus cash/card split
  tender and exact replay, partial refund, receipt reproduction, excessive
  discount denial, reasoned cash variance, none/fixed/percentage/full/new/
  threshold/prior-no-show deposit policy, cross-tenant payment denial, manager
  waiver preservation, duplicate provider events, late failed event after
  success, and payment-success/missing-flow recovery task.
- Public booking now displays a frozen deposit/cancellation/fee snapshot and
  requires a completed deposit intent rather than failing as "not connected".
  The Stripe adapter uses a separate appointment webhook secret and does not
  treat browser return pages as payment evidence. Live Stripe test-mode and
  settlement reconciliation have not been run.
- ADR-020 resolves India/INR/en-IN calculation behavior and the appointment
  gateway/method set. Paddle is intentionally excluded from client salon
  payments because its public product is positioned for digital/SaaS commerce;
  the supplied Paddle key must be rotated outside the repository.

### Inventory, commissions, reporting, and metrics verification (2026-08-15)

- `tests/Feature/InventoryCommissionReporting/InventoryCommissionReportingTest.php`
  passes 8 tests / 93 assertions on both isolated SQLite and the dedicated
  MySQL 8/InnoDB `barber_app_booking_test` database. It covers exact-once Sale
  deduction/replay, restock and customer-keeps void/refund disposition, CSV
  opening stock/export, receipts, reasoned manual adjustment, discounted and
  fixed Commission, effective rule changes, Tip/refund offsets, manager
  adjustment, Staff statement, every report catalog key, local-midnight
  boundaries, role/Staff/Location limits, cross-tenant export denial,
  dashboard/report/source/payment reconciliation, filter parity, private queued
  CSV, printable totals, and instrumentation minimisation/idempotency.
- The dedicated MySQL realistic-volume case inserts 2,000 completed Sales and
  returns 2,000 drillable rows plus `collected_minor=1,800,000` in 27.37 ms
  using 8 SQL statements. The guard is 15 statements and 2.5 seconds. This is
  indexed local query evidence, not sustained/concurrent production load or
  target-hosting certification.
- The focused regression with `MoneyCommerceTest` passes 13 tests / 123
  assertions on SQLite. The complete repository suite passes 175 tests / 1,211
  assertions with 28 intentional skips in 15.36 seconds; `phpunit.xml` sets a
  256 MB process ceiling for the expanded suite. Client and SSR production
  builds pass with the known
  DaisyUI `@property` and runtime font-resolution warnings only.
- CSV and printable summary totals use the same normalized filters and report
  service as the screen. Export rows retain source IDs/drill values, version,
  freshness, time zone, SHA-256 content hash, requesting Membership, and
  Business/Location/Staff scope snapshot. Queue execution re-authorizes the
  active Membership before reading source records.

## Implemented FR-20 platform administration and support operations

- [x] Safe Business search/summaries cover owner verification, onboarding,
  plan, usage, trial/subscription, invoice, payment/failure counts, and
  privacy-safe activity volumes without message content, payment secrets,
  authentication data, or Client notes.
- [x] Explicit platform administrator, support operator, and security auditor
  capability matrix; every platform role requires verified email, confirmed
  TOTP, active assignment, 15-minute idle, and eight-hour absolute sessions.
- [x] Administrator-only Business activation/suspension/closure, trial
  extension, coupon assignment, plan change, immediate cancellation, feature
  flags, notices, and single-Business export initiation write reasoned audit
  evidence.
- [x] Verification and invitation resend use normal notification workflows;
  replacement invitations rotate the token and revoke prior pending evidence.
- [x] Platform-internal append-only account notes have explicit visibility and
  bounded retention dates.
- [x] Support access requires approval by a different administrator, one
  Business, ticket, reason, enumerated scopes, and expiry within four hours.
  Entry keeps the operator identity, uses separate support endpoints, never
  satisfies Membership middleware, shows a tenant banner, and ends on exit,
  supersession, expiry, or revocation with immutable audit evidence.
- [x] Failure summaries minimize billing/payment/notification/job content.
  Reviewed Stripe/Paddle/payment/communication replay uses a reason plus unique
  operation key and duplicate requests return the first replay result. Generic
  failed jobs remain inspection-only.
- [x] Queue, communication, webhook, reconciliation, and honest backup health
  summaries exist. Backup remains `not_configured`, not falsely healthy.
- [x] Three Business entries in fifteen minutes raise a high cross-tenant
  alert. Bulk/cross-tenant export input is rejected, raises a critical alert,
  and creates no export request; valid requests carry exactly one Business
  lineage snapshot.
- [x] Larafast Filament User/Role/Permission and legacy billing/catalog
  resources plus discovered legacy widgets were audited and quarantined from
  the approved platform workflow.
- [x] Focused SQLite evidence: `PlatformOperationsTest`, `TenantIsolationTest`,
  `AuditEventTest`, and `SubscriptionLifecycleTest` pass 47 tests / 268
  assertions. The complete repository suite passes 185 tests / 1,290
  assertions with 28 intentional disabled-feature/MySQL-integration skips in
  15.72 seconds. Client and SSR production builds pass with only the existing
  DaisyUI `@property` and runtime font-resolution warnings.

## Phase 1.5 public front-site

### Prompt 14 discovery and SEO audit

- [x] Every anonymous executable route was inventoried and classified as
  acquisition, tenant booking, transactional, authentication, legal, content,
  webhook, private application/admin, framework utility, or error behavior.
- [x] The existing Home, public shell, legacy pages, CTAs, claims, components,
  assets, metadata, schema, sitemap, robots, content rendering, instrumentation,
  accessibility, responsive behavior, and production bundle baseline were
  audited. No broad public-page implementation occurred in Prompt 14.
- [x] ADR-024 accepts a curated acquisition IA and explicit separation from
  tenant booking and secure/private tasks. Route, indexation, linking, content,
  CTA and legacy-route contracts are in [`frontsite/README.md`](frontsite/README.md).
- [x] The verified/qualified/remove/decision-needed claim and entity ledger is
  in [`frontsite/content-and-claims.md`](frontsite/content-and-claims.md).
- [x] Prompt 14 evidence and severity-ranked remediation ownership is in
  [`audits/2026-08-16-frontsite-discovery-seo-audit.md`](audits/2026-08-16-frontsite-discovery-seo-audit.md).
- [x] Focused baseline: 30 tests passed / 309 assertions with one intentional
  skip; Node v24.19.0 production client and SSR builds passed. The baseline
  mobile Home had no horizontal overflow but contained extensive Larafast,
  fabricated-proof, dead-link, metadata/schema, legal and content-safety
  publication blockers.
- [x] Prompts 15–27 now have implementation and verification evidence below.
- [x] OPEN-12 records the missing marketing analytics/provider/consent/
  retention authority. No third-party tracker was installed.

### Prompt 15 public design system and navigation

- [x] `MarketingLayout` now provides skip navigation plus semantic header,
  primary navigation, main and footer landmarks while remaining separate from
  authenticated and tenant-booking shells.
- [x] Responsive navigation is route-aware and auth-aware; the controlled
  mobile menu exposes expanded state, closes on Escape/outside interaction/
  route change, returns focus, locks background scroll and fits small
  viewports. Only currently executable named routes are exposed.
- [x] The sticky public header uses a warm, lightly elevated cream surface with
  standard ink/pine navigation and a matching light mobile panel. Deep pine is
  reserved for the footer so the page is not visually top-heavy.
- [x] Footer boilerplate, fake social/service/newsletter links and theme control
  were removed from the active marketing shell. Global language now identifies
  Good Hours and its salon/barbershop booking-to-checkout purpose.
- [x] Reusable public container, section heading, CTA, card, proof frame,
  comparison table, FAQ/disclosure, breadcrumb and conversion-band contracts
  are implemented in one marketing namespace and documented in the design
  system.
- [x] Public CTA uses `Start your trial` for anonymous visitors and `Open
  dashboard` for authenticated users, with a stable privacy-safe context
  attribute but no tracker.
- [x] Prompt 15 verification: 3 focused tests / 34 assertions pass; production
  client and SSR builds pass. Browser checks at 360×800 and 1440×900 found no
  shell overflow, verified landmarks and real footer destinations, and proved
  mobile Escape/focus-return/scroll-unlock behavior. Evidence:
  [`prompt-15-shell-mobile.png`](evidence/frontsite-2026-08-16/prompt-15-shell-mobile.png)
  and [`prompt-15-shell-desktop.png`](evidence/frontsite-2026-08-16/prompt-15-shell-desktop.png).
- [x] Prompt 16 is unblocked. Homepage boilerplate remains visible only because
  page composition is explicitly owned by Prompt 16, not by the shell phase.

### Prompt 16 homepage positioning and conversion

- [x] The root page is now a complete Good Hours narrative led by “Run your
  salon or barbershop from booking to checkout,” one working trial action and
  an explicit existing-customer Login path in the shared shell.
- [x] Six verified outcome groups cover getting booked, protecting the
  calendar, running the day, getting paid, knowing clients and knowing the
  business. The workflow carries one governed record from availability through
  checkout/reporting without advertising Phase 2 behavior.
- [x] The page uses two reviewed synthetic product captures from the Prompt 01
  evidence set, with factual captions, meaningful alternatives, intrinsic
  dimensions, eager hero proof and lazy below-fold booking proof. No customer
  logo, testimonial, rating, usage count, integration logo or fabricated metric
  remains.
- [x] Visible answers define Good Hours, its audience, managed work, difference
  from booking-only tools, import support, and live provider qualifications.
- [x] Home metadata now has a unique natural title, description and configured
  canonical. Core copy and links are SSR-compatible; Prompt 23 still owns the
  final cross-site metadata/schema engine.
- [x] Focused Homepage/shell/registration/public-booking verification passed 17
  tests / 187 assertions with one intentional skip. Production client + SSR
  build passed; the Home client chunk fell from the 96.31 kB audit baseline to
  14.48 kB (5.47 kB gzip), excluding the shared app chunk and two optimized
  evidence images.
- [x] Fresh browser checks at 360×800 and 1440×900 found one `h1`, no horizontal
  overflow, no broken media and no console warnings/errors. All four CTA
  placements resolved to registration. Evidence:
  [`prompt-16-home-mobile.png`](evidence/frontsite-2026-08-16/prompt-16-home-mobile.png)
  and [`prompt-16-home-desktop.png`](evidence/frontsite-2026-08-16/prompt-16-home-desktop.png).
- [x] Prompt 17 is unblocked. Homepage contextual links to later hubs remain
  deliberately absent until those routes have substantive pages.

### Prompt 17 product and feature architecture

- [x] `/features` and four substantive details are implemented: online
  booking; calendar and walk-ins; client management; and checkout/reporting.
  Homepage outcome cards now link to those real destinations.
- [x] Staff/resource scheduling is consolidated into the calendar cluster;
  deposits/inventory/commission into the completed-commerce cluster; and
  reminders into booking/client context. No thin module-per-page or keyword
  permutation was published. Decisions are in
  [`frontsite/feature-clusters.md`](frontsite/feature-clusters.md).
- [x] Each detail has a unique definition, four-step workflow, requirement
  evidence, three concrete proof statements, two visible limitations, two
  adjacent feature links, breadcrumb, canonical metadata and contextual trial
  CTA. Provider, retention, medical-compliance and throughput limitations are
  stated where relevant.
- [x] Unknown/unapproved feature slugs return 404. Phase 2 AI, payroll, loyalty,
  gift-card and forecasting routes/content are not generated.
- [x] Prompt 17 verification: 11 focused tests / 184 assertions passed with the
  existing Home/shell checks; production client and SSR builds passed. Browser
  checks found one `h1`, valid hub/detail links, active Product navigation, no
  console warnings/errors and no 360px overflow. Evidence:
  [`prompt-17-features-mobile.png`](evidence/frontsite-2026-08-16/prompt-17-features-mobile.png)
  and [`prompt-17-feature-detail-mobile.png`](evidence/frontsite-2026-08-16/prompt-17-feature-detail-mobile.png).
- [x] Prompt 18 is unblocked.

### Prompt 18 industry and business-type solutions

- [x] `/solutions` now links four differentiated, supportable pages for
  barbershops, salons, independent stylists and small non-medical spas.
- [x] Each solution has a distinct fit statement, three operation-specific
  pressure points, a four-step representative day, three relevant feature
  paths, requirement evidence, two honest limitations, canonical metadata,
  breadcrumb and contextual trial CTA.
- [x] Hair-salon and beauty-salon intent is consolidated into the salon page;
  nail salons remain deferred for lack of distinct research/proof; medical spa
  is rejected. The candidate matrix is in
  [`frontsite/industry-solutions.md`](frontsite/industry-solutions.md).
- [x] No city/country, directory, marketplace, customer-story, review,
  regulatory-compliance, stereotype or stock-image page was created. The spa
  page explicitly disclaims healthcare/HIPAA claims.
- [x] Prompt 18 verification: 14 focused tests / 259 assertions passed with
  feature/home regressions; production client and SSR builds passed. Fresh
  360×800 browser checks found one `h1`, all four hub links, three relevant
  feature links, the medical qualifier, no overflow and no console warnings or
  errors. Evidence:
  [`prompt-18-solutions-mobile.png`](evidence/frontsite-2026-08-16/prompt-18-solutions-mobile.png).
- [x] Prompt 19 is unblocked.

### Prompt 19 use-case and problem pages

- [x] `/use-cases` now curates four non-overlapping problem pages: reducing
  scheduling conflicts, managing booked/walk-in demand, protecting time with
  deposits, and keeping client history together. Candidate consolidation and
  deferral rationale is recorded in
  [`frontsite/use-case-pages.md`](frontsite/use-case-pages.md).
- [x] Every page provides an early direct answer, three diagnostic symptoms,
  four useful operating steps that stand without the product, four verified
  Good Hours steps, requirement evidence, two visible limitations and exact
  feature/business-fit links. No unsupported statistic, migration-service,
  guaranteed no-show, regulatory or AI claim was introduced.
- [x] Prompt 19 route/content regression verification passed 18 tests / 344
  assertions with feature and solution pages. A fresh 360×800 browser check
  found one `h1`, the operating qualifier and no horizontal overflow.
- [x] Prompt 20 is unblocked.

### Prompt 20 public pricing and trial conversion

- [x] `/pricing` reads active/effective Starter and Pro amounts, currency,
  intervals and entitlements through one server presenter. It shows commercial
  values only when the complete approved Paddle `pri_…` catalog is available;
  incomplete, expired, mixed-currency or non-Paddle state renders a truthful
  unavailable panel instead of partial prices.
- [x] Monthly/annual controls are semantic radios. Annual savings are computed
  from current rows; the comparison table uses current entitlement values; and
  trial, tax, cancellation, appointment-payment separation and live Paddle
  certification limits are visible. No Vue/content file owns a price.
- [x] Pricing CTAs carry only allow-listed plan/interval identifiers. The
  server revalidates them against the current catalog, stores the preference on
  the owner registration intent, and presents it later to billing review
  without choosing a provider price or charging at signup.
- [x] Prompt 20 pricing tests passed 4 tests / 77 assertions, including complete
  catalog, calculated savings, expired/unavailable state and query/POST
  tampering. Registration and the 30-test subscription lifecycle regression
  also pass. A fresh 360×800 browser check found one `h1`, the accessible
  interval control, complete prices and no horizontal overflow.
- [x] Prompt 21 is unblocked. Live Paddle checkout remains a Prompt 13 blocker.

### Prompt 21 trust, company, and legal pages

- [x] `/company` and `/security` now provide specific, evidence-backed identity
  and control summaries without inventing an operator, address, certification,
  security mailbox, SLA, uptime history, customer proof, or support workflow.
- [x] Terms and privacy use the public shell but are visibly marked as review
  drafts and remain `noindex`. Contact, status, cookie, accessibility-statement,
  and security-reporting pages were not fabricated without an accountable
  intake or approval process.
- [x] The accountable-owner, approval, and indexation matrix is recorded in
  [`frontsite/trust-and-legal-matrix.md`](frontsite/trust-and-legal-matrix.md).
  OPEN-10 and OPEN-11 remain critical production-publication blockers.

### Prompt 22 resources, guides, and editorial system

- [x] `/resources`, two maintained operating guides, `/blog`, and only eligible
  `/blog/{slug}` records use the public shell and useful related links. Legacy
  rows default to draft and are not silently published.
- [x] Article eligibility requires active/published state, a reached publication
  time, owner, author, review date, excerpt, image, and SEO fields. Stored
  Markdown is rendered server-side with raw HTML stripped and unsafe protocols
  disabled; inactive, draft, incomplete, and future records return 404.
- [x] The ownership, freshness, correction, and content-safety contract is in
  [`frontsite/editorial-system.md`](frontsite/editorial-system.md). Stored-XSS,
  publication-gate, guide, and empty-state regressions pass.

### Prompt 23 technical SEO, indexation, and structured data

- [x] One server policy emits HTML robots metadata and `X-Robots-Tag` for
  approved public, auth/booking/legal, secure/private, utility, and error route
  families. Canonicals are absolute, query-free, and page-owned.
- [x] `/sitemap.xml` is deterministic and curated; `/sitemap` redirects with
  301; generated `/robots.txt` advertises only that sitemap. The 23 base URLs
  exclude auth, tenant booking, secure tokens, legal drafts, roadmap,
  changelog, provider, admin, and test surfaces.
- [x] Central JSON-LD contains only factual Organization, WebSite, WebPage,
  SoftwareApplication, eligible Article/Guide, and visible current offer data.
  The unused legacy fabricated schema producer was removed. Real
  branded 404/410/500/503 pages fail closed with noindex directives.

### Prompt 24 GEO, AEO, and AI-assisted search

- [x] Visible answers, metadata, and schema share one canonical definition:
  “Good Hours is the daily operating system for salons and barbershops.” Page
  ownership prevents thin duplicate answers across Home, features, solutions,
  use cases, pricing, company, security, and resources.
- [x] The entity and question authority sheet is in
  [`frontsite/entity-facts.md`](frontsite/entity-facts.md). No bot-only page,
  hidden content, `llms.txt`, fake locale, AI feature, review schema, or
  unsupported global/compliance answer was introduced.

### Prompt 25 performance, accessibility, and international readiness

- [x] Public HTML declares `en-IN`; one reviewed locale is launched without a
  fake selector or `hreflang`. ADR-025 requires equivalent path-prefixed legal,
  commercial, provider, copy, and reciprocal metadata review before another
  locale can be indexed.
- [x] Skip navigation, landmarks, focus visibility, reduced-motion handling,
  touch targets, semantic disclosures/radios/tables, responsive wrapping, print
  rules, intrinsic image dimensions, and lazy below-fold evidence are present.
- [x] Production client and SSR builds pass on Node v24.19.0. The shared app JS
  is 275.72 kB (94.86 kB gzip), public CSS is 258.85 kB (39.02 kB gzip), Home
  is 12.78 kB (5.06 kB gzip), and public route chunks remain below 10 kB except
  Home. `npm run check:frontsite-budgets` passes all 17 public route entries.
  Independent WCAG, real-device/browser, field-performance, and load evidence
  remain external launch controls.

### Prompt 26 conversion, analytics, and internal linking

- [x] Public CTAs use one authenticated-aware trial/dashboard contract. Pricing
  stores only server-validated plan and interval preferences; query attribution
  is never trusted or persisted as functional checkout state.
- [x] A vendor-neutral, storage-free DOM boundary emits bounded CTA-click and
  user-initiated interval-change events. It sends no network request and loads
  no tracker. Verified owner onboarding records `trial.qualified_started`
  exactly once through existing first-party instrumentation.
- [x] The CTA matrix, event dictionary, privacy boundary, and link graph are in
  [`frontsite/conversion-analytics-linking.md`](frontsite/conversion-analytics-linking.md).
  OPEN-12 still blocks any analytics vendor, pixel, replay, fingerprinting,
  retention, or paid-attribution implementation.

### Prompt 27 final front-site and SEO launch audit

- [x] The full 23-page base sitemap crawl returns 200, exactly one self-
  canonical per page, and `index, follow`; eligible articles extend the count.
  Query-like attribution never changes or leaks into canonical identity.
- [x] Browser sweeps at 320, 360, 390, 768, and 1440 widths found one `h1`, a
  main landmark, no horizontal overflow, no empty links, and no broken loaded
  media on sampled Home, hub, detail, guide, pricing, resource, legal, and 404
  surfaces. Pricing interval selection and mobile navigation behavior work.
  Final evidence: [`prompt-27-resources-mobile.png`](evidence/frontsite-2026-08-16/prompt-27-resources-mobile.png)
  and [`prompt-27-pricing-desktop.png`](evidence/frontsite-2026-08-16/prompt-27-pricing-desktop.png).
- [x] The complete repository suite passes 252 tests / 2,247 assertions with
  28 intentional disabled-feature/MySQL-integration skips in 18.94 seconds.
  Production client/SSR builds and front-site budgets pass. No lint or static
  TypeScript command is configured. `git diff --check` passes after legal-copy
  whitespace cleanup.
- [x] No unresolved in-scope P0/P1 front-site defect remains. Phase 1.5 is
  technically ready for controlled staging and named-owner review, but public
  production launch/indexation is **NO-GO** until the external gates in the
  final audit close. The overall product remains **NO-GO** under Prompt 13.
  Final register: [`audits/2026-08-16-frontsite-final-launch-audit.md`](audits/2026-08-16-frontsite-final-launch-audit.md).

## Important baseline risks

- The 2026-08-16 dependency update remediated the then-known Composer/npm
  advisories and fresh registry checks returned zero. Continuous scanning and
  review of the large transitive update remain production controls.
- Full SQLite rollback remains blocked after the platform-access migration has
  rolled back by the pre-existing 2019 Cashier customer-column migration's
  index/drop ordering.
- Jetstream Team is superseded by accepted ADR-006 and is no longer created at
  registration, but legacy Team models/tables/actions remain preserved pending
  a separately reviewed cleanup/backfill decision.
- Paddle is selected for Good Hours SaaS subscriptions; Stripe is separately
  selected for appointment payments. Overlapping legacy provider tables,
  resources, and packages remain quarantined until a separately reviewed
  cleanup/backfill proves they contain no data requiring preservation.
- The legacy invoice download now enforces User ownership, global model
  unguarding is removed, unsafe magic/social routes are disabled, and platform
  roles are separated. Other provider billing/admin resources remain legacy
  boilerplate and are not approved tenant billing behavior.
- Boilerplate product, price, blog, roadmap, AI, and marketing features are
  quarantined by the Prompt 14 audit; their visible replacement and safe
  indexation are owned by Prompts 15–27 while destructive deletion remains
  bounded by OPEN-09.
- Atomic Staff/Resource capacity and booking idempotency are proven on local
  MySQL with parallel processes. This is not sustained-load, target-topology,
  failover, replica, queue, or payment-idempotency evidence. Availability search
  currently repeats authoritative reads (746 statements for 20 measured
  slots), and any-qualified commits lock every eligible candidate Staff member.
- India commerce behavior is implemented under ADR-020, but each live tenant's
  GST/receipt/legal review, provider approval, and settlement certification are
  still hard launch controls.
- Resend sender/domain authentication and Twilio WhatsApp sender/Content SID
  approvals are not configured or externally certified; production sending
  remains blocked by those provider controls and OPEN-11.

## Next recommended work

Do not begin paid launch. Execute the blocker plan in the versioned release
record: certify Paddle/Stripe/Resend/Twilio in their target environments;
approve India tax/privacy/retention with named reviewers; add upload malware
scanning; optimize and load-test availability, checkout, and webhook bursts on
the target database/queue/storage topology; configure and exercise monitoring,
on-call, status communications, backups, restore, DR, and rollback; and run an
independent WCAG plus Chrome/Safari/Edge/Firefox matrix. Then repeat Section 7
and the complete Section 17 representative-shop test without direct database
work or product-team assistance. OPEN-09/10/11 remain explicitly retained.
