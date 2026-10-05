# Walk-in Queue review and redesign — 2026-10-03

Implemented in the existing authenticated application. The Calendar supplies the
visual reference; the queue now prioritizes actual serving order, waits, qualified
staff openings and current work. [Initial evidence and target](visual-target.md).

## Reviewed business boundaries

Inspected WalkInEntry/WalkInHistory/statuses and commands; Appointment lifecycle,
segments, versions, atomic booking and command keys; canonical Client identity,
contact and note permissions; Service durations, segment kinds and staff variants;
qualification and eligible locations; recurring/temporary staff rules, working
hours, breaks, leave, exceptional hours and closures; Calendar projection, active
capacity holds, resource claims and cross-location work; role/assigned-location
policies; notification intents, check-in, completion and canonical checkout; the
shared shell, typography, buttons, search/select and native dialogs. Product scope
remains FR-03–FR-08/FR-11/FR-15/FR-19 and accepted decisions.

## Findings and resulting behavior

| Priority | Finding | Implemented result |
| --- | --- | --- |
| P1 | A staff selector offered little context about qualification or upcoming appointments | Ordered qualified choices, queue forecasts and a full on-demand staff/resource fit check with actionable conflict text |
| P1 | Queue conversion/start could leave a partial appointment when a later step failed, and selected canonical identity was not explicit | Single conversion/lifecycle/history transaction, replay keys and canonical client linkage |
| P1 | Calendar completion left a linked walk-in active | Calendar starts, terminal transitions and reviewed replacements synchronize linked queue records atomically |
| P1 | Planned finishes could imply availability while a service was still running, including from a previous day | Unknown-finish staff state, withheld forecast and a domain start guard; no invented overrun duration |
| P1 | A drawer left open could submit a start time from an expired availability check | Reject expired new-service checks before any appointment is persisted and offer Check again |
| P1 | Queue contacts/notes and branch reorder scope needed protection | Server redaction, contact-aware search, note-write permission, two-permission reorder and assigned-location checks |
| P1 | Intake or assignment could accept a service/staff outside the branch's offered capabilities | Branch service eligibility and authoritative staff qualification validation |
| P2 | Count-times-duration arrival estimates ignored different service lengths and parallel staff | Arrival and refreshed forecasts share ordered hypothetical placements across qualified staff and real scheduling gaps |
| P2 | The original table hid the most useful decisions among selectors and repeated actions | Numbered compact rows, explicit NEXT, client/wait/service hierarchy, one Serve action and a contextual drawer |
| P2 | Refresh and per-row timing could interrupt a busy operator | One shared display clock, preserved idle refresh, explicit stale/error cues and no per-entry requests/timers |
| P2 | Internal terminology confused front-desk users | “Walk-in added to the queue.”, “Staff assigned.”, “Service started.” and “Service completed.” |
| P2 | Dense queues and phone drawers needed deliberate layouts | 78px desktop rows, sticky labels/team rail, tablet disclosure and phone cards with 44px Serve actions |

The existing Waiting/Notified/Assigned/In service/Completed/Left states remain.
Search and filters preserve actual positions. Manual reorder still requires a
reason and unchanged full queue membership; duplicates are rejected. Preferred
staff can cause a longer wait while another qualified staff member serves the next
compatible client. Starting someone further down explicitly shows how many are
ahead; it never silently rewrites order. No VIP, hold or automated priority rule
has been invented. OPEN-17 records the missing temporary-hold policy.

The staff forecast deliberately reserves each hypothetical service's full span;
it does not claim resource availability or exploit processing gaps. The drawer
checks the complete booking rules, and atomic start revalidates. Once a walk-in
has a Calendar visit, reservation changes and cancellation stay in Calendar.
Checkout links use canonical Appointment/Sale records and existing permissions;
no payment or append-only financial history is changed.

## Browser walkthrough and visual evidence

Local Chrome; original page inspected before editing. Mutating workflow checks
used an explicitly approved, isolated synthetic salon and test clients. No client
notification, payment, removal or queue mutation was submitted to the original
salon. The review seeder is restricted to local/testing environments and keeps the
original tenant intact. Screenshots of the populated board use synthetic records.

1. Open a 12-client queue and identify actual NEXT, service, elapsed wait and staff.
   All desktop rows measure 78px, including long service names and the recorded
   reorder cue. [Initial redesigned board](03-desktop-dense.png),
   [final board](13-final-desktop.png).
2. Open Sofia: her assigned staff's imminent booking prevents a 20-minute service.
   Start is disabled, the booking interval is named, and other qualified staff
   remain selectable. Breaks and unqualified staff are also explained.
   [Conflict drawer](04-service-conflict.png).
