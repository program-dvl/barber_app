# Product Vision and Roadmap

**Status:** Strategic proposal; not an automatic promotion of deferred PRD scope  
**Date:** 2026-08-30  
**Planning horizon:** 24–36 months

Companion documents:

- [Competitive gap analysis](01-FRESHA-COMPETITIVE-GAP-ANALYSIS.md)
- [Business and client ecosystem](03-BUSINESS-AND-CLIENT-ECOSYSTEM.md)
- [UX and onboarding strategy](04-UX-ONBOARDING-AND-CLIENT-EXPERIENCE.md)
- [Implementation master plan](05-IMPLEMENTATION-MASTER-PLAN.md)

## 1. Product vision

ClipperDesk should become the dependable operating system and customer-growth network for appointment-led beauty, wellness, and personal-care businesses.

It should be valuable in three increasingly powerful modes:

1. **Operating system:** a business can run bookings, staff, clients, money, communications, inventory, and reporting without joining a marketplace.
2. **Customer ecosystem:** customers can maintain one secure account while each business retains an isolated CRM relationship and consent record.
3. **Opt-in network:** businesses can publish profiles and available inventory into a discovery marketplace with transparent attribution and commercial terms.

The sequence matters. A marketplace cannot compensate for weak merchant activation, and a customer account cannot compensate for unreliable booking or payments.

## 2. Positioning and product principles

### Target wedge

Start with independent and growing multi-staff businesses in hair, barbering, nails, beauty, spa, and massage. These share a strong appointment/staff/service/client model and match the current codebase.

Medspa, physical therapy, broader health practices, fitness/recovery, tattooing, tanning, mobile-only professionals, virtual services, and pet grooming should enter through explicit vertical capability profiles. Regulated health and pet services require separate compliance and subject-model decisions and must not be enabled by merely adding dropdown values.

### Differentiators

- Fast path from registration to first bookable slot.
- Reliable calendar, checkout, communications, and recovery under real operational pressure.
- Transparent plan, payment, and marketplace economics.
- Merchant-owned direct booking that is not disabled by leaving a marketplace.
- Privacy-safe consumer identity and explicit business/client consent boundaries.
- Strong auditability and data portability.
- Useful defaults without hiding how the system is configured.

### Non-negotiables

- Preserve the modular monolith until measured scale requires extraction.
- Keep business SaaS billing, customer appointment commerce, stored value, and marketplace settlement as separate financial domains.
- Enforce tenant, location, role, entitlement, and usage constraints server-side.
- Use append-only financial and consent evidence.
- Make demo data clearly labelled, reversible, and incapable of producing real financial/provider events.
- Support guest booking even after customer accounts exist.

## 3. Roadmap overview

| Phase | Indicative window | Outcome | Decision gate |
|---|---:|---|---|
| 1. Foundation | 0–6 months | Existing operations become coherent, usable, and launch-ready | Core workflow reliability and activation targets met |
| 2. Best-in-class SaaS | 4–12 months | Strong direct-booking and business operating product | Healthy retention, payments, support, and operational SLIs |
| 3. Customer ecosystem | 9–18 months | Durable customer identity, retention, and stored-value products | Privacy, identity-linking, ledger, and payment reviews complete |
| 4. Marketplace | 15–27 months | Opt-in discovery and demand network | Pilot-city supply, search quality, attribution, and commercial policy proven |
| 5. Multi-location and enterprise | 18–30 months | Consolidated controls for groups and franchises | Multi-location adoption and governance demand proven |
| 6. Platform ecosystem | 24–36 months | APIs, integrations, and partner distribution | Versioning, security, support, and partner economics ready |

Windows overlap only after exit evidence is met. They are not commitments.

## 4. Phase 1 — Foundation

### Goals

- Make current backend capabilities visible as complete owner/staff workflows.
- Reduce time to first bookable slot and first completed checkout.
- Stabilize subscription, entitlement, membership, location, and UI state.
- Complete merchant payment/refund/reconciliation launch evidence.

### Features

