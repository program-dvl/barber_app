# Decision log

Use this file for durable product and architecture decisions. A decision is not
accepted until its status says `Accepted`. Pending entries are questions, not
permission to assume an answer.

## Decision record template

```text
### ADR-NNN: Short title

- Status: Proposed | Accepted | Superseded | Rejected
- Date: YYYY-MM-DD
- Owners: Product | Engineering | Design | Operations
- Related requirements: FR-NN

Context:
Decision:
Consequences:
Evidence or follow-up:
```

## Accepted decisions

### ADR-001: Markdown documentation is project memory

- Status: Accepted
- Date: 2026-07-27
- Owners: Product and Engineering
- Related requirements: All

Context: The product will be built through multiple Codex threads and by human
developers. Both need a durable shared source of truth.

Decision: Maintain the PRD, module specifications, architecture, quality gates,
status, roadmap, prompts, and decisions in `docs/`. Root `AGENTS.md` requires
agents to read and update them.

Consequences: A behavior-changing implementation is incomplete until its
documentation and verified status are updated. Chat history alone is not an
authoritative product record.

### ADR-002: Build a modular monolith first

- Status: Accepted
- Date: 2026-07-27
- Owners: Engineering
- Related requirements: All

Context: The Phase 1 workflow is highly transactional across booking, capacity,
payments, inventory, commissions, and audit history.

Decision: Build Phase 1 as a Laravel modular monolith with explicit domain
boundaries, services, policies, events, and jobs. Do not introduce distributed
services unless measured scale or isolation needs justify the added failure
modes.

Consequences: Cross-module writes can use database transactions where
appropriate. Boundaries must still be clear enough to extract later.

### ADR-003: The model is location-aware from the first migration

- Status: Accepted
- Date: 2026-07-27
- Owners: Product and Engineering
- Related requirements: FR-03 and FR-18

Context: Initial plans may expose one active location, but later plans require
multiple locations.

Decision: Entities, uniqueness rules, policies, availability, reporting, and
time handling must include location ownership where the domain requires it.

Consequences: The first UI may hide multi-location controls, but the data model
must not hard-code a global single-location assumption.

### ADR-004: Preserve history through versioning and compensating records

- Status: Accepted
- Date: 2026-07-27
- Owners: Product and Engineering
- Related requirements: FR-06, FR-12, FR-14 through FR-19

Decision: Financial, inventory, commission, consent, policy, and significant
appointment corrections must preserve the original event. Use immutable
snapshots, versioned rules, status history, or compensating entries rather than
destructive rewriting.

### ADR-005: Entitlements are independent of plan names

- Status: Accepted
- Date: 2026-07-27
- Owners: Product and Engineering
- Related requirements: FR-01

Decision: Capabilities and limits are represented as server-enforced
entitlements. UI visibility is not authorization. Pricing and plan names may
change without spreading plan-name conditionals through application code.

### ADR-010: Use a brand-neutral semantic shell until identity is approved

- Status: Superseded by ADR-011
- Date: 2026-08-10
- Owners: Product, Design, and Engineering
- Related requirements: PRD Sections 3, 4, 10, and 12

Context: OPEN-01 does not yet supply an approved product name, logo, palette,
typeface, or voice. The reusable shell cannot safely make those choices, but it
still needs concrete accessible tokens and product language.

Decision: Use semantic design tokens and the explicitly temporary label
`Salon operations`. Use a neutral text-first mark, system fonts, and direct
operational language from the PRD. Do not encode a permanent identity in
component names or APIs. OPEN-01 remains open and must be resolved before
production branding, domains, outbound messages, or documents are approved.

Consequences: The three shells and shared components can be implemented and
tested now. Final identity work should replace token values, label, logo assets,
and voice guidance without restructuring navigation or component behavior.

Evidence or follow-up: Implemented tokens and component rules are recorded in
`design-system.md`; browser evidence is under `evidence/product-shell/`.

### ADR-011: Adopt Good Hours as the product identity

- Status: Accepted
- Date: 2026-08-11
- Owners: Product, Design, and Engineering
- Related requirements: PRD Sections 3, 4, 10, and 12

Context: OPEN-01 required a permanent product name, logo, voice, palette,
typography, domain direction, and outbound-message identity before the neutral
shell could become a production brand. Three complete visual directions were
reviewed, and the Good Hours direction was selected.

Decision: The product name is **Good Hours** and the brand promise is **Make
every hour count.** The generated mark combines an open doorway, sunrise, and
clock arc to express welcome, useful time, and a well-run service business. The
identity deliberately avoids scissors, barber poles, moustaches, script
lettering, black-and-gold luxury, and AI positioning so it can credibly serve
barbers, salons, spas, independents, and multi-location teams.

The permanent foundation uses deep pine `#173F3A`, accessible action poppy
`#C13F28`, expressive poppy `#E56A4D`, apricot `#F2B880`, oat `#F6F1E8`, and
ink `#19201F`. Manrope is the product and message typeface; Newsreader is
reserved for selected editorial display headings. Both are self-hosted under
the SIL Open Font License.

The preferred public domain is `getgoodhours.com`, with
`app.getgoodhours.com` for authenticated work and `book.getgoodhours.com` for
public booking. An RDAP check on 2026-08-11 returned no registration record for
`getgoodhours.com`; this is a point-in-time availability signal, not ownership.
`goodhours.com` and `goodhours.app` were already registered and are not launch
assumptions.

