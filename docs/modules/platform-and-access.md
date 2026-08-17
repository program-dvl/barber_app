# Module: Platform and access

Status: Access, SaaS subscription, entitlement, and FR-20 platform-operations
foundations implemented and verified. Destructive retention execution and
production provider/backup certification remain launch gates.

Requirements: FR-01, access portions of FR-05, FR-19, FR-20.

## Purpose

Create and operate isolated paying businesses, give each person only the access
they need, enforce plan entitlements, and provide safe platform support.

## Responsibilities

- tenant registration, verification, trial, subscription, invoices, and closure;
- memberships, staff invitations, roles, permissions, sessions, and revocation;
- server-side entitlements and usage limits;
- shop settings and versioned critical policies;
- immutable audit events for significant actions;
- platform tenant search, billing support, provider failure visibility, feature
  flags, and health summaries; and
- reasoned, time-limited, visible support access.

## Boundaries

- This module owns SaaS billing, not appointment deposits or checkout payments.
- A `User` authenticates; a `Membership` grants tenant access; a `Staff Profile`
  is schedulable. Do not collapse these concepts without an accepted decision.
- Platform administrators do not bypass tenant policies through ordinary route
  access.
- Entitlements decide whether an operation is allowed; plan names are display
  and commercial configuration.

## Core invariants

1. Tenant creation is idempotent across verification, checkout, and webhook
   retries.
2. Every tenant-owned action resolves tenant context before record lookup.
3. Access removal invalidates relevant active sessions promptly.
4. Subscription restriction is progressive and preserves required export and
   billing access.
5. Permission, entitlement, subscription, support-access, refund, and override
   changes are auditable.
6. Support access requires reason/ticket, approved scope, expiry, visible
   tenant banner, and immutable entry/exit evidence.
7. Bulk cross-tenant operations are separately authorized and monitored.

## Required interfaces

- `TenantContext` resolution for HTTP, jobs, commands, and provider events
- authorization policies and location-aware permission checks
- entitlement evaluation contract with feature and numeric limit support
- audit writer with safe before/after metadata
- subscription provider adapter and normalized lifecycle
- support-access grant, revoke, expire, and inspect use cases

## Implemented foundation (2026-08-11)

- `Business` is the canonical tenant; `Location` belongs to exactly one
  Business and location-assignment pivots carry the same Business ID with
  composite foreign keys.
- Explicit Business URLs and middleware resolve an active Membership before
  child model binding or policy authorization. Tenant context is cleared after
  the request and may not be inferred from Jetstream `current_team_id`.
- `User`, `Membership`, and `StaffProfile` are separate records. A StaffProfile
  may exist without login; linking or revoking login does not delete the
  profile.
- Spatie roles and direct permissions attach to the tenant-bound Membership.
  Starter roles and exact custom Business roles are supported. Platform roles
  use a separate expiring assignment table and never bypass tenant middleware.
- Invitations are single-use, token-hashed, Business/role/email bound,
  expiring, revocable, optionally StaffProfile-bound, and carry validated
  Location assignments.
- Membership revocation is idempotent, audited, denies the next tenant request,
  deletes database sessions and scoped/legacy unscoped tokens, and rotates the
  remember token. Because browser sessions are User-wide today, revocation
  deliberately logs the User out everywhere rather than risk retaining access.
- AuditEvent and AuditWriter provide append-only application behavior,
  attribution, correlation, safe before/after metadata, and secret redaction.
- Tenant-aware conventions exist for jobs, private files, cache keys, search
  envelopes, and export filenames. See
  [`../tenant-isolation-matrix.md`](../tenant-isolation-matrix.md) for coverage.
- Launch authentication is verified email/password plus Fortify/Jetstream TOTP
  and recovery codes. Boilerplate magic-link and unrestricted Socialite routes
  are disabled. Platform administration requires verified email and confirmed
  TOTP.
- Verified owner registration records a pending Business name but creates no
  tenant until email verification. The verified event completes one locked,
  unique registration intent, one Business, Owner Membership, and dated trial;
  duplicate event delivery returns the existing Business.
- Business-owned plans, effective prices, subscriptions, immutable changes,
  invoices, payments, coupons, provider events, notices, usage, and
  effective-dated entitlement records form the application billing contract.