- Adaptive business-setup wizard backed by the existing onboarding session.
- First-class Services, Team, Location, and Availability pages.
- Launch readiness as actionable tasks, not a giant form.
- Consistent entitlement, permission, missing-setup, and payment-recovery states.
- Improved dashboard/calendar/walk-in/client/checkout task continuity.
- Richer direct-booking business profile and mobile booking polish.
- Currency/tax/phone/address defaults based on a regional capability catalogue.
- Optional labelled demo workspace or safe guided preview.

### Dependencies and changes

- Reuse `OnboardingManager`, `ReadinessEvaluator`, `OwnerOnboardingService`, `DefaultLocationProvisioner`, existing service/staff/location models, and entitlement middleware.
- Add a versioned setup-answer/profile structure only where normalized business fields cannot express conditional answers.
- Replace placeholder routes with domain controllers and Vue pages; do not duplicate onboarding writes.
- Formalize regional metadata: country, currency, time zone, language, address template, phone region, tax posture, and payment-method availability.

### Payment and security work

- Certify the appointment-payment lifecycle from intent through webhook, allocation, refund, and reconciliation.
- Preserve separate webhook secrets and idempotency stores for subscription and appointment events.
- Verify every new management endpoint against business, membership, permission, feature, location, and limit.

### Complexity / impact

- **Complexity:** Medium–high, mainly product integration rather than new domains.
- **Business impact:** Very high; activation, conversion, support burden, and perceived completeness.

### Success metrics

- Median registration-to-first-bookable-slot under 20 minutes; P90 under 45 minutes.
- At least 70% of verified new businesses complete one bookable path within seven days.
- No raw authorization/setup errors in instrumented production journeys.
- Booking and checkout critical-path success above 99.5%, excluding customer validation failures.
- Reduced setup-related support tickets and payment reconciliation exceptions.

## 5. Phase 2 — Best-in-Class Salon & Wellness SaaS

### Goals

- Make recurring daily work faster than incumbent tools.
- Turn CRM and communications into measurable retention workflows.
- Support disciplined expansion into adjacent, compatible verticals.

### Features

- Calendar productivity: multi-select, recurring blocks, quick rebook, conflict explanations, resource visibility, and front-desk mode.
- Service templates and vertical capability profiles.
- Team lifecycle: invitations, non-login staff, roles, shifts, commissions, leave, and utilization.
- CRM timeline, segmentation, forms/tasks, communication history, preferences, and duplicate resolution.
- Checkout reliability, catalog search, taxes, deposits, refunds, tips, receipts, and inventory consumption.
- Lifecycle communications: confirmation, reminders, forms, post-visit, rebook, no-show recovery, and consented campaigns.
- Decision-oriented dashboards with documented metric definitions.
- Direct-booking profile, portfolio, team, policies, accessibility, and tracking.

### Backend/database work

- Introduce taxonomy entities (`IndustryVertical`, `ServiceTaxonomyNode`, `BusinessCapabilityProfile`) instead of extending enums indefinitely.
- Add transactional outbox and idempotent consumers for cross-domain projections.
- Add explicit availability/search read models without weakening the scheduling write boundary.
- Add communication segment snapshots and campaign delivery evidence.

### Frontend/UX work

- Role-aware home views for owner, manager, receptionist, and service provider.
- Responsive task surfaces and keyboard/touch shortcuts.
- Consistent command palette/search and domain-specific empty/recovery states.
- Workflow-based settings: Business, Locations, Team access, Booking, Payments, Communications, and Security.

### Payment/security work

- Country/payment capability matrix and graceful unsupported-region handling.
- Automated reconciliation and alerting; strict redaction of provider payloads.
- Tax snapshots and refunds remain append-only; no mutable historic totals.

### Complexity / impact

- **Complexity:** High.
- **Business impact:** Very high; retention, expansion revenue, and referrals.

### Success metrics

- Weekly active businesses and active staff by role.
- Appointment creation time, schedule conflict rate, no-show rate, rebooking rate.
- Checkout completion and refund-resolution time.
- Gross and net revenue retention, module adoption, and support contacts per active business.

