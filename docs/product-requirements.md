---
title: Barber Shop and Salon Management SaaS - Phase 1 MVP
status: launch-baseline
version: 1.0
prepared: 2026-07-27
authority: canonical-product-requirements
---

> This Markdown file is the canonical, searchable transcription of the approved Phase 1 PRD. Curated module documents may clarify implementation, but changes to product scope must be reflected here.

PRODUCT REQUIREMENTS DOCUMENT

# Barber Shop & Salon Management SaaS

_Phase 1 MVP Product Requirements Document_

Document status: Launch baseline

Version: 1.0

Prepared: 27 July 2026

Primary audience: Product, design, engineering, QA, operations, sales and support

Phase objective: A chargeable, self-serve system for the daily operation of barbershops and salons

PRODUCT PROMISE Run the complete client journey-from booking to checkout and rebooking-without losing control of staff time, physical resources, payments, client history or daily revenue.

CONFIDENTIAL WORKING DOCUMENT

## Contents

_The numbered headings below are available in Word's Navigation pane. Page references reflect this release._

| Section | Title | Page |
| --- | --- | --- |
| 1 | Executive Summary | 3 |
| 2 | Product Vision and Outcomes | 4 |
| 3 | Target Users and Core Jobs | 5 |
| 4 | MVP Principles | 6 |
| 5 | Scope and End-to-End Journey | 7 |
| 6 | Detailed Phase 1 Functional Requirements | 8 |
| 7 | Critical Real-World Scenarios and Acceptance Tests | 21 |
| 8 | Deferred Phase 2 Scope | 23 |
| 9 | Subscription Packaging and Commercial Rules | 24 |
| 10 | Information Architecture and Minimum Navigation | 25 |
| 11 | Technical Foundations and Domain Model | 26 |
| 12 | Non-Functional Requirements | 27 |
| 13 | Implementation Roadmap | 28 |
| 14 | Launch Readiness Checklist | 29 |
| 15 | Success Metrics and Instrumentation | 30 |
| 16 | Benchmark References | 31 |
| 17 | Final MVP Chargeability Test | 32 |

## 1 Executive Summary

This document defines the Phase 1 scope for a multi-tenant SaaS product that enables barbershops, salons, spas and independent professionals to configure their business, accept bookings, run the day, collect payment and understand performance. The MVP is intentionally larger than a basic appointment prototype: it must be useful enough to replace paper diaries, spreadsheets and fragmented messaging while remaining focused enough to launch.

PHASE 1 DEFINITION The product is ready to charge for when a new shop can subscribe without assistance, publish a booking link, prevent staff and resource conflicts, serve online/phone/walk-in demand, collect deposits and final payment, retain client history, close the day and export its data.

The core product loop is: discovery and booking -> confirmation and reminders -> arrival and service delivery -> checkout and payment -> client history -> rebooking. All Phase 1 decisions should improve the reliability, speed or commercial value of this loop.

| Business outcome | Phase 1 capability | Why it is chargeable |
| --- | --- | --- |
| Fill the calendar | 24/7 booking, waitlist and walk-in queue | Captures demand and recovers cancelled slots |
| Protect staff time | Conflict controls, reminders, deposits and cancellation rules | Reduces idle time and no-show loss |
| Run one source of truth | Calendar, client CRM, checkout and audit history | Removes duplicate records and manual reconciliation |
| Control the day | Staff schedules, resources, status workflow and daily close | Supports front-desk and service-floor operations |
| See performance | Operational and financial reports | Turns activity into decisions and renewal value |

Launch posture: web-first, mobile-responsive, one location enabled in the initial plans but location-aware in the architecture, one integrated payment gateway, and a provider abstraction for messaging. Native mobile apps, marketplace discovery and advanced growth modules are deferred.

## 2 Product Vision and Outcomes

Vision. Become the dependable daily operating system for appointment-led personal-care businesses: simple enough for a solo barber, structured enough for a busy front desk, and extensible enough to support multi-location operators later.

The product should deliver five outcomes:

- More completed appointments through easy booking, reminders, waitlist filling and no-show protection.
- Less operational friction through one fast calendar, controlled workflows and automated communication.
- Better client retention through durable profiles, treatment history, notes, consent records and simple rebooking.
- Accurate money movement through deposits, checkout, split payments, refunds, cash close and auditability.
- Confident business decisions through revenue, utilisation, client, product and commission reporting.

POSITIONING Sell operational reliability and revenue protection-not merely a digital calendar. The customer is paying to run the shop with fewer gaps, fewer mistakes and clearer numbers.

### 2.1 Product boundaries

Phase 1 owns the booking-to-checkout workflow and the minimum SaaS administration needed to sell and support it. It does not attempt to be a full payroll, accounting, e-commerce, enterprise franchise or marketing-automation suite.

## 3 Target Users and Core Jobs

| User | Primary job | Essential product surfaces |
| --- | --- | --- |
| Owner / operator | Grow revenue, protect time, monitor the business, control billing and permissions. | Dashboard, reports, settings, subscription, audit |
| Manager | Coordinate staff, resolve exceptions, approve overrides and maintain standards. | Calendar, staff, reports, refunds, inventory |
| Receptionist | Book quickly, check clients in, manage walk-ins and close transactions. | Calendar, queue, clients, checkout |
| Barber / stylist / therapist | See the right schedule and client context, deliver service, record notes and rebook. | Own calendar, client notes, service status |
| Accountant / bookkeeper | Reconcile sales, taxes, payment methods, refunds and commissions. | Read-only finance reports and exports |
| Client | Find a suitable service and time, book with little friction, manage the appointment and receive clear communication. | Public booking and secure self-service |
| Platform operator / support | Manage tenants, subscriptions, failures and support access safely. | Super-admin console |

Priority customer segments for launch:

- Independent barbers and stylists who are moving from phone/WhatsApp bookings.
- Single-location barbershops and salons with 2-15 staff and a shared front desk.
- Beauty, nail and spa businesses that require consultation notes, forms, rooms or equipment.
- Existing businesses switching from spreadsheets or legacy scheduling tools and therefore requiring import.

## 4 MVP Principles

