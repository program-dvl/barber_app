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

- Status: Superseded by ADR-027
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

- Status: Superseded by ADR-039 for launch region, mobile-channel, and sender-ownership direction; retained as historical implementation context
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

- Status: Superseded by ADR-039 for target-market direction; retained as the currently implemented legacy commerce profile pending regionalisation
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

- Status: Superseded by ADR-029
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

### ADR-029: Stripe is the sole ClipperDesk SaaS subscription provider

- Status: Accepted
- Date: 2026-08-29
- Owners: Product and Engineering
- Related requirements: FR-01, FR-19, FR-20

Context: ClipperDesk requires Business-owned billing for a multi-user salon,
while the inherited LaraFast/Cashier schema is User-owned. ADR-021 selected
Paddle at the provider edge, but Product has now directed a complete move to
Stripe and removal of the Paddle runtime.

Decision: Stripe is the only selectable provider for new ClipperDesk SaaS
subscriptions. The existing application-owned `BusinessSubscription`, invoice,
payment, checkout-attempt, provider-event and entitlement aggregates remain the
authoritative local domain. The Stripe SDK/Cashier dependency supplies the
provider primitives, but the inherited User-owned Cashier tables and routes do
not own salon access. Checkout uses Stripe-hosted Checkout, payment method and
invoice management use the Stripe Customer Portal, and signed Stripe webhooks
are the authority for paid state.

The approved catalog is centralized in `config/billing.php`; checkout and plan
changes require an active command-managed local mapping that matches the plan
code, interval, amount and currency. `billing:sync-stripe-catalog` previews by
default. `--apply` verifies explicitly configured Price IDs without remote
mutation. `--provision` follows LaraFast's product/price command workflow but
targets the Business-owned catalog: it manages Stripe objects with stable
lookup keys and metadata, creates immutable replacements for amount changes,
commits effective-dated local mappings, and only then archives prior managed
Prices. Live-mode mutations require `--force`. Browser redirects never activate
access.

Business plan entitlements and Membership permissions remain independent and
both must pass. Trial expiry and exhausted dunning move the account to a
read-only state while billing, profile, data viewing and eligible exports remain
available. Downgrades that exceed a numeric limit never delete resources; they
are scheduled and retain a usage/limit snapshot for remediation.

Consequences: Paddle routes, handlers, provider adapter, frontend components,
catalog command and JavaScript package are removed. Historical Paddle rows and
already-applied migrations are retained as financial evidence. Any Business
with a paid Paddle external ID requires an explicit migration/reconciliation
runbook; only clean trials without external IDs are automatically relabeled.
Live launch requires a provisioned or explicitly mapped Stripe catalog and
webhook secret, verified catalog sync, webhook delivery/replay evidence,
Customer Portal configuration, and Finance/Legal ownership of taxes, refunds
and customer communications.

### ADR-030: Resolve workspace access from one membership, billing, and location decision

- Status: Accepted
- Date: 2026-08-30
- Owners: Product, Engineering, Security, and Operations
- Related requirements: FR-01 through FR-08, FR-14, FR-16, FR-18, and FR-19

Context: A successfully paid owner could see Calendar, Walk-in Queue and Reports
in navigation but receive a raw 403 or missing-report-location error. Owner
onboarding had not created a default Location, a completed Stripe Checkout had
not reached the local projection because webhook verification was unconfigured,
and navigation did not consume the backend's permission and entitlement state.

Decision: Verified owner onboarding must idempotently create or reuse one active
default Location and assign the Owner Membership. Existing Businesses receive a
forward-only corrective backfill without deleting or merging tenant data.
Authenticated feature entry and navigation consume `WorkspaceAccessService`,
which evaluates Membership permission, capability entitlement, and accessible
Location as separate gates. Role-denied entries are hidden; plan and setup gates
remain explainable. Expected denials render a product-owned unavailable state
with the appropriate status code instead of raw framework output.

Signed Stripe webhooks remain the primary external state authority. A stored,
tenant-owned pending Checkout Session may be recovered from authenticated
Stripe API evidence in the status endpoint or scheduled reconciler. Browser
parameters never supply provider status or identifiers. New Checkout fails
closed when the Stripe server credential or subscription webhook signing secret
is absent.

Consequences: Plan entitlements never grant a user role, owner role never
bypasses plan state, and a Location belonging to the Business does not grant an
unassigned employee Location access. Configuration completes the provisioned
Location rather than exceeding plan limits with a duplicate. Operators can
recover a dropped webhook without direct database edits, while deployed and
local environments must still configure and certify signed webhook delivery.

### ADR-031: Treat Salon setup and regional context as shared operating data

- Status: Accepted
- Date: 2026-08-30
- Owners: Product, Design, Engineering, Finance, and Operations
- Related requirements: FR-02 through FR-05, FR-11, FR-14, FR-15, and FR-19

Context: The inherited Settings form mixed Business identity, booking policy,
Staff, Availability, and launch status in one surface. A small hard-coded
country list and repeated raw phone inputs made regional behavior inconsistent.
Currency and tax posture could be stored without reliably updating the commerce
defaults that use them.

Decision: The owner-facing surface is **Salon setup**, a guided view of connected
operating configuration rather than a generic settings dump. It groups Business
details, bookable foundation, booking experience, import, and preview under an
explicit readiness overview. Readiness names required blockers and optional
improvements; it does not claim a vague percentage. Staff and Availability
remain owned by their dedicated workflows and are summarized, not duplicated.

Business country is the defaulting context for phone country, suggested
currency, locale, time zones, and address expectations. `CountryCatalog`
provides the complete ISO country set and derived suggestions; those suggestions
remain editable and are validated server-side. Phone values use one E.164
contract across staff-assisted and public workflows. Business currency and tax
posture synchronize transactionally to commerce currency, tax inclusivity, and
default tax rate. Historical monetary records retain their captured currencies;
an owner cannot casually change Business currency after services or appointments
exist.

Consequences: Regional choices affect booking display, checkout, inventory,
communications locale, phone entry, and future invoice/receipt adapters through
shared Business or commerce state. They are not legal or tax determinations.
Launch-market accounting, address, invoicing, privacy, and receipt rules still
require accountable review. New regional adapters must consume the shared state
instead of introducing new country or currency constants.

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

- Status: Superseded by ADR-039 for target-market direction; retained as the currently implemented public-site profile pending regionalisation
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

### ADR-027: Adopt ClipperDesk and one semantic enterprise design system

- Status: Accepted
- Date: 2026-08-25
- Owners: Product, Design, and Engineering
- Related requirements: PRD Sections 3, 4, 10, and 12; FR-01 through FR-20
- Supersedes: ADR-011 for current product identity, palette, typography, assets,
  domain direction, and message identity

Context: The product has been renamed from Good Hours to ClipperDesk. The prior
cream, pine, poppy, and editorial-serif identity no longer represents the
desired operational, premium SaaS position. The repository already had a useful
semantic CSS layer, but the implementation still mixed legacy palette utilities,
duplicated Jetstream controls, component-local colors, and repeated brand copy.

Decision: The current product name is **ClipperDesk** and the brand promise is
**Run the day. Grow the business.** The approved identity uses deep navy for
trust and structure, indigo for primary action, restrained cyan for recognition,
cool gray canvases, white working surfaces, and Manrope throughout. The mark is
a restrained interlocking C/D monogram without a heavy enclosing app tile. It
communicates software identity without generic salon clip art.

`config/brand.php` is the server-side source of truth for product/company names,
description, asset paths, public URLs, support identity, and the small public
palette projection. `resources/css/app.css` is the source of truth for semantic
color, typography, spacing, radius, elevation, focus, status, navigation, and
control tokens. Vue, Blade, Filament, PDFs, email, and SEO adapters consume those
sources rather than defining an independent theme.

Historical database columns, integration metadata, event identities, schema
versions, migration history, cache keys, and durable provider contracts that
contain `good_hours` or equivalent values are compatibility identifiers, not
visible brand. They remain stable unless a separately reviewed migration or
provider transition requires a change. Visible billing plan display names are
migrated with an exact-match, forward-only data migration so financial history
and external provider identifiers are not rewritten.

