# Implementation Master Plan

**Status:** Actionable engineering blueprint; epics marked `FUTURE` remain outside approved launch scope until promoted by PRD/decision record  
**Date:** 2026-08-30

Inputs: [competitive gap](01-FRESHA-COMPETITIVE-GAP-ANALYSIS.md), [product roadmap](02-PRODUCT-VISION-AND-ROADMAP.md), [ecosystem model](03-BUSINESS-AND-CLIENT-ECOSYSTEM.md), and [experience specification](04-UX-ONBOARDING-AND-CLIENT-EXPERIENCE.md).

## 1. Delivery rules

- Preserve tenant isolation, location scope, role/permission, subscription entitlement, usage limits, audit, time zones, and append-only financial history.
- Reuse current domain services and tests; Vue pages are clients of domain behaviour, not alternate business logic.
- Ship vertical slices with migrations, APIs, UI, error/recovery states, instrumentation, tests, documentation, and rollback controls.
- Prefer expand/backfill/switch/contract migrations. Never reinterpret historic financial or consent records in place.
- Every external write uses idempotency; every webhook is signature-verified, deduplicated, replay-safe, and observable.
- A customer-facing account never weakens business CRM isolation.
- Marketplace and enterprise phases require explicit exit evidence and accepted decisions.

## 2. Classification legend

- **EXISTING – keep:** production-worthy boundary to preserve.
- **EXISTING – improve:** functioning capability that needs product/integration work.
- **REFACTOR:** current behaviour should move behind a clearer boundary without a rewrite.
- **NEW:** approved near/next capability once prerequisites are met.
- **FUTURE:** strategic design only; not currently approved scope.

## 3. Current reusable architecture

| Area | Reuse | Required change |
|---|---|---|
| Tenancy/access | `Business`, `Membership`, business-scoped roles/permissions, tenant middleware, audit/support access | Add future customer/group scopes without weakening tenant scope |
| Onboarding | `OwnerOnboardingService`, `DefaultLocationProvisioner`, `OnboardingManager`, `ReadinessEvaluator` | Split UI and add versioned conditional answers/tasks |
| Catalogue/team | Service/category/add-on/location/staff/resource models and assignment services | First-class controllers/pages; eliminate placeholder destinations |
| Scheduling | Atomic booking service, availability engine, holds, segments, resources, walk-ins, waitlist, exceptions | Add projections and richer UX; retain one write boundary |
| CRM | Client identity/duplicates, consent, forms, notes, attachments, privacy | Add customer-account link later; improve timeline/segments |
| Commerce | Sales, tax snapshots, tenders, refunds, deposits, tips, cash close, inventory | Certify provider integration; add value ledger separately |
| Business billing | Plans/prices, Stripe provider, checkout, webhooks, lifecycle, entitlements, restrictions | Finish lifecycle UX and operations; do not merge with appointment commerce |
| Appointment payments | `AppointmentPaymentProvider`, Stripe intents/webhooks/reconciliation | Add connected accounts only after accepted settlement decision |
| Communications | Provider contracts, templates, consent, quiet hours, dedupe, callbacks | Add event orchestration, campaign domain, more channels conditionally |
| Reporting | Dashboard/report services, exports, instrumentation | Metric registry, projections, cohorts/network reporting later |

## 4. Dependency sequence

```text
E1 access/billing stabilization
  → E2 onboarding + regional configuration
    → E3 services + E4 team/availability
      → E5 scheduling UX + E6 direct booking
        → E7 CRM + E8 commerce/payments + E9 communications/reporting
          → E10 customer identity
            → E11 value/retention products + E12 reviews/public profiles
              → E13 marketplace
                → E14 marketplace settlement

E15 multi-location/enterprise builds after E3–E9 stabilize.
E16 APIs/integrations builds after domain contracts and event schemas stabilize.
```

## 5. Epic 1 — Access, subscription, and product-state foundation

**Classification:** EXISTING – improve  
**Goal:** one deterministic answer for whether a user can perform an action and one typed UI explanation when they cannot.

