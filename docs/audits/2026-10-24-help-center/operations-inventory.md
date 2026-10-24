# Operational workflow audit and content inventory

Review date: 2026-10-24. Evidence level: repository source review of current routes, rendered Vue templates, controller validation, domain services, relevant module contracts and accepted decisions. This file does not claim live-provider or deployed verification. Browser screenshots and final automated checks are recorded by the parent implementation audit.

The 38 guides below cover current visible workflows plus explicit customer-facing limits for retained backend capabilities. Customer articles do not expose requirement IDs, database vocabulary, internal task notes or raw provider payloads. No external generative-answer service or tenant-data indexing is used.

## Sources and approved scope

- **ops** — FR-03, FR-05–08, FR-11–12, FR-15–18. Sources: `resources/js/Pages/Dashboard.vue`; `app/Domain/Reporting/Services/DailyWorkspaceService.php`; `app/Http/Controllers/Shop/DashboardController.php`.
- **calendar** — FR-03–08, FR-11, FR-15, FR-19. Sources: `resources/js/Pages/Operations/Calendar.vue`; `app/Http/Controllers/Shop/CalendarController.php`; `app/Domain/SchedulingOperations/Services/CalendarWorkspaceQuery.php`.
- **appointments** — FR-05–07, FR-09–10, FR-13–15. Sources: `resources/js/Pages/Operations/Calendar.vue`; `resources/js/Pages/Booking/{Welcome,Manage,WaitlistOffer}.vue`; `app/Http/Controllers/PublicBooking/`; `app/Http/Controllers/Shop/AppointmentOperationsController.php`.
- **walk** — FR-03–08, FR-11, FR-13, FR-15, FR-19. Sources: `resources/js/Pages/Operations/WalkInQueue.vue`; `app/Http/Controllers/Shop/WalkInQueueController.php`; `app/Domain/SchedulingOperations/Services/WalkInWorkspaceQuery.php`.
- **clients** — FR-06, FR-08, FR-11–15, FR-19. Sources: `resources/js/Pages/Clients/{Index,Show}.vue`; `app/Http/Controllers/Shop/ClientRecords/`; `app/Domain/ClientRecords/Services/`.
- **checkout** — FR-06, FR-08, FR-11, FR-14–19. Sources: `resources/js/Pages/Shop/Checkout.vue`; `app/Http/Controllers/Shop/CheckoutController.php`; `app/Domain/MoneyCommerce/Services/`.
- **inventory** — FR-16–19. Sources: `resources/js/Layouts/AppLayout.vue`; `resources/js/Pages/Shop/Inventory.vue`; `app/Http/Controllers/Shop/{Inventory,Report}Controller.php`.
- **reports** — FR-14–19. Sources: `resources/js/Pages/Shop/Reports.vue`; `app/Http/Controllers/Shop/ReportController.php`; `app/Domain/Reporting/Services/{ReportCatalog,ReportService}.php`.
- **notifications** — FR-09–15, FR-19. Sources: `resources/js/Pages/Communications/Index.vue`; `app/Http/Controllers/Shop/CommunicationSettingsController.php`; `app/Domain/Communications/Services/NotificationWorkspaceCatalog.php`.

Module contracts read: dashboard, scheduling-operations, client-records, checkout-sales, reports and communications. PRD scope and accepted ADR-018/036/043–049/051–052/055/064/066–069 govern the observed behavior. Existing product-status release gates are preserved.

## Feature-to-article inventory