Consequences: Public acquisition, authentication, public booking, tenant work,
platform administration, billing, generated documents, email, browser metadata,
and icons share one ClipperDesk identity while retaining layouts appropriate to
their jobs. Production domain ownership, trademark clearance, sender
authentication, and formal legal operator approval remain external launch gates;
the configurable local defaults are not evidence those gates are closed.

Evidence or follow-up: The normative token and component rules are in
`docs/design-system.md`. The implementation and residual-identifier audit are in
`docs/audits/2026-08-25-clipperdesk-design-system-rebrand.md`; browser evidence
is stored under `docs/evidence/product-shell/`.

### ADR-028: Permit one verified, stateful Google authentication path

- Status: Accepted
- Date: 2026-08-25
- Owners: Product, Engineering, and Security
- Related requirements: FR-01, FR-05, FR-19, and FR-20
- Supersedes: ADR-013 only for the reviewed Google authentication path; magic
  links, generic Socialite drivers, and every other social provider remain disabled

Context: ClipperDesk needs lower-friction owner signup and returning-user access,
but the Larafast Socialite controller accepted an arbitrary provider, linked by
unverified email, created incomplete accounts, and retained provider access
tokens. A Google identity also cannot supply the Business name and legal
acceptance required by the approved owner-onboarding flow.

Decision: ClipperDesk exposes explicit, guest-only, rate-limited Google redirect,
callback, and signup-completion routes. Socialite remains stateful so its OAuth
state is validated. The callback accepts only a non-empty Google subject and a
Google-asserted verified email. An existing provider subject authenticates its
linked User; an unlinked subject may attach to an exact normalized email only
when that User has no different Google identity. Database constraints enforce
one User per provider and one User per provider subject. ClipperDesk stores the
provider subject but no OAuth access or refresh token.

A new identity receives a server-side, ten-minute registration continuation.
Only display name and verified email are shared with the page; provider subject
and selection evidence remain in the session. The owner must still provide a
Business name and accept the current terms. Completion creates the User,
registration intent, provider link, verified-email evidence, Business, Owner
Membership, and trial through the existing idempotent onboarding path. The
account receives an unguessable local password so the existing verified-email
password-reset path remains an independent recovery option. Google sign-in does
not bypass an already configured Fortify TOTP challenge.

Consequences: Google can be used for both sign-in and owner signup without
reintroducing generic drivers, silent tenant creation, provider-token retention,
or a two-factor bypass. A Google account already linked to another local identity
is rejected with a recovery-safe message. Provider-console consent-screen,
redirect-domain, key-rotation, outage, and production security review remain
operational launch responsibilities; enabled local credentials are not evidence
those controls are complete.

Evidence: `GoogleAuthenticationTest` covers the explicit redirect, existing
identity linking, token minimisation, verified-email enforcement, short owner
completion, exactly-once tenant bootstrap, conflicting-link rejection, and TOTP
challenge preservation. `TenantIsolationTest` proves generic Socialite and
magic-link route names remain absent while the three reviewed Google routes exist.

### ADR-032: Treat business activation as one guided journey across canonical workspaces

- Status: Accepted
- Date: 2026-08-31
- Owners: Product, Design, Engineering, Security, and Operations
- Related requirements: FR-02, FR-03, FR-04, FR-05, FR-06, FR-09, and ADR-014

Context: The configuration domain could already atomically create a complete
first bookable path, but its UI combined Location, hours, provider, availability,
Service, price, and capacity into one oversized form. The main Staff and Services
navigation still opened placeholders. This made onboarding appear complete in
the backend while the durable operating workspaces were unavailable.

Decision: Salon setup is the resumable launch task centre; Location, Team &
availability, and Services are canonical focused workspaces used both during
activation and after launch. Their write orchestration lives in one
`BusinessActivationManager` so retries, row locking, tenant validation,
entitlement limits, onboarding progress, and audit evidence remain consistent.
StaffProfile represents a schedulable provider independently from login-bearing
Membership. ReadinessEvaluator remains the only publish authority, and public
booking remains the only evidence that the assembled delivery path is usable.

The legacy all-in-one endpoint is retained as a compatibility bridge, not linked
from the owner experience. Service edits update reusable segment identities
rather than deleting rows referenced by historical appointments. Archive is a
status change and never deletes financial or booking history.

Consequences: A solo owner moves through a calm sequence and continues managing
the same records in the same workspaces after launch. New service/team/location
features must extend these domain contracts rather than reintroduce forms under
Settings. Bulk operations, templates, sophisticated schedule exceptions and
provider invitations remain separate follow-up increments and must preserve the
same tenant, impact-preview, and history rules.

Evidence: `BusinessActivationJourneyTest` proves the focused HTTP journey,
idempotent retries, tenant/role isolation, readiness/publish, real slot search,
hold, confirmation, Appointment creation, and Client creation.

### ADR-033: Use a visual, industry-led acquisition system without expanding clinical scope

- Status: Accepted
- Date: 2026-09-11
- Owners: Product, Design, Engineering, Security, and Privacy
- Related requirements: PRD Sections 1-4, FR-01 through FR-19, ADR-024, and ADR-027

Context: The verified public site accurately described the operating system but
presented it with abstract software diagrams and only four industry pages. The
result read as an enterprise software specification rather than an aspirational
brand for owner-operated service businesses. The approved Phase 1 capability
model already supports configurable services, staff, rooms, stations,
equipment, forms, booking, client context, checkout, and reporting across
several appointment-led operating patterns. Some adjacent audiences, however,
also have regulated clinical, health, age, consent, or animal-record needs that
ClipperDesk does not implement.

Decision: The public acquisition identity uses the promise **Your whole day.
Beautifully run.**, an editorial photography-led visual system, and distinct
indexable pages for barbershops, salons, independent stylists, spa and sauna,
nail salons, medspas, massage, fitness and recovery, physical therapy, health
practices, tattooing and piercing, pet grooming, and tanning studios. Each page
must describe a genuinely distinct scheduling, capacity, client-service, or
commercial operating pattern; it may reuse the common product capability set
but may not imply a vertical-specific module, customer relationship, guaranteed
outcome, certification, or regulatory approval.

Umbrella acquisition pages define ClipperDesk as appointment scheduling and
service-business management software for appointment-led beauty, wellness,
personal-care, fitness, recovery, health and pet-service teams. Salon and barber
language remains valid on their dedicated industry pages and as representative
examples, but must not present those two industries as the product's only
market. Search titles, descriptions, visible answers, image descriptions and
structured data must stay aligned with that broader, evidence-backed position.

Medspa, physical-therapy, health-practice, massage, tanning, tattoo/piercing,
and pet-grooming pages must state the relevant boundary. ClipperDesk is not an
EMR/EHR, clinical chart, diagnosis, prescribing, insurance, veterinary, or
healthcare-compliance product. Local licensing, consent, age, exposure,
retention, and care obligations remain the Business's independently reviewed
responsibility. Publishing these acquisition pages does not promote deferred
clinical, classes, memberships, marketplace, campaign, or medical-compliance
capabilities into Phase 1.

Consequences: ADR-024's curated solution set is expanded, while its
indexability, canonical, SSR, sitemap, claim-evidence, and conversion rules stay
in force. Realistic generated editorial imagery may represent an industry, but
alt text and nearby copy must not imply that the people or premises are actual
ClipperDesk customers. Product scope remains the shared booking-to-checkout
operating system; regulated vertical fit is explicitly non-clinical.

### ADR-034: Use a focused Stripe-hosted confirmation for owner plan changes

- **Status:** Accepted on 2026-09-13.
- **Context:** The former owner flow sent an immediate Subscription update with
  `pending_if_incomplete`, created local pending state first, and returned only
  a processing message. A card decline or required customer authentication had
  no completion surface, leaving the account displaying “Change pending.”
- **Decision:** ClipperDesk remains the plan-selection and entitlement policy
  boundary, but owner upgrades and interval switches finish in Stripe's focused
  Customer Portal `subscription_update_confirm` flow. Stripe previews and
  applies proration, handles authentication/payment failure, and redirects back
  only after completion. Signed webhooks remain authoritative, with an
  authenticated provider snapshot as the delayed-webhook recovery path. Owner
  self-service does not expose or accept lower-plan changes. Support retains the
  existing audited period-end downgrade capability required by the PRD.
