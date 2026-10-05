# Services catalogue and configuration

Requirements: [FR-02, FR-03, FR-04, FR-05, FR-06, FR-07, FR-08, FR-09, FR-14–FR-19](../product-requirements.md).
Accepted boundary: [ADR-049](../decisions.md). Dependencies: implemented
[scheduling](scheduling-operations.md), [workforce](team-availability.md),
[checkout](checkout-sales.md) and public booking contracts. This document restores
the Services specification under OPEN-14.

## Workspace and access

One Services navigation entry contains the catalogue, categories and add-ons.
Use a compact category rail on desktop and a selector on phones. Search name/category
instantly, combine status/channel/staff/location/type filters and sort by category,
name, price, full calendar span or recency. Category sorting puts active items first;
within status, use persisted category order and alphabetical service names. Paginate
25 visible rows. Configuration warnings explain missing branches, staff, hours or
online staff; enabled online visibility is not a promise of a free bookable slot.

Existing `settings.manage` governs reads and writes. Receptionists consume eligible
service configurations through Calendar/Queue/Checkout, rather than receiving
configuration authority. Managers require every configured service branch and
current qualified professional's work branch within their membership scope. Owners
may manage all business branches. The same guard applies to direct commands,
status, duplicate sources, add-on references and category changes/reordering.
Cross-business references fail on the server. Query loading is bounded by eager
relations; expanding the test catalogue from one to 41 services adds at most two
queries. No production response-time or load guarantee is inferred.

## Onboarding starter categories (FR-02 / ADR-050)

The reviewed starter-services step shows the small category set configured for
all 12 supported business types. On confirmation, a new catalogue receives the
whole set in its configured order, including empty categories; only selected
starter services are created and placed in those categories. For example, a hair
salon starts with Hair, Styling, Colour and Treatments. Generic appointment
businesses receive Appointments rather than unrelated salon categories.

Categories are ordinary tenant-owned records, immediately editable through
Services. The bootstrap reuses names case-insensitively within the business,
preserves existing names/order/archive status and appends missing records. A
selected starter service under an already archived category remains inactive and
internal. Empty categories do not create public offers. The existing business-first
transaction and completed-session guard protect retry safety; generated category
IDs/count are retained in onboarding evidence and its audit event.

This is an additive new-onboarding default, not an existing-catalogue backfill.
Completed sessions, established service catalogues and subsequent business-type
changes do not regenerate categories or overwrite customization. The barbershop's
combined haircut/beard appointment uses Combined services, avoiding confusion with
Phase 2 prepaid packages. No new migration or schema-version change is required.

[Verification and screenshots](../audits/2026-10-04-starter-categories/README.md).

## Explicit editing

The drawer uses Overview, Team & locations and Booking & rules. Required creation
is name, non-negative price and positive active time. No staff/branch assignment
is required to save a draft configuration; the catalogue marks incomplete active
items Needs setup. Blank override fields inherit defaults; zero is a real price
or buffer. Natural duration presets include a custom minute control. Existing
inactive branch/staff references stay visible and can be retained without restoring
bookability; new capabilities require active staff and branches.

Price resolution remains staff override, then location override, then base price.
Only fixed and starting-from models exist. Each service retains its recorded
currency; business currency cannot silently migrate existing services. Staff
variants include qualification, price, active/processing/cleanup time and online
visibility. Assignment changes retire current versions and create effective-now
versions, preserving old records. Stored commission references remain intact;
actual sale commissions use the existing commission-rule/ledger workflows.

Saving uses a configuration revision and upcoming appointment version signature.
The command locks business, locations, service and staff in booking-compatible
order, rechecks revisions and rejects affected live holds. Scheduled future variants
or dated service end/start configuration block this current-only editor (OPEN-19).
Future price/time/availability/removal/override changes require a reason confirming
review. Existing appointments are never moved, resized, repriced or reassigned.
Changing only descriptive fields does not require that impact reason.

An exact command UUID/payload record is stored in the same transaction. Replaying
an applied save returns the original service without another assignment/audit event;
reusing its key with different content fails. The browser persists an unconfirmed
request per user/business in session storage, freezes edits and offers exact replay.
Dirty close/navigation/refresh is guarded; the save itself passes the navigation
guard. Validation keeps the editor/draft open, links errors and focuses a control
after processing releases its disabled state. Close and reopen stale configuration.

