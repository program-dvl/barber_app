# Business and Client Ecosystem

**Status:** Target-domain blueprint; implementation is phased and subject to PRD/decision approval  
**Date:** 2026-08-30

See also the [competitive benchmark](01-FRESHA-COMPETITIVE-GAP-ANALYSIS.md), [roadmap](02-PRODUCT-VISION-AND-ROADMAP.md), [experience design](04-UX-ONBOARDING-AND-CLIENT-EXPERIENCE.md), and [engineering plan](05-IMPLEMENTATION-MASTER-PLAN.md).

## 1. Ecosystem thesis

The product should behave as one connected system:

```text
Discovery/direct link
  → public business profile
  → service + professional + availability
  → booking/hold
  → customer + consent + forms
  → appointment delivery
  → checkout/payment/refund
  → notification/receipt
  → loyalty/value entitlement
  → rebook/review
  → operational + financial reporting
```

The connection must not collapse domain boundaries. A `User`, business `Membership`, schedulable `StaffProfile`, tenant `Client`, future global `CustomerAccount`, business subscription, appointment payment, and future wallet balance answer different questions.

## 2. Core identity and ownership model

### Current, preserved concepts

- `Business`: tenant and business-owned subscription boundary.
- `Membership`: a user's access to a business, role, status, and permissions.
- `StaffProfile`: a schedulable worker who may or may not have a login.
- `Location`: operational context for availability, delivery, reporting, and permissions.
- `Client`: one business's CRM record, preferences, consent evidence, notes, forms, and history.
- `Appointment`: tenant-owned service delivery record.
- `Sale` / payment records: merchant commerce evidence.

### Proposed customer identity

Add a global `CustomerAccount` (authentication/security) and `ConsumerProfile` (customer-chosen shared fields). Link it to a tenant `Client` through an explicit `ClientAccountLink`.

Rules:

- The global account never grants access to another business's CRM notes, forms, tags, internal fields, or marketing consent.
- The tenant `Client` remains authoritative for the merchant relationship.
- Linking records verification method, provenance, timestamp, scopes, and revocation.
- Guest booking remains supported; account creation is encouraged after confirmation, not required for browsing.
- Duplicate/link resolution must be reviewable and reversible.

## 3. Business owner experience

### Problem solved

Owners need to launch, operate, understand, and grow the business without becoming system administrators.

### Core workflow

Register → verify → create business → guided setup → first bookable path → publish → receive booking → deliver → checkout → review performance → improve.

### Required data

Business profile, vertical/capabilities, regions, locations, hours, services, staff, availability, booking rules, taxes, payment settings, communication preferences, policies, and public profile.

### Backend requirements

- Reuse idempotent owner/business/membership/default-location/trial provisioning.
- Make onboarding answers versioned and resumable.
- Use the existing readiness evaluator as the server-side publication contract.
- Audit sensitive settings and plan/limit changes.
- Produce task/readiness projections for the owner dashboard.

### Frontend requirements

- Outcome-based setup tasks with progressive disclosure.
- Role-aware dashboard and exception inbox.
- First-class Services, Team, Locations, Availability, Booking, Payments, and Communications destinations.
- Plain-language recovery states for missing setup, limits, permission, subscription, and provider failures.

### Business rules

- A business can operate privately before publishing online.
- Publication requires one valid service-delivery path, truthful policies, business contacts, hours, and preview.
- Marketplace listing is a later, separate opt-in from direct online booking.

### MVP / advanced

- **MVP:** direct-booking operating system, guided setup, readiness, daily dashboard, reliable commerce.
- **Advanced:** portfolio/governance across locations, workflow approvals, growth insights, and opt-in network distribution.

## 4. Staff experience

### Problem solved

Staff need a fast schedule and client-service workflow while owners need controlled access, availability, performance, and compensation evidence.

### Core workflow

Owner creates profile → assigns location/services/shifts → optionally invites login → role grants permissions → staff manages day → service completes → sale/tip/commission evidence is recorded.

