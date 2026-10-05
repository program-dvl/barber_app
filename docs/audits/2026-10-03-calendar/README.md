# Calendar product review and redesign — 2026-10-03

Implemented in the existing authenticated Calendar. The target is a dense,
staff-first salon schedule with a compact operational layer and contextual drawers.
This is application code backed by the existing scheduling commands, not a static
mockup. [Chosen visual target and initial screenshots](visual-target.md).

## Reviewed boundaries

Reviewed Calendar, appointment booking/change/status/copy/note/print workflows,
walk-ins, staff/location assignments, recurring working rules, temporary changes,
breaks/leave, special hours/closures, service variants and processing/cleanup,
qualification and resource capacity, holds, canonical Sale checkout readiness,
all/own permissions, client identity/contact visibility, and the shared shell,
select and native dialog components. Authority remains the PRD, accepted decisions
and the existing atomic scheduling/lifecycle/checkout services.

## Findings and implementation

| Priority | Finding | Result |
| --- | --- | --- |
| P1 | Empty calendar areas did not distinguish real staff availability from unavailable time | Intersected working windows, unavailable bands, blocks, holds and cross-location occupancy; filtered bookings remain Reserved |
| P1 | Client identity could be recreated when a contactless client was booked or rescheduled | Explicit tenant-checked identity and original-client preservation during replacement; replay remains idempotent |
| P1 | Selecting a saved client validated a formatted mobile as new input, leaving an error with no editable field | Resolve saved contacts before validation; normalize display formatting, show saved phone, clear stale errors and offer a contact-authorized appointment fallback for missing/invalid numbers |
| P1 | Overnight capacity could carry into next-day leave or an explicit closure | Validator checks dated exclusions and timed breaks as real intervals; projection uses the same precedence |
| P1 | Inactive/former staff could leave existing visits outside the default staff columns | Keep selected-range visits visible; expose only active assigned staff for new booking |
| P2 | Stacked controls and large panels delayed the first useful schedule view | Single toolbar, slim operational strip, secondary filter panel and proportionate cards |
| P2 | Appointment operations took focus away from the schedule | Quick-view and creation drawers; reviewed drag/reassignment/resize; direct permitted lifecycle actions |
| P2 | Short cards lacked a useful hierarchy | Client/service first, start time and status cue; longer cards progressively reveal time/source/form/note/checkout information |
| P2 | Phone rows and tablet controls wasted space or wrapped awkwardly | Dense phone agenda with 44px actions; two-row tablet toolbar; unbroken control labels and fractional-width breakpoint coverage |

Retained Day and Week, added Agenda, and made Team the default. Existing print,
copy/rebook, cancellation reasons, notes, blocks and operational-impact workflows
remain reachable. Cancel/no-show/drag changes require review. Terminal visits expose
rebooking and permitted checkout rather than invalid schedule/lifecycle actions.
Contact, notes and checkout metadata are redacted before serialization; calendar-own
options and booking targets are restricted to the operator's scope.

The current-time line appears only on the current location date and uses the salon
zone. Opening/working hours determine the initial range. Explicit URL selections
win over per-user/business device preferences. Filters preserve schedule scroll.
The visible idle Calendar refreshes once per minute, pausing during input, open
menus/drawers and mutations. Search covers the loaded day/week; existing-client
search is abortable, debounced and limited to 15 results.

## Browser walkthrough and evidence

Local Chrome, signed-in demo salon. Browser zoom was already 90%; responsive checks
used the measured CSS viewport widths, rather than assuming requested physical sizes.
No appointment/status/payment mutation was submitted to the demo database.

1. Open the populated 2026-08-17 Team day: seven visits across three staff, readable
   status/service/time hierarchy and exactly aligned header/body column coordinates.
   [Final desktop](17-final-desktop.png), [original populated screen](03-before-populated.png).
2. Open Maya's visit: client link, canonical service/staff, permitted actions and
   native focus containment/restoration. [Appointment drawer](07-appointment-drawer.png).
3. Drag Maya from Aria to Dev at 12:00: the review names Maya, Dev, the date/time
   and reason field. Cancel without mutation. [Drag review](18-drag-review.png).
4. Filter Confirmed and search Maya: omitted appointments remain reserved; the
   visible card count changes without exposing false free slots.
   [Filtered capacity](11-filtered-capacity.png).
5. Switch Day: simultaneous visits receive independent lanes, while adjacent
   appointments regain the full available width. [Day overlaps](19-day-overlaps.png).
6. Open a future Dev 10:15 slot: date, time and Dev are prefilled. Search/select
   an existing client, then cancel. [Slot prefill](13-slot-prefill.png).
7. Open Mark no-show: review explicitly explains release/history; Cancel leaves
   the record unchanged. Saving transitions is covered by HTTP feature tests.
8. Check measured 320, 390, 770, 1280 and 1680 CSS widths: no document horizontal
   overflow in the sampled states. Phone uses an agenda; Week scrolls within the
   calendar on tablet. [Dense phone agenda](09-mobile-dense.png),
   [corrected tablet Week](16-tablet-verified.png). Earlier numbered captures are
   intermediate observations, including the tablet wrapping defects that were fixed.

At 1680×778, the final grid header starts at approximately 256px and the complete
board ends at 750px. Header/body x coordinates match exactly: 264.55, 324.55,
768.18 and 1211.81. The live sampled page reports no console errors or warnings.
Long-name search, overlapping/adjacent geometry, overnight clipping, released
processing time and UTC subtraction across DST are covered by frontend tests.

Follow-up phone regression verification: searched for Anika, selected her saved
formatted international mobile and confirmed that the drawer displays it without
requesting re-entry. An invalid new-client mobile submission showed validation
beside its editable input; selecting Anika cleared the stale contact errors.
No appointment was created. The saved-number summary fits at the measured 351px
mobile viewport with no document overflow. Final built-page console has no errors
or warnings. [Saved contact drawer](20-existing-client-phone.png),
[mobile drawer](21-existing-client-phone-mobile.png). HTTP regressions cover saved
contact reuse/replay, optional missing contacts, invalid-number correction without
profile mutation, new-client validation and restricted contact access.

## Verification

- Full repository suite: **348 passed, 3,796 assertions, 28 intentional skips**.
- Focused calendar/front-desk suite: **23 passed, 279 assertions**.
- Frontend helper suite: **21 passed**, including seven new Calendar tests.
- Production client and SSR builds pass using the manifest-required Node runtime.
- Targeted PHP formatting and `git diff --check` pass.
- No new migration or new scheduling/payment provider is required.

The focused regression coverage includes filtered reservations and private leave
reasons, own access/contact/payment redaction, explicit contactless client linkage
and replay, inactive-client replacement, overnight leave denial, sanitized client
lookup and inactive staff visibility. Existing tests exercise booking capacity,
stale versions, lifecycle, manager notice policy, cancellations and checkout.

## Practical limits

Free staff gaps are not promises about service qualifications or equipment; the
atomic commit revalidates those rules. Wall-grid drag/slot creation pauses on
clock-change dates. Dragging running visits is disabled, with existing manual
change workflows retained. Search does not fetch an unbounded appointment history.
No Month view, recurring series, attendance tracking, predicted lateness, generated
insights or new payment flow is introduced.

This local review does not certify target-topology load, live providers, independent
screen-reader/WCAG compliance or Safari/Firefox/Edge. Small desktop slot cells have
keyboard alternatives; coarse pointers use the booking drawer. The existing release
gates and MySQL concurrency integration skips remain recorded, without a new waiver.
