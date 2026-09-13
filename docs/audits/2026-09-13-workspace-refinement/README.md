# Authenticated workspace refinement — 13 September 2026

The owner workspace now has a calmer, denser shared interface without reducing
the main reading size. The largest gains are consistent custom selects, clearer
catalogues, focused editing drawers, simpler report controls and a full-width
calendar with optional details. Navy, indigo, cyan and the ClipperDesk identity
remain intact. This is an implementation and representative-screen review, not
a claim of measured usability superiority over a competing product.

## Scope and evidence

Reviewed the shared tokens, CSS, buttons, fields, surfaces, status/empty states,
tables, dialogs, navigation and authenticated Vue page families before changing
them. Visually checked the signed-in owner workspace at 1440px and representative
mobile screens at 360px (some intermediate captures used 450px). Screenshots were
saved and visually inspected through the local browser. Earlier numbered images
record intermediate states; the screenshots linked below are the relevant
evidence for each finding and its resolution.

The checkout already contained substantial work before this refinement. This
pass preserved it and did not modify domain services, routes or financial logic.
`docs/modules/` was absent, so the PRD, accepted ADRs and design system supplied
the available requirements context; OPEN-14 records the documentation gap.

## Screen review

1. **Dashboard and shell — refined.** Before: oversized metrics and repeated
   explanations competed with the working day. Now: consolidated metrics,
   quieter secondary numbers, a clear booking action and a compact view switcher.
   Navigation groups separate daily work from setup; the sidebar is 240px and
   the top bar is 56px. Inventory is absent from desktop and mobile navigation
   as requested, with underlying functionality retained.
   [Before](01-dashboard-before.png) · [After](08-dashboard-after.png).

2. **Calendar — refined and follow-up fixes verified.** Location/date and the
   Day/Week/Team switch remain immediately visible; optional filters disclose
   below them. Details and attention are collapsed initially. The schedule uses
   the full available width; opening the panel allocates an 18rem side column on
   desktop. At 1440px, measured schedule width changes from 1152px collapsed to
   844px expanded. A selected appointment opens its actions, including through
   Enter/Space; closing or Escape restores toggle focus. Mobile shows the panel
   above the schedule and brings it into view. The first 07:00 label now sits
   4px below the grid boundary, verified in all three views; subsequent labels
   remain aligned with their hour lines. Proportional appointment timing is
   unchanged. [Before](03-calendar-before.png) ·
   [Full-width day](21-calendar-full-width.png) ·
   [07:00 correction](19-calendar-start-fixed.png) ·
   [Mobile actions](22-calendar-details-mobile.png).

3. **Services — refined.** The list now gets the full content width, with search
   by name/category and quieter record-specific row actions. Add and Edit open
   a shared drawer. Its footer stays reachable while the body scrolls; validation
   retains the form and native submit contract. Price, time, locations and
   provider assignments remain explicit. [Before](02-services-before.png) ·
   [Catalogue](05-services-after.png) · [Edit and select menu](06-service-drawer-select.png).

4. **Team — refined.** Provider creation no longer competes with the team list.
   The drawer preserves editable job titles, service assignments, working days
   and the distinction between a provider profile and login access. Mobile
   fields remain readable and the save/cancel footer stays visible.
   [Mobile provider drawer](14-provider-drawer-mobile.png).

5. **Clients, forms and detail pages — refined.** Search and directory are one
   surface, with separate initial-empty and no-search-results recovery. Preferred
   services use a multi-select menu with checkmarks and selected counts. Client
   tabs now expose tablist/panel relationships, roving focus, arrow keys and
   Home/End. Required audit reasons remain mandatory; attempting to save without
   one focuses the enhanced select and announces its inline error. No profile
   change was saved during this verification. Form publishing and privacy
   workflows retain their existing server contracts.
   [Mobile directory](09-clients-mobile.png) ·
   [Multiple selection](10-client-multiselect-mobile.png) ·
   [Forms](11-client-forms-mobile.png).

6. **Business, booking and location setup — refined.** Existing progressive
   setup sections remain intact. Shared fields, radios, checkboxes and searchable
   country/time-zone lists have consistent dimensions and states. The region
   menu filtered to Asia/Kolkata successfully. Working-day controls and local
   opening times remain explicit. [Before](04-settings-before.png) ·
   [Search](15-settings-search.png) · [Booking rules](16-booking-settings.png) ·
   [Location form](23-location-settings.png).

