# Domain model

This is the shared conceptual model for product, design, engineering, QA, and
support. It refines PRD Section 11 without fixing premature table names.

## Aggregate map

| Domain | Primary records | Owns |
| --- | --- | --- |
| Platform commerce | Business/Tenant, Plan, Entitlement, Subscription, Invoice, Coupon | SaaS access and commercial lifecycle |
| Identity and access | User, Staff Profile, Membership, Role, Permission, Session, Support Access | Authentication and authorization |
| Business configuration | Location, Hours, Closure, Resource, Service, Add-on, Staff-Service Assignment, Schedule, Time Off | What can be sold, where, by whom, and when |
| Client records | Client, Duplicate Candidate, Note, Attachment, Form Template/Version/Request/Submission, Consent, Privacy Request | Durable client identity, protected service context, and consent evidence |
| Scheduling | Appointment, Service Line, Segment, Status History, Capacity Hold, Queue Entry, Waitlist Entry | Demand, capacity, and operational lifecycle |
| Sales and payments | Sale, Sale Item, Payment, Deposit Allocation, Refund, Tip, Cash Close | Commercial truth and reconciliation |
| Inventory and compensation | Product, Inventory Movement, Commission Rule, Commission Entry, Adjustment | Stock and payroll inputs |
| Operations | Notification, Provider Event, Import Job, Export Job, Audit Event, Feature Flag | Reliable automation and safe support |

## Core relationship chain

A `Business/Tenant` owns one or more `Locations`. A user gains tenant access
through an explicit membership and may be linked to a `Staff Profile`.

A `Service` defines its normal sellable behavior. A `Staff-Service Assignment`
may override qualification, price, duration, commission, and visibility. A
service or segment can require one or more `Resources`.

An `Appointment` belongs to a tenant, location, and client. It contains one or
more service lines, which contain schedulable segments. Each capacity-using
segment identifies its staff member, resources, local timing, duration, and
commercial snapshot.

When checkout starts, appointment lines seed a `Sale`; they do not become the
same record. The sale owns immutable or compensating financial lines and
payments. Deposits are explicitly allocated so one successful payment cannot be
applied twice. Completed sale lines drive inventory and commission entries.

Client history is a read model assembled from linked appointments, forms,
notes, communications, sales, payments, refunds, and products. It must remain
readable after staff or services are deactivated.

## Identity distinctions

| Concept | Meaning |
| --- | --- |
| User | A person or service identity that can authenticate |
| Membership | A user's scoped access to a tenant and possibly locations |
| Staff Profile | A worker who may be scheduled, assigned services, and earn commission |
| Client | A customer receiving services; no login is required |
| Support Access | Temporary platform-operator authorization into a tenant context |

A user may have memberships in multiple tenants. A staff profile can exist
without an active login. Deactivating a login does not remove historical staff
attribution.

### Implemented identity and lineage schema (2026-08-11)

- Business uses an internal numeric key for database lineage and an immutable
  ULID `public_id` for explicit route context. Business closure is a state
  transition; tenant records use restrictive deletion rather than cascade loss.
- Membership is unique by Business and User, carries active/suspended/revoked
  lifecycle timestamps, and owns Business-scoped Spatie role/direct-permission
  assignments. A User can therefore hold unrelated roles in two Businesses.
- StaffProfile belongs to one Business and may link uniquely to one Membership
  and User in that Business. Separate Businesses create separate StaffProfiles
  even when the authenticating human is the same.
- Location belongs to one Business. Membership, StaffProfile, and Invitation
  Location pivots repeat `business_id` and use composite foreign keys so a
  cross-Business assignment is rejected by the database as well as actions.
- StaffInvitation persists a token digest, normalized email, Business role,
  inviter Membership, optional StaffProfile, Locations, expiry, revocation,
  and single-use acceptance evidence.
- PlatformRoleAssignment is global, expiring, and separate from Membership.
  It provides no tenant lineage and therefore cannot satisfy tenant policies.
- AuditEvent may be Business-owned or platform-only. It snapshots actor and
  target attribution and is append-only through application models.

## Subscription billing concepts