### Feature / user story

**Workspace access resolver.** As a staff member, I see and can execute only actions allowed by my business membership, role, subscription, usage, location, and setup state.

### Backend tasks

- Consolidate request decisions through the existing workspace access/entitlement services and middleware.
- Define typed denial reasons: unauthenticated, not-member, permission, feature, subscription status, limit, location, setup.
- Ensure menu capability payload uses the same resolver as routes/policies.
- Finish Stripe checkout reconciliation, webhook ordering/dedupe, cancellation/grace, payment recovery, and provider operations.

### Frontend/API tasks

- Map typed 401/403/409/422 business states to full-page, banner, inline, or plan-lock components.
- Eliminate raw error HTML inside dialogs/iframes.
- Contextual billing CTAs: current, Stripe-confirmed upgrade, switch interval,
  manage, cancel, and resume. Lower-plan changes remain support-controlled and
  are not offered as owner self-service.

### Data/integrations

- Reuse billing/subscription tables and entitlement catalogue; add denial/event codes only if absent.
- Stripe remains authoritative through signed webhooks; browser success is confirmation UX, not activation authority.

### Tests/security

- Matrix: owner/staff × role × plan × subscription status × location × feature.
- Duplicate checkout/webhook, out-of-order events, grace/resume, past due, no membership, cross-tenant IDs.
- Verify no secret/provider payload/PII leaks in errors or logs.

### Acceptance criteria

- Calendar, walk-in, reports, settings, and billing navigation agree with server enforcement.
- Same-plan checkout cannot create a duplicate subscription.
- Expected business-state failures never render raw framework errors.

## 6. Epic 2 — Adaptive business onboarding and regional configuration

**Classification:** REFACTOR (UI/orchestration), EXISTING – keep (provisioning/readiness)

**Wave 2 status (updated 2026-09-13):** Core activation and the adaptive
first-run experience are delivered. Business setup now begins with four
versioned, resumable decisions and safely generates editable starter records in
the canonical Location, Team, Services, commerce and public-booking models.
Regional suggestions and persistence remain centralized. A separate relational
taxonomy/profile schema is still later scale work; the current versioned config
and answer payload deliberately avoid duplicating product sources of truth.

### Feature / user story

**Create and launch workspace.** As a new owner, I can create a correctly provisioned business and first bookable path in short, resumable steps.

### Backend tasks

- Preserve verified-email → business → owner membership/role → default location → trial transaction.
- Extend `OnboardingSession` with schema version and safe step payload references where needed.
- Introduce taxonomy/capability profile records rather than expanding `business_type` indefinitely.
- Formalize `RegionalProfile`: country, default currency, phone region, time zones, address format, week start, languages, tax/payment capability.
- Keep `ReadinessEvaluator` the publish authority and expose stable blocker/action codes.

### Frontend tasks

- Implement the 12-step experience in the UX blueprint with autosave/resume/skip logic.
- Canonical deep links to Services, Team, Availability, Payments, and Public profile editors.
- Mobile/desktop preview and actionable launch centre.

### Database/API changes

- New taxonomy/profile tables and optional onboarding-answer/version table.
- Versioned endpoints: show session, save step, validate, preview, publish.
- Safe migration from existing string business types to mapped taxonomy plus `legacy_value` evidence.

### Testing/security

- First registration/verification resume, concurrent retries, stale step, duplicate callbacks, invalid region combinations.
- Assert one business/membership/default location/subscription per registration intent.
- File upload type/size/privacy; no provider action from demo data.

### Acceptance criteria

- Refresh/login/verification returns to the exact valid step.
- A solo owner can create one bookable service path without visiting a giant settings form.
- Country-derived defaults are suggestions and persisted only after confirmation.

## 7. Epic 3 — Services and resource catalogue

**Classification:** EXISTING – improve

**Wave 2 status (2026-08-31):** Launch-critical catalogue/editor delivered with
create/retry, edit, archive/restore, currency/tax derivation, Location/provider
assignment and readiness. Templates, bulk actions, duplication, rich resource
editing and concurrency-version UI remain follow-up scope.