Operational completeness over feature breadth. Complete the booking-to-checkout loop before adding loyalty, marketing or marketplace modules.

Self-serve by default. A shop should register, configure, import clients and publish without vendor assistance.

Fast at the front desk. Common calendar, client and checkout actions must take seconds and work well on touch devices.

Rules before exceptions. Prevent conflicts and policy violations; expose explicit, permissioned overrides with reasons.

One durable record. Appointments, client history, money and audit events must remain linked and traceable.

Tenant safety from day one. Every query, job, export, attachment and webhook must preserve business-level isolation.

Local-market flexibility. Currency, tax, time zone and payment/messaging providers must be configurable.

Web-first reach. Clients should not need an app or password; staff use a responsive web app or PWA.

Entitlements, not plan-name conditionals. Capabilities and limits are modelled independently so pricing can change safely.

Measure the loop. Instrumentation must show acquisition, activation, booking completion, operational health and renewal value.

## 5 Scope and End-to-End Journey

Primary service journey:

1. Business subscribes and completes guided setup.
1. Client discovers the branded booking page or contacts the shop.
1. System finds availability across service, staff, location and required resources.
1. Client confirms policy and pays a deposit if required.
1. System confirms the appointment and schedules reminders.
1. Client arrives or joins the walk-in queue; staff check in and start service.
1. Services, add-ons and retail products flow into checkout.
1. Payment, deposit application, tips, refunds and receipt are recorded.
1. Client history, commission and reports update automatically.
1. Client rebooks or is invited back according to shop policy.

SOURCE OF TRUTH An appointment must remain connected to the client, booked service lines, assigned staff, resources, status history, notifications, deposit, sale, payments and any resulting commission entries.

| ID | Capability | Launch priority |
| --- | --- | --- |
| FR-01 | SaaS registration & subscriptions | P0 |
| FR-02 | Guided onboarding & business setup | P0 |
| FR-03 | Locations, hours & resources | P0 |
| FR-04 | Service catalogue & add-ons | P0 |
| FR-05 | Staff, schedules & permissions | P0 |
| FR-06 | Appointment calendar & lifecycle | P0 |
| FR-07 | Multi-service appointments | P0 |
| FR-08 | Walk-in & waiting queue | P0 |
| FR-09 | Customer-facing online booking | P0 |
| FR-10 | Waitlist & cancellation filling | P1 |
| FR-11 | Client CRM & history | P0 |
| FR-12 | Forms, consent & notes | P1 |
| FR-13 | Notifications & communication | P0 |
| FR-14 | Deposits, cancellations & no-shows | P0 |
| FR-15 | Checkout & basic POS | P0 |
| FR-16 | Lightweight inventory | P1 |
| FR-17 | Basic commissions & tips | P1 |
| FR-18 | Dashboard & reports | P0 |
| FR-19 | Settings & audit history | P0 |
| FR-20 | Platform super-admin | P0 |

## 6 Detailed Phase 1 Functional Requirements

Priority convention: P0 capabilities are required for a chargeable launch; P1 capabilities may ship within the same Phase 1 release train but cannot weaken the core booking-to-checkout journey. Every override that affects availability, money, permissions or records must be attributed and audited.

### FR-01 SaaS Registration and Subscription Management

Purpose: Allow a shop to become a paying tenant, understand its plan and manage its commercial relationship without manual support.

#### Account And Trial

- Capture business name, owner name, verified email and mobile number, country, currency, time zone, language and business type.
- Support email or mobile verification, secure owner login, a clearly dated free trial and onboarding progress.
- Create a tenant only once; prevent duplicate tenant creation when verification, payment or webhook requests are retried.

#### Billing Lifecycle

- Offer monthly and annual billing, saved payment method, automatic renewal, invoices and payment history.
- Support plan upgrade, downgrade, cancellation at period end, immediate cancellation by support, reactivation and coupon codes.
- Handle failed renewals with retry schedule, owner notices, a configurable grace period and progressive restriction after grace expiry.
- Allow data export before closure and apply a defined retention/deletion policy after termination.

#### Entitlements

- Model reusable limits and feature flags, including maximum locations, maximum active staff, messaging allowance, deposits, inventory, advanced reports and custom branding.
- Evaluate entitlements server-side for UI actions, APIs, background jobs and imports; do not rely on hidden navigation alone.
- Record plan and entitlement changes with effective dates and the actor who made the change.

ACCEPTANCE OUTCOME A verified owner can start a trial, choose monthly or annual billing, pay, receive an invoice, change or cancel the plan, survive a failed renewal flow and export data-without support intervention.

### FR-02 Guided Onboarding and Business Setup

Purpose: Minimise time-to-value and switching friction so a shop can publish a usable booking page in one session.

#### Onboarding Wizard

- Guide the owner through business details, opening hours, services, staff, staff availability, booking rules, client import, preview and publish.
- Save progress after every step; allow safe exit, resume and editing after publication.
- Show launch readiness with explicit blockers and optional improvements rather than a vague completion percentage.

#### Business Profile And Rules

- Support logo, cover image, physical address/map, phone, email, website/social links, currency, time zone, week start and appointment interval.
- Configure tax-inclusive or tax-exclusive pricing, default cancellation policy, terms/privacy links and a unique, editable booking-page slug.
- Preview desktop and mobile booking views before publishing; preserve existing links when a slug changes through redirect or alias.

#### Import

- Provide CSV templates, mapping, validation preview and error export for clients, staff, services and products.
- Treat imports as idempotent jobs; report created, updated, skipped and failed rows.
- Detect likely client duplicates and require review before destructive merging.

ACCEPTANCE OUTCOME A new tenant can complete setup, import its existing clients and publish a branded booking page with valid availability in under 30 minutes using sample or real data.

### FR-03 Locations, Operating Hours and Physical Resources

Purpose: Model where and when services can be delivered and prevent overbooking of rooms, chairs, stations or equipment.

#### Location

- Store location name, address, contact data, time zone, normal hours, day-specific hours and location status.
- Support holidays, full-day closure, special opening hours and temporary closure, including an impact preview for existing appointments.
- Allow location-specific staff, services and prices even if the launch plan exposes only one active location.