### Required data

Identity/contact, employment/display metadata, locations, qualifications, services, price/duration overrides, working shifts, leave/exceptions, permissions, commissions, and public portfolio.

### Rules and security

- Login access and schedulability remain separate.
- Role permissions do not imply plan entitlements.
- Staff only see authorized clients/locations and the minimum sensitive data needed.
- Historic appointment/commission evidence survives staff deactivation.

### MVP / advanced

- **MVP:** team directory, service/location assignment, shifts, role/access, non-login staff.
- **Advanced:** leave requests, goals, payroll integrations, portfolio, cross-location mobility, and approvals.

## 5. Customer experience

### Problem solved

Customers want trusted discovery, quick booking, safe payment, control over appointments, and effortless repeat visits.

### Core workflow

Discover or open direct link → evaluate business → choose service/professional/time → identify/verify → agree to terms/deposit → confirm → receive reminders/forms → attend/pay → receipt → review/rebook/redeem benefit.

### Data

Account identity, tenant-specific client links, bookings, payment tokens, receipts, consent, favourites, review eligibility, value entitlements, and notification preferences.

### Security/privacy

- Provider tokens only; never store raw card details.
- Per-business marketing consent; transactional messages are purpose-limited.
- Step-up authentication for payment methods, stored value, export, and deletion.
- Customer views expose business-approved and customer-owned data, never internal CRM notes.

### MVP / advanced

- **MVP:** guest flow, secure links, optional account, upcoming/history, reschedule/cancel/rebook.
- **Advanced:** favourites, saved methods, wallet, loyalty, packages, memberships, gift cards, reviews, and marketplace discovery.

## 6. Multi-location experience

### Problem solved

Growing operators need consistency without forcing every location into identical hours, staffing, prices, or legal/financial context.

### Core model

Business → locations → service/location eligibility → staff/location assignment → local schedules/resources → location-scoped sales/reporting.

### Business rules

- Shared templates have explicit local overrides and effective dates.
- Users receive business and location scopes; neither is inferred.
- Currency/legal entity/tax constraints must be explicit if cross-country locations are allowed.
- Gift cards/credits/packages declare redeemable locations.

### MVP / advanced

- **MVP:** current location-aware model plus better switcher, assignments, and reports.
- **Advanced:** business groups, central catalogue/policy, approvals, consolidated reporting, inter-location value settlement.

## 7. Public booking experience

### Current reusable foundation

The existing `PublicBookingService` supports catalogue selection, availability, holds, confirmation, deposits, waitlist, and signed appointment actions. Preserve it as the transactional authority.

### Target workflow

Public profile → location → one/multiple compatible services → professional preference → date/time → customer details/account → forms/policies → deposit/payment → confirmation.

### Rules

- Price, duration, staff eligibility, location, and payment requirement are server-derived.
- A hold has an explicit expiry and accessible countdown.
- If inventory changes, explain what changed and offer valid alternatives.
- Direct links continue to work independently of marketplace listing.

### MVP / advanced

- **MVP:** richer profile, service information, team, policies, trust, mobile polish.
- **Advanced:** group bookings, classes, multi-service optimization, available-now discovery, promotions, customer account continuity.

## 8. Loyalty and retention

### Problem solved

Businesses need consistent ways to earn a repeat visit without indiscriminate discounting.

### Core workflow

Qualifying completed sale → earn rule evaluation → ledger entry → visible progress → redemption reservation → completed redemption or reversal → reporting.

### Required data/backend

Program/version, qualification rule, earning event, points/value account, immutable entries, expiry, reservation, redemption, reversal, and liability report.

### Rules

- No points on voided/refunded amounts; adjustments are compensating entries.
- Rule versions are retained for audit.
- Marketing consent is not implied by membership.

### MVP / advanced

- **MVP:** simple spend- or visit-based program within one business.
- **Advanced:** tiers, bonus events, household accounts, cross-location rules, and partner-funded rewards.