- **Consequences:** No local access state changes before provider confirmation;
  annual-to-monthly switches preserve the paid term; duplicate clicks reuse an
  idempotent Portal session; generic billing management cannot become a hidden
  downgrade path; and Stripe credentials need Billing Portal configuration and
  session permissions in addition to Subscription/Product/Price access.

### ADR-035: Prepare new businesses from four reviewed decisions

- **Status:** Accepted on 2026-09-13.
- **2026-10-05 clarification:** ADR-053 supersedes the implicit preview/publication behavior below. Starter preparation now stays private until explicit owner review and go-live.
- **Owners:** Product, Design, Engineering, Security, and Privacy.
- **Related requirements:** FR-02 through FR-05, FR-09, ADR-030, ADR-032, and
  ADR-033.

Context: The canonical Location, Staff, Service, readiness and booking models
were already strong, but the first owner experience exposed their configuration
as work. New trial users could enter an empty product or face a large setup
form before experiencing a calendar or client booking page. “Salon setup” also
misrepresented the wider appointment-led business scope accepted in ADR-033.

Decision: New onboarding sessions use schema version 2 and collect four small,
resumable groups: business type, operating shape, first-location region and
starter services. Confirming those recommendations invokes one retry-safe,
audited provisioner that writes only to the existing Business, CommerceSetting,
Location, LocationHour, Membership, StaffProfile, StaffAvailabilityRule,
ServiceCategory, Service, ServiceSegment and assignment models. Generated
records and defaults are ordinary editable records, not a second configuration
system. ReadinessEvaluator remains the only publish authority.

The public owner surface is named Business setup. Setup state remains available
through a lightweight header drawer that separates essentials from recommended
refinements. If the reviewed starter workspace is valid, onboarding records the
preview and publishes it immediately so the completion state can show a live
booking page and calendar. If the owner is not bookable or another blocker
remains, the workspace stays usable and the drawer presents the next task.

Existing onboarding sessions are backfilled as guided-complete and receive no
starter data, configuration overwrite or automatic publish. Brand images remain
private tenant files and are exposed publicly only through an active, published
booking slug. An industry image is a labeled fallback, never evidence that a
pictured business or person is a ClipperDesk customer.

Consequences: Trial time-to-value becomes a prepared workspace instead of an
administrative checklist; owners can still change type, services, hours, team,
booking URL, policies and branding afterward. New business profiles remain
config-driven until a future scale decision justifies relational taxonomy.
Multi-location expansion, invitations, payments and advanced preferences stay
progressive tasks and continue to enforce their existing authorization and plan
limits.

Evidence: `GuidedOnboardingExperienceTest` covers persistence, generated
records, slug uniqueness, publishability, retry idempotency, regional validation,
tenant isolation, backward compatibility and private public-media delivery. The
2026-09-13 combined UX/accessibility audit captures the workspace entry,
readiness drawer, guided decisions and public booking entry.

### ADR-036: Keep front-desk work canonical, progressive and locally timed

- **Status:** Accepted on 2026-09-13.
- **Owners:** Product, Design, Engineering, Security, Privacy and Operations.
- **Related requirements:** FR-02 through FR-08 and FR-11 through FR-18.

Context: The underlying Appointment, Client, Location, Sale, communication and
reporting domains were already capable, but several owner/front-desk surfaces
made those capabilities hard to discover. Calendar views repeated one layout,
time placement was not proportional, actions looked fragmented, walk-ins could
remain detached from CRM, checkout exposed an implementation-oriented surface,
and report navigation advertised inventory records before the corresponding
user workflow was ready.

Decision: Calendar navigation exposes Day, Week and Team as separate models,
with Today as a date shortcut. All render from the Location's time zone on a
continuous proportional grid; simultaneous visits receive deterministic visual
lanes. Appointment mutation continues through the existing append-only
replacement and lifecycle services. Cancelled appointments and superseded
versions are excluded from the default active view but remain queryable through
explicit status filters and history.

A walk-in must reference an existing tenant Client or create one as part of the
same queue-entry transaction. Location setup creates separate canonical
Location rows only within the entitlement allowance. Suggested team titles are
editable descriptive vocabulary and never replace Membership roles or
permissions.

The current checkout UI records only money received externally and does not
imply card or online payment processing. It opens/reuses the canonical Sale,
records an idempotent full-balance tender, issues the existing receipt, queues a
transactional receipt email and drives the same reporting sources. Reports are
shown only when their source workflow is part of the visible product; inventory
report keys remain implemented internally but are hidden for this release.

Transactional appointment and receipt email is on when an email destination
exists unless the Appointment or Client has explicitly disabled email.
WhatsApp/SMS delivery is not surfaced until its product, consent and provider
release is deliberately enabled.

Consequences: Reception work is faster without creating parallel records or
weakening tenant, permission, capacity, audit, idempotency or time-zone rules.
The interface can later add online tenders, mobile messaging and inventory
reports without replacing the canonical Appointment, Client, Sale,
CommunicationIntent or report contracts.

### ADR-037: Refine authenticated workspace density and shared interactions

- **Status:** Accepted on 2026-09-13, per the product owner's refinement request.
- **Related requirements:** FR-02 through FR-09, FR-11 through FR-19; PRD usability and accessibility requirements.

Decision: Use an authenticated workspace density layer with 40px standard
fine-pointer desktop controls, 32px optional row actions and 36px menu options.
Restore at least 44px targets on mobile/coarse-pointer devices. Preserve body
readability and the existing brand; reduce excess spacing and duplicate
navigation before reducing typography. This clarifies the previous universal
44px rule for daily desktop operations, without reducing touch targets.

Shared native-option select enhancement owns search, selected/disabled states,
keyboard navigation, validation focus and viewport-aware popovers. Shared
dialogs own scrolling and stable action footers. Services and provider creation
use drawers; reports use a single searchable picker. Existing server endpoints,
permissions, audit reasons, money and scheduling rules remain authoritative.

At the user's explicit follow-up request, hide Inventory from the shared
navigation source used by desktop and mobile. Retain the underlying module,
routes and entitlements for later work; this does not authorize deletion or
expand the current phase. ADR-036's report visibility decision remains intact.

Evidence: [workspace refinement audit](audits/2026-09-13-workspace-refinement/README.md),
client/SSR builds, six menu boundary tests and the existing application suite.

Follow-up refinement: The user requested a collapsible calendar side panel.
It starts closed, yields its width to the schedule, and opens through a toggle
or appointment selection; mobile places it above the grid. The first hour label
stays within the grid boundary. Report presentation uses readable labels and
the report's time zone, with optional full source references; three additional
frontend tests verify UTC/offset/DST formatting and labels.

### ADR-038: Treat team access as a secure invitation attached to a canonical team seat

- **Status:** Accepted on 2026-09-14, per the product owner's team-access request.
- **Owners:** Product, Engineering and Security.
- **Related requirements:** FR-01, FR-05 and FR-19; ADR-008 and ADR-013.

Context: The tenant access foundation already separated User, Membership and
StaffProfile and supported secure invitations, starter/custom permissions,
revocation and audit evidence. The owner-facing Team workspace created only a
bookable StaffProfile, however, and explicitly deferred login access. Invitees
without an existing account also landed behind authenticated middleware, so
the secure token could not complete their account setup.

Decision: Team setup may create or update the canonical StaffProfile and issue
its Business-bound Membership invitation in the same owner action. Login access
remains optional because a schedulable person is not always a software user.
The existing `staff.max` entitlement remains the operational team-seat limit;
connecting a login to that profile does not create or charge a duplicate seat.

Invitation links remain hashed, single-use, expiring and bound to the exact
email, Business, role, locations and optional StaffProfile. Existing Users
authenticate normally. A new invited identity chooses its own password on the
invitation surface; possession of the emailed one-time token verifies that
email before the Membership is activated. ClipperDesk does not generate or
email temporary passwords.

Owners choose a reviewed starter role or an exact Business-scoped permission
set. A manager with staff-management access may grant only capabilities already
held by that manager and may never grant or remove Owner access. No member may
change or revoke their own access through Team management. Membership role,
permission and location changes are audited; revocation requires a reason,
invalidates sessions/tokens promptly and preserves StaffProfile and historical
actor attribution.

Owners and other `audit.view` roles receive a tenant-scoped Activity workspace
over append-only AuditEvent records. The UI presents searchable, categorized
summaries; ordinary users cannot edit/delete evidence and the existing audit
writer continues to redact credentials and tokens.