Outbound identities are:

- account and billing: `Good Hours <account@getgoodhours.com>`;
- appointment and client messages: `[Business name] via Good Hours
  <appointments@getgoodhours.com>` with a verified tenant reply-to where
  available;
- human support: `Good Hours Support <support@getgoodhours.com>`; and
- security-sensitive mail: `Good Hours Security <security@getgoodhours.com>`.

No production sender may use these addresses until the domain is acquired and
SPF, DKIM, DMARC, return-path, reply handling, and provider verification pass.

The voice is calm, capable, human, and clear. Use sentence case, concrete next
steps, local date/time context, and reassuring recovery language. Avoid hype,
beauty clichés, blame, artificial urgency, and unsupported business claims.

Consequences: OPEN-01 is resolved and ADR-010 is superseded. Existing semantic
component APIs remain stable while their values, product mark, page titles,
booking language, and auth identity adopt Good Hours. Formal trademark
clearance and domain acquisition remain launch-readiness work under OPEN-11;
selection of this identity does not represent a legal clearance opinion.

Evidence or follow-up: Concrete rules are in `design-system.md`. The selected
visual reference and post-build browser evidence are recorded by the product
shell design-QA workflow.

## Foundation decisions

### ADR-006: Use an explicit Business as the canonical tenant

- Status: Accepted
- Date: 2026-08-11
- Owners: Product and Engineering
- Related requirements: FR-01, FR-02, FR-03, FR-05, FR-19, FR-20

Context: Larafast contains Jetstream Team models, pivots, invitations, actions,
and pages, but Team support is disabled. Registration still creates a personal
Team. Team provides collaboration-workspace semantics and no Business
lifecycle, location ownership, support-access model, or safe tenant context for
jobs, files, exports, and provider events.

Decision: Introduce `Business` as the canonical tenant aggregate. Do not adapt
Jetstream Team as the domain tenant. Business owns locations and all tenant
records. Resolve tenant context explicitly for HTTP, jobs, commands, webhooks,
files, caches, search, imports, and exports. Retire Team coupling only through a
separately approved, evidence-backed migration/cleanup slice.

Consequences: The first tenancy slice has more application-owned schema and
actions than simply enabling Team. It avoids a later migration of Team foreign
keys and personal-team assumptions after salon data exists. Jetstream/Fortify
account-security components may still be adapted independently.

Evidence or follow-up: See
`audits/2026-08-10-larafast-adoption-audit.md`. Prompt 02 approved this
direction and resolves OPEN-03. Existing Jetstream Team tables remain legacy
boilerplate data and are not silently repurposed.

### ADR-007: Business owns SaaS billing behind one provider adapter

- Status: Superseded in provider selection by ADR-021
- Date: 2026-08-11
- Owners: Product and Engineering
- Related requirements: FR-01, FR-19, FR-20

Context: The boilerplate currently makes User billable through Cashier/Stripe,
also carries Lemon Squeezy customer/subscription/order/license tables, and
exposes Paddle routes despite the Paddle package being absent. The PRD requires
one launch SaaS billing provider and reusable entitlements.

Decision: Make Business the SaaS customer, subscription, invoice, and
entitlement owner. Stripe is the single launch SaaS subscription provider and
is integrated through an application-owned `SubscriptionProvider` contract and
normalized lifecycle. Stripe Checkout, customer portal, subscription schedules,
promotion codes, invoices, payments, and signed webhooks are provider-edge
capabilities; Stripe payloads are not the domain model. Keep SaaS billing
separate from appointment deposits and checkout payments even if Stripe is
later selected for both.

Provider selection is superseded by ADR-021. The Business ownership boundary,
adapter contract, normalized lifecycle, and separation from salon-client
payments remain accepted.

Consequences: Provider-specific identifiers and payload evidence remain behind
the adapter. User-owned Cashier behavior is disabled, as are Lemon Squeezy and
Paddle runtime routes/listeners. Their legacy tables, models, resources,
commands, and installed dependencies remain quarantined rather than being
deleted without data/backfill evidence. OPEN-04 is resolved. OPEN-02 still
blocks publication of currency, prices, tax, and receipt settings.

Evidence or follow-up: See
`audits/2026-08-11-subscription-provider-audit.md`. Local contract tests cover
the installed Stripe signature verifier, renewal/failure/grace/cancellation,
deduplication, out-of-order events, invoices, payments, and replay. A live
Stripe sandbox/test-clock run remains a pre-launch requirement because no
sandbox credentials are present.

### ADR-008: Separate User, Membership, and StaffProfile authorization

- Status: Accepted
- Date: 2026-08-11
- Owners: Product and Engineering
- Related requirements: FR-05, FR-19, FR-20

Context: A login is not necessarily a schedulable staff member. Current
Jetstream membership is a pivot with a role string, while Spatie permissions
are global because team scoping is disabled. Neither represents staff history,
location assignments, invitation lifecycle, or a clean separation between
platform and business roles.

Decision: `User` authenticates. A first-class `Membership` grants a User access
to one Business and owns membership status and business role. `StaffProfile`
belongs to Business and may link to a User. Invitations are expiring,
revocable, hashed, business-bound, and may bind a StaffProfile and location
assignments. Platform roles are global and separate; business permissions are
Business-scoped and optionally Location-scoped.

Consequences: Staff may exist without login, and deactivating login/membership
does not erase historical staff attribution. Authorization must be enforced by
policies/actions and tenant-scoped queries independently of navigation.