| Concept | Meaning |
| --- | --- |
| Billing Plan | Display/commercial grouping; never an authorization condition |
| Plan Price | Effective-dated monthly or annual price plus provider price reference |
| Business Subscription | One normalized current commercial lifecycle per Business |
| Subscription Change | Append-only requested/effective/applied plan or status evidence |
| Entitlement Definition | Stable feature or numeric operation key |
| Plan Entitlement | Effective-dated value supplied by a plan |
| Business Entitlement Override | Effective-dated, reasoned exception for one Business |
| Billing Invoice | Business-scoped normalized provider invoice evidence |
| Billing Payment | Append-only attempt/success/failure evidence linked to an invoice |
| Billing Provider Event | Signature, deduplication, ordering, replay, and payload evidence |
| Billing Coupon | Application-owned commercial rule mapped to a provider promotion reference |

Business has exactly one current normalized subscription. Provider payloads do
not become aggregate state directly; the lifecycle service validates a signed,
deduplicated event and writes the normalized state. Invoice/payment rows retain
provider identifiers for reconciliation and are never treated as appointment
sale or deposit records.

Trial dates, billing periods, grace end, scheduled cancellation, termination,
and export availability are stored as UTC instants. A downgrade below current
numeric use records both usage and limit snapshots and takes effect at period
end. Existing tenant records are preserved; only new/increasing consumption is
denied until usage is compliant.

## Implemented business-configuration schema (2026-08-11)

Business configuration refines the aggregate map into these relationship
chains:

```text
Business
  ├─ OnboardingSession; BookingSlugAliases; private brand paths
  ├─ Locations ─ LocationHours / LocationScheduleExceptions
  │             └─ PhysicalResources ─ ResourceHours / MaintenanceBlocks
  ├─ Services ─ Segments ─ ResourceRequirements
  │           ├─ eligible Locations and optional Add-ons
  │           └─ StaffServiceAssignments
  ├─ StaffProfiles ─ Locations / AvailabilityRules / ServiceAssignments
  ├─ ConfigurationChangePreviews / immutable ConfigurationSnapshots
  └─ ConfigurationImports ─ Rows ─ DuplicateCandidates
                            └─ durable ImportedConfigurationRecords
```

Service and Staff-Service Assignment rules may be effective-dated. Resolution
uses the requested instant and applies base Service, Location price, then Staff
override. The resolved value includes segment occupancy, tax, deposit, and
price/duration inputs and can be captured once as an immutable Configuration
Snapshot for a later Appointment Service Line.

Location and staff schedules use local wall-clock terms governed by the
Location IANA time zone. Resource maintenance stores UTC instants plus original
local intent. Full closures override special hours on the same local date.

Import rows are never destructive domain merges. Staff, service, and Client
imports project into their aggregates after validation and duplicate review.
Client creation uses the same normalizer and candidate detector as booking;
only an explicit reviewed `update` resolution may change an existing profile.
Product rows remain tenant-owned import records until inventory owns their
projection rules.

## Implemented client-records schema (2026-08-12)

```text
Business
  └─ Clients ─ Appointments
             ├─ TagAssignments ─ ClientTags
             ├─ PreferredServices ─ Services
             ├─ preferred StaffProfile
             ├─ append-only Consents
             ├─ encrypted authored Notes
             ├─ DuplicateCandidates ─ reviewed Merge evidence
             ├─ FormRequests ─ one-use hashed Links
             │               └─ immutable FormSubmissions
             ├─ private Attachments ─ expiring hashed AccessLinks
             └─ PrivacyRequests ─ optional private Export Attachment

Business
  └─ FormTemplates ─ immutable TemplateVersions
                   └─ eligible Services
```

Client stores display and normalized identity separately. Exact contact plus
exact normalized name may match automatically; a spelling difference on the
same contact creates a separate Client and Duplicate Candidate. Contact changes
use an optimistic version and revoke future Appointment action links.
Tags, preferred Staff, and preferred Services carry the same Business lineage;
their composite foreign keys reject cross-tenant assignments and a merge unions
the many-to-many preference relationships into the chosen survivor.

ClientConsent, ClientFormTemplateVersion, and ClientFormSubmission are
application-immutable. Submission stores the exact template wording/fields,
encrypted answers/signature/identity evidence, template version, Client,
Appointment, and submission instant. A merge changes the relational Client
owner but does not alter the submitted identity or wording snapshots.

Sensitive personal values use application encryption where they do not need to
be searched. Searchable contact has normalized tenant-indexed columns and
role-filtered delivery. Private file object keys carry the Business namespace;
only a hashed, expiring, revocable link authorizes byte retrieval.

Client history currently assembles Appointment/performer snapshots, notes,
consent, forms, and attachments. Sales/Payment/Product/Communication modules
must attach their authoritative records to this Client and extend merge,
history, and export registries when those ledgers are introduced. Until then,
lifetime spend is explicitly unavailable rather than inferred from booked
Appointment prices.

