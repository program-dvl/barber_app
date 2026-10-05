# Product experience and presentation

Scope: presentation of existing Phase 1 capabilities, under FR-01–FR-20 and
PRD sections 3, 4, 10 and 12. This document does not promote deferred features,
change domain rules, or waive release gates. See [design-system](../design-system.md),
[decisions](../decisions.md) and [requirements](../product-requirements.md).

## Shared contract

- An appointment is the scheduled visit. Booking is the process or public page
  used to reserve it. Staff actions use “Add appointment”; client journeys use
  “Book an appointment”. Existing stored values and user-entered data are retained.
- Daily operations use 14px body text, 12–14px metadata, 17px section headings,
  24–28px page headings (26px in the workspace), 40px desktop controls and at least 44px touch controls.
  Public/auth inputs remain 16px to avoid mobile focus zoom. Root rem sizing
  remains unchanged. Spacing and semantic tokens live in the shared styles.
- Pages have one primary heading, nearby actions, and proportional empty states.
  Required fields have visible labels; field errors are associated with controls.
  Native modal dialogs contain focus, support Escape, and restore the trigger.
- Preserve payment collection versus recording, provider acceptance versus
  delivery, simulation versus live sending, and prepared versus published setup.
  Do not conceal policy, consent, expiry, access or destructive consequences.
- Audit presentation is a read-only whitelist of meaningful changes. Stored
  append-only evidence is never rewritten. Plan names come from the shared
  catalogue; private payload fields are not rendered as change summaries.
- Views named Appointment list, By staff, and Now & next represent the same
  permission-filtered schedule. Internal saved preference keys remain compatible.

## Review and evidence

The platform inventory, visual coverage, role checks, tests and explicit gaps
are recorded in [the dated audit](../audits/2026-10-02-platform-refinement/README.md).
Source review and build success do not count as rendered-screen verification.
Legacy/quarantined routes stay quarantined. Missing domain module specifications
remain tracked in OPEN-14; this specification only covers presentation.

## StepES-inspired record composition

Use primary-blue navigation and the pale operational canvas, shared breadcrumbs,
labelled `SearchField`, record toolbar and native `DataTable` patterns. Keep
identity/contact metadata in the first column on mobile, hide secondary columns
at the same 768px breakpoint, preserve local table scrolling and keep amounts
and row actions unbroken. Prefer aligned records over individual record cards
when users compare clients, services, staff, events or sales. Reports disclose
secondary filters while retaining selected-filter counts. These presentation
patterns do not change permissions or domain calculations.

Fresh follow-up evidence and exclusions: [StepES-inspired refinement](../audits/2026-10-02-stepes-inspired-refinement/README.md).

Workspace surfaces distinguish canvas, navigation, toolbars, headings and record
bodies through shared tokens. Navy ink strengthens body, label and metadata
contrast; indigo identifies actions and navy identifies navigation. Desktop and mobile
navigation share these tokens. Latest limited visual verification:
[surface hierarchy](../audits/2026-10-02-surface-hierarchy/README.md).

The latest owner-selected shell uses primary-blue desktop/mobile navigation with
inverse brand/text and a visible selected/focus state. Signed-in headers use a
subtle blue wash, business identity and a defined account disclosure. Content
backgrounds and all existing actions remain as implemented.
[Latest shell evidence and gaps](../audits/2026-10-02-brand-account-menu/README.md).

The public frontend's brand-primary navy (`#172554`) also defines authenticated
navigation and header identity. The account popover uses compact labelled icon
rows, sentence-case grouping and wrapping identity details; billing links retain
their existing permission filter and workspace destinations. A single permitted
workspace has one Subscription & billing action. Multiple workspaces retain their
names under Business billing. No account or billing behavior changes.

## Daily workspace

The [dashboard contract](dashboard.md), ADR-043 and [dated evidence](../audits/2026-10-02-daily-workspace/README.md)
define the daily landing screen. Now & next is the default for new preferences.
Urgent operational exceptions and unpaid visits have direct authorized actions;
financial information follows the schedule. Phone/tablet layouts expose a compact
attention summary with disclosure, rather than stacking every desktop section
above the current customer. Personal and finance-only scopes are independent of
professional titles. The record drawer, calendar and checkout preserve context,
access rights and the existing authoritative mutation services.


## Calendar refinement (2026-10-03)

Calendar uses the same brand shell and native dialog/select system with a dedicated,
compact schedule composition. `AppointmentCard` and pure `calendarWorkspace`
helpers separate card hierarchy from UTC capacity subtraction and lane layout.
The local calendar stylesheet owns schedule geometry; unrelated pages retain their
existing density. Shared `AppSelect` accepts an optional empty-selection placeholder
while preserving its native option/value, search, keyboard and multiple-selection
contracts. Defaults on other pages remain compatible.

Evidence and design rationale: [Calendar audit](../audits/2026-10-03-calendar/README.md).

## Clients refinement (2026-10-03)

The [client-records specification](client-records.md), ADR-046 and
[dated review](../audits/2026-10-03-clients/README.md) define the operational CRM.
Directory rows show identity/contact, last/next visit, preferred staff and exact
completed-visit count. Search, relationship/staff filters and sorting run on the
server with pagination. The default profile prioritizes the next booking, visible
important notes, explicit preferences and recent past visits. Visits, payments,
notes and supporting records progressively disclose detail. Calendar rebooking
uses today's eligible catalogue and a review drawer; Queue handoff retains the
canonical Client. Financial totals come from completed/open sales per currency,
not appointment quotes. Existing authorization, consent, private files, privacy
cases and reviewed merge commands remain authoritative.

## Team and availability refinement (2026-10-03)

ADR-048 and the [workforce specification](team-availability.md) define a single
compact operational directory, weekly coverage, time off and manager Access.
Profiles use four contextual drawer sections; active reserved-time ratios, today's
shift, booking gaps and appointments lead into Calendar. Different weekday hours,
split shifts, copying and dated exceptions use precise branch-local time controls.
Impact review names affected appointments and preserves edits after recoverable
failures. Services use searchable category groups and explicit duration/price
overrides; custom access groups use business capability labels. Read-only staff
views and smaller screens retain the same authority with focused day lists.
The dated [Team audit](../audits/2026-10-03-team/README.md) records browser evidence
and limits. Screenshots do not establish independent accessibility certification.

## Services refinement (2026-10-04)

[ADR-049 and Services](services.md) define the compact category catalogue and
three-section drawer. Search/filter/sort, natural durations, inherited staff/branch
prices, setup warnings, parent add-ons and inline categories replace the generic
CRUD flow. Explicit save, pending-command replay, unsaved guards and review reasons
protect configuration changes. Phone rows retain useful price/time/availability
metadata; narrow summaries avoid broken currency values. The
[Services audit](../audits/2026-10-03-services/README.md) records local evidence and
remaining scope limits.