#### Resources

- Create reusable resource types such as barber chair, salon station, treatment room, nail station, wash basin and equipment.
- Assign required resource quantity to a service or service segment; resources may have their own active hours and maintenance blocks.
- Availability must reserve staff and all required resources atomically; controlled manager override requires a reason.

ACCEPTANCE OUTCOME The availability engine never presents or confirms a slot when the location is closed or a required staff member/resource is unavailable, including simultaneous requests from different devices.

### FR-04 Service Catalogue, Staff Variants and Add-ons

Purpose: Represent sellable services accurately enough to drive availability, price, deposits, checkout and reporting.

#### Core Service

- Capture category, name, description, image, active status, duration, processing time, cleanup/buffer time, base or "from" price and tax category.
- Control online-booking visibility, eligible locations, qualified staff, required resources, consultation requirement and new/existing-client eligibility.
- Support minimum booking notice, maximum advance window and optional fixed/percentage deposit policy.

#### Staff-Specific Variants

- Allow a staff member to have a different service price, duration, skill/qualification status, availability, commission rate and online visibility.
- Resolve the final duration and price before presenting availability and again before confirmation to protect against stale values.

#### Add-Ons

- Offer optional add-ons such as beard trim, hair wash, blow-dry, nail art, conditioning or massage.
- Each add-on may change duration, price, tax, required staff skill or resource usage and must become an explicit appointment and sale line.

ACCEPTANCE OUTCOME A customer or receptionist can select a service/staff combination and see a correct bookable duration, displayed price, resource need, tax and deposit requirement through to checkout.

### FR-05 Staff Profiles, Schedules, Roles and Permissions

Purpose: Give each worker the right schedule and access while protecting client, financial and administrative data.

#### Staff Profile

- Capture name, photo, email, mobile, title, biography, services, locations, staff-specific price/duration, commission, status and online visibility.
- Invitations must expire, be revocable and bind the login to the correct tenant and staff profile.

#### Scheduling

- Support regular weekly hours, split shifts, lunch/recurring breaks, time off, holidays, sick leave, temporary schedule changes and personal blocks.
- Allow work at different locations in the data model and prevent travel-impossible or overlapping assignments.
- When availability changes after bookings exist, show impacted appointments and require reassignment, cancellation or an explicit retained exception.

#### Roles And Permissions

- Provide Owner, Manager, Receptionist, Barber/Stylist and Accountant starter roles with custom permission sets.
- Control access to all versus own calendars, client contact details, appointment deletion, discounts, refunds, revenue, commissions, inventory, settings and subscription billing.
- Permission changes, access revocation and support access must take effect promptly and appear in the audit log.

ACCEPTANCE OUTCOME A former employee loses access, a stylist sees only permitted information, and manager-only actions such as discounts or refunds cannot be executed by UI or API without authorisation.

| Role | Calendar | Clients / appointments | Financial | Configuration |
| --- | --- | --- | --- | --- |
| Owner | All locations and calendars | Full | Full | Full |
| Manager | All within assigned location | Operational | Reports; no subscription by default | Operational settings |
| Receptionist | All operational calendars | Book/check in/checkout | No revenue analytics by default | No |
| Barber / Stylist | Own calendar by default | Own appointments and notes | Own commission optional | No |
| Accountant | Read-only optional | No operations | Reports and exports | No |

### FR-06 Appointment Calendar and Controlled Lifecycle

Purpose: Provide the fastest and most reliable operational surface for the front desk and service team.

#### Views And Filters

- Provide today, day, week and staff-column views with filters for location, staff, service and status.
- Show walk-ins, unassigned appointments, blocked time and meaningful colour/status cues without relying on colour alone.
- Remain usable on desktop, tablet and mobile, with rapid date navigation and a clear current-time marker.

#### Actions

- Create, edit, drag-to-reschedule, resize duration, reassign staff, add/remove service, duplicate, cancel, rebook, add internal notes, block time and print the daily schedule.
- Support booking sources: online, phone, reception, walk-in, recurring, consultation, personal block and staff break.
- Require confirmation for destructive or financially material changes and preserve before/after history.

#### Lifecycle And Conflicts

- Use controlled statuses: Pending confirmation, Confirmed, Arrived, Checked in, In service, Completed, Cancelled by client, Cancelled by shop, No-show, Late and Rescheduled.
- Validate transitions; record actor, time, source, reason and previous state for every status change.
- Prevent double-booked staff/resources, out-of-hours booking, leave/break overlap, unqualified staff, invalid notice windows and race-condition conflicts.
- A permissioned manager may override selected conflicts with a visible warning and mandatory reason; true data-integrity conflicts remain non-overridable.

ACCEPTANCE OUTCOME Reception can operate the full day from the calendar; two concurrent users cannot claim the same staff/resource capacity; every lifecycle change is recoverable through history.

| Status | Meaning | Typical next action |
| --- | --- | --- |
| Pending confirmation | Shop approval required | Approve or reject |
| Confirmed | Capacity reserved | Arrive, reschedule, cancel |
| Arrived / Checked in | Client on premises | Start service |
| In service | Work underway | Complete or adjust |
| Completed | Eligible for checkout/close | Pay, receipt, rebook |
| Cancelled-client/shop | Capacity released per policy | Waitlist match, refund/fee |
| No-show | Visit did not occur | Apply policy, update risk history |
| Late | Client delay recorded | Continue, shorten, reschedule or cancel |
| Rescheduled | Historical terminal marker | Links to replacement booking |

### FR-07 Multi-Service and Multi-Provider Appointments

Purpose: Support realistic salon visits where several services, people, processing periods or providers form one commercial appointment.

#### Composition

- Allow multiple service lines, optional add-ons and a combined customer-facing appointment summary.
- Assign a different staff member and resource to each service segment while preserving sequence and handoffs.
- Support active work, processing and cleanup segments; staff may be released during configured processing time while the resource remains reserved if required.

#### Commercial Behaviour