### Feature / user story

As an owner/manager, I can create, organize, price, publish, assign, and diagnose services from a dedicated module.

### Tasks

- Build controllers/requests/resources over current Service, category, add-on, staff/location assignment, duration, price, tax, and physical-resource models.
- Add service templates linked to versioned taxonomy; copying creates tenant-owned editable records.
- Add delivery-path health projection: online visibility, eligible location, qualified staff, availability, resources.
- Build directory, guided editor, bulk actions, duplicate, archive, and audit history.

### Data/API

- Prefer current tables; add template/taxonomy references and optional effective-date/version fields only when required.
- APIs server-derive currency, entitlement limits, and permitted assignments.

### Tests/security/acceptance

- Tenant/location isolation, permission, plan limits, archive with future appointments, concurrent edits.
- Published service cannot claim a delivery path that scheduling rejects.
- Remove the Services placeholder only when create/edit/archive/assign/readiness flows pass.

## 8. Epic 4 — Team, access, and availability

**Classification:** EXISTING – improve

**Wave 2 status (2026-08-31):** Launch-critical Team workspace delivered with
non-login provider creation/retry, Location/Service assignment, weekly hours,
overlap validation, plan limits and explicit login-access state. Rich profile
editing, invitations/revocation, split shifts, breaks/leave and effective-date
templates remain follow-up scope.

### Feature / user story

As an owner, I can manage providers and app access without confusing a staff profile with a login membership.

### Tasks

- Team directory combines StaffProfile, optional User/Membership, locations, services, shift source, and setup health.
- Add/edit non-login staff; explicit invite/revoke access; roles and location scope remain permissioned workflows.
- Visual working-shift, break, leave, and exception editor with inheritance from location hours.
- Validate service/location/staff/resource compatibility through existing scheduling services.

### Data/API

- Reuse StaffProfile, assignments, availability rules, Membership, roles/permissions.
- Add shift template/version/effective-date metadata only if the existing recurrence model cannot express UX needs.

### Tests/security/acceptance

- No-login provider remains bookable; revoked member loses access but historic appointments remain.
- Cross-location scope, daylight-saving transitions, overlaps, plan staff limit, invitation replay.
- Remove Staff placeholder after the full owner lifecycle is accessible.

## 9. Epic 5 — Calendar, booking, and front-desk excellence

**Classification:** EXISTING – improve

### Feature / user story

As front-desk staff, I can schedule, move, arrive, waitlist, and complete work quickly with explainable conflict recovery.

### Tasks

- Preserve the scheduling write boundary; add task-optimized query/read models.
- Add appointment drawer/timeline, quick create, filters, status/payment/form flags, recurring blocks, and clear conflict alternatives.
- Refine walk-in add/client lookup/queue/assignment/conversion for touch screens.
- Add optimistic UI only when every mutation can reconcile authoritative server state.

### Testing

- MySQL concurrency, capacity/resource constraints, time zones/DST, idempotency, hold expiry, waitlist races, permission/location matrix.
- Browser tests for keyboard, touch, focus, modal escape/history, mobile layouts, and typed errors.

### Acceptance criteria

- Two concurrent requests cannot overbook capacity.
- A rejected move preserves the appointment and offers valid alternatives.
- Front desk never needs a placeholder/settings screen for a routine booking task.

## 10. Epic 6 — Direct booking and public profile

**Classification:** EXISTING – improve

### Feature / user story

As a customer, I can trust a business and complete a mobile booking from a direct link without marketplace participation.

### Tasks

- Extend public profile payload with approved photos, location/service-area, hours, policies, amenities, team/public portfolio, and accessible directions.
- Preserve current start/search/hold/confirm/deposit/waitlist and secure-link actions.
- Add multi-service compatibility only after booking-engine support is certified.
- Add structured metadata, canonical URLs, sitemap control, share/embeddable booking links, and source attribution.

### Data/API