## 6. Phase 3 — Customer Ecosystem

### Goals

- Give customers a secure, convenient identity and booking history.
- Improve repeat booking and retention without leaking data across businesses.
- Establish one financial ledger for merchant-issued value products.

### Features

- Customer registration/login, verification, profile, preferences, communication choices, and account deletion/export.
- Consent-based link between global customer identity and each tenant `Client`.
- Upcoming/history, reschedule/cancel, rebook, forms, receipts, and reminders.
- Favourites and preferred professionals.
- Saved payment methods through provider tokens; no card data stored by ClipperDesk.
- Merchant-specific wallet credits, packages, memberships, gift cards, and loyalty.
- Installable PWA with deep links and notification preferences.

### Backend/database work

- New `CustomerAccount` / `ConsumerProfile` identity separate from `Client`.
- `ClientAccountLink` with verification, link provenance, revocation, and tenant-scoped disclosure.
- Append-only value ledger with account, instrument, entry, reservation, redemption, expiry, and reversal records.
- Dedicated `ClientMembership` name/domain to avoid collision with staff `Membership` and business subscription.
- Review eligibility and moderation foundations may be built late in this phase.

### Security/privacy

- Never expose a customer's relationship with Business A to Business B.
- Require step-up verification for identity linking, saved-payment changes, and stored-value transfers.
- Model marketing consent per business/channel/purpose; global account preferences do not grant a merchant marketing permission.
- Define data-controller responsibilities, retention, portability, deletion exceptions, and minor/dependent handling before launch.

### Complexity / impact

- **Complexity:** Very high because identity and money boundaries change.
- **Business impact:** High; repeat rate, conversion, and lower booking friction.

### Success metrics

- Account attach rate, verified-link success, repeat booking rate, rebook time.
- Customer booking conversion vs guest flow.
- Stored-value liability accuracy and redemption rate.
- Privacy requests completed within SLA and zero cross-tenant disclosure incidents.

## 7. Phase 4 — Marketplace

### Goals

- Create incremental discovery without making direct booking dependent on marketplace membership.
- Prove liquidity in narrow markets before broad expansion.
- Establish fair ranking, review, attribution, and monetization rules.

### Staged rollout

1. **Publish:** opt-in SEO profiles, direct booking, structured data, portfolios, verified details.
2. **Directory:** city/category browsing, service search, maps, filters, and favourites.
3. **Intent inventory:** availability windows, available-now, offers, and waitlist matching.
4. **Commercial network:** clearly labelled sponsored positions, qualifying-new-client attribution, and optional marketplace fees.

### Backend/infrastructure

- Search index fed by outbox projections; never run public discovery queries over transactional tenant tables.
- Geospatial location index, service taxonomy, denormalized availability summaries, quality signals, and freshness timestamps.
- Review eligibility tied to completed appointments, moderation queue, merchant response, appeals, and fraud signals.
- Attribution ledger with source, click/session, booking, identity, cancellation/refund, and fee decision.
- Marketplace policy/version acceptance and audit.

### Payments

- Complete a formal merchant-of-record, tax, refund, dispute, chargeback, payout, reserve, and country analysis.
- Stripe Connect is a candidate, not an assumption. Choose direct charges, destination charges, or separate charges/transfers only after the commercial/legal model is accepted.
- Keep marketplace settlement separate from business SaaS invoices and merchant sales records.

### Go-to-market

- Pilot one city/category cluster with existing paying businesses.
- Seed demand through merchant-owned booking links, local SEO, rebooking, referrals, and selected partnerships.
- Do not promise “nearby” or “available now” until coverage and freshness thresholds are met.

### Complexity / impact

- **Complexity:** Very high.
- **Business impact:** Potentially transformative, but only with liquidity and trust.

### Success metrics

- Search coverage, zero-result rate, profile-to-service and service-to-book conversion.
- Incremental new-client bookings, repeat rate after marketplace acquisition, cancellation/no-show rate.
- Merchant opt-in/retention, attribution disputes, acquisition cost, contribution margin.

## 8. Phase 5 — Multi-Location & Enterprise

### Goals

