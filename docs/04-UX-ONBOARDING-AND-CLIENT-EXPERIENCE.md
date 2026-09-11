# UX, Onboarding, and Client Experience

**Status:** Experience blueprint; staged delivery required  
**Date:** 2026-08-30

This design is grounded in the [competitive analysis](01-FRESHA-COMPETITIVE-GAP-ANALYSIS.md) and the current ClipperDesk onboarding, readiness, scheduling, booking, CRM, billing, and payment implementation. See the [ecosystem model](03-BUSINESS-AND-CLIENT-ECOSYSTEM.md), [roadmap](02-PRODUCT-VISION-AND-ROADMAP.md), and [implementation plan](05-IMPLEMENTATION-MASTER-PLAN.md).

## 1. Experience principles

1. **Ask only what is needed for the next useful outcome.** Do not start with every setting.
2. **Show cause and effect.** “These hours become the owner's initial shifts,” not just “Opening hours.”
3. **Default safely, disclose clearly.** Defaults are editable and never silently create financial or consent decisions.
4. **One concept, one home.** Setup can deep-link to Services or Team, but should not maintain duplicate editors.
5. **Protect progress.** Autosave, resume, idempotent provisioning, and clear retry states.
6. **Keep guest paths.** Customer accounts add continuity; they do not block browsing or direct booking.
7. **Explain business states.** Permission, plan lock, usage limit, incomplete setup, subscription recovery, and system error are different experiences.
8. **Design mobile and recovery with the happy path.** Loading, empty, partial, offline/retry, provider delay, and conflict states are first-class.

## 2. Information architecture

### Business application

```text
Home
Calendar
Bookings
Walk-ins
Clients
Checkout
Team
Services
Inventory
Reports

Business
  Overview
  Locations
  Online booking
  Payments & taxes
  Communications
  Brand & public profile

Account & access
  My profile
  Team access & roles
  Subscription & billing
  Security
  Audit & privacy
```

“Settings” becomes a secondary concept, not the starting destination. Business configuration is organised around operating workflows. Team access is separated from schedulable Team profiles in the UI while remaining connected.

### Customer product

```text
Discover
Search
Favourites
Bookings
  Upcoming
  History
Wallet
Account
  Profile
  Payment methods
  Notifications & consent
  Privacy & security
```

On a business profile: Overview, Services, Team, Reviews, About/Policies.

## 3. New-business onboarding model

Use a short **Create your workspace** sequence, followed by an actionable **Launch your booking page** workspace. The short sequence should take 2–4 minutes; the launch sequence can be resumed.

### Global interaction rules

- Progress shows named stages, not an intimidating field count.
- Each step saves on Continue; optional draft autosave is debounced and visibly confirmed.
- Back never loses data.
- URL and server session represent the current step so refresh/login/verification can resume.
- Skip is offered only when the information is genuinely optional; explain impact.
- Conditional questions are driven by a versioned capability profile.
- Validation is inline, specific, and preserves server truth.
- Completion lands on the next best task, not a generic dashboard.

## 4. Create your workspace — screen specification

### Step 1: What kind of business do you run?

- **Ask:** primary vertical and up to three relevant specialties.
- **Why:** seed service templates, terminology, booking rules, form suggestions, and setup path.
- **Default:** none; searchable recommended categories.
- **Conditional:** regulated and structurally different categories show scope/compliance availability before proceeding.
- **Validation:** one supported primary vertical.
- **Automatic configuration:** assign a versioned capability profile and suggested service taxonomy.

Do not expose one undifferentiated list forever. Group categories into Hair & grooming, Beauty & nails, Spa & body, Wellness & recovery, Clinical/regulated, and Other supported services.

### Step 2: Tell us about the business

- **Ask:** business name, operating model, owner/manager role.
- **Operating model:** clients visit a location, mobile provider visits clients, virtual services, or mixed where supported.
- **Why:** determines location/address, travel, availability, and public-profile requirements.
- **Automatic configuration:** preserve the idempotently provisioned business/location; label the default location appropriately.

### Step 3: Where do you operate?

- **Ask:** country, address or service area, time zone.
- **Suggested defaults:** browser region/time zone only as suggestions; user confirms.
- **Conditional:** address for physical location; service radius for mobile; platform availability warning where payments/channels are unsupported.
- **Automatic configuration:** currency, phone region, address format, week start, language suggestion, tax posture options, payment capability matrix.

