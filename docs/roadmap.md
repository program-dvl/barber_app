# Delivery roadmap and prompt sequence

This roadmap turns the PRD into dependency-aware implementation threads. Each
thread should use one prompt from `docs/prompts/`, finish its exit evidence,
update the documentation, and leave the repository in a releasable state.

Prompt numbers describe dependency order, not calendar estimates. P1 work may
move later within Phase 1 if it would weaken P0 quality.

## Delivery sequence

| Prompt | Scope | Key requirements | Prerequisites | Exit evidence |
| --- | --- | --- | --- | --- |
| 00 | Foundation audit and adoption plan | Cross-cutting | Documentation baseline | Keep/adapt/remove inventory, risk register, accepted tenant direction |
| 01 | Product shell and design foundation | Navigation and NFR UX | Brand direction or explicit temporary design token decision | Responsive accessible shell and design conventions |
| 02 | Tenancy, identity, staff access, audit skeleton | FR-05, FR-19 foundations | Prompt 00; tenant decision | Isolation matrix, session revocation, role/policy tests |
| 03 | Subscription lifecycle and entitlements | FR-01 | Prompt 02; billing provider decision | Trial-to-paid, renewal failure, restriction, and export tests |
| 04 | Onboarding and business configuration | FR-02 through FR-05 | Prompts 02-03 | Published test business with correct hours, staff, services, resources, and import |
| 05 | Availability and booking engine | FR-06, FR-07 foundations | Prompt 04; production DB decision | Atomic conflict and edge-case suite |
| 06 | Operational calendar and walk-in queue | FR-06, FR-08 | Prompt 05 | Front-desk day simulation and lifecycle history |
| 07 | Public booking, self-service, and waitlist | FR-09, FR-10 | Prompts 05-06 | Mobile booking, secure links, atomic waitlist claim |
| 08 | Client CRM, forms, consent, and privacy | FR-11, FR-12 | Prompt 02 and scheduling identities | Duplicate/merge, immutable consent, privacy workflow tests |
| 09 | Notifications and communication | FR-13 | Prompts 04, 06-08; provider decision | Idempotent delivery, local-time, consent, and failure visibility |
| 10 | Deposits, checkout, payments, and cash close | FR-14, FR-15 | Prompts 05-09; gateway and tax decisions | Payment/booking recovery, split tender, refund, and reconciliation tests |
| 11 | Inventory, commission, dashboard, and reports | FR-16 through FR-18 | Prompt 10 | Append-only adjustments and report-to-payment reconciliation |
| 12 | Platform admin and support operations | FR-20 | Prompts 02-03 and provider operations | Audited support access and safe failure replay |
| 13 | Launch hardening and migration | Sections 7, 12, 14, 15, 17 | All P0 prompts | Security, accessibility, load, restore, compliance, and chargeability sign-off |

## Stage gates

### Gate A: Foundation accepted

- Tenant model and ownership are decided.
- Boilerplate features are classified.
- Production database, queue, storage, and provider decisions have owners.
- Existing tests and build provide a trustworthy baseline.

### Gate B: Valid business configuration

- A tenant can configure a location, operating exceptions, staff, schedules,
  services, variants, add-ons, and resources.
- Import behavior is idempotent and tenant-safe.
- Availability can consume configuration without special-case demo data.

### Gate C: Scheduling integrity

- Availability and commit use the same rules.
- Staff and resource claims are atomic.
- Multi-service visits are all-or-nothing.
- Operational edits revalidate instead of bypassing the engine.

### Gate D: Customer and operations loop

- Public, reception, phone, and walk-in demand use shared domain commands.
- Clients can manage bookings securely without accounts.
- Calendar history, CRM, consent, and communications are linked and supportable.

### Gate E: Financial integrity

- Deposits and final payments reconcile through idempotent provider events.
- Checkout supports required tenders, tips, receipts, refunds, and cash close.
- Inventory, commission, and reports derive traceably from sale/payment events.

### Gate F: Chargeable launch

- All P0 requirements and critical scenarios pass in a production-like
  environment.
- Product, quality, security, compliance, recovery, observability, and support
  checklists are signed off.
- A representative shop completes PRD Section 17 without internal intervention.

## Release posture

Closed alpha with test businesses -> design-partner beta with real schedules and
payments -> limited paid launch by geography -> general availability after
operational, security, financial, and support gates remain healthy.

## How to change this roadmap

Record reordered dependencies or scope changes in `decisions.md`. Do not close a
gap by silently merging large modules into one thread. If a prompt becomes too
large, split it along a transactional boundary and retain the same gate.