Owners may explicitly restore revoked Membership access with a new reason; the
person then signs in with their existing password rather than receiving a
second identity or an administrator-known credential.

Consequences: The owner experiences one coherent people-and-access workflow,
invitees can join without an administrator distributing credentials, and seat
pricing can rely on the existing team limit without double-counting login
identity. Production invitation delivery still requires the approved sender
domain and live transport certification.

### ADR-039: Multi-region messaging uses safe transport promotion and optional business-owned senders

- **Status:** Accepted on 2026-09-20, per the product owner's messaging and target-market direction.
- **Owners:** Product, Engineering, Security, Privacy, Operations and Support.
- **Related requirements:** FR-01, FR-13, FR-19 and FR-20; supersedes ADR-019 for communications direction and ADR-020/ADR-025 for target-market direction.

Context: ClipperDesk's initial target businesses are in the United States,
Canada, the United Kingdom and selected European markets, not India. Customers
need useful appointment communication before completing their own provider
onboarding, while established businesses need messages and replies to appear
from their approved business identity. Provider tests must not accidentally
send or spend, and a shared inbound number cannot safely infer a tenant from a
reply in production.

Decision: Email remains a separate application-owned channel. SMS and WhatsApp
share an application-owned mobile contract with two sender modes:

- **ClipperDesk messaging** is the default platform sender for consented,
  business-labelled outbound notifications. It does not offer a shared
  production inbox because replies cannot be assigned to a tenant reliably.
- **Your branded client line** is an entitled capability using an approved
  business sender identity. It may support direct replies and a tenant-scoped
  inbox only after provider and country-specific verification is active.

Every plan may include an explicit mobile-message allowance; the current
Starter and Pro values are 100 and 1,000 per billing period. Additional-credit
purchasing is intentionally deferred until the core delivery, callback and
reply workflow is externally certified. Branded sender ownership and two-way
messaging are separate entitlements from usage credits.

SMS and WhatsApp require channel-specific opt-in evidence for outbound traffic.
Transactional purpose never grants marketing consent. Recognised STOP and START
keywords update suppression and append consent evidence immediately. The
preferred mobile channel is queued first; a different configured fallback is
held and released only after a definitive failure, following a fresh consent,
template and sender-readiness check.

Transport promotion is explicit and configuration-driven: `fake` performs no
network request; `twilio_test` uses Twilio test credentials for zero-charge SMS
API simulation; `whatsapp_sandbox` can create real, potentially billable
Sandbox traffic only when its independent enable switch is true; and `live`
requires its own production-send switch. Production authentication prefers a
restricted Twilio API key, while the Auth Token or dedicated webhook token is
retained for request-signature verification. Credentials remain server-side
and encrypted when stored for a business sender.

Consequences: booking, queue, waitlist and payment events can select email plus
one consented mobile path without duplicate outreach. A provider-owned sender
can be introduced without changing the appointment workflow or rewriting
history. Sandbox is a controlled integration step, not a free or production
environment. Live launch remains blocked until the exact countries are chosen
and the relevant sender registrations, WhatsApp business/display-name/template
approvals, public HTTPS callbacks, legal wording, privacy/retention rules,
quiet-hour policy and end-to-end provider certification are complete.

The existing India/INR/`en-IN` commerce and public-site implementation is not
silently treated as suitable for the new market direction. It must be
regionalised and reviewed under OPEN-15 before a paid regional launch.

Evidence: `docs/modules/communications.md`, the FR-13 reliable communications
feature suite, client/SSR production builds, and the transport-lock tests.

### ADR-040: Use Amazon SES for ClipperDesk business-account email

- **Status:** Accepted on 2026-09-20, per the product owner's configured provider direction.
- **Owners:** Product, Engineering, Security and Operations.
- **Related requirements:** FR-01, FR-05, FR-13, FR-19 and FR-20; complements ADR-039 without changing the client mobile-channel contract.

Context: Owner and login-bearing team journeys need professional transactional
email for identity verification, security changes, workspace access and SaaS
billing. The repository had mixed boilerplate templates, no installed SES
runtime, no coherent trigger catalogue and no privacy-safe delivery evidence.
Client appointment messaging already has a different consent and tenant-brand
boundary that must not be weakened by account-email implementation choices.

Decision: Amazon SES is the production transport for ClipperDesk business-
account email through Laravel's application-owned notification layer and the
official AWS SDK. Account, Security and Billing are distinct configured sender
streams, even if they initially share one verified address. Critical state-
change notifications queue after transaction commit with bounded retries;
verification and password-reset links remain synchronous. Successful sign-in
email defaults to a new-browser/device alert rather than one email for every
routine session, with explicit `always` and `off` deployment modes.

Every mail-channel attempt writes a metadata-only delivery record with masked/
hashed recipient evidence and provider message ID. Message bodies and plain
recipient addresses are not copied into the ledger. A transport acceptance is
not represented as inbox delivery. Client appointment and receipt email remains
in the FR-13 Communications outbox with its existing consent, template,
idempotency and fallback rules.

Consequences: AWS credentials can be rotated without changing product code;
local/test environments remain non-sending by default; workers must consume the
`emails` queue; and support can distinguish sending, accepted and failed
attempts without exposing message contents. OPEN-11 continues to block real
production-domain sending. SES sandbox exit, SPF/DKIM/DMARC, custom MAIL FROM,
bounce/complaint event destinations, alarms, suppression handling and a live
deliverability rehearsal remain Operations launch gates.

Evidence: `docs/modules/business-email-notifications.md` and the focused
business-email journey feature suite.

### ADR-041: Launch client mobile notifications as essential SMS only

- **Status:** Accepted on 2026-10-01, per the product owner's direction to defer WhatsApp and focus on text notifications.
- **Owners:** Product, Engineering, Security, Privacy and Operations.
- **Related requirements:** FR-13 and FR-20; narrows the initial user-facing scope of ADR-039 without deleting its provider abstraction or historical evidence.

Context: The multi-channel foundation proved sender, consent, fallback and inbox
boundaries, but its Sandbox and channel choices made the first owner and booking
experience harder to understand. The immediate product need is dependable text
notification for important appointment events, with minimal client fatigue and
a straightforward path from local simulation to registered live sending.

Decision: The initial client-facing mobile channel is SMS. Owner and public
surfaces expose email and SMS only. SMS requires explicit channel consent and
every message identifies the business. The default cadence is a booking/status
message when needed, material changes/cancellation, payment or deposit actions,
requested waitlist/queue alerts, and one reminder normally 24 hours before the
appointment. Promotional feedback and rebooking texts, cross-channel fallback,
free-form replies and the client inbox are disabled.

The shared ClipperDesk sender remains the default. An entitled business may
prepare an approved branded SMS line, but it cannot be selected until active.
Inbound SMS remains enabled for STOP/START compliance. The application keeps
dormant provider adapters and historical records, while a server-side enabled-
channel allow-list prevents new deferred-channel messages and suppresses old
queued work before any provider call. No historical consent or delivery evidence
is deleted.

Consequences: The booking and owner journeys become simpler, delivery costs and
client interruptions are predictable, and live promotion requires only SMS
sender registration for the chosen launch country. Reintroducing another mobile
channel, fallback or conversational inbox requires a later accepted decision,
updated consent wording, provider certification and renewed UI/test coverage.

Evidence: `docs/modules/communications.md`, the FR-13 focused suite, public
booking coverage, the SMS-only data migration and client/SSR production builds.

### ADR-042: Compact, plain-language presentation across existing capabilities

- **Status:** Accepted on 2026-10-02, at the product owner's request.
- **Related requirements:** FR-01–FR-20; PRD sections 3, 4, 10 and 12.

Use shared semantic typography, density and interaction components across shop,
public booking, account and platform administration. Use “appointment” for a
scheduled visit and “booking” for reserving it or the public booking page.
Dashboard view names describe their layout: Appointment list, By staff, Now &
next. Keep compatible stored preference keys and all user-entered business data.

UI state must match implemented payment, delivery and publishing behavior.
Readable audit summaries are read-only projections of whitelisted changes;
they never modify historical payloads. The refinement does not add deferred
features, enable quarantined routes, or waive production launch gates.