Evidence or follow-up: Prompt 02 implements and verifies the starter-role
permission matrix. Spatie business roles and direct permissions attach to the
tenant-bound Membership, never to the global User identity. This permits a
single User to have different access in different businesses without relying
on a hidden current-team field.

### ADR-009: Separate platform administration from audited support access

- Status: Accepted
- Date: 2026-08-11
- Owners: Product, Engineering, and Operations
- Related requirements: FR-19 and FR-20

Context: Filament currently grants the entire admin panel to a global `admin`
role or a stale `is_admin` fallback. No support grant, tenant-visible banner,
reason/ticket, expiry, scoped permission, or entry/exit audit record exists.

Decision: Retain Filament as a platform-operations surface, protected by
platform-specific roles and stronger authentication. Platform role alone does
not grant ordinary tenant data access. Tenant support access uses a separate,
time-limited grant with reason/ticket, approved scope, visible banner, explicit
entry/exit, and immutable audit evidence.

Consequences: Platform resources require explicit policies. Bulk/cross-tenant
operations are separately authorized and monitored. Invisible impersonation is
not permitted.

Evidence: Prompt 02 implements separate expiring platform-role assignments and
prevents those roles from entering tenant routes. Prompt 12 implements the
distinct tenant-visible support-grant workflow and proves reason, scope,
expiry, banner, revocation, and entry/exit behavior before support entry is
accepted.

### ADR-012: Use MySQL 8/InnoDB as the production transactional baseline

- Status: Accepted
- Date: 2026-08-11
- Owners: Engineering and Operations
- Related requirements: FR-05, FR-19, FR-20

Context: OPEN-07 blocked irreversible tenancy schema work because the local
environment used MySQL but no production database or hosting topology had been
selected. Locking and constraint behavior will later be central to scheduling
and payments.

Decision: MySQL 8.0 or a wire-compatible managed MySQL service with InnoDB is
the Phase 1 transactional database baseline. Migrations use portable Laravel
schema operations where practical but production concurrency evidence must run
against MySQL. SQLite `:memory:` is allowed only for fast isolated unit and
authorization tests. Production hosting vendor, replica topology, cache, queue,
backup provider, and regional placement remain bounded operational choices;
they may not weaken tenant isolation, durable asynchronous work, RPO/RTO, or
restore testing.

Consequences: OPEN-07 no longer blocks the ownership schema. Sync queues and
file cache remain local-development facts, not accepted production topology.
Before scheduling or payment concurrency is claimed verified, MySQL integration
tests and the remaining hosting/backup choices are required.

### ADR-013: Launch authentication uses verified password identities and TOTP

- Status: Accepted
- Date: 2026-08-11
- Owners: Product, Engineering, and Operations
- Related requirements: FR-05, FR-19, FR-20

Context: OPEN-08 covered unsafe boilerplate magic-link account creation,
unrestricted Socialite drivers and email linking, an unverified `User` model,
and the stronger controls required for owners and platform operators.

Decision: Phase 1 staff authentication retains Fortify/Jetstream email and
password login, password reset, email verification, browser-session management,
and TOTP two-factor authentication with recovery codes. Staff tenancy access is
created only by a business-bound invitation or an approved owner-onboarding
flow. Boilerplate magic-link and social-login routes are disabled. Platform
administration requires a verified email, confirmed TOTP, and a separate active
platform-role assignment. Sanctum remains installed for first-party session
authentication and future scoped API use, but user-created API tokens remain
disabled; any future tenant token must bind to a Business and Membership and be
revocable with that membership.

Consequences: OPEN-08 is resolved for launch. Adding a social provider,
passwordless login, or service token later requires a new decision and explicit
account-linking, verification, expiry, tenant binding, and revocation tests.

### ADR-014: Publish explicit readiness and expose one availability-configuration contract

- Status: Accepted
- Date: 2026-08-11
- Owners: Product and Engineering
- Related requirements: FR-02, FR-03, FR-04, FR-05, FR-06, FR-07

Context: A percentage can claim progress while a shop still has no deliverable
service path. Prompt 05 also needs configuration without coupling its booking
engine to onboarding controllers or raw tables.

Decision: Readiness is an ordered set of blocking facts and optional
improvements. Publishing requires a complete business/rules profile, an active
Location with hours, active scheduled Staff, an online Service with a qualified
staff/location/resource path, and a reviewed preview. Import and branding are
improvements. Availability consumers use the application-owned
`AvailabilityConfiguration` contract for local Location windows, effective
service resolution, and resource quantities. Booking search/commit remains out
of this module. Published availability-reducing changes require an expiring
Appointment impact preview and explicit resolution when affected records exist.

Consequences: Prompt 05 must bind `AppointmentImpactSource` to its Appointment
store and use the same read contract during search and commit. Resolved values
are captured as immutable snapshots, so future configuration edits cannot
rewrite historical Appointment decisions. OPEN-02 remains unresolved; country,
currency, locale, and tax posture are explicit inputs.

### ADR-015: Serialize booking capacity with deterministic InnoDB root locks

- Status: Accepted
- Date: 2026-08-11
- Owners: Engineering and Product
- Related requirements: FR-03 through FR-07

Context: Advisory search cannot prevent two online/reception requests from
selecting the same staff or pooled physical capacity. MySQL has no portable
exclusion constraint for arbitrary time-range overlap, and a unique key on an
Appointment start would neither model resource quantity nor processing
segments. Capacity Holds and idempotent replay must also share the same rule.