## Scheduling concepts

### Appointment

The customer-facing commercial visit. It groups service lines, deposit policy,
client, location, source, and overall lifecycle.

### Service line

A priced service or add-on selected for the appointment. It preserves the
displayed name, price, tax treatment, duration rule, and performer allocation
used for that visit.

### Segment

A time interval within a service line, such as active work, processing, or
cleanup. A segment specifies whether staff and each resource remain occupied.

### Capacity hold

A short-lived reservation used while booking is committed or payment is
pending. It is not a confirmed appointment and must have an expiry, owner,
idempotency identity, and deterministic release behavior.

### Queue and waitlist

A queue entry represents a client physically waiting for near-term service. A
waitlist entry represents preferences for a future opening. Both consume the
same availability rules as normal appointments once capacity is claimed.

### Implemented scheduling-capacity schema (2026-08-11)

The booking core refines Scheduling into these persisted chains:

```text
BookingCommandKey ─ stable request digest / Hold-or-Appointment result
CapacityHold ─ HoldLines ─ HoldSegments
             └─ HoldResourceClaims
Appointment ─ ServiceLines (immutable configuration snapshot)
            ├─ Segments ─ staff attribution and occupancy
            ├─ ResourceClaims ─ pooled quantity and occupancy interval
            └─ StatusHistory
```

Every record repeats `business_id`; Location, Service, Staff, Segment, resource,
Hold, Appointment, and line references use composite Business foreign keys.
Configuration entities use restrictive deletion where history depends on them.
Appointment aggregate children and unconfirmed Hold children cascade only with
their owning aggregate.

Appointment and capacity intervals are half-open UTC ranges `[start, end)`.
They also preserve the governing IANA time zone and offset-bearing local start
and end text where human interpretation matters. A processing Segment may keep
its provider attribution while `occupies_staff=false`; conflict detection then
releases staff but retains any configured resource claim.

`booking_command_keys` owns command replay identity independently of capacity.
A key is unique by Business and command scope, stores the normalized request
SHA-256 digest, and points to the original result. `capacity_holds` separately
retains its stable key, digest, expiry, status, and eventual Appointment link.
Exact replay returns the original record. A different request under the same
key is a domain error.

## Appointment lifecycle

Canonical states:

- Pending confirmation
- Confirmed
- Arrived
- Checked in
- In service
- Completed
- Cancelled by client
- Cancelled by shop
- No-show
- Late
- Rescheduled

State transitions are explicit commands. Every transition records the actor,
time, source, reason where required, and previous state. A `Rescheduled`
appointment links to its replacement instead of being overwritten.

Implemented transitions are:

- `confirmed -> late | arrived | cancelled_by_client | cancelled_by_shop | no_show`
- `late -> arrived | cancelled_by_client | cancelled_by_shop | no_show`
- `arrived -> checked_in | cancelled_by_client | cancelled_by_shop`
- `checked_in -> in_service | cancelled_by_client | cancelled_by_shop`
- `in_service -> completed`

`completed`, cancellation, `no_show`, and `rescheduled` are terminal. A
reschedule, resize, reassignment, or service-composition change is represented
as a linked replacement Appointment rather than an in-place rewrite. Every
mutation checks the expected version, claims a replay-safe operation key, and
preserves actor, time, source, reason, and safe before/after metadata in
append-only history. UI drag-and-drop requests that command; it never writes a
timestamp directly.

Schedule Blocks, Walk-in Entries/History, Operational Exceptions, and
Operational Notification Events are Business/Location-owned operational
records. A Walk-in may link to exactly one normal Appointment only after the
shared capacity engine accepts service start. Queue estimates persist their
evidence and actual wait remains available after service or abandonment.

Public booking adds three tenant-owned chains:

```text
PublicBookingFlow ─ CapacityHold ─ confirmed Appointment
Appointment ─ PublicAppointmentLinks (hashed token, purpose, expiry, use/revoke)
WaitlistRequest ─ WaitlistMatches (offer batch) ─ winning Appointment
PublicBookingEvent ─ privacy-safe conversion evidence
```

The Flow stores a hashed secret, last step, policy version, bounded state, Hold,
expiry, and optional confirmed Appointment. Appointment links never store the
raw token and a display reference grants no access. Waitlist requests retain
their preferences and lifecycle; clearing `active_dedupe_key` on cancellation
or booking permits a later distinct active request without deleting history.