Evidence: `docs/modules/product-experience.md` and the dated platform refinement
audit; individual verification claims belong in project-status.md.

The owner's 2026-10-02 StepES reference follow-up refines this contract with
light operational navigation, a pale canvas, shared breadcrumbs, 26px workspace
headings, restrained statistics cards and aligned searchable records. Public
controls retain their existing touch-friendly sizing. Reusable authenticated
record composition is loaded separately from the public core stylesheet. This
changes presentation only; it does not alter reporting calculations or the
release scope. Evidence: the StepES-inspired follow-up audit.

The subsequent owner-requested surface refinement distinguishes light navigation,
canvas, toolbar and table-heading surfaces, with navy ink and selective existing
indigo accents. These are shared presentation tokens, without a new product mode.
Evidence: the 2026-10-02 surface-hierarchy audit.

The owner's next request keeps content surfaces and selects primary-action blue
for left navigation and mobile drawers, with inverse brand/text and a cyan
selection edge. Signed-in headers receive a subtle blue wash and clearer identity
and account controls. This supersedes the light-navigation color choice only;
navigation structure, permissions and workflows remain unchanged. Evidence:
the 2026-10-02 blue-shell audit.

The owner's brand correction replaces that action-color navigation with the
public frontend's brand-primary navy (`#172554`); action-primary indigo remains
the CTA color. Account disclosures use compact icon rows, an account identity
section, sentence-case multi-workspace billing grouping and a separated Sign out
action. Existing permission-filtered billing destinations and native disclosure
keyboard behavior are preserved. Evidence: the 2026-10-02 brand-account-menu audit.

### ADR-043: The dashboard is a permission-aware daily workspace

- **Status:** Accepted on 2026-10-02, under the product owner's explicit dashboard redesign authorization.
- **Related requirements:** FR-03, FR-05–FR-08, FR-11–FR-12, FR-15–FR-18; PRD sections 10 and 12.

Prioritize live operational work and resolvable exceptions before supporting
financial information. Use capabilities rather than title/role-name layouts.
Calendar-own visibility fails closed without a linked StaffProfile. Location,
contact, appointment-note, checkout, revenue and inventory access are enforced
before serializing dashboard data. Existing calendar/lifecycle/checkout/report
services remain authoritative; no duplicate scheduler or advice engine is added.

FR-18's staff availability is presented as **Staff scheduled**: working windows
after breaks/leave, intersected with opening hours. This avoids implying attendance
or bookable resource capacity. Expected revenue is labelled **Sale value** with
its open/completed-sale definition. Reporting formulas and append-only money
history are unchanged. Queue counts include waiting, notified and assigned
entries that have not started service. Historical/future dates never claim that
appointments are occurring now or overdue relative to today's clock.

Dashboard appointment details are bounded to 100, prioritizing active visits
before finished history, with an explicit completeness warning and exact status
aggregates. Tablet/phone attention uses a compact summary with explicit disclosure. The visible idle page refreshes every minute;
mutations and open drawers pause it. Existing per-user device view keys remain
compatible, with Now & next as the default for users without a saved preference.
Financial comparisons remain in Reports; the known previous-period defect is
not used to invent a dashboard trend. Attendance, general tasks, client
memberships, subscription performance and generated insights remain outside this
slice because they have no approved/implemented operational data source.

Consequences: operational actions reuse version/idempotency, authorization, audit
and notification rules. Exact appointment links preserve date/location. Checkout
links to older unpaid visits require a complete, location-scoped lookup, so the
checkout entry and record operations now enforce that same location boundary.
The linked calendar applies the same contact/note redaction, preserves unreadable
fields during schedule changes and restricts note edits; its print path checks
location assignment. Inventory remains outside the visible operations release
under ADR-036; this redesign does not promote it.
This does not waive live-provider or production release gates.

Specification: [daily workspace](modules/dashboard.md). Evidence:
[audit and verification](audits/2026-10-02-daily-workspace/README.md).

### ADR-044: Calendar is the staff-first operational schedule

- **Status:** Accepted on 2026-10-03 under the product owner's explicit full Calendar redesign authorization.
- **Requirements:** FR-03–FR-08, FR-11, FR-15, FR-19; complements ADR-015/016/037/043.

Default to a dense Team day with time vertically and staff horizontally. Retain
Day and Week and add a deliberate Agenda/mobile presentation. Month and recurring
series generation are not promoted. One toolbar, restrained status cards, working
windows, unavailable periods and contextual drawers replace the stacked controls.

Selected-range appointment filters never alter capacity. Project unfiltered staff
segments, blocks, active holds and other-location commitments; released processing
time is distinct. A gap means free staff time, with service qualifications, duration,
resource capacity and booking policies revalidated by the existing atomic commands.
Drag/reassignment/resize opens a review with an explicit reason before committing.
Current-day operational status follows persisted lifecycle state, not inferred attendance.

Explicit dated closures and full-day leave take precedence over overnight carry.
Timed unavailable rules are checked as real intervals in both the projection and
booking validator. Clock-change dates pause wall-grid drag/slot creation and direct
users to the booking drawer; UTC capacity remains authoritative. Historical visits
remain visible when staff are inactive or no longer assigned to the location;
only active assigned staff are offered for booking.

Existing-client lookup is bounded and tenant/own-history scoped; contact fields
require contact permission. Explicit client identity participates in new-command
hashes; null identity preserves existing hashes. Replacements retain the original
canonical client, including inactive client records, without creating duplicates.
Existing-client booking resolves saved contacts before form validation and removes
international mobile display formatting without guessing a country code. A valid
saved number is reused automatically. Contact-authorized operators may supply an
optional appointment-only number when the saved mobile is missing or invalid;
permanent profile corrections remain in the existing authorized/audited client
workflow. The drawer shows the saved number or the editable fallback, clears stale
contact errors on selection, and retains contact redaction for restricted roles.
New selected-client bookings/copies require an active client. Checkout readiness
comes from canonical sales and is serialized only with checkout permission.

View/filter device preferences are per user/business, contain no client data and
never override explicit URL state or authorization. The visible idle page refreshes
once per minute; input, open disclosures/drawers and mutations pause it. Navigation
preserves browser history and filtering preserves schedule scroll.

Specifications: [scheduling operations](modules/scheduling-operations.md),
[calendar client linkage](modules/client-records.md). Evidence:
[Calendar review and verification](audits/2026-10-03-calendar/README.md).

### ADR-045: Walk-ins are a live front-desk operations board

- **Status:** Accepted on 2026-10-03 under the product owner's explicit end-to-end Walk-in Queue redesign authorization.
- **Requirements:** FR-03–FR-08, FR-11, FR-15, FR-19; complements ADR-015/016/037/044.

Keep waiting clients primary, with permanent real positions, a NEXT marker,
elapsed waits, requested services and staff preferences/assignments. A compact
team panel exposes available gaps and upcoming reservations; in-service and
recently finished visits connect to authorized completion and canonical checkout.
Search and filters preserve actual order. Use the existing statuses; introduce no
priority, VIP, hold or automatic-assignment policy. Reasoned queue overrides
require both queue management and schedule-override access in the assigned branch.

Share Calendar's authoritative availability projection for an ordered, conservative
qualified-staff forecast, including variants, closures, breaks, bookings, blocks,
holds and cross-location work. Arrival and refreshed estimates use this same
method. Forecasts are advisory ranges; full staff/resource rules are checked on
selection and commit. Do not invent an end time for a service running over its
planned finish. Reception may save a qualified assignment without reserving time.
A configured booking interval still governs appointment time. Expired fit checks
must be repeated before a new service starts.

Minimal canonical client intake stays in the drawer; saved contacts are reused
and normalized without editing the profile. Protect contact/note permissions.
Queue-add replay is idempotent. Conversion/start/lifecycle/history commit together,
and Calendar starts/terminal transitions/replacements synchronize linked queue
records. Once converted, Calendar owns reservation changes and cancellation.
Completion hands off to the existing Sale checkout; no new payment behavior.

Use one elapsed-time clock and 30-second visible idle polling. Keep the board
visible during refresh; pause for user input, filter/recent disclosures, dialogs
and mutations, but not the expanded team panel. Replace stale forecast precision
with a refresh cue. Reuse the compact Calendar shell and native contextual drawers;
phones use cards and a collapsible team strip. Plain operational messages such as
“Walk-in added to the queue.” replace internal estimation terminology.