Decision: Use one application-owned `BookingRuleEngine` for search, Hold,
direct commit, and Hold confirmation. Writes run in a MySQL 8/InnoDB transaction
and acquire rows in this order: Business/scope command key, shared Location,
shared sorted Services, sorted explicit/eligible Staff, and sorted required
Resources. Staff/Resource roots are pessimistically locked before indexed overlap reads.
Intervals are half-open. Pooled resource claims sum overlapping quantities.
Active unexpired Holds participate exactly like Appointments; confirmation
excludes its own Hold, revalidates, writes the entire Appointment aggregate,
and marks the Hold confirmed atomically. Laravel transaction deadlock retry is
limited to five attempts.

Idempotency uses a unique command-key row plus normalized request digest. Exact
replay returns the original result. Reusing the key for different input fails.
Search never returns private schedule reasons, and write errors expose stable
safe rule codes. No manager integrity override exists in this slice.

Consequences: This is intentionally a conservative correctness-first lock
scheme. Any-qualified requests lock all eligible candidates and may serialize
popular broad services. Availability reads remain bounded and advisory; their
current repeated rule reads need a batched/cached projection before peak public
traffic. Optimization may narrow read and lock scope only if the MySQL parallel
acceptance suite continues to prove identical atomicity. Controllers,
Filament, Vue, jobs, and later drag/drop behavior must call the use-case
contracts and may not write Appointment timestamps.

Candidate-assignment and resource-requirement discovery are shared locking reads
so no consistent snapshot is established before an exclusive-root wait.

Evidence or follow-up: MySQL 8.0.42 process-level tests use independent PDO
connections behind a fork barrier for online/online, online/reception,
staff/resource, quantity, multi-segment, expiry, duplicate, and stale-search
races. The measured local 20-slot search was 210.52 ms/746 SQL statements and
direct commit was 15.53 ms/55 statements. ADR-016 defines the lifecycle and
narrow manager policy override without bypassing this boundary.

### ADR-016: Preserve schedule history with linked replacements and narrow policy overrides

- Status: Accepted
- Date: 2026-08-12
- Owners: Product, Engineering, and Operations
- Related requirements: FR-06, FR-07, FR-08, FR-19

Context: Calendar edits must be safe under concurrent front-desk use and must
not erase the schedule/commercial evidence that existed before a move, duration
change, provider change, or service change. Managers also need limited
discretion for policy warnings without gaining a way to create impossible
capacity.

Decision: Appointment lifecycle mutations require an expected version and a
Business-scoped idempotency key. Reschedule, resize, reassign, and service-list
changes create a new Appointment through the authoritative booking engine,
link it bidirectionally to the original, and make the original `rescheduled`
and terminal. Selected `NOTICE_WINDOW` and `ADVANCE_WINDOW` policy failures may
be overridden only by an authorized manager/owner after explicit warning
acknowledgement and a non-empty reason. Hours, closure, staff qualification or
availability, block, travel, Appointment overlap, and physical-resource
capacity failures cannot be overridden. Every override and lifecycle change
retains actor/source/reason/change evidence.

Consequences: Historical reporting can distinguish what was originally booked
from what replaced it; stale tabs fail safely; command retries do not duplicate
work; and a manager cannot silently manufacture capacity. Queries must treat
linked terminal records consistently, and later communications/payments must
follow the active replacement while retaining the original reference chain.

### ADR-017: Use hashed purpose links and atomic waitlist offer batches

- Status: Accepted
- Date: 2026-08-12
- Owners: Product, Engineering, Security, and Operations
- Related requirements: FR-07, FR-09, FR-10, FR-19, FR-20

Context: Passwordless clients need to manage one Appointment without an account,
but a human-readable reference is guessable and must not authorize access.
Waitlist openings may also be offered to more than one client, creating a race
that ordinary first-come application checks cannot resolve safely.

Decision: Public booking sessions and Appointment actions use cryptographically
random opaque secrets whose SHA-256 digests alone are persisted. Appointment
links bind one purpose, Appointment, Business, and UTC expiry. Mutating action
links are single-use; contact, cancellation, and replacement changes revoke
older links, and the client receives a newly issued view link. References remain
display identifiers only. Public endpoints are throttled and reject malformed,
unknown, wrong-purpose, expired, used, and revoked tokens without tenant
enumeration.

Waitlist requests use a nullable active fingerprint to converge exact duplicate
preferences while retaining completed history. A released opening may be
offered to a Business-configured small batch. Claim locks every match in that
batch in deterministic order and commits through the authoritative booking
engine; one claimant becomes booked and every sibling offer becomes lost.

Consequences: Raw links must never appear in audit metadata, logs, analytics, or
support search. Prompt 09 delivers temporary signed bridge links from revocable
action records without weakening purpose/expiry rules. Prompt 10 must coordinate deposit Holds through the same
booking session rather than adding a second token or capacity model. Offer
delivery and expiry cleanup may be asynchronous, but delayed work cannot create
capacity or revive an invalid claim.

### ADR-018: Bound destructive Client privacy work until retention policy is approved

- Status: Accepted
- Date: 2026-08-12
- Owners: Product, Engineering, Security, Privacy, and Operations
- Related requirements: FR-11, FR-12, FR-19, FR-20