Country is not a cosmetic field. Changing it after live financial activity requires a guarded migration or a new location/legal entity.

### Step 4: Team size

- **Ask:** just me, 2–5, 6–15, 16+, and whether anyone needs login access now.
- **Why:** choose setup path and explain plan limits before the user invests work.
- **Default:** “Just me” only if account facts support it.
- **Automatic configuration:** create the owner StaffProfile when the owner provides services; otherwise prompt for the first provider.

### Step 5: Start with services

- **Ask:** choose templates or create first services; name, duration, price, and online visibility.
- **Why:** services drive staff qualification, availability, booking, tax, and reporting.
- **Conditional:** processing/cleanup, resources, variants, add-ons, or consultation only after the base service.
- **Automatic configuration:** category, currency, default tax behaviour, current location eligibility, owner eligibility where appropriate.

Bulk templates are editable suggestions, not immutable industry assumptions.

### Step 6: Business hours

- **Ask:** normal location hours with copy-to-days.
- **Why:** provide customer expectation and safe initial shift defaults.
- **Automatic configuration:** initial staff working rules inherit the hours, visibly labelled “Using business hours.” Staff can override later.

### Step 7: Team setup

- **Ask:** first providers' names; email/login is optional; choose services and location.
- **Skip logic:** a solo service provider can confirm the owner profile instead.
- **Automatic configuration:** active profile, location assignment, selected qualifications, inherited hours. Invitation is sent only after explicit confirmation.

### Step 8: Booking preferences

- **Ask:** online booking on/off, new-client policy, notice/window, cancellation cutoff, staff preference, appointment interval.
- **Defaults:** capability/profile-based and expressed in plain language.
- **Conditional:** waitlist, deposits, group capacity, gender request, and resources only where relevant.
- **Automatic configuration:** public rules and scheduling constraints.

### Step 9: Payments and tax

- **Ask:** accept online deposits now, tax registration/posture, inclusive/exclusive display, default rate where valid.
- **Why:** prevent later pricing surprises.
- **Conditional:** payment-provider onboarding based on country/currency; deposits only after provider readiness.
- **Skip:** “Set up later” keeps pay-at-venue and adds a launch improvement, not a false blocker unless business policy requires payment.
- **Automatic configuration:** tax defaults for newly created services and payment readiness task.

### Step 10: Communications

- **Ask:** transactional email/WhatsApp channels, sender/business contact, reminder timing.
- **Do not ask:** blanket customer marketing consent; that belongs to each customer relationship.
- **Automatic configuration:** safe transactional templates, quiet-hour defaults, channel capability checks.

### Step 11: Public profile

- **Ask:** logo, cover, short description, phone/email visibility, photos, policies, booking slug.
- **Why:** build trust and enable direct booking.
- **Automatic configuration:** live preview on mobile and desktop with private draft URL.

### Step 12: Review and launch

- Show three groups:
  - **Required to publish:** precise blockers with Fix action.
  - **Recommended:** confidence/conversion improvements.
  - **Can do later:** clearly optional modules.
- Preview the exact customer journey.
- Publish uses the existing server-side readiness evaluator; never rely on visual checklist state.
- Completion CTA: “Open your calendar” and secondary “Copy booking link.”

## 5. Demo and empty-state strategy

Offer two safe choices after workspace creation:

- **Set up my business:** real data and launch tasks.
- **Explore with a demo workspace:** isolated, labelled, synthetic data; one-click reset/delete.

Never mix sample appointments, clients, sales, or payment data into a real production workspace without an explicit namespace/flag. Demo entries cannot trigger webhooks, communications, invoices, payouts, loyalty, or real reporting. If a separate demo workspace is too expensive initially, use a guided static preview instead.

Every empty state has:

- what this area does;
- what is needed first;
- one primary action;
- optional import/template action;
- no misleading metrics.

## 6. Launch readiness redesign

Readiness is an owner task centre, not a permanently heavy sidebar.

### Required

- Business identity and regional settings.
- One active operating location or supported mobile/virtual model.
- One active online-visible service.
- One qualified active provider.
- One valid provider/location availability path.
- Booking rules/policies.
- Public preview completed.

### Recommended

- Logo/cover/photos and full description.
- Online payment/deposit readiness.
- Reminder channel.
- Client/product import.
- Additional cancellation and privacy details.