- Paddle Billing is the accepted Good Hours subscription provider behind
  `SubscriptionProvider`. Legacy User/Cashier and Lemon Squeezy runtime
  paths are disabled; their stored boilerplate is retained for reviewed cleanup.
- Owner billing endpoints and the responsive subscription page expose trial and
  renewal dates, monthly/annual prices, saved-method evidence, portal access,
  coupons, invoices/payment history, plan change, cancellation, reactivation,
  restrictions, and export availability. New subscriptions use a dedicated
  in-app review page and Paddle's inline checkout frame; no popup or hosted-page
  redirect is used.
- Every Paddle checkout transaction is stored as a tenant-scoped attempt. The
  checkout page polls an owner-authorized confirmation endpoint and replaces the
  payment frame with a processing/success state. Refresh recovery verifies the
  exact transaction and active subscription through Paddle's authenticated API,
  then projects the plan, invoice, payment, and saved-method evidence. A later
  signed webhook converges idempotently without duplicate financial records.
- Billing-capable owners see the verified current plan/status in the application
  header. Upgrades use immediate Paddle proration after a consequence-first
  confirmation; same-interval downgrades keep current access through renewal.
  Cancellation is scheduled at period end with the access-end date shown, and
  owners can remove the scheduled cancellation before it takes effect. Staff
  without `billing.manage` do not receive subscription details in shared page
  properties.
- A Paddle account may contain another SaaS catalog, but Good Hours products,
  customers, transactions, and subscriptions carry an `application=good_hours`
  marker. Signed events explicitly marked for another application are discarded
  before their payload is persisted. Unmarked legacy events are accepted only
  when an existing Good Hours provider customer/subscription can be resolved.
- Paddle's customer-visible seller identity is shared by the seller account.
  Good Hours does not attempt to override the hosted checkout's legal/display
  name; a neutral Stylnexa identity requires a reviewed account-wide Paddle
  change or a separate seller account.
- Entitlement checks are reusable for HTTP middleware, domain actions, jobs,
  and imports. Billing and export recovery remain available through progressive
  restriction; plan names never authorize operations.

## Normalized subscription lifecycle

| State | Normal operations | Billing/export | Transition |
| --- | --- | --- | --- |
| `trialing` | Allowed by trial entitlements | Available | Verified registration |
| `active` | Allowed by paid entitlements | Available | Signed paid/subscription event |
| `past_due` | Allowed with warning | Available | First failed renewal |
| `grace` | Allowed with warning and dated grace end | Available | Failed retry |
| `restricted` | Read/deactivate/delete only; no new use/import/job consumption | Available | Grace expires |
| `cancel_scheduled` | Allowed until period end | Available | Owner period-end cancellation |
| `canceled` | Read-only | Available through dated window | Support immediate cancel or ended term |
| `terminated` | Closed | Export only until `export_available_until`; billing recovery retained | Signed terminal provider event |

Payment recovery returns `past_due`, `grace`, or `restricted` directly to
`active`, clears grace, and retains the complete invoice/payment/event history.

## Entitlement catalog

| Key | Type | Meaning | Over-limit behavior |
| --- | --- | --- | --- |
| `locations.max` | Numeric | Active location cap | Existing locations remain; create/import is denied |
| `staff.max` | Numeric | Active staff cap | Existing staff remain; create/import is denied |
| `messaging.monthly_allowance` | Numeric | Included mobile messages per period | Further included consumption is denied or metered separately |
| `deposits.enabled` | Feature | Appointment deposits | Deposit operation denied when false |
| `inventory.enabled` | Feature | Inventory operations | Write/job/import operation denied when false |
| `reporting.advanced` | Feature | Advanced reports | Advanced report operation denied when false |
| `branding.custom` | Feature | Custom public branding | Customization denied when false |
| `support.priority` | Feature | Priority support service level | Normal support remains; priority routing denied |
| `exports.enabled` | Feature | Business data export | Preserved through recovery/closure window |
| `billing.manage` | Feature | Billing and payment recovery | Always reachable for the billing Owner |

A downgrade that would be below current numeric usage is scheduled for period
end with usage and limit snapshots. It never deletes or deactivates records.
After effectiveness, reads and reductions remain allowed while operations that
increase usage are denied until usage is within the new limit or the plan is
changed.