Context: Prompt 08 must make export, correction, consent withdrawal, and
deletion/anonymisation requests operational. OPEN-02 does not yet select the
launch jurisdiction and OPEN-10 does not yet define record-specific retention
periods. Guessing either could destroy evidence that must be retained or keep
personal data longer than permitted. Financial and audit ledgers also cannot be
rewritten to simulate erasure.

Decision: Implement the complete tracked privacy-case lifecycle, data
classification, deadline, reviewer, export artifact, correction, withdrawal,
and retained-data preview now. Export produces a private, content-hashed,
30-day artifact and a minimized manifest. Correction uses optimistic Client
versions and rotates vulnerable Appointment links. Withdrawal appends a Consent
event and changes current marketing eligibility without rewriting history.

Deletion/anonymisation is reviewable but has no destructive executor. Until
OPEN-02 and OPEN-10 are accepted, review must set `blocked_policy`, record the
counts/classes that would be retained, state that no destructive change ran,
and preserve the Client. There is no scheduled hard-delete job, cascade path,
or UI/API bypass. Consent submissions remain immutable; financial and audit
history will remain append-only under the final policy.

Consequences: The product can intake and evidence every privacy request without
ad hoc database changes, but cannot claim that a deletion/anonymisation request
has completed. Counsel and product must approve the jurisdiction-specific
schedule, identity-verification standard, attachment treatment, export window,
and anonymisation map before a destructive executor is designed or enabled.
Prompt 13 must treat that approval and executor certification as a paid-launch
gate.

### ADR-019: Launch communications use India/en-IN, Resend email, and Twilio WhatsApp

- Status: Accepted
- Date: 2026-08-14
- Owners: Product, Engineering, Security, Privacy, Operations, and Support
- Related requirements: FR-13, FR-19, FR-20

Context: OPEN-06 required one approved mobile launch channel, and the
locale/consent portion of OPEN-02 had to be resolved before choosing it. The
implemented and demo configuration already uses India, `en-IN`, INR, E.164
Indian mobile numbers, and `Asia/Kolkata`; a mobile provider must also support
approved business-initiated templates, explicit channel opt-in, delivery
callbacks, stable provider identifiers, and safe failure handling. The
repository already includes Resend support for email.

Decision: The communications launch profile is India with `en-IN` as the safe
template fallback locale. Location IANA time zones continue to govern
appointment and reminder wall time, so this decision does not hard-code
`Asia/Kolkata` into delivery calculations. Email uses Resend and the approved
mobile channel is WhatsApp through Twilio Programmable Messaging, both behind
separate application-owned contracts. WhatsApp templates cannot be published
until their Twilio Content SID reports `approved`; SMS, browser push, and in-app
delivery remain contract-compatible later channels, not launch surfaces.
Twilio's standard Messages create API has no documented provider-idempotency
input. Application uniqueness and send locking prevent duplicate creates; only
a definite 429 rejection is automatically retried. Ambiguous WhatsApp
transport/5xx or missing-SID outcomes are terminal until provider reconciliation,
so the application never claims an unsupported provider guarantee.

Transactional appointment, queue, waitlist, deposit, and receipt messages are
recorded with contract performance, user-requested service, or legal-obligation
basis as applicable; this never grants marketing permission. Marketing requires
an active explicit marketing consent, the selected channel preference, no
active suppression, and an unsubscribe path. WhatsApp additionally requires an
explicit channel opt-in for every outbound category. Selecting WhatsApp for a
specific waitlist request is retained as request-scoped opt-in evidence; linked
Clients receive append-only WhatsApp consent evidence. Consent and suppression
are rechecked immediately before delivery, not only when queued.

Consequences: OPEN-06 is resolved. The locale/mobile-format/communication-
consent portion of OPEN-02 is resolved for implementation. India-specific tax,
receipt wording, broader privacy obligations, and final retention execution
still require counsel/accounting approval through the launch checklist and
OPEN-10; this ADR is not legal advice. Production sending remains disabled
until Resend domain authentication, Twilio WhatsApp sender onboarding, approved
Content SIDs, callback secrets, and the OPEN-11 sender-identity controls pass.
Content-free diagnostics and safe replay remain tenant-authorized settings
operations. A platform role does not bypass Business ownership; support staff
must wait for the distinct ADR-009 grant before using those tenant surfaces.

### ADR-020: India commerce profile and compliant appointment-payment boundary

- Status: Accepted
- Date: 2026-08-15
- Owners: Product, Engineering, Finance, Operations
- Related requirements: FR-14, FR-15, FR-18, FR-19

Context: OPEN-02 left tax, receipt, and launch-market behavior unspecified;
OPEN-05 left appointment deposits and local tenders without a gateway. A request
to use Paddle for every payment surface conflicts with Paddle's public
merchant-of-record positioning for SaaS and digital products, while salon
services and retail are local, physical commerce.

Decision: India, INR, and `en-IN` are the commerce implementation profile.
Each business owns an explicit tax-inclusive/exclusive flag and tax rate; the
safe default rate is zero, so the system never silently asserts GST liability.
Receipts show the tenant identity, currency, line values, tax, deposit applied,
payments, and immutable issue time. Stripe is the launch appointment-card
adapter behind `AppointmentPaymentProvider`; cash, card, UPI, bank transfer,
payment link, custom, and pay-later are normalized tender methods. Paddle is
not used for salon-client deposits, checkout, retail, refunds, or cash close.
The SaaS-subscription provider remains separate from appointment payments.
ADR-021 records the subsequent, independently implemented Paddle provider,
entitlement, invoice, webhook, backfill, and contractual boundary.