- Support regional groups and franchises without weakening local autonomy or tenant isolation.
- Deliver consolidated governance, catalogue management, access, and reporting.

### Features

- Business groups/brands, location hierarchy, shared templates with local overrides.
- Central service/product catalogue, pricing zones, policies, roles, and communication governance.
- Staff mobility with explicit home/working locations and compensation rules.
- Consolidated reporting, inter-location gift/credit policy, and group-level audit.
- SSO/SCIM, approval workflows, data-retention policies, and enterprise support controls where justified.

### Architecture/security

- Introduce group scope as a new authorization boundary; do not infer it from shared email/domain.
- Keep location financial close and legal-entity reporting explicit.
- Partition high-volume data and reporting workloads based on measured usage.

### Complexity / impact

- **Complexity:** Very high.
- **Business impact:** High ACV and lower logo churn, with longer sales/support cycles.

### Success metrics

- Multi-location activation, time to add a location, template adoption, local override rate.
- Consolidated report latency and cross-location authorization defects.
- Enterprise retention, expansion, implementation time, and support load.

## 9. Phase 6 — Platform Ecosystem

### Goals

- Let partners extend distribution and operations safely.
- Reduce switching friction through supported imports, exports, and accounting/marketing connections.

### Features

- Versioned public APIs, scoped OAuth applications, outbound webhooks, and developer portal.
- Accounting, calendar, payment, communication, analytics, and commerce integrations.
- Partner catalogue, certification, rate limits, usage visibility, and revocation.
- Bulk migration tooling and repeatable onboarding services.

### Security/operations

- Per-tenant scopes, consent, secret rotation, replay protection, audit, quotas, and abuse response.
- Contract/version lifecycle and deprecation policy.
- Partner sandbox with synthetic data only.

### Complexity / impact

- **Complexity:** High.
- **Business impact:** High defensibility and lower integration friction once the core is stable.

### Success metrics

- Active integrations per business, API reliability, partner-attributed activation/revenue.
- Integration-related incident and support rates.

## 10. Now, next, later

### Now

- Complete and certify current subscription/access/registration stabilization.
- Replace Staff and Services placeholders.
- Split setup into a resumable wizard and workflow settings.
- Improve direct public profile/booking and launch readiness.
- Complete payment/refund/reconciliation evidence and regional defaults.
- Establish event/outbox and metric definitions where current coupling requires them.

### Next

- CRM timeline/segments, lifecycle messaging, service templates, role-focused workspaces.
- Customer account and tenant-safe identity linking.
- PWA, reviews, favourites, rebooking, saved payment methods.
- Stored-value ledger followed by merchant-specific loyalty, packages, gift cards, and memberships.
- Opt-in public directory pilot.

### Later

- Marketplace inventory/ranking/monetization and settlement.
- Enterprise hierarchy/franchise controls.
- Public APIs/integrations and native applications where PWA evidence justifies them.
- High-risk verticals only after explicit regulatory/domain decisions.

## 11. Required decision records

Before implementation, add or accept decision records for:

1. Target vertical clusters and excluded/regulated categories.
2. Global customer identity, tenant CRM linking, and data-controller responsibilities.
3. Demo workspace/data isolation and deletion semantics.
4. Stored-value accounting, expiry, breakage, refunds, and jurisdiction limits.
5. Merchant-of-record, connected accounts, payouts, disputes, reserves, and marketplace fees.
6. Marketplace ranking, sponsorship, attribution, reviews, and appeals.
7. Business group/franchise tenancy and legal-entity boundaries.
8. Public API/OAuth and partner governance.

## 12. Portfolio governance

Every phase should pass five gates:

- **Evidence:** customer problem and baseline metric are documented.
- **Boundary:** tenant, identity, financial, privacy, and ownership implications are accepted.
- **Design:** happy path, empty state, recovery, mobile, accessibility, and support journey are reviewed.
- **Delivery:** migrations, API contracts, audit, idempotency, observability, tests, and rollout controls exist.
- **Outcome:** adoption and business impact meet the pre-agreed threshold before expanding scope.