### Interaction

- Dashboard card shows “4 of 7 required steps” and the next highest-value action.
- Full task centre groups required/recommended/completed and deep-links to canonical editors.
- Completed setup collapses to a small “Business profile health” entry.
- If a previously valid path breaks, show an operational alert with cause and affected booking channel.

## 7. Dashboard redesign

### Owner

- Today: appointments, expected service value, unpaid/deposit exceptions, staff coverage.
- Attention: conflicts, failed payments/messages, incomplete forms, low stock, readiness problems.
- Momentum: rebooking, no-shows, utilization, revenue trend with metric definitions.
- Quick actions: add booking, walk-in, client, sale, block, service, staff.

### Receptionist

Prioritize today's arrivals, walk-ins, schedule gaps, client search, payment status, and communication actions. Hide business-billing and sensitive revenue detail unless permitted.

### Service provider

Prioritize personal schedule, client preparation, forms, notes permitted for role, checkout handoff, and availability changes.

No role sees a disabled navigation item without a reason. Plan-locked features may be visible with a lock and preview; permission-only features are usually hidden.

## 8. Services experience

- Directory with active/online status, category, locations, qualified staff, duration, price, tax, and delivery-path health.
- Guided creation: Basics → Pricing & timing → Where/who → Online booking → Advanced.
- Vertical templates and duplicate action.
- Inline warnings for no eligible staff/location/resource.
- Bulk edit for visibility, category, location, tax, and price with preview/audit.
- Do not force add-ons/resources/processing time into the first form.

## 9. Team and availability experience

### Team directory

Show profile, bookable status, login/access state, locations, services, next shift, and setup issues. Actions: add provider, invite access, edit services, edit schedule, deactivate.

### Availability

- Week grid with inherited business hours, custom shifts, breaks, leave, and exceptions.
- Explicit time zone and location.
- Copy week, repeat rules, conflict preview, and effective date.
- “No access” staff can still be schedulable; make this distinction clear.

Settings should contain only a status summary and links to these canonical modules.

## 10. Calendar, bookings, and walk-ins

### Calendar

- Fast day/week views with location/staff filters that persist.
- Appointment cards expose status, client, service, payment/form flags, and resource conflicts without visual overload.
- Quick create from an empty slot; drag/reschedule requires a server validation preview and recovery if state changed.
- Explain unavailable slots and conflicts in human language.

### Booking detail

Use one timeline: booked, changed, messages, forms, arrival, service, checkout, payment/refund, and follow-up. Keep audit/internal detail behind permissioned disclosure.

### Walk-ins

Touch-friendly add-client or guest entry, requested service/provider, quoted wait, status, assignment, and convert-to-appointment. Phone input defaults from business country while allowing any country. Never show a raw 403 in a modal; route failures to permission/setup/plan recovery states.

## 11. Clients experience

- Make “Add client” a primary action beside search/import.
- Quick-create asks name plus one contact method; advanced fields remain optional.
- Standard international phone component with region default, E.164 storage, formatted display, and explicit validation.
- Client profile tabs: Overview, Appointments, Sales & payments, Forms/files, Notes, Communications/consent, Preferences, Activity/privacy.
- Display duplicates and merge decisions without silently combining consent or financial history.
- Future account-link status is visible but does not expose global activity from other businesses.

## 12. Payments experience

- Checkout shows services/products, discounts with permission, tax, deposits/credits, tips, tender, and amount due.
- Payment state is not an opaque spinner: creating → action required → processing → succeeded/failed/unknown.
- Unknown state triggers server/provider reconciliation before allowing another charge.
- Refund dialog explains maximum refundable amount, original tender, reason, and downstream value/commission effects.
- Business subscription billing and customer checkout use visually distinct language and routes.

## 13. Notifications and communications experience

- Event-based settings: booking confirmation, reminder, change/cancel, forms, receipts, payment recovery, rebook.
- Each event shows enabled channels, recipient, timing, template preview, consent requirement, and fallback.
- Delivery history is attached to appointment/client timelines with safe error summaries.
- Marketing campaigns are a later separate workflow with audience, consent reach, cost estimate, test send, schedule, and attribution.

## 14. Business/account setup after launch

Replace the giant settings concept with canonical workflow pages:

- **Business profile:** identity, contact, country/language, brand, public details.
- **Locations:** address/service area, time zone, hours, resources, booking state.
- **Online booking:** rules, policies, availability horizon, customer requirements, profile/links.
- **Payments & taxes:** merchant readiness, deposits, tax defaults, receipts, refund policy.
- **Communications:** transactional events, sender, channels, templates, consent controls.
- **Team access:** memberships, roles, location scope, invitations.
- **Subscription & billing:** current plan, usage, invoices, payment method, lifecycle actions.
- **Security/privacy/audit:** authentication, support access, export/deletion, audit.

## 15. Customer portal and marketplace experience

### Authentication

- Browse and check availability without account.
- At confirmation, choose continue as guest or sign in/create account where guest booking is permitted.
- Email/mobile verification is redirect-safe and resumes the held booking if still valid.
- Social login does not bypass tenant terms/consent.

### Discover

- Search by service/category and location; time is optional.
- Results show actual bookable relevance: service match, price range, rating count, distance, next availability, and sponsored label.
- Zero results offer time/radius/category alternatives, not an empty screen.

### Business profile

Hero photos, verified details, rating/review count, address/directions/service area, amenities, hours, policies, services, team, portfolio, and accessibility. Sticky “Book” action on mobile.

### Booking

1. Service(s): clear duration, price, deposit, and preparation.
2. Professional: Any for earliest/lowest valid option or named provider with difference explained.
3. Time: date strip/calendar, timezone, grouped slots, waitlist.
4. Details/account: minimal information, phone region, existing-account detection without disclosure.
5. Payment/policy: server-derived amount, cancellation terms, stored-value options.
6. Confirmation: add calendar, directions, forms, manage link/account, notification expectation.

### Upcoming and history

Cards show business, service, provider, local date/time, status, payment/form needs, directions, and manage actions. Rebook starts with the previous selection but revalidates current price/availability.

### Wallet, loyalty, memberships, packages, gift cards

Use separate labelled balances and expiry/policy details; do not imply cash equivalence. Every item links to transaction history, eligible businesses/locations/services, and support path.

### Reviews and favourites

Only eligible completed appointments can create verified reviews. Favourites are private to the customer. Removing a favourite has no effect on the business's CRM data.

## 16. Responsive, accessibility, and content standards

- Meet WCAG 2.2 AA for contrast, focus, labels, error association, target size, keyboard operation, and reduced motion.
- Mobile uses sticky primary actions and bottom sheets only when focus, history, escape, and screen readers work correctly.
- Avoid loading full HTML error responses into dialogs/iframes. Render typed business-state responses.
- Dates always include relevant location time zone when ambiguity exists; money always includes currency.
- Status labels use text and icon, never color alone.
- Destructive actions state consequence, effective date, reversibility, and affected records.

## 17. Error and locked-state language

| State | UI treatment | Primary action |
|---|---|---|
| Plan does not include feature | Feature preview/locked page with value and required plan | Compare plans |
| Usage limit reached | Current usage, limit, remediation choices; never delete records | Manage usage or upgrade |
| Subscription past due | Persistent billing banner; grace impact and date | Update payment method |
| User lacks permission | Friendly full-page/inline state; no upgrade message | Contact administrator |
| Location/setup missing | Explain dependency and preserve entered work | Complete setup |
| Concurrent availability change | Keep selections, explain changed slot, show alternatives | Choose another time |
| Provider state unknown | Prevent duplicate action, reconcile in background | Check status / retry safely |
| Unexpected error | Stable reference ID, safe retry, support route | Try again |

## 18. Experience measurement

- Registration completion and verification-resume success.
- Time and steps to first bookable slot, first booking, and first checkout.
- Drop-off per onboarding and customer-booking step.
- Empty-state primary-action conversion.
- Calendar task time and scheduling conflict recovery.
- Customer account attach, rebook, reschedule, no-show, and repeat rates.
- Payment completion, duplicate prevention, unknown-state resolution, and refund turnaround.
- Accessibility defects, rage clicks, raw-error exposure, and support contacts by journey.

## 19. Benchmark evidence limits

The audit captured Fresha's public consumer marketplace, business product/pricing pages, business authentication entry, customer authentication, business profile, and public booking through time selection. It did not create an external Fresha account, submit a booking, or inspect an authenticated merchant workspace. Recommendations about authenticated flows combine Fresha's official help-centre documentation with the visible public entry and are labelled as benchmark guidance rather than an implementation-level clone.