- Calculate combined duration, displayed price, tax and a single deposit policy for the appointment.
- Use one checkout while allocating service revenue, tips and commission to the correct performers.
- When a service is removed or replaced, recalculate remaining balance and handle excess deposit through transfer, credit or refund policy.

#### Scheduling Integrity

- Find contiguous or valid segmented availability across every staff member and resource before confirmation.
- Make the booking atomically; partial reservation is never visible to another customer.
- Revalidation is required after drag-and-drop, staff reassignment or duration edits.

ACCEPTANCE OUTCOME Haircut + beard trim, colour + processing + wash + blow-dry, and provider handoff scenarios can be booked, changed and checked out without hidden conflicts or duplicated deposits.

### FR-08 Walk-In and Waiting Queue

Purpose: Let appointment-led shops serve unscheduled demand without compromising upcoming bookings.

#### Queue Entry

- Add a walk-in using name and mobile number, requested service, preferred staff or first available, arrival time and notes.
- Estimate wait from current queue, in-progress work, future appointments, staff availability and service duration.
- Display queue position and expected service time; permit manager-controlled reordering with reason.

#### Operations

- Check in, assign/reassign staff, notify when the turn approaches, convert to a normal appointment/client and mark as left or no longer waiting.
- The queue and calendar share one availability model; accepting a walk-in may not create an unacknowledged collision with a future booking.
- Track actual wait time, service start and abandonment for reporting.

ACCEPTANCE OUTCOME Reception can add and serve a walk-in, communicate an evidence-based estimate and protect the staff member's next booked appointment.

### FR-09 Customer-Facing Online Booking and Self-Service

Purpose: Convert demand 24/7 with a low-friction, mobile-first flow that does not require an app or password.

#### Booking Flow

- Choose location, one or more services, preferred staff or any available, date/time, customer details and optional special request.
- Collect name, mobile, email, optional date of birth, referral source and communication/marketing preferences.
- Present the applicable price, duration, deposit, cancellation policy, terms and consent before confirmation.
- Confirm through payment where required and issue a booking reference and calendar-add options.

#### Shop Controls

- Control online services/staff, advance window, minimum notice, cancellation cutoff, deposit/card requirement, new-client restrictions, staff gender request and exact versus "from" price.
- Prevent search engines or public pages from exposing private staff contact data or internal notes.

#### Passwordless Self-Service

- Use short-lived or revocable secure links to view upcoming appointments, reschedule/cancel within policy, rebook, join/leave waitlist, update contact details and view payment/deposit status.
- Changes must revalidate availability and policy at commit time and trigger communication plus audit history.

ACCEPTANCE OUTCOME A new client can book from a phone without creating a password, pay a required deposit, receive confirmation and later reschedule or cancel only within the shop's rules.

### FR-10 Waitlist and Cancellation Filling

Purpose: Recover revenue by matching newly available capacity to customers willing to take it.

#### Waitlist Request

- Capture service, eligible staff, location, acceptable dates, time ranges, notification method, expiry and notes.
- Permit several requests per client but prevent unintentionally duplicated identical requests.

#### Match And Claim

- When capacity opens, identify requests that fit the full service/staff/resource duration and current booking rules.
- Notify one or a controlled batch of clients with a secure claim link and explicit expiry.
- Award the slot to the first valid confirmation using an atomic hold/claim; other links expire gracefully.
- Close or update the request after successful booking and retain match/notification history.

ACCEPTANCE OUTCOME A cancelled slot can be offered and claimed without two clients receiving confirmed ownership of the same capacity.

### FR-11 Client CRM, Service History and Duplicate Control

Purpose: Preserve the context required for safe, personalised service and repeat business.

#### Profile

- Store name, contact data, photo, birthday, preferred employee/services, tags, referral source, communication preference and marketing consent.
- Store allergies, sensitivities, hair-colour formula, hair/skin/treatment notes, patch-test status, special preferences and an internal important warning.
- Show lifetime spend, visit count, last visit, next appointment, cancellations and no-shows.

#### History

- Link appointments, service performers, products, payments, refunds, discounts, tips, notes, attachments, communications and submitted consent forms.
- Protect clinical/sensitive notes with permissions and show authorship plus timestamps; history remains readable after staff deactivation.

#### Duplicates And Privacy

- Detect likely duplicates by normalised mobile, email and similar name/phone combinations.
- Provide previewed, permissioned merge with a selected surviving profile; preserve all relationships and write an audit event.
- Support client data export, correction, deletion/anonymisation request workflow and consent withdrawal according to applicable policy.

ACCEPTANCE OUTCOME Booking automatically creates or updates one client record; staff can see reliable service context; a controlled merge preserves every appointment and financial relationship.

### FR-12 Forms, Consent, Attachments and Notes

Purpose: Collect service-specific information and preserve the exact consent record relied upon at the time of treatment.

#### Form Builder

- Create simple forms with text, number, date, yes/no, multiple-choice, required fields and signature.
- Associate forms with selected services, send before the appointment and show completion status in the calendar/client profile.
- Provide starter templates for consultation, allergy declaration, patch test, treatment consent, hair-colour history and photography consent.

#### Evidence And Retention

- Store an immutable snapshot of the submitted form version, answers, signature, submission time, client identity/link and originating appointment.
- Allow authorised staff to add notes, files and before/after images with clear visibility and retention controls.
- Edits to a form template affect future requests only and never rewrite historical submissions.

ACCEPTANCE OUTCOME An authorised user can prove which form wording and answers were accepted for a specific appointment, even after the template changes.

### FR-13 Notifications and Communication

Purpose: Keep clients and staff informed while respecting consent, local time and delivery reliability.

#### Events And Channels

- Send booking confirmation/pending/approval/rejection, reminder, change, cancellation, deposit/payment receipt, waitlist opening, queue update, feedback request and rebooking reminder.
- Support email and at least one mobile channel at launch; provide abstractions for SMS, WhatsApp, in-app and browser push.
- Separate transactional communications from marketing consent and unsubscribe rules.

#### Templates And Timing

- Allow shop-level templates with validated variables such as client, staff, service, location, date, amount and secure action link.
- Configure reminder offsets and quiet hours in the recipient/location time zone; prevent duplicate sends after retries.
- Preview templates across channels and fall back when a variable is absent.