Specifications: [scheduling operations](modules/scheduling-operations.md),
[client linkage](modules/client-records.md). Evidence:
[Walk-in audit](audits/2026-10-03-walk-in-queue/README.md).

### ADR-046: Clients is an operational salon CRM over governed records

- **Status:** Accepted
- **Date:** 2026-10-03
- **Requirements:** FR-11/12/13/15/19; complements ADR-018/043/044/045.
- **Basis:** Owner's explicit end-to-end Clients improvement request.

Directory prioritizes identity, last/next visit, completed visits and explicit staff
preference. Default profile is a readable overview; editing, notes and supporting
form/file/privacy tools are deliberate actions. Authorized appointment summaries
and server-paginated history share staff/location scopes. Financial summaries read
the canonical sale ledger with currency groups and separate net receipts, open
balance and awaiting-checkout counts; they never combine currencies or treat a
booking quote as a payment. Contact-redacted edits preserve canonical contacts.
Manual intake warns before creating a different name with a matching contact;
existing conservative matching and previewed/versioned/reasoned merge remain.
Calendar and Queue receive server-authorized active identities and reviewable
current defaults. No Phase 2 client benefit instruments or new messaging flow is
promoted. The client-records specification is restored before these changes.

### ADR-047: Checkout is a reviewed basket followed by received-payment recording

- **Status:** Accepted
- **Date:** 2026-10-03
- **Requirements:** FR-14/15/16/17/18/19; complements ADR-020/039/044/045/046.
- **Basis:** Owner's explicit end-to-end Checkout & Sales improvement request.

Restore `modules/checkout-sales.md` before changing checkout. Completed appointments
and converted walk-ins remain the checkout aggregate. A server preview resolves
booked snapshots, eligible current service variants and location retail stock.
Saving binds to the exact quote and freezes sale lines; reasons accompany removal,
price and booked-performer changes. Owner/manager authority is required for price
or booked-performer overrides and discounts above the configured limit. Ordinary
discounts require `discounts.apply`; browser approval flags are not authority.

Same-appointment, same-currency deposits must have succeeded payment evidence.
Application is capped, idempotent and distinct from new tender. A fully prepaid or
zero-value sale completes without a fabricated payment. Stock and commission/tip
entries follow existing completion and reversal rules. Partial and split receipts
use Sale-root serialization; a split commits all ledger/audit/stock/commission
changes together. Pay later leaves an open balance and creates no tender.

Manual cash, external card, UPI, transfer, link and custom method entries only
record money already received; they never initiate a charge. Persist uncertain
payment/refund payloads and their keys per user, business and sale, recover with
exact replay, and reject changed-key payloads. Refund/void records remain append-only
and require original tender, reason, confirmation, cumulative item bounds and
explicit product disposition. Provider-managed payments remain blocked from this
manual refund flow under OPEN-13/16. Later adjustments do not rewrite receipts or
reopen settled sales for collection. Separate Revenue permission gates paginated
business history; Checkout permission retains specific operational visit detail
and receipt access. Accountants can inspect history without checkout authority.

Use compact desktop context with a bounded sticky summary and a phone payment
shortcut that keeps the due visible. Preserve Calendar, Clients and Queue handoffs.
No standalone unlinked sale model, Phase 2 benefit instruments, online payment
certification, new receipt-delivery consent bypass or financial allocation-transfer
UI is inferred. Existing cash-close/report services remain available; the bounded
screen does not claim a new till-close or tax-configuration console.

## ADR-048 — One workforce workspace with shared capacity and reviewed edits

- Date: 2026-10-03
- Status: Accepted
- Requirements: FR-03–FR-06, FR-08, FR-17–FR-19

Team & availability is one workspace with Team, Weekly coverage, Time off and
manager-only Access. A compact staff drawer contains Overview, Schedule, Services
and permissioned Access. The existing Calendar capacity projection is the single
source for effective windows, unavailable bands and reservations. The weekly view
shows staffing coverage; free staff time is not a guarantee of service/resource
fit. Overdue in-service work cannot be labelled available. Reserved-time ratios
are operational capacity context, not attendance or employee performance scores.

Calendar-all operators may read assigned-branch coverage; calendar-own operators
see only their linked staff profile. No self-service schedule writes or leave
approval model is inferred. Managers may edit only within every assigned work
branch, and may grant only capabilities/branches they themselves control. Work
locations and Membership login-access locations stay separate. Owner and current
account protections, tenant binding and existing notification/audit behavior stay
in force. Login-only accounts remain reachable without inventing booking profiles.

Schedule edits require a stored revision and an exact normalized-content hash,
15-minute impact preview, and staff/location locks compatible with atomic booking.
Every future occupying visit outside the proposed windows is reviewed, including
existing exceptions; changed appointment versions invalidate review. Conflicts
must be resolved in Calendar or explicitly retained with a reason. Active affected
holds block reductions until resolved or expired. Dates/times, split shifts,
recurring/dated blocks and cross-zone branch overlap are validated on the server.
Business closures/special hours affect capacity, while outside-hours warnings
preserve entered hours. Schedule writes audit before/after and exact applied
replays create no additional changes. The legacy staff-availability endpoint uses
the same contract. Expired dated exceptions remain intact and are excluded from
current editing. New exceptions need a current/future end date. Coverage shows
seven days, with four upcoming shift days and two weeks of reservation context.

Current service qualifications, duration, price and online visibility are versioned;
existing appointment/sale snapshots and commission/tip entries remain unchanged.
Future-effective variants are preserved and cannot be silently overwritten by the
current-only editor. Qualification removals used by future visits require a reason;
active holds protect affected qualification and bookability changes. Minimal
creation may omit hours, services and login; exact create retries do not rewrite
existing profiles. Permissions use existing business starter/custom roles rather
than professional titles. Commission statements add balances per recorded currency.

No HR payroll fields, leave approval, attendance tracking, external calendar sync,
travel policy, schedule notifications, or Phase 2 scope is inferred. OPEN-19 records
the remaining dated/overnight/travel editing contract. See the
[workforce specification](modules/team-availability.md) and
[local review evidence](audits/2026-10-03-team/README.md).

## ADR-049 — One reviewed salon catalogue preserves booked and financial truth

- Date: 2026-10-04
- Status: Accepted
- Requirements: FR-03–FR-09, FR-14–FR-19

Services owns one dense catalogue with inline categories/add-ons and an explicit
three-section drawer. Existing settings.manage and full work-branch scope govern
configuration. Reception consumes this truth through booking/Queue/Checkout.
Staff > branch > base pricing, effective staff variants, canonical segments,
resources and historical snapshots remain the shared operational contract.

Current changes require configuration/upcoming revisions, booking-compatible locks,
live-hold protection and reasoned impact review for future high-impact work. Retire
and version assignments; preserve segment/resource identities and effective dates.
Exact UUID/payload saves commit idempotency with the change and audit. Recover
uncertain requests with exact replay; protect meaningful unsaved changes. No edit
silently publishes/reactivates or rewrites existing appointments, sales or ledgers.

Categories reorder accessibly and cannot archive active children. Copies start
inactive/internal with new identities and remapped resource segments, without
historical records. Import is a new-service setup path: existing rows are skipped
and changed through the reviewed catalogue, never an unreviewed bulk pricing/status
update. Imported duration/currency/name/price and older previews are validated.

Calendar displays eligible dated variants, selected-professional duration and
estimated current price; booking commit remains authoritative. Public add-ons need
attached selected parents; checkout enforces parent eligibility for added add-ons.
Service deposit none inherits global policy. Tax category is a reference; actual
tax remains CommerceSetting/recorded booked tax. No tax exemption, Phase 2 benefits,
new image-upload contract or paid online confirmation is inferred. OPEN-19/20 and
OPEN-16 bound unsupported dated editing, service-rule extensions and deposits.

See [Services specification](modules/services.md) and
[browser/automated evidence](audits/2026-10-03-services/README.md).

## ADR-050 — Prepare editable categories with business-type starter services

- Status: Accepted on 2026-10-04 following the product owner's request.
- Requirements: FR-02, FR-04; clarifies ADR-035 and ADR-049.