- Explicit public/private fields and media ordering/captions.
- Signed upload/delivery; public read model/cache invalidated from outbox events.
- Rate limiting, bot/abuse controls, hold quotas, and PII minimization.

### Acceptance criteria

- Direct booking remains available when future marketplace listing is off.
- Prices, deposits, availability, and terms are always server-derived.
- Public profile and booking meet accessibility/performance budgets.

## 11. Epic 7 — CRM, consent, and client operations

**Classification:** EXISTING – improve

### Feature / user story

As authorized staff, I can manually add, find, understand, and serve a client while respecting consent and privacy.

### Tasks

- Promote Add client, international phone input, quick create, import, duplicates, and merge.
- Build a unified client timeline from appointments, sales, forms, messages, consent, notes, and privacy events.
- Add event-based segments as reproducible query definitions/snapshots; no opaque mutable tags as sole evidence.
- Distinguish internal notes from future customer-visible information.

### Tests/security

- E.164 normalization without destructive rewriting, duplicate/merge financial and consent preservation, private file signed links, export/delete/legal holds.
- Field-level/role permissions for sensitive records; complete tenant tests.

### Acceptance criteria

- Walk-in/phone booking can create or attach a client in seconds.
- Merge never silently broadens marketing consent or loses source records.

## 12. Epic 8 — Merchant commerce and appointment payments

**Classification:** EXISTING – improve

### Feature / user story

As a business, I can collect deposits and checkout customers with reliable payment, refund, receipt, tax, tip, and reconciliation evidence.

### Tasks

- Certify `AppointmentPaymentProvider` lifecycle, unknown-state recovery, duplicate prevention, and signed webhook processing.
- Complete checkout state machine and refund/void/adjustment policy.
- Reconcile provider intent/charge/refund/balance evidence to deposits, sales, and tenders.
- Add country/currency/payment-method readiness and explicit unsupported states.

### Data/API/integration

- Preserve append-only Sale, tender, tax snapshot, refund, deposit allocation, tip, cash close.
- Correct legacy metadata names prospectively; retain compatibility for historic events.
- Provider idempotency key per business/intent/action; secrets never reach frontend.

### Tests/acceptance

- Success, decline, authentication required, timeout, browser close, webhook-before-redirect, duplicates, refund, partial refund, currency mismatch, provider outage.
- Unknown payment state cannot trigger a blind second charge.
- Daily/provider reconciliation exceptions are visible and actionable.

## 13. Epic 9 — Communications, reporting, and operational insight

**Classification:** EXISTING – improve

### Feature / user story

As an owner, I can configure reliable lifecycle messages and understand operations using defined metrics.

### Tasks

- Build event-oriented notification settings and appointment/client delivery timeline over current contracts.
- Add SMS only behind provider/region/consent/cost controls.
- Introduce metric registry: name, owner, definition, dimensions, currency/time-zone rule, freshness.
- Add role-aware dashboards and exception queues; move heavy exports/projections to queues.
- Add transactional outbox for reliable domain event publication where direct coupling remains.

### Tests/security/acceptance

- Consent/purpose/channel, quiet hours, suppression, provider retry/callback ordering, dedupe, template locale/fallback.
- Reports reconcile to transactional evidence and enforce tenant/location/permission scopes.
- No sensitive message/provider payload appears in logs or broad staff views.

## 14. Epic 10 — Customer accounts and tenant-safe linking

**Classification:** NEW

### Feature / user story

As a customer, I can use one secure account for bookings while each business retains a private, independent CRM relationship.

### Backend/database

- Add `customer_accounts`, `consumer_profiles`, verification/security sessions, and `client_account_links` with provenance/scopes/revocation.
- Add customer-facing appointment projection containing only explicitly safe fields.
- Add account export/deletion workflow and per-business communication preferences.
- Resolve/link existing clients with verified email/mobile and non-disclosing challenge flows.

### Frontend/API

- Customer auth, profile, upcoming/history, secure manage/rebook, forms, receipts, favourites shell.
- Guest-to-account attach after booking and account-to-existing-client linking.
- Customer API under a distinct guard/rate limit; do not reuse staff business routes.