3. Add Jordan with only name/mobile/service: a canonical Client is linked, a new
   waiting entry appears at its real final position, and the exact success text
   is “Walk-in added to the queue.” [Minimal intake](05-add-client.png).
4. Complete Riley from the team shortcut; choose Ava for Jordan and start service.
   Jordan leaves waiting, enters In service, and retains one canonical visit.
   Complete Jordan and expand the recent list: recorded actual wait and authorized
   Checkout links appear. [Completion and handoff](07-completed.png).
5. Save Alex's assignment, reopen his drawer, and check the qualified staff fit.
   Tab wraps from the final action to the first staff choice; Escape closes the
   drawer and returns focus to Serve Alex Taylor. [Fit and keyboard focus](12-staff-fit.png).
6. Use N to open intake, search/select saved Sofia on a 360px phone, and cancel.
   No phone re-entry is required; the next service control receives focus after
   selection. Dialog dimensions are 360×800 with no document overflow.
   [Phone drawer](09-mobile-drawer.png).
7. Check 360/390/768/1024/1280/1440/1920 CSS widths in sampled board states.
   Document scroll width equals viewport width; phone cards and tablet team
   disclosure retain operational context. [Phone](06-mobile.png),
   [tablet](08-tablet.png), [laptop](10-laptop.png).
8. Switch to the empty second branch: no queue/staff records leak from the busy
   branch, structure stays visible, and the timezone follows the branch.
   [Empty branch](11-empty-branch.png).
9. Observe the idle expanded-team board: its timestamp advances from
   06:28:31 UTC to 06:29:01 UTC without manual refresh. At scrollY 565,
   the topbar ends at 56px, column labels start at 56px and the team rail at 68px.
   Refresh preserves content/scroll. Final sampled browser warning/error log is empty.
10. Reopen the original seeded demo without mutation: one remaining client keeps
    position 2 and explicit NEXT, a 47-day historical wait renders in days/hours,
    and a still-running booked visit is correctly excluded from free staff.
    At the restored 1512×700 viewport, document width is 1512 with no overflow.
    The current-service summary link reaches its named section.
    [Single entry and long-wait edge case](14-single-long-wait.png).

Intermediate captures intentionally retain the earlier service state and shorter
elapsed waits. The synthetic records were left available for repeatable review;
actual times/estimates continue changing with the local clock.

## Four-perspective final review

- Reception: NEXT and real position survive filtering; wait exceptions and staff
  openings are immediately visible. Intake, assign/start, completion and checkout
  stay in context. The summary links directly to current service activity.
- Owner: the page reads as live front-desk work rather than a generic record list,
  with Calendar and branch context in the same operational language.
- Design: consistent 78px rows, restrained status treatment, compact controls,
  progressive disclosure, intentional phone hierarchy and precise sticky offsets.
  The NEXT wrap and topbar/sticky collision found during QA were corrected.
- Engineering: one projection with eager relationships and one overdue-staff query;
  query growth stays bounded from one to 40 waiting entries. One shared clock,
  abortable client/fit requests, scoped replay/version checks, transactional
  lifecycle/history and server authorization preserve maintainability and integrity.

## Verification and limits

Full repository suite: **358 passed / 3,941 assertions**, with **28 existing
intentional skips**. Frontend helper suite: **26 passed**.

Verification commands: `php artisan test --compact`,
`node --test tests/Frontend/*.test.mjs`, `npm run build` using Node 24.19.0,
scoped `vendor/bin/pint` and `git diff --check`. Coverage includes
parallel/preferred staff forecasts, breaks and imminent bookings, canonical
identity, add/start replay and mismatch, Calendar replacement/start/completion
sync, expired-check rollback, overnight live overruns, contact/note redaction,
branch/tenant rejection and board query growth. Frontend tests cover elapsed/long
waits, advisory/stale ranges, attention lifecycle, search and branch-local arrival.
Client/SSR production builds and targeted PHP formatting pass.

No push infrastructure is introduced: visible idle polling is every 30 seconds;
inputs, filter/recent disclosures, dialogs and mutations pause it. Staff capacity
is advisory and resource fit is checked on selection/start. The existing business
booking interval remains authoritative; future schedule starts are displayed.
Shared resource forecasts, automated assignment, temporary hold/VIP states and
new payment/rebooking flows are not silently invented. The current design system
is light-themed; there is no independent dark theme to certify.

This local Chrome review is not independent screen-reader/WCAG certification,
Safari/Firefox/Edge coverage or production load/concurrency/provider evidence.
Existing integration/disabled-feature skips and external release gates remain.