#### Delivery Operations

- Track queued, sent, delivered, failed, retried and suppressed states with provider response identifiers.
- Retry transient failures with bounded backoff; expose failures to support without revealing unnecessary message content.
- Record consent basis and communication history against the client where applicable.

ACCEPTANCE OUTCOME Every critical appointment and payment event creates at most one intended notification per channel, at the correct local time, with delivery status visible for support.

### FR-14 Deposits, Cancellation Rules and No-Show Protection

Purpose: Protect revenue and staff time without producing ambiguous or unfair charges.

#### Deposit Rules

- Support no deposit, fixed amount, percentage, full prepayment, new-client-only, selected-service, threshold-value and prior-no-show policies.
- Display the rule and amount before payment; bind the successful deposit to the intended appointment and client.

#### Cancellation And Reschedule

- Configure free-cancellation cutoff, deposit refundability, cancellation fee and no-show fee.
- Allow manager waiver/override with reason; notify affected staff and release capacity immediately when cancellation commits.
- Transfer a deposit to the replacement appointment when permitted; never duplicate its applied value.

#### Payment Integrity

- Use idempotency keys and verified webhooks so delayed or duplicate gateway events cannot create double charges or double bookings.
- If payment succeeds but booking finalisation fails, automatically recover the booking or create a visible refund/reconciliation task.
- Handle final totals below the deposit through an explicit refund or credit workflow.

ACCEPTANCE OUTCOME A deposit can be charged, transferred, refunded or forfeited exactly once, with the client-facing policy and manager decisions visible in history.

### FR-15 Checkout and Basic Point of Sale

Purpose: Close the appointment financially in the same system and produce a trustworthy sales record.

#### Basket And Totals

- Bring appointment services and add-ons into checkout; permit authorised addition/removal of services and retail products.
- Calculate tax, discounts, tips, deposit applied and outstanding balance; show source values and rounding consistently.
- Require approval for discounts beyond role thresholds and for price overrides.

#### Payments And Corrections

- Record cash, card, UPI/local method, bank transfer, payment link and custom method; integrate at least one online gateway.
- Support full, partial, split and pay-later payment; allocate tips to one or multiple staff.
- Support full/partial refund, void/correction and transfer of an incorrectly allocated payment through controlled workflows.
- Send receipt through configured channels and retain a printable version.

#### Daily Close

- Record opening cash, cash sales/refunds, expected cash, actual closing cash, difference and explanation.
- Produce a daily settlement summary by payment method and show unresolved or outstanding balances.
- Closing does not rewrite sales; post-close adjustments require elevated permission and remain auditable.

ACCEPTANCE OUTCOME A receptionist can apply a deposit, split the remainder between cash and card, allocate a tip, issue a receipt and reconcile the till; later corrections never erase the original trail.

### FR-16 Lightweight Retail Inventory

Purpose: Support common retail sales and stock visibility without delaying launch for full procurement.

#### Catalogue And Stock

- Store product name, category, SKU/barcode, sale price, cost, tax, active status, current stock and low-stock threshold.
- Import/export CSV; support stock received and permissioned manual adjustments with reason.

#### Movements And Sales

- Deduct stock only when a sale completes; reverse or adjust stock appropriately after void/refund according to product disposition.
- Maintain an append-only movement history with source sale, adjustment, quantity before/after, user and time.
- Report low stock, current valuation inputs and product sales.

ACCEPTANCE OUTCOME A product sold at checkout updates stock once, appears in sales and client history, and can be traced through a movement record.

### FR-17 Basic Commissions and Tips

Purpose: Give owners and staff a transparent payroll input without building full payroll.

#### Rules

- Support service-sales percentage, product-sales percentage and fixed amount per service.
- Calculate after discount using the employee who performed each service; keep tips separate from commission.
- Version rules by effective date so historical statements do not change when settings change.

#### Statements And Adjustments

- Provide staff/date-range statements with underlying sale lines, refunds and net commission.
- Allow manager adjustment with amount, reason and audit history; export for payroll processing.
- Reverse or offset commission predictably after refunds and voids.

ACCEPTANCE OUTCOME The same completed sale produces explainable, reproducible commission and tip totals in staff and manager views.

### FR-18 Dashboard and Operational Reporting

Purpose: Demonstrate measurable business value and support daily management decisions.

#### Today Dashboard

- Show today's appointments by status, walk-ins waiting, staff available, expected revenue, collected revenue, outstanding payments, new clients and low stock.
- Make every count drillable to the filtered records behind it; display data freshness and the location time zone.

#### Reports

- Provide appointment, sales, service revenue, staff revenue, payment method, location, discount, refund, tip, commission, new/returning client, cancellation/no-show, utilisation, popular service, visit frequency, product sales, stock and daily closing reports.
- Support date range and relevant location, staff, service and status filters; export CSV and printable summary.
- Compare with the previous equivalent period where the metric definition is stable.

#### Metric Integrity

- Define gross/net revenue, tax, discounts, refunds, deposits, expected revenue, utilisation and client classification centrally.
- Reports must reconcile to underlying immutable payment/sale events and honour permissions.

ACCEPTANCE OUTCOME An owner can explain the day's collected revenue from the dashboard through report lines to individual sales and payments, and export the evidence.

### FR-19 Shop Settings and Audit History

Purpose: Centralise configuration and create accountability for high-risk operational and financial actions.

#### Settings

- Manage booking, cancellation, tax, receipt, notifications, payment methods, statuses, roles, retention and booking-page branding.
- Validate configuration combinations and warn when a change will affect published availability, future appointments or financial treatment.
- Version critical policies with effective dates; do not silently apply new rules to past transactions.

#### Audit

- Record appointment deletion/override, price change, discount, refund, inventory adjustment, client merge/delete, permission change, subscription change and support access.
- Each event includes tenant, actor, role, action, target, time, source/device where appropriate, reason and before/after summary.
- Audit events are searchable, exportable to authorised users and protected from ordinary editing or deletion.

ACCEPTANCE OUTCOME An owner or investigator can determine who changed a financially or operationally significant record, when, why and what changed.