### Tests/security/acceptance

- Account takeover, enumeration, link replay, shared phone/email, changed contact, revoked link, business deletion, customer deletion, cross-tenant query/property tests.
- Business B cannot infer or read Business A relationship.
- Guests retain all current signed-link appointment management.

## 15. Epic 11 — Stored value, loyalty, packages, memberships, and gift cards

**Classification:** NEW

### Feature / user story

As a customer, I can safely buy/earn/use merchant-issued value; as an owner, I can report the liability and honour rules over time.

### Backend/database

- Build append-only value accounts, instruments, entries, reservations, redemption, reversal, expiry, and balance projections.
- Add program/version tables for loyalty, service packages, gift cards, and `ClientMembership` plans/subscriptions/benefits.
- Post every issue/sale/redemption/refund/expiry to commerce and value evidence transactionally or through idempotent orchestration.

### Frontend/API

- Merchant configuration with liability/policy preview.
- Checkout application order, eligibility, reservation, and receipts.
- Customer wallet with separate instrument types, history, expiry, and eligible scope.

### Integrations/security/tests

- Recurring client-membership payment provider contract remains distinct from business SaaS billing.
- High-entropy gift codes, rate limits, no editable balances, maker/checker for manual adjustments.
- Property tests for balance invariants; concurrent redemption; refund/reversal; expiry; multi-currency rejection; chargeback.

### Acceptance criteria

- Projected balance equals immutable entries and reconciles to liability reports.
- Records are never deleted to correct a balance.
- Merchant-specific MVP launches before network-wide instruments.

## 16. Epic 12 — Reviews, favourites, and public reputation

**Classification:** NEW

### Feature / user story

As a customer, I can save businesses and leave a verified review; as a business, I can respond and appeal fairly.

### Tasks/data

- Add review eligibility from completed appointment, review/version, response, report, moderation, appeal, aggregate projection.
- Add customer favourites and preferred-provider references separated from tenant Client preferences.
- Add public profile verification state and portfolio moderation.

### Tests/security/acceptance

- Eligibility, one-review rule, edits, deleted accounts, refunds/no-shows policy, moderation roles, abuse/rate limits.
- Sponsorship cannot mutate ratings; removed content retains moderation evidence.

## 17. Epic 13 — Marketplace discovery pilot

**Classification:** FUTURE

### Feature / user story

As a customer in a supported pilot market, I can find relevant opt-in businesses and real availability; as a merchant, I can measure incremental demand.

### Backend/infrastructure

- Search index with public business/location/service/team/review/availability projections and freshness.
- Geocoding/service-area policy, taxonomy mapping, filters, ranking feature registry, and quality thresholds.
- Search/session/click/profile/booking attribution events with privacy limits.
- Listing opt-in, pause/unlist, policy acceptance, and direct-booking independence.

### Frontend

- Search, location, categories, filters, maps/list, zero-result recovery, profile, favourites, available-now where coverage is adequate.
- Clearly label sponsored/featured results.

### Tests/security/acceptance

- Search index cannot expose private/disabled tenant data; delete/unlist propagation SLA.
- Availability preview is never booking authority; final hold revalidates.
- Pilot launch requires minimum supply/coverage, maximum zero-result rate, freshness SLI, moderation/support runbook.

## 18. Epic 14 — Marketplace payments, fees, and payouts

**Classification:** FUTURE; blocked on legal/commercial decision

### Feature / user story

As a participating business, I understand each platform fee, payout, refund, and dispute; as the platform, we reconcile every movement.

### Required decisions

- Merchant of record, seller of record, tax responsibility, countries, KYC/KYB, disputes/chargebacks, negative balances, reserves, payout schedule, refunds, tips, and commission attribution.
- Stripe Connect charge type and loss liability based on accepted model—not convenience.

### Backend/data/integration