Consequences: OPEN-02 and OPEN-05 are resolved as product/engineering choices.
Legal/accounting confirmation of a specific shop's GST registration, tax rate,
receipt wording, provider account approval, UPI/acquirer setup, and live
webhook certification are release controls, not open implementation choices.
Paddle API credentials supplied outside a secret manager must be rotated and
must not be committed.

Evidence or follow-up: `MoneyCommerceTest` proves calculation, deposit/split
tender/reconciliation, duplicate and out-of-order event handling, receipt
reproduction, and cash variance locally. Before live collection, run Stripe
test-mode Payment Intent/refund/webhook tests with the configured endpoint,
reconcile a provider settlement, and have Indian counsel/accounting approve the
tenant receipt and tax profile.

### ADR-021: Paddle is the Good Hours SaaS subscription provider

- Status: Accepted
- Date: 2026-08-15
- Owners: Product and Engineering
- Related requirements: FR-01, FR-19, FR-20

Context: Good Hours earns its platform revenue through salon subscriptions.
The former Stripe launch choice did not use the provided Paddle sandbox
configuration, and no Paddle price IDs had been linked to the billing catalog.

Decision: Paddle Billing is the selectable provider for Good Hours business
subscriptions only. The application keeps its Business-owned subscription,
entitlement, invoice, payment and immutable provider-event model, while the
Paddle adapter creates subscription checkout transactions and consumes
signed, deduplicated, ordering-safe webhook events. Salon-to-client deposits,
retail and appointment checkout remain manual/local tender records until a
separate customer-payment provider is approved.

Consequences: `BILLING_PROVIDER=paddle` selects the Paddle adapter when the
Paddle API key is configured. A real Paddle `pri_…` ID must be attached to each
monthly and annual `billing_plan_prices` row before a price can be shown. The
Paddle notification-destination signing secret is required as
`PADDLE_WEBHOOK_SECRET`; an API key, client-side token, Retain key, or old
Cashier webhook setting is not a valid webhook secret. Existing Stripe evidence
is retained and is never deleted.

Operational check: `PADDLE_API_KEY` must contain a valid Sandbox API key—not
the tracked `your-paddle-api-key` placeholder—and configuration cache must be
cleared after it changes. The app never exposes Paddle's raw provider error to
a salon owner: it records the status for support and returns a safe retryable
checkout message beside the selected plan.
Every configured `pri_…` ID must be retrievable by that same Paddle account in
the configured environment. Sandbox and Live catalogs are isolated; a Sandbox
API key cannot use a Live price ID (or one from another Sandbox account). The
adapter verifies the price before it creates a Paddle customer.

Checkout presentation: Good Hours uses Paddle.js inline checkout inside a
dedicated application-owned review page. The owner remains on the Good Hours
page; no overlay/popup or hosted payment-page redirect is used. Paddle's frame
continues to collect card, billing, tax, and payment data so Good Hours does not
handle card data. Browser completion is only progress feedback; paid access
requires either a signed Paddle webhook or an authenticated server-to-server
Paddle API confirmation of the stored checkout attempt. API confirmation must
match the transaction, customer, Business public ID, application marker, local
price, currency, and active provider subscription; a browser event alone can
never activate access. The eventual signed webhook remains idempotent after API
recovery and must not duplicate invoices or payments.

Lifecycle presentation: billing-capable owners see the current plan and status
in the application header. A Starter-to-Pro upgrade (and monthly-to-annual
change) is confirmed in-app, sent to Paddle with immediate proration, and
audited. Same-interval downgrades retain current access and are recorded for the
renewal boundary; an annual-to-monthly change is not silently applied mid-term.
Cancellation defaults to the end of the paid period, states the exact access
date, preserves records/export recovery, and requires consequence-first
confirmation. Undoing a scheduled Paddle cancellation clears
`scheduled_change`; Paddle's paused-subscription resume endpoint is not used.

Shared-account boundary: one Paddle account may contain more than one SaaS
catalog. Every Good Hours customer and transaction includes
`application=good_hours` plus the Business public ID. Webhooks explicitly
marked for another application are acknowledged and discarded before storage,
preventing the second SaaS's customer payloads from entering Good Hours logs or
provider-event records. Good Hours retains distinct products/prices and does
not infer application ownership from the shared seller account.

Paddle's seller legal/display identity is account-wide, not a per-product
Paddle.js setting. Consequently, the seller name, marketing-consent copy, and
checkout footer cannot be changed from QRxpress to Stylnexa for Good Hours in
application code without affecting the other SaaS in the same Paddle account.
The shared account must use an approved neutral seller identity such as
Stylnexa, or Good Hours must use a separate Paddle seller account. Product and
Engineering may not hide or rewrite Paddle's hosted compliance footer.

Domain posture: sandbox checkout may run before a public domain is purchased;
Paddle does not require sandbox website approval. Live checkout remains blocked
until the eventual production domain/default payment link is approved and live
credentials/catalog mappings are separately certified.

Configured catalog: Product approved a two-plan Paddle sandbox catalog on
2026-08-15. Starter is USD 50/month or USD 500/year with one location and two
staff. Pro is USD 100/month or USD 1,000/year with three locations, twenty
staff, 1,000 included messages, and the currently implemented paid
capabilities. These are entitlement values, not plan-name conditionals; a later
commercial change must create a new effective-dated entitlement record rather
than rewrite subscription history.