## 9. Memberships and packages

### Problem solved

Memberships create recurring benefits; packages pre-sell a defined quantity of services. They are not interchangeable.

### Data and backend

- `ClientMembershipPlan`, `ClientMembership`, recurring charge agreement, benefit rule, cycle/usage, pause/cancel, failure state.
- `ServicePackage`, purchased package, service/location eligibility, session balance, expiry, transfer/refund policy.
- Both post to the shared value ledger and sale/payment evidence.

### Rules

- Name the domain explicitly to avoid collision with staff `Membership` and SaaS `BusinessSubscription`.
- Entitlements are reserved at booking and consumed on completion unless policy states otherwise.
- Failed recurring payment does not delete history; benefits follow documented grace/recovery rules.

### MVP / advanced

- **MVP:** one-business, fixed-benefit memberships and fixed-session packages.
- **Advanced:** tiers, freezes, family sharing, add-ons, cross-location redemption, upgrades/downgrades.

## 10. Gift cards and wallet/credits

### Problem solved

Gift cards drive acquisition; credits preserve value through refunds/adjustments. A wallet presents the customer's available merchant-issued value.

### Required architecture

Use an append-only ledger:

```text
ValueAccount
  ├─ Instrument (credit, gift card, package, loyalty, membership benefit)
  ├─ Entry (issue, earn, reserve, redeem, expire, reverse, adjust)
  └─ Liability owner + currency + redeemable scope
```

### Rules/security

- Never mix currencies inside one account.
- Gift codes are high-entropy, hashed where possible, rate-limited, and auditable.
- Balances are projections of entries, not independently editable fields.
- Jurisdiction-specific expiry, cash-out, abandoned-property, tax, and liability requirements require legal review.

### MVP / advanced

- **MVP:** merchant-specific credits and gift cards in one currency.
- **Advanced:** scheduled delivery, corporate gifting, cross-location/network cards, transfers—only after liability ownership is defined.

## 11. Reviews

### Problem solved

Verified reviews help customers choose and give businesses structured feedback.

### Workflow

Completed eligible appointment → time-limited review invitation → rating/text/media → moderation → publish → merchant response → report/appeal.

### Rules

- One review per eligible appointment with controlled edit window.
- Sponsored status never changes review content or rating.
- Clearly explain moderation, aggregation, and removal.
- Separate private feedback from public review when appropriate.

### MVP / advanced

- **MVP:** verified rating/text, response, report, moderation.
- **Advanced:** service/staff dimensions, media, reputation syndication, quality insights.

## 12. Marketing

### Problem solved

Merchants need measurable, consented retention—not merely bulk messaging.

### Workflow

Define objective → select event-based audience → show consent/reach estimate → choose template/channel → schedule → deliver → handle callbacks/actions → attribute booking/revenue/unsubscribe.

### Reuse

The current communications domain already provides templates, consent, quiet hours, suppression, callbacks, and dedupe. Add campaign orchestration and reporting on top of it.

### Rules

- Marketing opt-in is channel, purpose, and business specific.
- Transactional and marketing purposes remain separate.
- Attribution windows and exclusions are documented.

### MVP / advanced

- **MVP:** rebook, lapsed-client, birthday, and cancellation-fill segments.
- **Advanced:** journeys, experiments, referral programs, promotion budgets, and marketplace attribution.

## 13. Payments

### Four separate domains

1. **Business SaaS billing:** ClipperDesk charges the business subscription.
2. **Appointment commerce:** the business charges a customer for deposits/services/products/tips.
3. **Stored value:** merchant liability for credits, gift cards, packages, memberships, and loyalty.
4. **Marketplace settlement:** potential platform fee, connected account, payout, dispute, and reserve flows.

Never reuse a business subscription record as evidence for an appointment payment or client membership.

### Current reuse