## Timing, segments and resources

Client visit = active + processing; calendar span additionally includes cleanup.
Existing segment identities, relative layout, occupancy and resource links are
preserved. The resolver redistributes effective aggregate times across same-kind
segments, omits zero-time kinds and introduces a missing kind for staff overrides.
New base processing goes before cleanup; shifting stored sequence numbers never
replaces segment IDs. Processing releases the professional only when its canonical
segment does. Existing room/chair/equipment requirements are visible and stay
managed through Business setup; this drawer does not invent a resource/layout editor.

## Categories, copying and history

Create/rename/archive categories inline. Names are unique case-insensitively in the
business; active services prevent category archive. Up/down controls provide
accessible explicit ordering with stale-order detection. Category archive/rename
retains service and financial identities. There is no service delete or merge.
Deactivate prevents new work while keeping online preference; activating an internal
service does not publish it. The status dialog rechecks revisions/holds/upcoming
versions and requires a reason when deactivating an upcoming service.

Duplicate opens an inactive, internal copy. Name/public IDs/segment IDs/assignment
IDs are new. Appropriate base values, branch/staff exceptions, add-on links and
resource requirements are copied, with segment resource links remapped. Appointment,
sale, commission, receipt and audit history is never copied. Names must be unique.

## Booking, add-ons, commerce and reporting

Calendar projects staff variants and effective bounds in the selected branch's
zone. Eligible staff selectors, configured duration and an estimated price/range
reflect the chosen professional. First available can vary in duration/price;
canonical booking revalidates capability, hours, resource fit and availability at
commit. Only attached add-ons are offered with selected parents in creation/public
selection. Public selection removes orphan add-ons when a parent is deselected.
Public projections exclude inactive/expired services and unavailable branches.
Only services with qualified online professionals at the selected branch appear.
Estimates use the selected professional, then branch price, then base price; private
commission/contact data is omitted. Switching branches removes unavailable selections
and orphan add-ons before requesting slots.

Queue recommendations and assignment continue through `WalkInWorkspaceQuery`,
`EffectiveServiceResolver` and the atomic booking engine. Added checkout services
resolve current qualification, branch/staff price and currency. Newly added add-ons
must have an eligible parent retained in the basket. Booked rows keep historical
snapshots, even after catalogue deactivation/renaming; removing or adjusting them
uses Checkout's existing reason/authority/quote contract. Taxes come from the
CommerceSetting rate/inclusive posture, with recorded booked-line tax where present.
The service tax category is currently descriptive and cannot make a service exempt.

Service deposit none means inherit business deposit policy, not override it to
zero. Fixed/percentage settings retain entitlement and booking validation; effective
fixed deposits are bounded by price. Global client/value/no-show conditions still
apply. OPEN-16's unfinished public payment interface remains a real limit; this
change neither collects payments nor bypasses paid deposit confirmation.

Memberships, prepaid packages, benefit prices, wallets and campaign instruments
remain Phase 2. Named length/colour variants, location duration overrides, image
upload and completed-consultation prerequisites have no supported operational
editor/contract here (OPEN-20); do not add misleading controls. Existing image
references are retained on saves, without claiming a public image-upload feature.
Authorized checkout discounts and commission rules remain in their existing owners.

Client history and reports retain appointment/sale service identities, recorded
names/prices/durations and append-only ledgers. Category changes reorganise current
catalogue grouping; no retrospective financial recategorization is performed.
Activity records one explicit applied change, useful price/time/count summaries and
before/after configuration. Exact replays and unchanged saves add no change event.

## Imports and verification

CSV import remains in Business setup, reachable from the catalogue footer. Its
existing private preview, row errors, duplicate review, counts and error export
remain. Service imports validate positive duration, bounded price/name and business
currency. Import creates new internal services requiring assignment setup. Existing
service rows must be skipped and edited through reviewed Services commands;
imports cannot reactivate, convert, change currency or silently overwrite live
pricing. Commit revalidates old previews and serializes against catalogue commands.
There is no new export or bulk price/duration editor.

Evidence: [dated Services review](../audits/2026-10-03-services/README.md),
`ServiceCatalogTest`, existing scheduling/public/checkout/workforce suites and
frontend service-catalog helper tests. Local browser evidence does not certify
production concurrency, target-region taxes, WCAG or all browser engines. Existing
release/payment/legal gates are unchanged.
