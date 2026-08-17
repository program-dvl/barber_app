# Quality and testing strategy

## Latest release-gate result

The 2026-08-16 Prompt 13 exercise is a **no-go** for limited paid launch and
general availability. Local tests, MySQL concurrency, dependency audits,
Chromium smoke/responsive checks, and a synthetic restore pass; live providers,
target-topology load/DR, independent WCAG/browser/security review, production
observability, and named India compliance sign-offs do not. See
[`release/2026-08-16-limited-paid-launch/`](release/2026-08-16-limited-paid-launch/README.md).

Quality is part of product scope. A feature is not complete when its happy-path
screen works; it is complete when its rules, failure modes, permissions,
observability, recovery, and documentation are proven.

## Phase 1 quality attributes

| Attribute | Launch requirement | Priority |
| --- | --- | --- |
| Security and isolation | No cross-tenant access, least privilege, strong owner/admin authentication options, secure sessions, rate limits, and OWASP-aligned controls | P0 |
| Availability | At least 99.9% monthly availability for booking and operations, excluding planned maintenance | P0 |
| Performance | Typical authenticated screens usable within 2.5 seconds at p75; common calendar actions acknowledge within 500 ms and commit within 2 seconds under target load | P0 |
| Concurrency | Capacity claims are atomic; stale clients revalidate; duplicate requests safely return the original result | P0 |
| Accessibility | WCAG 2.2 AA target for public booking and core staff workflows | P0 |
| Responsiveness | Core journeys work from 360 px mobile through desktop with appropriate touch targets | P0 |
| Privacy | Consent, minimisation, access logging, export, correction, withdrawal, and deletion/anonymisation workflows | P0 |
| Recoverability | RPO at most 24 hours and RTO at most 4 hours for MVP, with tighter payment reconciliation where possible | P0 |
| Observability | Correlated logs, queue/webhook health, error alerts, metrics, and tenant-safe support diagnostics | P0 |
| Compatibility | Current and previous major Chrome, Safari, Edge, and Firefox versions | P1 |
| Localisation | Locale-aware date, time, currency, tax, and translatable content | P1 |
| Exportability | CSV exports for core records and printable financial summaries | P0 |

Targets are hypotheses until production-like load, design-partner usage, and
provider behavior establish a measured baseline.

## Test layers

### Domain and unit tests

Use for deterministic policy, pricing, tax, duration, transition, entitlement,
commission, deposit, and allocation logic. Include boundaries, invalid states,
effective dates, rounding, and time-zone transitions.

### Feature and authorization tests

Exercise Laravel use cases through HTTP or Livewire/Inertia boundaries. Every
tenant-owned resource needs same-tenant success, unauthorized role denial, and
cross-tenant denial tests. Hiding UI controls is never sufficient evidence.

### Database integration tests

Use the production database engine for locking, unique constraints, concurrent
capacity claims, transaction rollback, and query behavior. SQLite-only evidence
is insufficient for concurrency guarantees.

### Provider contract tests

Use recorded or provider-sandbox examples for payment signatures, duplicate and
out-of-order webhooks, notification status callbacks, retryable versus terminal
errors, and reconciliation.

### End-to-end tests

Cover representative mobile public booking and front-desk workflows across
authentication, booking, payment, communication, checkout, history, and
reporting. Keep a small reliable launch suite rather than duplicating all domain
combinations through the browser.

### Non-functional tests

- accessibility scans plus keyboard and screen-reader spot checks;
- responsive checks at 360 px, tablet, and desktop sizes;
- load and concurrency tests for availability, booking commit, calendar, and
  webhook bursts;
- security review for tenant access, object references, sessions, rate limits,
  uploads, webhooks, and support access;
- backup restoration and disaster-recovery exercise; and
- export/import volume, error, replay, and cancellation tests.

## Mandatory critical scenarios

These scenarios originate in PRD Section 7 and are release acceptance tests.

### Booking and availability

- Two clients claim the same slot concurrently.
- Reception and a public client claim the same staff/resource capacity.
- Staff is free while a required resource is occupied.
- An appointment overlaps closure, break, leave, or operating boundaries.
- Multi-service visits include different providers and processing periods.
- Future bookings are resolved when staff availability changes.

### Clients and privacy

- A new booking resembles an existing client record.
- A passwordless client securely manages an appointment.
- Contact details change without losing history or weakening link security.
- Export, correction, consent withdrawal, and deletion/anonymisation requests
  follow policy.
- Duplicate merge preserves appointments, payments, forms, and audit history.

### Appointment operations

- A walk-in is assigned without harming the next booking.
- Late and over-running service exceptions are explicit and recoverable.
- Reassignment revalidates capacity and updates messages and commission
  attribution.
- Removing a line from a deposited appointment reconciles the deposit.
- An unexpected closure identifies and communicates with affected clients.

### Payments and billing

- Payment succeeds while booking finalisation initially fails.
- A hold expires or confirms safely during delayed payment.
- Deposit plus split tender reconciles.
- Final total is below the deposit.
- Partial refund adjusts reports, inventory, and commission without erasure.
- Webhooks arrive twice or out of order.
- Subscription renewal traverses retry, grace, and restricted states.

### Permissions and security

- Staff cannot view protected owner or peer data.
- Reception cannot bypass refund or discount limits through the API.
- Identifier manipulation cannot cross tenant boundaries.
- Former-employee sessions are revoked.
- Support access cannot exceed its reason, scope, or time window.

Every scenario records preconditions, roles, tenant, location, time zone, policy,
test data, UI/API steps, parallel execution where relevant, expected state,
messages, audit evidence, provider evidence, and a safe support recovery path.

## Definition of done for a module slice

- [ ] Linked requirements and non-goals are explicit.
- [ ] Open decisions that change the design are resolved.
- [ ] Authorization and tenant lineage are designed before UI work.
- [ ] Schema constraints, indexes, retention, and historical behavior are
  documented.
- [ ] Domain rules are implemented once and reused by public, staff, admin, and
  job entry points.
- [ ] Happy paths, validation, policy denial, cross-tenant denial, replay, and
  important failures are tested.
- [ ] Financial or capacity concurrency has production-engine integration
  evidence.
- [ ] Events, jobs, notifications, audit, metrics, and support recovery are
  observable.
- [ ] Accessibility and responsive behavior are checked for changed journeys.
- [ ] No unrelated Phase 2 behavior was added.
- [ ] Module docs, decisions, and project status match the verified result.
- [ ] Test, formatter, and build commands pass.

## Launch evidence

General availability requires:

- complete tenant-isolation coverage across records, files, jobs, search,
  exports, and platform tools;
- threat modeling, dependency review, penetration testing, and remediation of
  high-severity findings;
- accessibility evidence for public booking and core operations;
- load evidence for availability, commit, calendar, and provider bursts;
- successful restore and disaster-recovery exercise;
- payment certification, signature verification, idempotency, refunds, and
  reconciliation;
- financial report reconciliation;
- launch-market tax, receipt, privacy, consent, retention, and marketing review;
- support playbooks that avoid unsafe database edits; and
- production-like completion of every final chargeability statement in PRD
  Section 17.