Catalog operation: the versioned `billing:sync-paddle-catalog` command is the
only approved catalog writer. It previews by default and requires `--apply` to
write Paddle or local mappings. Its central catalog is two products (Starter
and Pro) with monthly and annual prices. A re-run refreshes product and
non-financial price metadata; changing an amount creates a new Paddle price,
ends the previous local mapping, and preserves the old provider price and all
historical subscription evidence. The command must run against the same
Sandbox or Live account that will serve checkout.

### ADR-022: Project inventory, payroll inputs, and reports from completed commerce events

- Status: Accepted
- Date: 2026-08-15
- Owners: Product, Engineering, Finance, and Operations
- Related requirements: FR-16, FR-17, FR-18, FR-19

Context: Inventory, commission, dashboard, and export totals can disagree if
each feature owns an editable copy of Sale value. Multi-Location permission and
local-day rules also make a global stock/report cache unsafe. Refunds require a
durable physical-product outcome, while Tip offsets need one predictable Phase
1 policy.

Decision: A Sale becomes the projection trigger only when its final tender
commits `completed`; the same transaction creates deterministic inventory,
commission, Tip, usage, and report-visible effects. Product quantity is held at
Location level with a cross-Location Product aggregate. Sale completion deducts
each inventory-backed line once. Product refunds/voids require a retained
`restock`, `write_off`, or `customer_keeps` disposition. Commission rules are
immutable/effective-dated; the most-specific Staff/Service and latest effective
rule wins, with fixed Service ahead of Service percentage at equal specificity.
Commission uses the discounted line value. Refund commission offsets follow
the affected line; Tip offsets are proportional to refund divided by completed
Sale total and capped by earned Tip.

Metric definitions live in one versioned executable catalog. Reports query
completed Sale/Payment evidence (plus explicitly filtered open Sales for
expected/outstanding work), return source IDs/drills, and use the governing
Location time zone. A report spanning different Location time zones must run
per Location. Core exports always queue with Business, Membership, normalized
filter, and scope snapshots, then re-authorize before producing a private,
hashed artifact. Instrumentation is allow-listed and idempotent; only approved
segmentation dimensions and optional HMAC subject hashes are stored.

Consequences: Current totals remain explainable without rewriting prior
evidence, and permission scope is shared by screens, print, and CSV. Phase 1
does not implement procurement, purchase orders, suppliers, payroll execution,
or a warehouse transfer workflow. The proportional Tip-offset rule must remain
visible on statements; changing it requires a new effective metric/commission
version rather than rewriting entries.

Evidence: `InventoryCommissionReportingTest` passes on SQLite and MySQL 8,
including exact replay, dispositions, effective rule change, discount/refund
effects, local-midnight boundary, role/cross-tenant exports, filter/drill/CSV/
print reconciliation, all catalog keys, and a 2,000-Sale/8-query local
benchmark.

### ADR-023: Separate safe platform summaries from scoped support sessions

- Status: Accepted
- Date: 2026-08-15
- Owners: Product, Engineering, Security, and Operations
- Related requirements: FR-19 and FR-20

Context: ADR-009 required attributable support access but left approval,
session, scope, export, replay, and alert mechanics to Prompt 12. The retained
Larafast Filament User/Role editors could also expose or mutate authentication
and legacy authorization data outside the approved tenant model.

Decision: Platform administration uses three application roles with an
explicit capability matrix. Every role requires verified email, confirmed
TOTP, and 15-minute idle/eight-hour absolute platform sessions. Cross-tenant
search returns only safe account/commerce/volume summaries. Tenant support
requires approval by a different platform administrator, one Business, ticket,
reason, explicit enumerated scopes, and expiry within four hours. Entry never
changes identity and is accepted only by separate support endpoints. Tenant
shop routes continue to require Membership. Active entry is tenant-visible.

Safe replay is allow-listed by application-owned processor, reasoned, and
deduplicated in a replay ledger. Generic failed jobs remain inspection-only.
Platform export initiation is administrator-only and single-Business; bulk
input is rejected and alerted. Rapid access to three Businesses in fifteen
minutes is also alerted. Legacy Filament identity, role, permission, billing,
price, product, and discovered widget surfaces are quarantined rather than
reused.

Consequences: Support can resolve representative notification and provider
failures without SQL edits while operator identity and tenant lineage remain
visible. Dual approval adds operational friction by design. Backup health stays
explicitly `not_configured` until a production adapter and restore evidence
exist. New replay types, grant scopes, or bulk operations require a reviewed
adapter/capability and new isolation/idempotency evidence.

Evidence: `PlatformOperationsTest` covers role/MFA/session separation, safe
summaries, scoped/expired/revoked grants, tenant banner, identifier denial,
bulk-export alerting, immutable audit/notes, duplicate replay, provider
recovery, rapid cross-tenant alerts, and Filament quarantine.

### ADR-024: Separate the curated acquisition site from tenant booking and private tasks

- Status: Accepted
- Date: 2026-08-16
- Owners: Product, Design, Engineering, Security, and Privacy
- Related requirements: PRD Sections 3, 4, 12, 15, and 17; FR-01, FR-09, FR-10, FR-19

