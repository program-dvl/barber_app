# Product brief

## Product promise

Run the complete client journey from booking to checkout and rebooking without
losing control of staff time, physical resources, payments, client history, or
daily revenue.

The product is a multi-tenant, web-first SaaS for barbershops, salons, spas, and
independent personal-care professionals. It must be simple enough for a solo
operator and structured enough for a busy shared front desk.

## Chargeable Phase 1 outcome

A representative shop can, without vendor assistance:

- register, verify, subscribe, and complete setup;
- publish a branded, mobile-friendly booking link;
- accept online, phone, reception, and walk-in demand;
- prevent concurrent staff and physical-resource conflicts;
- send reliable confirmations, reminders, and change notices;
- collect, transfer, refund, or forfeit deposits according to policy;
- check clients in, deliver services, check out, issue receipts, and rebook;
- retain linked client, appointment, payment, inventory, and commission history;
- close the day, reconcile payment methods, and export its records; and
- receive support through visible, time-limited, audited access.

If routine operation requires direct database intervention, manual payment
repair, cross-system reconciliation, or product-team assistance, the MVP has
not met the chargeability standard.

## Target users

| User | Primary job |
| --- | --- |
| Owner/operator | Grow revenue, protect staff time, monitor performance, and control billing and access |
| Manager | Coordinate staff, resolve exceptions, approve overrides, and maintain standards |
| Receptionist | Book quickly, manage arrivals and walk-ins, and close transactions |
| Barber/stylist/therapist | Work from the right schedule and client context, record notes, and rebook |
| Accountant/bookkeeper | Reconcile sales, taxes, methods, refunds, tips, and commissions |
| Client | Find, book, manage, and pay for an appointment with minimal friction |
| Platform operator/support | Operate subscriptions and resolve failures without unsafe access |

Launch customers are independent professionals and single-location businesses,
typically with 2-15 staff. The architecture remains location-aware for future
growth.

## Core loop

Discovery and booking -> confirmation and reminders -> arrival and service ->
checkout and payment -> durable client history -> rebooking.

Every Phase 1 decision should improve the reliability, speed, or commercial
value of this loop.

## Product principles

1. Complete the operational loop before broadening the feature set.
2. Make onboarding and daily operation self-serve.
3. Keep front-desk work fast on desktop, tablet, and mobile.
4. Enforce rules and make exceptions explicit, permissioned, and auditable.
5. Maintain one linked and durable operational record.
6. Enforce tenant isolation in every storage and execution context.
7. Store time and money with explicit local-market context.
8. Allow passwordless client self-service.
9. Model entitlements independently of plan names.
10. Instrument acquisition, activation, operational health, and renewal value.

## Phase 1 capability groups

| Group | Requirements | Priority posture |
| --- | --- | --- |
| Platform foundation | FR-01, FR-19, FR-20 | P0 |
| Business configuration | FR-02 through FR-05 | P0 |
| Scheduling and operations | FR-06 through FR-10 | P0 except waitlist P1 |
| Client records and consent | FR-11 and FR-12 | CRM P0; forms P1 |
| Communications | FR-13 | P0 |
| Money and commerce | FR-14 through FR-17 | Deposits/POS P0; inventory/commissions P1 |
| Insights | FR-18 | P0 |

## Explicitly deferred

Phase 2 includes memberships, packages, gift cards, loyalty, referrals,
campaigns, native apps, marketplace discovery, e-commerce, advanced payroll,
supplier procurement, franchise controls, complex medical workflows, classes,
AI receptionist features, dynamic pricing, and forecasting.

A deferred capability may enter Phase 1 only through an accepted decision that
names the blocking problem, owner, measurable outcome, and effect on the P0
quality gate.

## Commercial posture

- Prefer per-location pricing with staff bands.
- Start with two paid plans and an optional higher tier.
- Offer monthly and annual billing with transparent annual savings.
- Keep messaging usage and payment-processing fees explicit.
- Use server-side entitlements for staff/location limits, deposits, inventory,
  reporting, branding, messaging allowance, and support level.
- Treat plan names and exact prices as commercial decisions still requiring
  market validation.

## Success measures

The canonical targets are in PRD Section 15. The defining indicators are setup
within 30 minutes, conflict leakage of zero, notification success at or above
98%, payment reconciliation exceptions below 0.1%, strong weekly usage, and
declining setup-related support demand.

