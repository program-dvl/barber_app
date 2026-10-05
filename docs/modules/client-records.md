# Client records: salon CRM and operational linkage

Requirements: [FR-11, FR-12, FR-08, FR-06, FR-13, FR-15](../product-requirements.md), ADR-018/044/045/046 in
[decisions](../decisions.md). This specification restores the existing aggregate from its models, services,
policies and ClientCrmConsentTest evidence before the CRM redesign. Other missing
module specifications remain tracked by OPEN-14.

The calendar's existing-client search requires client-view and appointment-management
capabilities. It returns at most 15 active clients matching a name or, with contact
permission, phone/email. Own-calendar access returns only clients with a prior visit
assigned to that staff profile. Queries are explicitly scoped to the current business.
The server removes contacts before serialization when contact access is absent.

Selecting an existing client passes its public identity. The appointment endpoint
checks tenant, active status, client permission and own-history scope, then uses
canonical server-owned name/contact values before validating the booking form.
Saved international mobiles are stripped of display spacing/punctuation without
guessing country codes. A valid saved mobile always wins over submitted contacts,
including stale or redacted form values, and is shown to authorized operators.
Canonical contacts are validated separately from the submitted request, so hidden
saved values are not flashed back as old form input when another field fails.
If the saved mobile is missing or invalid, contact-authorized operators see an
optional editable phone field for the appointment. This fallback changes the
appointment snapshot only; the drawer links to the authorized client profile
workflow to save a permanent correction. A phone is not required for contactless
clients. Restricted operators cannot supply a fallback for hidden contacts.
Appointment persistence links that
identity directly, so contactless clients are not duplicated. Command replay remains
idempotent. Schedule replacements preserve the original canonical client identity,
including inactive records; new bookings/copies require active selected clients.

Client detail links retain existing authorization and location rules. Profile,
merge, consent, privacy and financial commands retain their authoritative services.


## Walk-in check-in linkage

Walk-in-management permission authorizes the existing minimal intake workflow.
Search remains tenant scoped and returns at most eight active clients with a saved
mobile. Name matching is available to queue operators; phone/email matching and
contact serialization require contact permission. A saved international mobile is
normalized for the queue snapshot without editing the profile. An invalid saved
mobile requires the existing client-profile correction workflow, with an
explanatory field error. New intake uses the canonical manual client-identity
service and retains its duplicate/identity rules. Queue-add replay does not create
a second client or entry. Conversion supplies the selected canonical identity to
atomic booking. Contact/notes/profile links remain permission gated; the queue
adds no client merge, consent, payment or privacy behavior.


## CRM aggregate and preserved commands

Client is business-owned, with encrypted service/communication preferences,
normalized search identities, preferred staff/services, tenant tags and optimistic
versions. Bookings, converted walk-ins and reviewed imports link this identity.
Manual creation reuses exact normalized name/contact matches; different names
sharing contact information require explicit review before creation. No automatic
merge occurs. Merge retains selected-survivor versions, a preview, reason and all
registered appointment, sale, deposit, note, form, file, consent and privacy links.

Profile updates require client-manage plus view authorization, expected version
and a reason. Contact-hidden operators cannot overwrite saved contacts. Contact
corrections revoke future vulnerable links. Notes retain kind, visibility,
importance, author and timestamp; staff attribution falls back to the recorded
tenant audit actor for owners without a staff profile. Sensitive reads are separately authorized and
audited. Existing health-related kinds are preserved without new health fields.

Forms publish immutable versions; requested/submitted wording and answers retain
originating appointment and identity evidence. Attachments stay private with
expiring access links and sensitive visibility. Privacy case processing retains
ADR-018: anonymization is blocked pending approved retention policy; no destructive
client delete is introduced. These tools remain available through supporting
profile sections instead of occupying the default operational overview.

## Directory and profile projection (ADR-046)

Directory search is normalized and server-paginated (30 clients); phone matching
runs only with digits, email matching preserves email normalization, and contacts
are removed without contact-view. Filters cover booking relationship, no-shows,
last visit and preferred staff. Name, recently added, last visit, next booking and
completed-visit count are allow-listed sorts. Own-only users see linked clients,
with appointment metrics/history restricted to their staff and assigned locations.

Overview prioritizes identity/contact, current or next live appointment, explicit
preferences, important visible notes and recent visits. Recent visits exclude
future bookings, which have their own upcoming section. Visit history is paginated
20 at a time with eager-loaded performers, location and services. Aggregates use
all authorized history independently of history pagination. Converted walk-ins
are appointments with walk_in source; unconverted queue history is separately
bounded and labelled so visits are never double-counted.

Financial visibility requires revenue-view or checkout-manage, with assigned
location and own-appointment scope retained. Completed sale net receipts are
paid + applied deposit - refunded, grouped by currency without conversion.
Outstanding means positive balance on open sales; completed appointments without
completed checkout remain a separate count. Payments expose bounded append-only
transaction rows with kind, status, method and date; provider evidence is omitted.
Appointment prices are shown only with finance access. No zero placeholder claims
lifetime spend when the ledger is unavailable.

Booking handoffs resolve an active authorized Client on the server. Rebook uses
current eligible service/staff identities and the Calendar creation drawer; all
availability, duration, notice, price and command checks remain authoritative.
Inactive services/staff are not silently copied. Exact appointment links use the
appointment's location-local date and public identity. Queue handoff reuses the
Client and existing mobile validation. Handoffs are reviewable, never submissions.

Client memberships/packages, gift cards, wallet and loyalty have no approved
implemented client aggregate. Platform Membership is employee access and SaaS
subscription billing belongs to Business. Neither is presented as a client benefit.
No merge, sending, archive or financial mutation is added by a profile summary.

## Communication handoff (FR-13, ADR-052)

A permitted client profile shows six recent delivery records and links to
client-filtered Client notifications. It includes safe status/simulation
labels, never historical bodies or secure links. Assigned-location, own-calendar
and financial boundaries match the communication workspace. List/map preference
compatibility and explicit channel withdrawals are governed by the
[Communications specification](communications.md).