### FR-20 Platform Super-Admin and Support Operations

Purpose: Operate the SaaS safely across tenant onboarding, billing, support and service health.

#### Tenant And Subscription Operations

- Search shops; view owner, onboarding state, plan, usage, trial/subscription status, invoices, payment failures and activity volumes.
- Activate, suspend or close a business; extend a trial; apply coupon; change plan; initiate export with role controls and audit.

#### Support Tools

- Resend verification/invitation, inspect notification and payment-webhook failures, manage feature flags, publish notices and keep internal account notes.
- Support access requires a ticket/reason, least privilege, time limit, visible banner and immutable audit event; no invisible impersonation.
- Expose health summaries and safe replay for idempotent failed jobs/webhooks.

#### Risk Controls

- Require stronger authentication and session controls for platform administrators.
- Separate platform roles, restrict bulk export and alert on unusual cross-tenant access.

ACCEPTANCE OUTCOME Support can resolve a failed notification or billing issue without direct database manipulation, and every tenant access is attributable and visible.

## 7 Critical Real-World Scenarios and Acceptance Tests

These scenarios are launch acceptance tests, not optional edge cases. Each must be automated where practical and exercised end-to-end in a production-like environment.

### Booking and availability

- Two online customers attempt to claim the same slot simultaneously.
- Receptionist and online customer submit the same staff/resource slot at the same time.
- Employee is free but the required chair, room or equipment is occupied.
- Appointment crosses closing time or overlaps a break, time off or temporary closure.
- A multi-service visit uses different providers and a processing period.
- Staff becomes unavailable after future bookings already exist; impacted appointments are resolved.

### Client and privacy

- A new booking uses the same mobile number with a different name spelling.
- A returning client books without login and securely manages the appointment.
- Client changes phone/email without losing history or weakening link security.
- Client requests data export, correction, consent withdrawal or deletion/anonymisation.
- Two likely duplicate profiles are merged while preserving appointments, payments and forms.

### Appointment operations

- A walk-in is assigned while the employee has an upcoming booking.
- Client arrives late; staff continue, shorten, reschedule or cancel with a reason.
- Service runs long and the calendar communicates the impact without corrupting future capacity.
- An appointment is reassigned; notifications, resource checks and commission attribution update.
- One service is removed from a deposited multi-service booking.
- The shop closes unexpectedly and communicates with all affected clients.

### Payments and billing

- Deposit succeeds but booking finalisation initially fails.
- Booking is held while payment confirmation is delayed, then safely expires or confirms.
- Client pays partly in cash and partly by card after deposit application.
- Final total is lower than deposit after a service is removed.
- Partial refund adjusts reports, stock and commission without erasing history.
- A verified payment webhook is delivered twice or out of order.
- Subscription renewal fails through retry, grace period and restricted state.

### Permissions and security

- Staff user attempts to view owner-level revenue or another staff member's protected data.
- Receptionist attempts an unauthorised refund or excessive discount through the API.
- A user changes an identifier to access another tenant's record or attachment.
- A former employee's existing session is revoked.
- Support enters a tenant without a reason or beyond the approved time window.

### Evidence required for every scenario

- Documented preconditions, tenant, roles, time zone, policy and test data.
- UI and API steps, including parallel requests where concurrency is under test.
- Expected appointment, capacity, payment, inventory and client-state outcomes.
- Expected notifications, audit events, idempotency behaviour and provider records.
- A defined recovery path that support can perform without unsafe database changes.

## 8 Deferred Phase 2 Scope

The following capabilities are valuable and established in mature salon platforms, but are intentionally deferred until the core loop is stable and adoption data justifies expansion.

| Theme | Deferred capabilities |
| --- | --- |
| Growth and retention | Memberships, packages/prepaid sessions, gift cards, loyalty, referrals, reviews, segmentation, campaigns and advanced automation |
| Channels and marketplace | Native customer/staff apps, public discovery marketplace, e-commerce product store and branded white-label apps |
| Workforce and finance | Advanced payroll, tiered commission, salaries/deductions, statutory payroll, booth-rental settlements and automated payouts |
| Inventory and procurement | Suppliers, purchase orders, transfers, batch/expiry, backbar consumption and automated reorder |
| Enterprise | Franchise controls, multi-location reporting, cross-location memberships, central catalogues and enterprise SSO |
| Advanced services | Classes/group bookings, treatment charting, body mapping and complex medical workflows |
| Intelligence | AI receptionist/chatbot, dynamic pricing, demand forecasting and automated optimisation |

PHASE GUARDRAIL Do not pull a deferred feature into launch unless it resolves a blocker in the core journey, has a named owner and measurable outcome, and does not weaken the P0 quality gate.

## 9 Subscription Packaging and Commercial Rules

Recommended pricing basis: per location plus staff bands. Avoid per-appointment pricing that penalises successful customers. Keep messaging usage and payment-processing fees transparent and separate from the software subscription.

| Plan | Best for | Primary limit | Capability emphasis |
| --- | --- | --- | --- |
| Starter | Solo and very small shops | 1 location; up to 2 staff | Calendar, booking page, CRM, email reminders, payment recording, basic reports |
| Team | Typical barbershops and salons | 1 location; larger staff band | Roles, deposits, mobile messaging, POS, walk-ins, inventory, commissions, operational reports |
| Business | Larger single-location operators | Higher staff and usage limits | Custom branding, advanced permissions, automation, audit/reporting, priority support and exports |

### Commercial rules

- Offer monthly and annual billing, with annual savings in the 15-20% range subject to market testing.
- Offer a time-limited free trial with clear activation steps and expiry; do not require support to convert.
- Use entitlements for staff/location caps, messaging allowance, deposits, inventory, reporting, branding and support level.
- Meter SMS/WhatsApp separately or include transparent allowances; show usage before overage.
- State payment-processing charges separately from subscription fees.
- At launch, prefer two paid plans plus an optional higher tier over a confusing plan matrix.
- Grandfathering, plan migrations and downgrades must define what happens when current usage exceeds the new limit.

## 10 Information Architecture and Minimum Navigation