## Provider failure recovery and reconciliation

| Failure | Safe behavior | Recovery |
| --- | --- | --- |
| Checkout/portal API unavailable | Provider exception returns an error; no paid state is written | Owner retries when Stripe recovers |
| Browser success redirect forged/replayed | Redirect is never payment evidence | Wait for a signed subscription/invoice event |
| Duplicate webhook | Unique provider event ID and payload hash return the original result | No operator action |
| Event ID reused with different payload | Rejected as a conflict | Investigate provider/security logs |
| Out-of-order subscription event | Recorded, but `provider_state_at` prevents state rewind | Newer event remains authoritative |
| Processor/database transient failure | Verified event remains `failed` with attempts/error | `billing:reconcile-provider-events` replays the same idempotent processor |
| Renewal payment failure | Dated retry notice, then grace, then read-only restriction | Signed paid invoice restores active access safely |
| Notice delivery failure | Notice retains attempts/error and remains unsent | Scheduled sender retries; support can inspect without editing subscription state |
| Period-end plan/cancel change | Stripe schedule/cancel flag plus local effective record | Signed provider state and scheduled lifecycle advance converge the records |

Reconciliation evidence is queryable in `billing_provider_events`, normalized
invoices/payments, subscription version/provider timestamp, and audit events.
The FR-01 suite proves a duplicate event is processed once, a stale event cannot
rewind active state, a failed verified event can be replayed, and invoice totals
and payment identity remain Business-scoped. Live Stripe settlement/test-clock
reconciliation remains a pre-launch gate because sandbox credentials were not
available in this environment.

## Authorization matrix

Legend: `All` means all permitted records inside assigned Locations unless the
actor is Owner; `Own` requires the later target record to resolve to the
actor's StaffProfile. An empty cell is denied by default.

| Permission | Owner | Manager | Receptionist | Barber / stylist | Accountant |
| --- | --- | --- | --- | --- | --- |
| All calendars | All | All | All |  |  |
| Own calendar | All |  |  | Own |  |
| Client contact | Yes | Yes | Yes | Yes, for own workflow when target policies exist |  |
| Sensitive notes | Yes | Yes |  |  |  |
| Client attachments | Yes | Yes | Yes |  |  |
| Appointment deletion | Yes | Yes |  |  |  |
| Discounts | Yes | Yes |  |  |  |
| Refunds | Yes | Yes |  |  |  |
| Revenue | Yes | Yes |  |  | Yes |
| All commissions | Yes | Yes |  |  | Yes |
| Own commission | Yes |  |  | Yes |  |
| Inventory management | Yes | Yes |  |  |  |
| Operational settings | Yes | Yes |  |  |  |
| Subscription billing | Yes |  |  |  |  |
| Staff/role management | Yes | Yes |  |  |  |
| Audit history | Yes | Yes |  |  | Yes |
| Exports | Yes | Yes |  |  | Yes |

Custom roles contain an exact selected permission set. Direct per-Membership
grants are also supported and audited; no permission is implied by navigation.

## Major acceptance evidence

- owner can self-serve trial, payment, plan change, cancellation, and export;
- duplicate registration and billing webhooks do not create duplicate tenants
  or subscriptions;
- former employee sessions lose access;
- each starter role passes a documented permission matrix;
- identifier changes cannot expose another tenant's record, file, search result,
  export, or job;
- support resolves provider failures without direct database manipulation; and
- all server entry points enforce entitlements independently of navigation.

## Implemented FR-20 platform operations (2026-08-15)

The application-owned `/platform` surface is distinct from tenant-owner
administration and from the quarantined Larafast Filament resources. Safe
business summaries include owner verification, onboarding, plan/subscription,
usage, invoices, failure counts, and activity volumes. They deliberately omit
authentication material, provider payloads/secrets, message bodies and
destinations, and Client notes.

### Platform permission matrix