New-business onboarding shows a small configured category set alongside the
existing selectable starter services. The chosen business type determines the
set, rather than seeding every business with unrelated salon categories.
Confirmation creates all suggested categories, including empty groups, and only
the selected services with their correct category references. These are ordinary
business-owned categories, freely renamed, reordered or archived in Services.

The existing starter transaction and completed-session guard remain authoritative.
Reuse tenant categories case-insensitively and preserve existing names, order and
archive preferences; append missing groups without restoring archived ones.
Starter services in an archived group remain inactive/internal. Record newly
created category IDs/count alongside service evidence. Existing service catalogues
and completed sessions receive no automatic seed, overwrite or backfill; later
changes of business type never regenerate the catalogue.

Category definitions stay in the same configuration as the service templates.
The combined haircut/beard appointment belongs to Combined services; it does not
introduce Phase 2 package instruments. The change is additive to schema version 2
and requires no migration or new shared/global category table.

Evidence: [starter-category review](audits/2026-10-04-starter-categories/README.md)
and `GuidedOnboardingExperienceTest` cover all supported profiles, selected-service
mapping, empty groups, reuse/archive/order preservation, tenant isolation, ordinary
category edits, retry safety and existing-catalogue protection.

## ADR-051 — Reports shares operational truth with explicit analytical bases

- Date: 2026-10-04
- Status: Accepted; implemented directly in `/Applications/AMPPS/www/barber_app`
- Requirements: FR-18, FR-14–17, FR-19

Reports defaults to a historical Business overview with searchable report groups,
compact metrics, period comparisons and record investigation. It reuses shared
product controls and operational source links. SQL aggregates cover the complete
filtered scope independently of detail pagination. CSV generation streams the
same projection with requester/location/own-staff permission checks at request,
generation and retrieval; print is a bounded summary with complete totals.

Sale-cohort receipts preserve Dashboard/Checkout's recorded paid + applied deposit
− all returns basis. Transaction-date payment activity is a separate report and
excludes deposit collections. Immutable line values retain their original tax
basis; return allocations, tax and compensation are never reconstructed from
current catalogue prices or newly inferred rules. Reports selects one recorded
currency and independently enforces own-calendar and own-compensation scope.

Location-local calendar boundaries and comparisons use UTC half-open bounds;
mixed-zone aggregate scope requires a branch selection. Paying-client
classification counts distinct clients from first completed sales across permitted
branches/currencies. It does not claim appointment retention. Capacity reuses
Calendar windows, breaks, leave, closures and blocks, unions occupied segments,
and discloses that retained configuration is not historic attendance. Prior-period
capacity comparison is unavailable without historical schedule snapshots.

Membership/package/gift-card/recurring-billing analytics remains Phase 2. Inventory
navigation remains hidden under ADR-036. No forecast, external benchmark,
composite staff score, scheduled reporting or accounting liability is inferred.
OPEN-14 is further narrowed by the restored Reports specification; existing
launch, regionalisation and operational decisions remain unchanged.

See [Reports specification](modules/reports.md) and
[review evidence](audits/2026-10-04-reports/README.md).

## ADR-052 — Client notifications is an operational workspace with SES and Twilio

- Date: 2026-10-04
- Status: Accepted following the product owner's end-to-end redesign and explicit provider instruction.
- Requirements: FR-13, FR-06, FR-08, FR-14, FR-19; clarifies ADR-039/041.

Client notifications combines searchable automations, safe versioned template
editing, scoped delivery investigation and separately saved channels/timing.
Ten event rules are connected; two deposit templates remain visibly prepared
because no upstream deposit events invoke them. Neither a per-rule toggle nor
test-send infrastructure is inferred. One 24-hour reminder, essential SMS consent,
quiet hours, STOP/START and release locks retain ADR-041. Multiple reminders,
marketing, deferred mobile channels, inbox and Phase 2 benefit messages are not
promoted by this redesign.

Client email now uses AWS SES with existing environment credentials and configured
mail sender; SMS uses Twilio. This replaces the client Resend adapter for new
attempts while retaining historical callbacks. Account/security email stays a
separate ledger. SES SendEmail has no native idempotency parameter, so SDK retries
are disabled, explicit throttling alone permits automatic retry and unknown
acceptance requires investigation. Uncertain retained attempts cannot silently
switch providers. Configured-topic SNS signatures provide callback evidence;
credential presence is not a live delivery certificate.

Manager writes use row locks, current revisions and reasoned retry. Publication
cannot change queued snapshots. History omits message bodies, secure links and
credentials; tenant, assigned-branch, own-calendar and financial read boundaries
apply to projections and commands. Client and Calendar handoffs share the same
scope. Explicit client preference withdrawals survive profile edits and are
rechecked before delivery. Receipt actions view issued immutable receipts rather
than implying another payment or generating financial history.

No real provider send or launch waiver was performed. See
[Communications specification](modules/communications.md) and
[review evidence](audits/2026-10-04-client-notifications/README.md).

## ADR-053 — Business setup separates operational readiness from online publication

- Date: 2026-10-05
- Status: Accepted following the product owner's complete onboarding review and explicit instruction to avoid publishing unfinished businesses.
- Requirements: FR-01–FR-05, FR-09, FR-13–FR-15, FR-19; clarifies FR-02 and supersedes ADR-035's automatic preview/publication clause.

Preserve the four resumable decisions and versioned, transactionally prepared
starter workspace. Owners review and personalize ordinary tenant records rather
than entering a blank product. Generated service IDs retain provenance; keeping,
editing or deactivating suggestions never rewrites operational history. Empty
starter selection is valid. Completed sessions cannot reseed, and businesses
with publication, appointments or sales cannot restore starter defaults.

Business setup becomes a persistent readiness center. One backend projection
checks six operational essentials against current configuration: profile/region,
active location with opening hours, valid current services, active team, a
qualified service/staff/branch combination with usable overlapping hours, and
booking interval/cancellation window. The four-week configuration horizon is
explicit and does not promise an unoccupied slot. Date-effective assignments,
branch membership, resource eligibility and current working rules apply. Internal
operation does not require online visibility, publication, a logo, another staff
member, imports or connected payment/communication providers.

Preparing a workspace never marks the private preview reviewed or publishes.
Online publication additionally checks public contacts/policies/slug, online
service/staff paths and explicit review. The owner chooses Go live. Existing
published businesses retain their state; pause/resume remains explicit. A preview
shows only services with valid online paths and describes base-price variants.
Live delivery/provider or legal approval is not inferred from configuration.

Focused profile, weekly hours, service and team edits reuse canonical domain
commands. Hours changes bind a short-lived impact review to exact windows,
revision and upcoming appointment versions, recheck holds, retain booked visits
and audit once on replay. Business profile and commerce save atomically with
slug changes. Settings-capable branch managers cannot edit business-wide setup
unless assigned every location; Owners retain business authority. Advanced
Services and Team workspaces keep their existing scopes.

No Phase 2 promotion, real provider send or launch waiver is approved. Existing
OPEN-13/15/16/19/20/21 remain applicable. See [Business setup specification](modules/business-setup.md)
and [screenshots, six-perspective review and verification](audits/2026-10-04-business-setup/README.md).

## ADR-054 — One plan priced by locations and bookable staff, with local rates and SMS credits

- Status: Accepted direction; commercial rates and live certification pending.
- Date: 2026-10-05.
- Integration authorization: on 2026-10-05 the user supplied
  `subscription-billing-update.zip` and requested its subscription/billing code
  be integrated into this source checkout. The package records this capacity
  direction, Twilio/SES cost accounting and country-local pricing. This import
  authorizes the source integration; it does not approve draft commercial amounts
  or activate paid provider operations.
- Decision: one base plus additional-location/additional-bookable-staff quantities.
  Count global active StaffProfiles once; do not charge login-only administrators.
  Use immutable country/currency/cadence rate cards. Preserve old subscriptions
  until explicit migration. Verified provider quantities own purchased limits.
- SMS: pooled monthly allowance, actual rendered segments and approved destination
  multipliers, optional prepaid packs, no automatic purchases. Include routine
  operational SES email. Keep ADR-041's SMS-only client channels and marketing/
  branded-sender/inbox exclusions. This supersedes the extra-credit implementation
  deferral, while retaining all external provider/refund/paid-launch gates.