| Article | Workflow coverage and customer outcome | Application destination |
| --- | --- | --- |
| [Add an appointment at reception](../../../content/help/appointments-add.json) | Create a visit with the correct client, services, staff and local start time. | `calendar` |
| [Reschedule, reassign or change an appointment](../../../content/help/appointments-change.json) | Review changes to a visit while preserving its previous details and history. | `calendar` |
| [Troubleshoot missing services or online time slots](../../../content/help/appointments-no-slots.json) | Check publication, location, service, staff and scheduling settings in a useful order. | `calendar` |
| [Understand the customer’s online booking journey](../../../content/help/appointments-public-booking.json) | Learn what customers choose and what makes a booking confirmed. | `calendar` |
| [Help customers manage an appointment securely](../../../content/help/appointments-self-service.json) | Use private confirmation links for permitted changes without exposing a customer’s booking. | `calendar` |
| [Record visit progress, cancellation and no-shows](../../../content/help/appointments-status-cancel.json) | Keep arrivals, service progress and appointment history accurate. | `calendar` |
| [Understand waitlists and opening offers](../../../content/help/appointments-waitlist.json) | Help customers request a suitable opening and respond to a private offer. | `calendar` |
| [Understand staff availability and booking conflicts](../../../content/help/calendar-availability.json) | Find out why visible space on the calendar may not be bookable. | `calendar` |
| [Block staff time and record operational disruption](../../../content/help/calendar-blocks-closures.json) | Keep non-bookable time visible and review appointments affected by disruption. | `calendar` |
| [Find appointments and choose a Calendar view](../../../content/help/calendar-views.json) | Use Team, Day, Week and Agenda views, date controls, filters and printed schedules. | `calendar` |
| [Review deposits, tax and staff tips at checkout](../../../content/help/checkout-deposits-taxes-tips.json) | Understand how existing deposits, configured tax and explicit tips affect the amount due. | `checkout` |
| [Recover an interrupted payment record safely](../../../content/help/checkout-payment-recovery.json) | Check the same saved request and history before recording or collecting anything again. | `checkout` |
| [Find sales, outstanding balances and receipts](../../../content/help/checkout-receipts-history.json) | Search saved sales, inspect payment history and print the original issued receipt. | `checkout` |
| [Record received, split or partial payments](../../../content/help/checkout-record-payments.json) | Save money you have actually received and keep unpaid balances visible. | `checkout` |
| [Record money returned through the original payment method](../../../content/help/checkout-refund.json) | Review refund or void amounts, reasons and product disposition without rewriting the original sale. | `checkout` |
| [Review and save a sale after a visit](../../../content/help/checkout-review.json) | Check performed services, adjustments and totals before recording payment. | `checkout` |
| [Find or add a client](../../../content/help/clients-add-find.json) | Keep one useful client record and avoid accidental duplicates. | `clients` |
| [Review and merge duplicate client profiles](../../../content/help/clients-duplicates.json) | Keep the right profile and preserve connected history after a careful comparison. | `clients` |
| [Update a client’s details and preferences](../../../content/help/clients-edit-preferences.json) | Correct contacts, record preferences and tags, and save a reason for the change. | `clients` |
| [Create form templates and request a client form](../../../content/help/clients-forms.json) | Publish clear questions and keep completed forms tied to the wording the client saw. | `clients` |
| [Add useful client notes and control visibility](../../../content/help/clients-notes.json) | Save service context with a clear type, importance and appropriate visibility. | `clients` |
| [Understand a client’s payments and outstanding balance](../../../content/help/clients-payments.json) | Read recorded spending, payments and unfinished checkouts without confusing them with visit prices. | `clients` |
| [Record and process a client privacy request](../../../content/help/clients-privacy.json) | Track exports, corrections, withdrawals and deletion reviews without silently erasing history. | `clients` |
| [Store and access private client files](../../../content/help/clients-private-files.json) | Upload supported visit images and documents with appropriate visibility and expiring links. | `clients` |
| [Review a client’s visits and book their next appointment](../../../content/help/clients-visits-rebook.json) | Follow past and upcoming visits, then rebook using today’s eligible services. | `clients` |
| [Understand retail inventory availability](../../../content/help/inventory-availability.json) | Know which retail tools are currently visible and how saved retail sales affect stock. | `No action: unavailable self-service workflow` |
| [Understand SMS consent, opt-outs and text allowances](../../../content/help/notifications-consent.json) | Know why a notification may be stopped even when SMS is enabled. | `notifications` |
| [Check whether a client notification was delivered](../../../content/help/notifications-delivery.json) | Read delivery history, understand status and investigate a missing update. | `notifications` |
| [Choose client notification channels and quiet hours](../../../content/help/notifications-preferences.json) | Manage email and SMS preferences while ClipperDesk handles message wording and sending. | `notifications` |
| [Use your daily workspace](../../../content/help/ops-dashboard.json) | Follow today’s appointments, arrivals, waiting clients and payment follow-up from one place. | `dashboard` |
| [Understand appointment, walk-in and client reports](../../../content/help/reports-bookings-clients.json) | Use recorded outcomes and paying-client definitions without reading them as forecasts. | `reports` |
| [Read team capacity, tips and commission reports](../../../content/help/reports-capacity-commission.json) | Separate configured working time from attendance and compensation records from payroll. | `reports` |
| [Export or print a report](../../../content/help/reports-export.json) | Create a CSV with the report’s actual filters and download it when ready. | `reports` |
| [Understand sales, receipts, taxes and refunds in Reports](../../../content/help/reports-money.json) | Choose the correct date basis so payment activity and sale-period totals make sense. | `reports` |
| [Run and compare a report](../../../content/help/reports-run.json) | Choose a report, date range, location and permitted filters, then inspect the details. | `reports` |
| [Add a walk-in to the queue](../../../content/help/walk-ins-add.json) | Check in a new or existing client with their requested service and preferred staff. | `walk-ins` |
| [Notify waiting clients, reorder the queue or mark a client as left](../../../content/help/walk-ins-order-alerts.json) | Use explicit front-desk actions while preserving the queue’s real order. | `walk-ins` |
| [Assign staff and start a walk-in service](../../../content/help/walk-ins-serve.json) | Check staff readiness, start the visit and complete checkout without losing queue history. | `walk-ins` |