- Connected account/capability/onboarding records; charge, transfer, platform fee, reversal, dispute, reserve, payout, balance transaction, and reconciliation evidence.
- Attribution ledger decides fees using versioned policy and qualifying-new-client rules.
- Provider webhooks separate from SaaS subscription and ordinary appointment-payment event processing.

### Tests/security/acceptance

- Onboarding incomplete/restricted, split fee, refund after payout, partial refund, dispute won/lost, negative balance, payout failure, event reordering/duplicates, unsupported country.
- Finance/support views redact sensitive data and provide audit/recovery.
- No launch until legal, finance, security, and provider production-readiness signoff.

## 19. Epic 15 — Multi-location and enterprise controls

**Classification:** EXISTING – improve, then FUTURE hierarchy

### Feature / user story

As a multi-location operator, I can govern shared standards while locations retain explicit operational and legal differences.

### Tasks

- Improve current location switcher, assignments, location reports, and local readiness.
- Add future BusinessGroup/Brand hierarchy, membership/group scopes, templates with local overrides/effective dates, consolidated reports, approval workflows, and group audit.
- Model legal entity, currency, tax, payment account, and value redemption scope explicitly.

### Tests/acceptance

- Cross-location permission and report leakage tests; template update preview/rollback; local override precedence; staff mobility; legal/currency boundaries.
- Group membership never implicitly grants tenant access.

## 20. Epic 16 — Marketing and retention operations

**Classification:** NEW after communications/CRM/customer identity

### Feature / user story

As a business, I can reach consented audiences with lifecycle campaigns and measure bookings without obscuring attribution.

### Tasks

- Segment definitions/snapshots, campaigns, variants, schedule, test send, channel attempts, conversion attribution, unsubscribe/suppression.
- Starter journeys: rebook, lapsed, birthday, cancellation-fill, post-visit review.
- Cost/reach preview and frequency limits.

### Tests/security/acceptance

- Purpose/channel/business consent, quiet hours, locale, suppression, deletion, duplicate events, attribution exclusions.
- A campaign cannot send to ineligible consent state even if the UI payload includes that recipient.

## 21. Epic 17 — Platform API and integrations

**Classification:** FUTURE

### Feature / user story

As an approved partner/business, I can integrate supported data/actions through stable, scoped contracts.

### Tasks

- OAuth client/scopes, versioned REST/events, outbound webhooks, signatures/replay protection, rate/usage limits, developer portal, sandbox.
- Start with low-risk exports and calendar/accounting connections; add writes only with domain idempotency and audit.
- Contract tests, compatibility/deprecation policy, partner review/revocation.

### Acceptance criteria

- Scope maps to tenant/location/role; application identity never bypasses domain authorization.
- Webhook replay and secret rotation are supported and observable.

## 22. Epic 18 — Global readiness and vertical expansion

**Classification:** REFACTOR / FUTURE by country and vertical

### Feature / user story

As a supported business, I receive accurate regional defaults and a truthful statement of available payments, communications, taxes, and vertical capabilities.

### Tasks

- Regional capability registry: countries, currencies, time zones, locales, phone/address formats, payment methods, messaging senders, tax support, legal docs.
- Versioned vertical taxonomy/capability profiles and service templates.
- Translation workflow, fallback policy, RTL readiness, locale-aware money/date/name/address.
- Explicit compliance gates for regulated health and structurally different pet/dependent records.

### Acceptance criteria

- “Country supported” means the promised capability set passes production tests; otherwise show partial availability before signup/payment.
- No new high-risk vertical launches through a dropdown-only change.

## 23. Cross-epic migration strategy

1. **Expand:** add nullable/new tables and dual-compatible readers.
2. **Backfill:** idempotent command with progress, tenant batches, audit, and resumability.
3. **Verify:** counts, foreign keys, business invariants, financial reconciliation, sample tenant review.
4. **Switch:** feature flag/canary to new writes and reads.
5. **Observe:** errors, latency, business metrics, queue lag, data drift.
6. **Contract:** remove obsolete columns/routes only after a release window and recovery signoff.

Never delete existing appointments, clients, payments, consent, subscription, staff, or location history because a new model cannot initially represent it.