- Billing: actor/version/expiry-bound review; pending paid upgrades, renewal
  reductions, fixed proration date, signed or authenticated provider verification,
  stable idempotency and append-only credit history. No payment success from URLs.
- Prices: USD 29 base/19 extra location/9 extra staff and illustrative text packs
  are draft examples only. Country rates must be separately approved and verified.
- Consequence: production stays disabled by default. Additional countries need
  explicit local cards and verified Stripe prices; no currency conversion guess.
- Evidence and contract: `modules/subscription-billing.md`, capacity/lifecycle/
  webhook/communications tests. Real provider, tax, refund/dispute and MySQL
  concurrency certification remain required before release.

## Open decisions

| ID | Decision needed | Why it blocks or influences work | Resolve by | 2026-08-16 release disposition |
| --- | --- | --- | --- | --- |
| OPEN-09 | Approve the Prompt 00 remove/replace inventory for Larafast marketing, roadmap, AI, content, and superseded provider surfaces | Reduces dead code and prevents confusing product ownership | Before cleanup is authorized | **Retained.** Legacy surfaces remain quarantined; no destructive cleanup was silently assumed. Medium launch risk owned by Product/Engineering; named owner and date unassigned. |
| OPEN-10 | Final data retention and anonymisation schedule plus destructive executor authorization | Client privacy, attachments, audit events, financial records, and closure are safely bounded by ADR-018 but cannot complete destructive requests | Before destructive processing or paid public launch | **Retained; Critical blocker.** Requires named Indian privacy counsel/DPO and Product approval. No waiver or expiry exists. |
| OPEN-11 | Complete counsel-led ClipperDesk trademark clearance and acquire the approved production domains | ADR-027 selects the identity, but naming rights, domain ownership, sender authentication, and defensive domains are external launch controls | Before public launch, outbound production mail, or printed collateral | **Retained; Critical/High blocker.** Product/Operations and counsel must approve and acquire the final public, app, and booking domains; configuration defaults do not establish ownership. No waiver or expiry exists. |
| OPEN-12 | Approve marketing attribution/analytics provider, consent class, retention, and accountable privacy owner | Phase 1.5 can implement a bounded first-party event contract, but cannot load a tracker, advertising pixel, fingerprinting, session replay, or claim consent/retention approval | Before enabling any third-party marketing measurement or paid acquisition | **Open; bounded by ADR-024.** Product, Privacy/DPO, and Engineering must name the provider/purpose, allowed properties, consent behavior, retention and deletion process. |
| OPEN-13 | Approve the legal operator/contact suite and live refund responsibility for subscription and appointment payments | Stripe India website review expects public Terms, Privacy and Return/Refund/Cancellation URLs plus customer-service details and processing timelines; the current appointment adapter records internal refunds but has no certified provider refund executor or approved live merchant-of-record/connected-account allocation | Before legal documents become effective or any live payment is accepted | **Open; Critical/High blocker.** Legal/Product must approve operator, address, governing/dispute/liability terms and version acceptance; Finance/Operations must own direct support, request/decision/submission timelines, and the live Stripe subscription and appointment refund/reconciliation paths. The 2026-08-25 drafts remain `noindex` and non-effective. |
| OPEN-14 | Restore the remaining module documentation referenced by AGENTS.md | Communications, dashboard, product experience and scheduling specifications are restored; other domain module documents remain absent or partial | Before the next affected domain scope change | **Partially resolved.** `docs/modules/communications.md`, `dashboard.md`, `product-experience.md` and `scheduling-operations.md` are restored; `client-records.md` now restores CRM, history, governed commands and operational linkage (ADR-046). `checkout-sales.md` now restores checkout, payments, receipt and refund boundaries (ADR-047). `team-availability.md` now restores workforce, reviewed schedule/service changes and read/access boundaries (ADR-048). `services.md` now restores the catalogue, reviewed commands and booking/commerce boundaries (ADR-049). `business-setup.md` now restores initialization, operational readiness, reviewed edits and explicit publication (ADR-053). Restore the remaining affected specifications before changing those domains. |
| OPEN-15 | Approve the exact first-country launch order and complete regional legal, commerce and public-site profiles | The product owner selected the United States, Canada, the United Kingdom and selected Europe as target markets, while current commerce/public content still contains India/INR/`en-IN` assumptions; SMS registration, consent language, quiet hours, taxes, receipts, currency and privacy obligations differ by country | Before any paid regional launch or live mobile sending | **Open; Critical/High blocker bounded by ADR-039.** Product must choose the first exact countries and supported currencies. Named legal/privacy/finance owners must approve country-specific policies and provider registrations before live enablement. |

| OPEN-16 | Complete and certify the public deposit payment interface | The deposit API returns a Stripe PaymentIntent client secret, but the public Vue flow has no payment element or return/status recovery. The server correctly requires paid deposit evidence; a misleading “collect later” message hid this gap. The UI now shows the amount and directs clients to the business without claiming confirmation. | Before enabling deposit-required self-service booking in production | **Open; high blocker.** Engineering/Finance must complete the provider UI and recovery journey under FR-12 and FR-14, restore the affected module specifications under OPEN-14, and meet OPEN-13/live payment gates. No policy or paid-confirmation bypass is approved. |

| OPEN-17 | Define temporary queue holds/steps-away behavior and any priority policy | The existing queue has no persisted hold/VIP/accessibility-priority state. Adding one requires explicit ordering, estimate, expiry and audit semantics so staff cannot silently skip clients. | Before adding those workflows | **Open; not a release waiver.** The redesigned board retains recorded reorder, preferred staff, assignment, notification and reasoned leaving under ADR-045. No new priority is inferred. |

| OPEN-18 | Define compensating amendments/cancellation of a saved basket and the controlled payment-allocation transfer interface | Existing SaleLine/PaymentTransaction records are immutable; the workspace freezes reviewed rows. An inventory shortfall after saving cannot be repaired by silently rewriting paid or unpaid history. The legacy internal allocation-correction service has no approved operational UI contract. | Before exposing those commands | **Open; bounded by ADR-047.** Product/Finance/Engineering must define permission, source/target version checks, settled-sale and receipt treatment, post-close rules, inventory/commission compensation and concurrency evidence. Current stock failures roll back the payment command and require an authorized stock correction or governed support review; no cancellation or transfer is silently performed. |

| OPEN-19 | Approve overnight shift editing, travel buffers between branches and a manager interface for future-effective service variants | Legacy overnight records can display, but the existing editor validation requires end after start; the model has no travel rule or dated-variant editing interface. Silent coercion or overwriting would change scheduling truth. | Before adding those commands | **Open; bounded by ADR-048.** Existing split shifts and dated working exceptions are supported. Cross-zone simultaneous capacity is rejected. Existing future variants are preserved and block current-only editing. No commute recommendation, payroll meaning or overnight-edit contract is invented. |

| OPEN-20 | Define service-specific tax-rule execution, completed-consultation prerequisites, service image uploads and advanced segment/resource editing | The tax-category/image references and consultation flag do not establish a tax-rule registry, secure service-media workflow or completed-consultation gate. Canonical resource/segment data exists, but no reviewed layout editor is provided. | Before exposing these operational commands | **Open; bounded by ADR-049.** Business tax and existing consultation-only new-client policy remain authoritative; image references and resource/segment identities are preserved. Product/Engineering/Finance must define permission, validation, historical snapshots and public presentation before expansion. No Phase 2 promotion or release waiver is implied. |

| OPEN-21 | Connect deposit communication events and approve the usable client payment action | Deposit request/received handlers and templates exist, but no deposit domain events invoke them; the current public action is only information and OPEN-16 still blocks self-service payment. | Before labeling deposit notifications active or offering a payment CTA | **Open; bounded by ADR-052, OPEN-13/16.** Product/Engineering/Finance must define committed request/collection events, exact-once keys, failure/refund language and tested public action. Prepared templates are not proof of an operational automation; no synthetic event or paid-confirmation bypass is approved. |

| OPEN-22 | Approve each country rate card and SMS unit economics | The pricing model is approved, but country list, local amounts, annual discounts, destination costs, allowances, pack prices and refund/dispute handling are not | Before enabling each paid market | **Open; bounded by ADR-054.** Include actual Twilio route/carrier/sender/number/registration costs, SES costs and support margin. Certify taxed/async credit payment and refund/dispute recovery; draft USD amounts remain disabled. |