| Surface | Minimum navigation |
| --- | --- |
| Shop application | Dashboard; Calendar; Walk-in Queue; Clients; Checkout / Sales; Staff; Services; Inventory; Reports; Settings; Subscription & Billing |
| Customer-facing | Booking page; Service selection; Staff & time; Customer details; Deposit/payment; Confirmation; Secure appointment management |
| Platform administration | Businesses; Subscriptions; Plans & entitlements; Payments & invoices; Coupons; Support access; Notification logs; System health; Feature flags; Audit logs |

Navigation principles: prioritise today's work, keep high-frequency actions within one or two interactions, preserve filters and date context, support keyboard and touch input, and hide unauthorised features while still enforcing access server-side.

## 11 Technical Foundations and Domain Model

The UI may initially expose one location, but the database and service boundaries must be location-aware. Financial and historical records should be append-only or versioned where correction matters.

| Domain | Core entities |
| --- | --- |
| Tenant & commerce | Business/Tenant, Subscription, Plan, Entitlement, Invoice, Coupon |
| Identity & access | User, Staff Profile, Role, Permission, Session, Support Access |
| Catalogue & capacity | Location, Resource, Service, Service Add-on, Staff-Service Assignment, Working Schedule, Time Off |
| Clients & consent | Client, Client Note, Attachment, Form Template, Form Submission, Consent |
| Scheduling | Appointment, Appointment Service/Segment, Status History, Walk-in Queue Entry, Waitlist Entry, Capacity Hold |
| Sales & payments | Sale, Sale Item, Payment, Deposit Allocation, Refund, Tip, Cash Close |
| Inventory & compensation | Product, Inventory Movement, Commission Rule, Commission Entry, Adjustment |
| Operations | Notification, Provider Event/Webhook, Import Job, Export Job, Audit Event, Feature Flag |

### Mandatory implementation foundations

- Strict tenant scope in data access, caches, queues, object storage, search, logs and exports.
- Time-zone-aware storage: persist instants in UTC plus the governing location time zone and original local intent where needed.
- Transactional or reservation-based capacity locking for staff and resources.
- Idempotency for booking creation, payment operations, notifications, imports and incoming webhooks.
- Verified webhook signatures, event deduplication, out-of-order handling and safe replay.
- Append-only or compensating entries for money, inventory and commissions; corrections never erase the audit trail.
- Background jobs with retry policy, dead-letter visibility and correlation identifiers.
- Encrypted transport and storage, secure secret management and restricted attachment access.
- Automated backups, restore drills, error monitoring, structured logs and service health alerts.
- Data export, retention and deletion/anonymisation workflows designed before launch.

## 12 Non-Functional Requirements

| Quality attribute | Launch requirement | Priority |
| --- | --- | --- |
| Security & isolation | No cross-tenant access; least privilege; owner/admin MFA option; secure session/device management; rate limits; OWASP-aligned controls. | P0 |
| Availability | Target ≥99.9% monthly availability for booking and operational workflows, excluding planned maintenance. | P0 |
| Performance | Typical authenticated screens usable within 2.5 s at p75; common calendar actions acknowledge within 500 ms and commit within 2 s under target load. | P0 |
| Concurrency | Capacity claims are atomic; stale clients revalidate on commit; duplicate requests return the original result where safe. | P0 |
| Accessibility | WCAG 2.2 AA target for public booking and core staff workflows; keyboard, focus, labels, contrast and non-colour cues. | P0 |
| Responsiveness | Core shop and client journeys usable from 360 px mobile through desktop; touch targets appropriate for busy operations. | P0 |
| Privacy | Consent capture, minimisation, access logging, export and deletion/anonymisation workflows; region-specific policy reviewed before launch. | P0 |
| Recoverability | Documented RPO ≤24 h and RTO ≤4 h for MVP, with tested restore; tighter targets for payment records where provider reconciliation exists. | P0 |
| Observability | Metrics, traces/log correlation, queue/webhook health, error alerts and tenant-safe support diagnostics. | P0 |
| Compatibility | Current and previous major versions of Chrome, Safari, Edge and Firefox; graceful mobile browser behaviour. | P1 |
| Localisation | Locale-aware date/time, currency and tax formatting; content and templates structured for translation. | P1 |
| Exportability | Machine-readable CSV exports for core business records plus printable financial summaries. | P0 |

COMPLIANCE NOTE Tax, payment, privacy, marketing-consent and data-retention obligations vary by launch market. Legal and accounting review is a release dependency; this PRD is not jurisdiction-specific legal advice.

## 13 Implementation Roadmap

| Stage | Scope | Exit evidence |
| --- | --- | --- |
| 1. Foundation | Tenancy, identity, roles, subscriptions, entitlements, audit skeleton | Tenant isolation and subscription lifecycle tests |
| 2. Configuration | Onboarding, locations, hours, staff, services, resources and import | Published test business with valid availability |
| 3. Booking engine | Availability search, holds, conflicts, multi-service rules | Concurrency and edge-case test suite |
| 4. Operations | Calendar, lifecycle, walk-ins, client CRM and notes | Front-desk day simulation |
| 5. Customer journey | Public booking, passwordless self-service, notifications and waitlist | Mobile conversion and communication tests |
| 6. Money | Deposits, gateway webhooks, checkout, receipts, refunds and cash close | Reconciliation and duplicate-event tests |
| 7. Management | Reports, inventory, commissions and platform admin | Metric reconciliation and permissions audit |
| 8. Launch hardening | Migration, accessibility, security, performance, backup/restore and support playbooks | Production-readiness sign-off |

Recommended release strategy: closed alpha with test businesses -> design-partner beta using real calendars and payments -> limited paid launch by geography -> general availability after operational, security and support gates remain healthy.

## 14 Launch Readiness Checklist

### Product and operations

- [ ] A new owner can register, verify, subscribe, onboard and publish without internal assistance.
- [ ] Online, phone, reception and walk-in journeys reach completed checkout and client history.
- [ ] Staff/resource conflicts and all critical scenarios in Section 7 pass.
- [ ] Cancellation, deposit, refund and daily-close policies are documented for support.
- [ ] Import templates, error handling and customer data-export workflows are usable.