## Financial concepts

| Concept | Rule |
| --- | --- |
| Sale | Commercial record of what was ultimately sold |
| Sale item | Versioned service, add-on, product, fee, or discount line |
| Payment | Money tendered through a method or provider |
| Deposit allocation | Explicit application of prior payment value to an appointment or sale |
| Refund | Provider and ledger correction linked to original payment |
| Tip | Separate value allocated to one or more staff members |
| Cash close | Reconciliation snapshot; it does not rewrite sales |
| Commission entry | Reproducible payroll input linked to sale lines and effective rules |

### Implemented money-and-commerce schema (2026-08-15)

```text
CommerceSetting ─ policy/version inputs
PaymentIntent ─ verified PaymentProviderEvent evidence
               └─ immutable PaymentTransaction ─ Deposit ─ DepositAllocation
Appointment ─ Sale ─ immutable SaleLine / TipAllocation / Receipt snapshot
Sale ─ PaymentTransaction (payment, refund, void, correction)
Location/day ─ CashClose ─ append-only CashCloseAdjustment
Business ─ ProductCategory ─ InventoryProduct ─ Location InventoryLevel
                                      └─ append-only InventoryMovement
SaleLine ─ SaleLineRefund (disposition) ─ PaymentTransaction
CommissionRule (effective dated) ─ CommissionEntry ─ SaleLine/Staff/Payment
Sale ─ TipEntry ─ Staff/Payment
Business ─ ReportExport (saved scope, queued private artifact)
Business ─ privacy-safe InstrumentationEvent
```

Each record repeats `business_id`; sales, deposits, and payment intents also
participate in Client merge/history. A provider event is unique by provider ID
and hash; an Intent and financial transaction each have a business-scoped
idempotency key. Provider payloads remain evidence, never the authoritative
Sale state. A completed Sale may only gain a compensating transaction; it is
never rewritten or deleted.

Each immutable service Sale Line retains both its appointment-line source and
the tenant-constrained Service identifier. This keeps service filters,
commission selection, and drill-down lineage tied to the actual catalog item.

Money and inventory corrections use append-only or compensating records.
Derived totals must be reproducible from underlying events.

Inventory Product stock is the aggregate of Location levels; permissioned
views and exports use only assigned Location levels. A completed Sale Line is
the only stock deduction source and its deterministic movement key prevents a
retry from decrementing twice. Refund/void disposition is append-only even
when it produces no stock delta. Commission/Tip statements sum signed entries;
rules and historical earnings cannot be updated or deleted. Report exports
persist the requesting Membership and normalized scope, then re-authorize in a
tenant-aware queue job before reading or writing the private artifact.

## Required invariants

1. Every tenant-owned entity resolves to exactly one tenant.
2. Every location-scoped operation uses the governing location time zone.
3. No confirmed capacity interval overlaps another incompatible claim unless a
   permitted, recorded exception exists.
4. Multi-service confirmation is all-or-nothing.
5. A provider event and an idempotent command affect business state at most
   once.
6. A deposit's applied, refunded, transferred, and remaining values always
   reconcile to the original successful payment.
7. Completed sales, refunds, inventory movements, commissions, and cash closes
   form a traceable chain.
8. Policy or template changes never rewrite historical decisions or consent.
9. Staff deactivation removes access and future eligibility without deleting
   history.
10. Client merge preserves every relationship and records the surviving
    identity and audit event.
11. A duplicate candidate never causes an automatic merge; a permissioned
    reviewer selects the survivor from a current preview and supplies a reason.
12. Form wording, answers, signature evidence, identity, Appointment, version,
    and submission time are immutable after submission.
13. Destructive Client privacy actions remain policy-blocked until the launch
    jurisdiction and retention executor are approved under ADR-018.

## Data ownership questions still open

- Which records require location IDs versus deriving location through another
  aggregate
- Final jurisdiction-specific retention periods for client data, attachments,
  provider payloads, and audit events. ADR-018 already prohibits destructive
  processing until those periods are approved.

Resolved: ADR-006 replaces Jetstream Team with Business; Business, not an owner
User, is the SaaS billing owner; ADR-008 makes each StaffProfile belong to one
Business rather than sharing a profile across tenants; accepted ADR-007 selects
an application-owned billing contract, with Paddle selected for new Good Hours
subscription checkout and provider events under ADR-021.

Resolve the remaining periods before enabling the destructive executor or
public launch; the current schema records classifications, deadlines, and
retention evidence without deleting data.