## Authorization and cross-module dependency audit

- Dashboard, Calendar and queue operate in a selected assigned location and use its local time. Own-calendar users receive scoped appointments. A linked staff profile is required for personal scope; a job title alone never grants access.
- Client contact, notes, sensitive records, attachments, forms, privacy and merge permissions are independent. Restricted contact values are never described as missing or invited to be overwritten.
- Calendar and queue require eligible active services, qualified bookable staff, location hours, breaks/time off, resources and authoritative free capacity. Search/filtering cannot free capacity. Queue assignment is not a reservation; service start revalidates.
- Appointment completion hands off to Checkout. Checkout saves an immutable basket before recording actual received money; only full balance settlement commits stock and compensation. Rebooking returns to the currently eligible catalogue rather than copying inactive offerings.
- Reports use permitted locations, staff and compensation scope; revenue and export are distinct permissions. Sale-period receipts and transaction-date activity have different bases. A report never mutates money.
- Notifications require both the relevant enabled channel and client preference/consent. SMS STOP suppression, monthly business text units, destination readiness and sending outcomes remain authoritative. Enabling a switch is not evidence of delivery.
- SaaS subscription billing is separate from visit checkout and appointment payment records. Historical values and current provider readiness are not inferred from each other.

## Genuine screenshot requests

| Key | Article section | Intended actual screen |
| --- | --- | --- |
| `appointment-create` | `appointments-add#steps` | Add appointment drawer with client and service fields |
| `booking-services` | `appointments-public-booking#flow` | ClipperDesk customer booking page with example services |
| `calendar` | `calendar-views#steps` | Calendar showing staff columns and appointment cards |
| `checkout-payment` | `checkout-record-payments#steps` | Saved sale with payment method and received amount controls |
| `checkout` | `checkout-review#review` | Checkout workspace showing a completed visit and sale summary |
| `clients-directory` | `clients-add-find#find` | Client directory with sample client records |
| `client-profile` | `clients-edit-preferences#steps` | Client profile with visit and preference information |
| `notification-history` | `notifications-delivery#find` | Notification history with delivery outcomes |
| `notifications` | `notifications-preferences#channels` | Client notification channel switches and quiet-hour controls |
| `dashboard` | `ops-dashboard#work` | Daily workspace with sample appointments |
| `reports` | `reports-run#steps` | Reports workspace with date range, metrics and detailed rows |
| `walk-in-add` | `walk-ins-add#steps` | Add walk-in form with requested service and preferred staff |
| `walk-in-queue` | `walk-ins-serve#steps` | Walk-in queue showing waiting clients and service progress |