### Quality and security

- [ ] Tenant-isolation tests cover direct objects, search, exports, attachments, jobs and admin tools.
- [ ] Threat model, dependency review, penetration testing and high-severity remediation are complete.
- [ ] Public booking and core operator flows meet the accessibility target.
- [ ] Performance/load tests cover peak availability search, booking commit, calendar and webhook bursts.
- [ ] Backup restore and disaster-recovery procedure are exercised successfully.

### Money and compliance

- [ ] Gateway certification, signature verification, idempotency, refund and reconciliation are proven.
- [ ] Subscription invoices, renewal failure, grace period and cancellation behave as communicated.
- [ ] Tax, receipt, privacy, consent, retention and marketing rules are reviewed for the launch market.
- [ ] Financial reports reconcile to sale/payment events and post-refund outcomes.

### Go-to-market and support

- [ ] Plans, entitlement limits, trial terms, overages and processing fees are published clearly.
- [ ] Demo tenant, onboarding help, migration guide, status page and incident communication are ready.
- [ ] Support can diagnose failed notifications/webhooks without database access.
- [ ] Monitoring, ownership, escalation and rollback criteria are agreed for launch week.

## 15 Success Metrics and Instrumentation

Metrics must be segmented by acquisition channel, plan, business type, staff band, geography and cohort where privacy-safe. Definitions should be versioned and available to product, finance and support.

| Funnel | Metric | Definition | Initial target |
| --- | --- | --- | --- |
| Acquisition | Qualified trial starts | Verified businesses starting a trial | Baseline then improve |
| Activation | Published-booking activation | % of trials publishing with ≥1 service, staff member and valid slot within 24 hours | ≥60% |
| Time to value | First bookable setup time | Median time from tenant creation to first published valid slot | ≤30 min |
| Conversion | Trial-to-paid conversion | % of eligible trials becoming paying subscriptions | ≥20% initial |
| Demand | Online booking completion | Completed bookings ÷ booking flows that reach time selection | ≥65% |
| Reliability | Booking conflict leakage | Confirmed appointments with invalid staff/resource overlap | 0 |
| Reliability | Critical notification success | Delivered/accepted confirmations and reminders excluding invalid destinations | ≥98% |
| Revenue protection | No-show rate | No-shows ÷ appointments eligible to occur | Downward by cohort |
| Operations | Staff utilisation | Booked service time ÷ available service time | Tracked, not universally maximised |
| Payments | Payment reconciliation exceptions | Unresolved mismatches after provider settlement window | <0.1% |
| Retention | Logo retention | Paid businesses active at month-end ÷ starting paid businesses | ≥95% monthly after stabilisation |
| Customer value | Active-shop weekly usage | % paid shops completing calendar or checkout work each week | ≥80% |
| Support | Setup-related contact rate | New tenants needing human help to publish | ≤20%, declining |

METRIC CAUTION Targets are launch hypotheses, not contractual promises. Establish baselines with design partners, then lock targets for general availability. Do not optimise utilisation at the expense of breaks, service quality or staff wellbeing.

## 16 Benchmark References

The Phase 1 scope reflects recurring capability patterns visible in established appointment and salon platforms: online booking, staff-aware calendars, resource management, reminders, deposits/no-show controls, client history, checkout, inventory, commissions and reporting. The sources below are product benchmarks, not endorsements or specifications to copy.

| Platform | Benchmark area | Official source |
| --- | --- | --- |
| Fresha | Scheduling, resources, client profiles, reminders, deposits, POS and inventory | www.fresha.com/for-business/features/scheduling |
| Fresha | POS, deposits/prepayments, partial/split payments, tips and refunds | www.fresha.com/for-business/features/point-of-sale |
| Square Appointments | Plan feature matrix covering deposits, waitlist, resources, permissions, payments and reports | squareup.com/us/en/appointments/pricing |
| Square Support | Waitlist setup, client preferences, notifications and operational handling | squareup.com/help/us/en/article/7923-waitlist-with-square-appointments |
| Booksy Biz | Deposits, cancellation fees, reminders and client self-service controls | biz.booksy.com/features/no-show-protection |
| Booksy Biz | Payments, card-on-file, reporting and no-show protection | biz.booksy.com/features/payments |
| Mangomint | Online booking controls, cards on file, branding, waitlist and forms | www.mangomint.com/features/online-booking/ |
| Mangomint | Scheduling, group guests and intelligent waitlist | www.mangomint.com/features/scheduling/ |
| Vagaro | Calendar, custom service hours, processing gaps, personal blocks and recurring appointments | www.vagaro.com/pro/calendar |
| Vagaro | Barbershop online booking, notifications, deposits, no-show fees and calendar | www.vagaro.com/pro/barber-software |

_Reference review date: 27 July 2026. Product capabilities and pricing change; validate again before final commercial packaging._

## 17 Final MVP Chargeability Test

The Phase 1 product is genuinely ready to charge for only when a representative new shop can complete every statement below in production-like conditions:

- [ ] Register, verify and subscribe without vendor assistance.
- [ ] Configure the business, location, services, employees, resources and availability.
- [ ] Import existing clients and resolve errors or duplicates safely.
- [ ] Publish a branded, mobile-friendly booking link.
- [ ] Accept online, phone, reception and walk-in demand.
- [ ] Prevent concurrent staff, room, chair and equipment conflicts.
- [ ] Send confirmations, reminders and change notifications reliably.
- [ ] Collect, transfer, refund or forfeit a deposit according to policy.
- [ ] Check in, serve, complete and rebook a client.
- [ ] Take split payment, allocate tips, issue a receipt and correct a mistake safely.
- [ ] Update client history, inventory and commission from the transaction.
- [ ] Close the day and reconcile collected revenue by payment method.
- [ ] Review and export operational and financial reports.
- [ ] Export business data and manage or cancel the subscription.
- [ ] Receive support without invisible or unaudited account access.

DECISION RULE If any step requires routine database intervention, manual payment repair, cross-system reconciliation or the product team's help, the product has not yet met the self-serve chargeability standard.