## 24. Test programme

### Automated layers

- Unit: money/value invariants, time zones, ranking features, policy decisions.
- Feature: tenant/role/entitlement/location matrix and every domain command.
- Architecture: scheduling write boundary, financial append-only rules, forbidden cross-domain shortcuts.
- Contract: Stripe, communication, search, geocoding, partner APIs.
- Concurrency: holds, booking, checkout, redemption, webhook replay, import/merge.
- Browser: registration/verification resume, onboarding, public booking, calendar, checkout, billing, customer account, accessibility.
- Migration/recovery: seeded production-like snapshots, backfill restart, rollback/read compatibility.

### Critical end-to-end journeys

- Register → verify → setup → first bookable slot → publish.
- Direct booking → hold → deposit → webhook → confirmation → reschedule/cancel/rebook.
- Appointment → arrive → complete → checkout → refund → receipt/reconciliation.
- Trial → paid subscription → upgrade/downgrade → past due → recover → cancel/grace/resume/expire.
- Owner vs manager vs receptionist vs provider across locations/plans.
- Customer guest → account link → history → saved method/value → privacy request.
- Marketplace search → booking attribution → review → unlist/delete propagation.

## 25. Observability and operations

- Correlation IDs across request, domain event, queue, webhook, provider object, appointment/sale/subscription.
- SLIs: booking success/latency/conflicts, payment unknown rate, webhook lag/failure, message delivery, search freshness/zero results, report freshness, tenant access denials.
- Dead-letter/replay tools require permission, reason, idempotency preview, and audit.
- Runbooks for provider outage, webhook backlog, double-charge suspicion, payout failure, data export/deletion, marketplace abuse, and search staleness.

## 26. Release gates

| Gate | Required evidence |
|---|---|
| Foundation launch | Staff/services UI complete; onboarding/readiness, billing/access, booking, checkout, and regional tests pass |
| Customer account beta | Accepted identity/privacy decision; cross-tenant tests; guest compatibility; export/delete runbook |
| Stored-value beta | Accepted accounting/legal policy; invariant/concurrency tests; liability reconciliation |
| Marketplace pilot | Supply threshold, search freshness, ranking/review policy, moderation/support, direct-booking independence |
| Marketplace payments | Accepted MoR/Connect model; KYC, disputes, payouts, finance reconciliation and production signoff |
| Enterprise beta | Group authorization model, legal/currency boundaries, migration and consolidated-report evidence |
| Partner API beta | OAuth/scopes, version policy, sandbox, rate/abuse controls, audit and support model |

## 27. Immediate implementation backlog

1. Finish the current Stripe/access/registration stabilization and update verified project status.
2. Replace Staff and Services placeholder routes with real management vertical slices.
3. Split the configuration page into the adaptive setup shell and canonical workflow editors.
4. Add regional capability consistency tests and remove/redirect legacy reference-data duplication.
5. Certify appointment payment/refund/reconciliation in test mode and production-readiness documentation.
6. Enrich the direct-booking profile and preserve the existing transactional flow.
7. Add client quick-create/phone standardization and unified task-oriented empty states.
8. Establish the outbox/metric registry only where required by public projections, lifecycle messages, and reliable analytics.
9. Draft the customer-identity and stored-value decision records before schema work.
10. Define marketplace pilot success/failure thresholds before building search infrastructure.

## 28. Definition of done for every feature

- User outcome and non-goals are documented.
- Tenant/identity/location/role/entitlement/limit decisions are explicit.
- Data ownership, retention, audit, idempotency, time-zone, currency, and privacy are handled.
- Happy, empty, loading, validation, conflict, provider-delay, permission, plan-lock, and retry states are designed.
- APIs are server-validating and version/contract tested.
- Metrics and operational alerts exist; support can diagnose without sensitive access.
- Automated tests and browser/accessibility checks pass.
- Relevant docs, decision records, status, migrations, rollback, and runbooks are updated.
- Feature can be disabled or rolled back without losing committed customer or financial data.