These declared references have now been captured and validated in the final audit. The media manifest and capture evidence establish synthetic provenance, actual dimensions, current route and date. Future missing assets must still be reported, never replaced with generic mockups. Detailed template/form/merge/refund screens can be added as a follow-up to the same capture process; lack of an asset does not change verified written instructions.

## Explicit coverage limits and unsupported workflows

1. **Retail setup and stock movements:** product creation/import/receipt/adjustment endpoints and an inventory listing exist, but Inventory is deliberately absent from the normal business navigation under ADR-036. The listing has no product or stock-edit controls. Business setup → Optional setup → Import records does support Products CSV import, as documented by `setup-import-records`; this does not provide a stock receipt/adjustment editor. Reports filters out stock/retail reports. `inventory-availability` explains this limit and permitted existing-catalogue checkout behavior; it invents no add-stock button.
2. **Commission configuration and payroll:** ledger/rule/adjustment APIs exist. The current business frontend has no complete rule editor, adjustment form or payroll engine. Reports exposes existing permissioned Tip statement, Commission statement and Compensation records. `reports-capacity-commission` explains their boundaries.
3. **Cash close:** a reasoned cash-close POST endpoint and Cash reconciliation report exist, but Checkout has no self-service close form. `reports-money` describes reading existing closes and explicitly avoids nonexistent navigation to create them.
4. **Public deposit collection:** the actual booking page blocks deposit-required confirmation with “Online payment is not available yet” and a business contact action. `appointments-public-booking` states the appointment remains unconfirmed. No customer payment form, certified provider refund executor or deposit notification is promised.
5. **Waitlist operations:** public request, private offer/claim and linked customer leave actions exist; there is no generic business waitlist-management screen. The article explains customer behavior and verifying resulting appointments through Calendar.
6. **Saved sale amendments:** the current workspace cannot amend/cancel a prepared sale. Stock drift or incorrect prepared values require authorized review. Recovery guidance never tells users to create another financial record to bypass uncertainty.
7. **Messaging:** new client content/senders are platform managed. No custom template editor, branded-sender setup, WhatsApp, promotional campaign, conversational inbox, deposit request/received event or uncontrolled resend is documented as available. Provider acceptance, confirmed delivery and simulation are distinct.
8. **Privacy deletion:** export/correction/withdrawal processing is implemented; deletion/anonymisation remains blocked under retention policy ADR-018. No delete-client button or blanket erasure claim appears.
9. **Advanced client benefits:** memberships/packages, gift cards, wallets and loyalty are not implemented customer aggregates. Staff workspace membership and Business subscriptions must not be confused with client benefits.
10. **External certification:** article source review does not certify live payment/refund/email/carrier delivery, tax/legal suitability, independent accessibility, production load, or release readiness. Existing project gates remain open.

## Verification completed in this subtask

- Parsed every authored JSON file and confirmed all related article references resolve within the authored set.
- Reviewed exact main button/field labels against the current Vue templates; form required/optional rules against controllers. The public booking article was corrected to explicitly name Review appointment, Confirm appointment, Check confirmation, required email/mobile and the deposit-required block.
- Used only declared Help action keys, not user-supplied URL targets, for application handoffs. Parent backend owns authorization and route validation.
- This content subtask performed no live customer-data, payment, message, provider or primary database mutation.