| Capability | Platform administrator | Support operator | Security auditor |
| --- | --- | --- | --- |
| Safe tenant/search summary | Yes | Yes | Yes |
| Activate, suspend, close | Yes | No | No |
| Trial, coupon, plan, immediate cancel | Yes | No | No |
| Single-tenant export initiation | Yes | No | No |
| Failure and health summaries | Yes | Yes | Health only |
| Safe idempotent replay | Yes | Yes | No |
| Internal account notes | Yes | Yes | No |
| Feature flags and notices | Yes | No | No |
| Approve/revoke support grants | Yes | No | No |
| Enter an approved support grant | Yes | Yes | No |
| Immutable audit and alerts | Yes | No | Yes |

Every platform route requires a verified identity, confirmed TOTP, an active
expiring platform-role assignment, a 15-minute idle limit, and an eight-hour
absolute platform-session limit. Filament's legacy User, Role, Permission,
billing, price, and product editors are not approved FR-20 resources and are
disabled; its discovered legacy widgets are removed.

### Support-access lifecycle

1. A platform administrator approves another active administrator/support
   operator for one Business, a ticket, a non-empty reason, explicit scopes,
   and an expiry no more than four hours away.
2. Entry creates a support session bound to that operator and grant, appends an
   `support.access.entered` event, and never changes the authenticated identity.
3. Only `/platform/support/businesses/{business}` endpoints accept the session.
   Existing shop middleware still requires Membership and never reads a grant.
4. Account summary, billing, communications, webhook failures, invitations,
   and exports are separately grantable scopes. An identifier from another
   Business or an ungranted operation fails closed.
5. Active entry appears to tenant users as a named Good Hours Support banner
   with ticket, reason, and expiry. Exit, expiry, supersession, or revocation
   ends access; revocation ends every open session immediately.
6. Grant, entry, exit, replay, and revocation evidence is append-only. No
   impersonation, hidden operator, or direct database support workflow exists.

### Failure recovery, health, and alerts

- Billing and appointment-payment webhooks retain verified payloads internally,
  but platform responses return only provider/type/state/count/time evidence.
- Notification diagnostics reuse the content-minimizing FR-13 service: hashes,
  state, attempt/error class, provider ID, and correlation ID only.
- A replay requires an operator reason and operation key. The replay ledger has
  a unique idempotency key, calls only reviewed Stripe/Paddle/payment or
  communication processors, and returns the original result on duplicates.
  Generic failed jobs remain inspection-only unless their job type obtains an
  explicit reviewed replay adapter.
- Health summarizes queue age/count, failed work, delayed communications,
  billing/payment webhook age, reconciliation tasks, and the honest
  `not_configured` backup state.
- Three tenant entries by one operator within fifteen minutes raise a high
  unusual-cross-tenant alert. A bulk/cross-tenant export attempt is rejected
  and raises a critical alert. Valid export initiation records exactly one
  Business lineage snapshot and produces no immediate bulk download.
- Internal account notes are append-only, `platform_internal`, and carry a
  retention date of no more than two years.

### Exact automated evidence

`tests/Feature/PlatformAccess/PlatformOperationsTest.php` passes these ten
cases:

1. platform role separation plus MFA, idle, and absolute session limits;
2. content-minimized tenant summaries and manipulated identifier denial;
3. approved scope, tenant visibility, and cross-Business scope denial;
4. active-session termination when its grant expires;
5. immediate revocation and immutable entry/exit evidence;
6. role-restricted single-tenant exports plus critical bulk-export alert;
7. application feature flags/notices and append-only internal notes;
8. verified provider replay with duplicate operation-key convergence;
9. unusual rapid cross-tenant access alert; and
10. quarantine of legacy Filament identity/permission resources.

The focused FR-20 regression adds `TenantIsolationTest`, `AuditEventTest`, and
`SubscriptionLifecycleTest`: 47 tests / 268 assertions. The complete SQLite
suite passes 185 tests / 1,290 assertions with 28 intentional skips. Client and
SSR builds pass. Existing MySQL booking/concurrency evidence remains valid;
FR-20 adds no capacity or money-locking claim requiring a new concurrency run.

## Open decisions

OPEN-03, OPEN-07, and OPEN-08 are resolved by ADR-006, ADR-012, and ADR-013.
OPEN-04 is resolved by accepted ADR-007. See OPEN-09 in `../decisions.md`.
ADR-009 is implemented by the scoped support-grant and session workflow above.