7. **Reports — refined.** Duplicate report navigation was replaced with one
   searchable picker. Switching reports preserves the applied date/filter
   context. The populated appointment table now displays location names,
   understandable column labels and times in the report's stated zone:
   04:15 UTC appears as 09:45 in Asia/Kolkata, matching Calendar. Full source
   references remain available through an explicit toggle; exports and report
   calculations are unchanged. Keyboard-accessible table overflow and clearer
   empty states support small screens. [Populated report](07-reports-after.png).

8. **Walk-ins and checkout — refined.** The walk-in modal uses the shared field,
   select and footer patterns while keeping client lookup and service selection.
   Checkout has a useful empty-sales state and the same shared density. The
   existing externally-received-money confirmation was retained. No walk-in,
   sale or payment was created during browser review.
   [Walk-in dialog](18-walk-in-dialog.png).

9. **Billing and account — refined.** The current-plan summary and plan cards
   use less space, while trial expiry, access and payment information remain
   clear. Repetitive reassurance copy was condensed. Account forms share calmer
   surfaces and tighter spacing. Subscription transactions, password changes and
   other account mutations were not exercised.
   [Billing](17-billing.png) · [Account forms](20-account-profile.png).

## Shared interaction checks

- Single select: native numeric values preserved; keyboard selection changed
  the unsaved service booking horizon to 365, then Cancel left the record intact.
- Multiple select: two services selected and summarized correctly, then exited
  without saving the profile.
- Search: country, time-zone and report filtering; no-results recovery; selected
  and disabled option presentation.
- Drawer focus: Escape closes the menu first, leaves the dialog open and
  restores its trigger. Tab from country search moves to the phone input.
- Required select: blocked submission, visible inline error and focus on the
  missing client-change reason. Native constraint validation remains active.
- Client tabs: ArrowRight selected Visits, Home returned to Overview, with
  correct panel association and focus.
- Calendar panel: initially hidden, fully reclaims width, explicit toggle,
  keyboard activation, close/Escape focus return and mobile visibility.
- Mobile: no document-level horizontal overflow on the inspected 360px client,
  provider and calendar screens; wide week/team grids remain locally scrollable.
- Body text remains 14–16px; mobile/coarse-pointer controls and options restore
  at least 44px heights. Focus, disabled, selected and error states use shared
  styling. Native modal dialogs retain focus containment.

## Validation

- Application suite: **292 passed, 3,174 assertions; 28 intentionally skipped**
  legacy boilerplate tests. Completed after the shared-component migration;
  subsequent work was confined to presentation, documentation and frontend tests.
- Frontend tests: **9 passed**, covering menu navigation, disabled/empty lists,
  viewport placement, UTC and explicit-offset display, daylight saving, and
  report labels.
- Production client and SSR builds passed with Node 24.19.0.
- Frontsite budget check passed for all **17 route entries**.
- `git diff --check` passed. Latest browser error-log check returned no errors.
- No new UI library or remote asset dependency was introduced.

## Evidence limits and remaining release checks

This session used one owner account, one location and a small data set, including
one upcoming appointment. It is not a complete role matrix, high-volume calendar
test, screen-reader certification or cross-browser accessibility certification.
Platform administration received shared component migration and build coverage,
but was not visually reviewed through this owner session. Paid-plan states,
live payment execution, subscription cancellation, private attachment transfer,
bulk imports, privacy execution and destructive actions were not exercised.

Public marketing and public booking were outside the authenticated redesign;
their generous default density was retained. Shared phone/menu primitives can
also appear outside the workspace. The enhanced menu relies on the browser
Popover API; current browser behavior was verified, while a legacy-browser
support matrix remains a release check. The account avatar was blank in the
available local data; no profile or remote-image setup was changed to fabricate
that state. The existing report previous-period calculation and print/export
presentation were not redesigned. The dashboard's stock metric and billing
feature descriptions remain unchanged: the user's Inventory instruction was
specifically to hide it from menus.

The next validation should use representative salon staff, a populated multi-day
calendar, assistive technology and supported browsers. These checks should
measure task completion and clarity, rather than treating visual polish alone
as proof of usability.