- `SubscriptionProvider` and Stripe billing webhooks for SaaS.
- `AppointmentPaymentProvider`, payment intents, signed appointment webhook, deposit allocation, refunds, sales, and reconciliation for merchant commerce.

### Future requirements

- Formal connected-account/MoR decision before Stripe Connect.
- Provider-account capability/onboarding state, charge/transfer/payout/reversal/dispute evidence, and country support matrix.
- Reconciliation from provider balance transaction to internal sale/fee/payout ledger.

## 14. Marketplace

### Problem solved

Customers need trusted supply; businesses need incremental demand. The marketplace must be additive, opt-in, and measurable.

### Core workflow

Query/location/time → search index → profile/service → availability preview → booking flow → attribution → appointment/payment → review eligibility → repeat relationship.

### Required data/backend

Public profile, geo coordinates, taxonomy, amenities, price summaries, team portfolios, availability summary, review aggregates, ranking signals, sponsorship labels, search events, attribution, and listing state.

### Business rules

- Direct booking survives marketplace unlisting.
- Ranking factors and paid placement are explainable and labelled.
- Availability is a fresh projection; final booking still uses the authoritative scheduling engine.
- Only publish fields explicitly marked public.

### MVP / advanced

- **MVP:** opt-in profiles, city/category/service directory, basic filters, verified reviews.
- **Advanced:** available-now, personalized saved searches, promotions, sponsored discovery, marketplace fees, and supply/demand tools.

## 15. Communication

### Channels

- Email and WhatsApp: extend current reliable provider abstractions.
- SMS: add only with provider, sender, consent, regional, and cost controls.
- Push/in-app: add with customer PWA/account and granular preferences.

### Rules

- One notification event can fan out to channel-specific delivery attempts.
- A provider callback changes delivery evidence, not the underlying booking state.
- Every template has locale, version, purpose, variables, preview, and fallback.
- Quiet hours may delay marketing but must not suppress time-critical transactional events incorrectly.

## 16. Connected data map

| Event | Operational effect | Customer effect | Financial effect | Communication/reporting effect |
|---|---|---|---|---|
| Appointment confirmed | Capacity committed | Upcoming booking appears | Deposit allocated if required | Confirmation/reminder scheduled; booking funnel updated |
| Appointment rescheduled | Old slot released, new slot reserved | Timeline and calendar update | Deposit remains linked or policy recalculates | Change notice; reschedule metrics |
| Appointment completed | Staff/service delivery finalized | Becomes rebook/review eligible | Checkout can finalize | Follow-up, loyalty earn, utilization/revenue metrics |
| Sale refunded | No appointment deletion | Receipt/value view updates | Compensating refund/ledger entries | Notification; net-revenue and refund reports |
| Review submitted | No schedule mutation | Review status visible | None | Aggregate projection and moderation queue |
| Membership payment failed | Benefits enter recovery policy | Recovery CTA | Failed attempt evidence; no history deletion | Dunning communication and liability/report update |

## 17. Scalability model

- Keep transactional writes in the modular monolith and MySQL with strict tenant keys.
- Use queues, transactional outbox, and idempotent consumers for notifications, search, analytics, provider reconciliation, and imports.
- Build public search against a separate denormalized index/read model.
- Store media in object storage behind signed/CDN delivery; do not proxy large assets through app workers.
- Partition queues by criticality and provider; apply backpressure and rate limits.
- Measure hot tenants/queries before sharding or extracting services.
- Create immutable metric definitions and event schemas before a separate analytics store.

## 18. Privacy and security model

- Tenant scope is mandatory on every business-owned record and query.
- Location scope narrows operations; it never replaces tenant scope.
- Authorization is authentication → membership → role → entitlement → limit → context.
- Customer-account linking is explicit and revocable.
- PII is minimized, encrypted where appropriate, redacted from logs, and exported/deleted through audited workflows.
- Consent is purpose/channel/business/version specific.
- Payment credentials remain tokenized by the provider.
- Financial, value, review, and marketplace-attribution events are immutable or corrected by compensating records.