Context: The public root remained a generic Larafast starter page. Its global
navigation mixed unsupported marketing, roadmap, newsletter, blog and test
surfaces with valid account and tenant-booking routes. The dynamic sitemap
crawler had no durable distinction between acquisition pages, tenant booking,
secure token actions, authentication, administration and utilities.

Decision: Phase 1.5 uses one curated acquisition information architecture and
an explicit indexable route registry. Marketing pages are stable, SSR-readable
and eligible for sitemap inclusion only after their content, claims, metadata,
links and owner pass review. Tenant booking is a distinct task family under
`/book/{slug}` and is never part of marketing navigation or sitemap. Secure
appointment, waitlist, form, file and communication URLs plus authentication,
application, platform, admin, webhook and utility routes are non-indexable.

The global anonymous primary action is **Start your trial** and points to the
real owner registration flow. Authenticated visitors receive **Open dashboard**.
No demo, contact, newsletter, social, integration, testimonial or customer
logo surface is published without an owned workflow and evidence. The detailed
route/indexation/claim contract is in `docs/frontsite/`.

Consequences: Prompts 15–27 may replace visible boilerplate and implement the
approved page set without treating OPEN-09 as permission to delete unknown
legacy data. Canonicals and schema use the configured application origin until
OPEN-11 is resolved. Legal pages remain noindex placeholders until named
counsel/DPO approval; provider, security, accessibility and operational gaps
remain launch blockers even when local front-site checks pass.

Evidence: `audits/2026-08-16-frontsite-discovery-seo-audit.md`,
`frontsite/README.md`, and `frontsite/content-and-claims.md`.

### ADR-025: Launch one reviewed `en-IN` public experience before locale expansion

- Status: Accepted
- Date: 2026-08-16
- Owners: Product, Engineering, Design, Privacy, and Legal
- Related requirements: PRD Sections 13, 15, and 17

Decision: Phase 1.5 declares one English-for-India public experience as
`en-IN`, with server-owned USD subscription prices under ADR-021 and
jurisdiction-qualified legal/provider content. It publishes no locale selector,
country routes, `hreflang` or global-availability claim. A second locale must
have equivalent reviewed content, commercial/legal/provider approval and
reciprocal path-prefixed metadata before it can be indexed.

Consequences: application message lookup remains `en`; public display uses
locale-aware browser formatting. Missing content never machine-translates or
falls back to an unrelated homepage. Entity IDs remain stable, while offer
currency, language and availability must match visible regional content.

### ADR-026: Separate front-site technical readiness from production launch authority

- Status: Accepted
- Date: 2026-08-16
- Owners: Product, Engineering, Design, Security, Privacy, Legal, Finance, and Operations
- Related requirements: PRD Sections 12, 13, 15, and 17; FR-01, FR-19, FR-20

Decision: Prompts 14–27 satisfy the local Phase 1.5 technical gate for a
controlled staging review: the curated public routes, claims, registration
preference, publication safety, crawl/indexation, structured data, internal
links, accessibility foundations, build budgets, and regression suite pass.
This is not authority to expose the site for production acquisition or permit
search indexing. Production public launch remains NO-GO until OPEN-10,
OPEN-11, and the applicable legal, provider, security, operations,
accessibility/browser, and target-load evidence in the Prompt 27 audit close.
The overall product remains NO-GO under Prompt 13.

Consequences: Staging must remain access-controlled or otherwise non-publicly
indexable while named owners review it. No team may reinterpret a local build,
test, crawl, budget, or Chromium screenshot as counsel approval, domain
ownership, provider certification, independent WCAG/security assurance,
production reliability, or field-performance evidence. The final gate and
owner/evidence matrix are in
`docs/audits/2026-08-16-frontsite-final-launch-audit.md`.

## Open decisions

| ID | Decision needed | Why it blocks or influences work | Resolve by | 2026-08-16 release disposition |
| --- | --- | --- | --- | --- |
| OPEN-09 | Approve the Prompt 00 remove/replace inventory for Larafast marketing, roadmap, AI, content, and superseded provider surfaces | Reduces dead code and prevents confusing product ownership | Before cleanup is authorized | **Retained.** Legacy surfaces remain quarantined; no destructive cleanup was silently assumed. Medium launch risk owned by Product/Engineering; named owner and date unassigned. |
| OPEN-10 | Final data retention and anonymisation schedule plus destructive executor authorization | Client privacy, attachments, audit events, financial records, and closure are safely bounded by ADR-018 but cannot complete destructive requests | Before destructive processing or paid public launch | **Retained; Critical blocker.** Requires named Indian privacy counsel/DPO and Product approval. No waiver or expiry exists. |
| OPEN-11 | Complete counsel-led trademark clearance and acquire `getgoodhours.com` | ADR-011 selects the identity, but naming rights, domain ownership, sender authentication, and defensive domains are external launch controls | Before public launch, outbound production mail, or printed collateral | **Retained; Critical/High blocker.** Requires named counsel and Product/Operations owner. No waiver or expiry exists. |
| OPEN-12 | Approve marketing attribution/analytics provider, consent class, retention, and accountable privacy owner | Phase 1.5 can implement a bounded first-party event contract, but cannot load a tracker, advertising pixel, fingerprinting, session replay, or claim consent/retention approval | Before enabling any third-party marketing measurement or paid acquisition | **Open; bounded by ADR-024.** Product, Privacy/DPO, and Engineering must name the provider/purpose, allowed properties, consent behavior, retention and deletion process. |
